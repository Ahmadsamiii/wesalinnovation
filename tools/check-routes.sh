#!/usr/bin/env bash
#
# وصال: فحص قواعد .htaccess الجذر على Apache حقيقي، قبل التبديل وبعده.
#
#   bash tools/check-routes.sh
#
# يبني مجلداً مؤقتاً فيه .htaccess المستودع نفسه وصفحات بديلة، ويشغّل Apache على
# منفذ محلي خاص، ثم يفحص كل رابط: الروابط الثابتة (/chat و/login)، وتحويل
# corporate.html، وحالة «قبل التبديل» (بلا ملف .switch)، وحالة «بعد التبديل»
# (بوجوده): الجذر، وروابط الدعوة وإعادة التعيين والاستبيانات القديمة، وخرائط
# الموقع، والفهرسة. يفحص أيضاً أن الأسطر الحالية (www وhttps ومنع تنفيذ المرفوعات
# وأنواع MIME) لم تتأثر. لا يمس الإنتاج ولا يحتاج قاعدة بيانات ولا PHP.
#
# يحتاج Apache 2.4 (apache2 أو httpd) وcurl. خرج 0 نجاح، 1 فشل فحص، 2 بيئة ناقصة.
# مع LiteSpeed (استضافة Hostinger) السلوك مكافئ لكنه لا يُفحص هنا: كرّر الفحص
# اليدوي بعد النشر بالأمر نفسه على النطاق الحقيقي: bash tools/check-routes.sh --live

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LIVE=0
[ "${1:-}" = "--live" ] && LIVE=1

pass=0; fail=0
ok()  { pass=$((pass + 1)); printf '  \033[32m✓\033[0m %s\n' "$1"; }
bad() { fail=$((fail + 1)); printf '  \033[31m✗\033[0m %s\n      المتوقع: %s\n      الفعلي:  %s\n' "$1" "$2" "$3"; }
say() { printf '\n\033[1m%s\033[0m\n' "$*"; }

# ------------------------------------------------------------- الوضع الحي
# على النطاق الحقيقي: فحص قراءة فقط لما ينبغي أن يكون صحيحاً في أي وقت
if [ "$LIVE" -eq 1 ]; then
    APEX="${WESAL_APEX:-wesalinnovation.sa}"
    say "فحص حي على https://$APEX (قراءة فقط)"
    code() { curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$1"; }
    loc()  { curl -s -o /dev/null -w '%{redirect_url}' --max-time 20 "$1"; }
    c="$(code "https://$APEX/api/auth.php")"
    [ "$c" != "000" ] && [ "$c" != "500" ] && ok "الواجهة البرمجية ترد ($c)" || bad "الواجهة البرمجية" "غير 000 و500" "$c"
    for p in chat login corporate.html; do
        l="$(loc "https://$APEX/$p")"
        [ -n "$l" ] && ok "/$p يحوّل إلى $l" || bad "/$p" "تحويل" "لا تحويل"
    done
    c="$(code "https://$APEX/homepage/")"
    [ "$c" = "200" ] && ok "/homepage/ يعمل" || bad "/homepage/" "200" "$c"
    [ "$fail" -eq 0 ] && { printf '\n\033[32mنجح الفحص الحي (%d)\033[0m\n' "$pass"; exit 0; }
    printf '\n\033[31mفشل %d من %d\033[0m\n' "$fail" "$((pass + fail))"; exit 1
fi

# ------------------------------------------------------------- تجهيز Apache
APACHE="$(command -v apache2 || command -v httpd || true)"
[ -n "$APACHE" ] || { echo "Apache غير مثبت (apache2 أو httpd). ثبّته أو شغّل: bash tools/check-routes.sh --live بعد النشر." >&2; exit 2; }
command -v curl >/dev/null || { echo "curl غير مثبت." >&2; exit 2; }

MODDIR=""
for d in /usr/lib/apache2/modules /usr/lib64/httpd/modules /usr/lib/httpd/modules /usr/local/apache2/modules /opt/homebrew/lib/httpd/modules; do
    [ -d "$d" ] && { MODDIR="$d"; break; }
done
[ -n "$MODDIR" ] || { echo "لم أجد مجلد وحدات Apache." >&2; exit 2; }

TMP="$(mktemp -d)"
PORT="${WESAL_TEST_PORT:-$((20000 + RANDOM % 20000))}"
stop() { [ -f "$TMP/apache.pid" ] && kill "$(cat "$TMP/apache.pid")" 2>/dev/null; sleep 0.3; rm -rf "$TMP"; }
trap stop EXIT

DOCS="$TMP/htdocs"
mkdir -p "$DOCS/homepage" "$DOCS/api" "$DOCS/uploads" "$DOCS/assets" "$DOCS/workspace/build"
cp "$ROOT/.htaccess" "$DOCS/.htaccess"
for f in sitemap.xml sitemap-home.xml sitemap-chat.xml robots.txt robots-chat.txt; do
    [ -f "$ROOT/$f" ] && cp "$ROOT/$f" "$DOCS/$f"
done
echo "CHAT-APP"          > "$DOCS/index.html"
echo "HOMEPAGE"          > "$DOCS/homepage/index.html"
echo "OLD-CORPORATE"     > "$DOCS/corporate.html"
echo "LOGIN-PAGE"        > "$DOCS/login.html"
echo "SURVEY"            > "$DOCS/survey.html"
echo "INVITE-STUB"       > "$DOCS/api/invite-redeem.php"
echo "AUTH-STUB"         > "$DOCS/api/auth.php"
echo "SHELL"             > "$DOCS/uploads/x.php"
echo "LOGO"              > "$DOCS/assets/logo.webp"
# مساحة العمل: .htaccess التطبيق نفسه ومتحكم أمامي بديل (يقدّم رداً نصياً)
cp "$ROOT/workspace/public/.htaccess" "$DOCS/workspace/.htaccess"
echo "WS-FRONT"          > "$DOCS/workspace/index.php"
echo "WS-ASSET"          > "$DOCS/workspace/build/app.js"
chmod -R a+rX "$TMP"

RUNUSER=""
if [ "$(id -u)" -eq 0 ]; then
    for u in www-data apache nobody; do id "$u" >/dev/null 2>&1 && { RUNUSER="$u"; break; }; done
    [ -n "$RUNUSER" ] || { echo "لا مستخدم لتشغيل Apache بدل root." >&2; exit 2; }
fi

mods=""
for m in mpm_prefork unixd authz_core authz_host dir mime rewrite headers expires deflate filter setenvif env alias log_config; do
    [ -f "$MODDIR/mod_$m.so" ] && mods+="LoadModule ${m}_module $MODDIR/mod_$m.so"$'\n'
done

cat > "$TMP/httpd.conf" <<EOF
ServerRoot "$TMP"
PidFile "$TMP/apache.pid"
Listen 127.0.0.1:$PORT
ServerName localhost
ErrorLog "$TMP/error.log"
LogLevel warn
${RUNUSER:+User $RUNUSER
Group $RUNUSER}
$mods
TypesConfig /etc/mime.types
DocumentRoot "$DOCS"
<Directory "$DOCS">
    AllowOverride All
    Require all granted
</Directory>
EOF

if ! "$APACHE" -f "$TMP/httpd.conf" -t >"$TMP/conftest.log" 2>&1; then
    echo "إعداد Apache التجريبي لم يمر:" >&2; cat "$TMP/conftest.log" >&2; exit 2
fi
"$APACHE" -f "$TMP/httpd.conf" -k start >"$TMP/start.log" 2>&1
for _ in $(seq 1 30); do
    curl -s -o /dev/null "http://127.0.0.1:$PORT/" && break
    sleep 0.2
done
curl -s -o /dev/null "http://127.0.0.1:$PORT/" || { echo "تعذّر تشغيل Apache:" >&2; cat "$TMP/start.log" "$TMP/error.log" >&2; exit 2; }

# ------------------------------------------------------------- أدوات الطلب
APEX="wesalinnovation.sa"; CHAT="chat.wesalinnovation.sa"
BASE="http://127.0.0.1:$PORT"

# curl HOST PATH [ترويسات إضافية...]: يطبع «الحالة|Location|الجسم»
hit() {
    local host="$1" path="$2"; shift 2
    curl -s -o "$TMP/body" -w '%{http_code}|%{redirect_url}' -H "Host: $host" -H 'X-Forwarded-Proto: https' "$@" "$BASE$path"
    printf '|%s' "$(tr -d '\n' < "$TMP/body" | head -c 6000)"
}
hdr() { curl -s -o /dev/null -D - -H "Host: $1" -H 'X-Forwarded-Proto: https' "$BASE$2" | tr -d '\r' | grep -i "^$3:" | head -1 | cut -d' ' -f2-; }
norm() { sed -E 's#^http://[^/]+##'; }   # تحويل نسبي يبنيه Apache بمنفذ الاختبار

# expect_redirect وصف host path الحالة الوجهة
expect_redirect() {
    local desc="$1" host="$2" path="$3" want_code="$4" want_loc="$5"
    local out code loc
    out="$(hit "$host" "$path")"; code="${out%%|*}"; loc="$(cut -d'|' -f2 <<<"$out" | norm)"
    if [ "$code" = "$want_code" ] && [ "$loc" = "$want_loc" ]; then ok "$desc"; else bad "$desc" "$want_code → $want_loc" "$code → $loc"; fi
}
# expect_body وصف host path نص_في_الجسم [الحالة]
expect_body() {
    local desc="$1" host="$2" path="$3" want="$4" want_code="${5:-200}"
    local out code body
    out="$(hit "$host" "$path")"; code="${out%%|*}"; body="$(cut -d'|' -f3- <<<"$out")"
    if [ "$code" = "$want_code" ] && [[ "$body" == *"$want"* ]]; then ok "$desc"; else bad "$desc" "$want_code وفي الجسم «$want»" "$code وفي الجسم «$body»"; fi
}
expect_code() {
    local desc="$1" host="$2" path="$3" want="$4"; shift 4
    local out code; out="$(hit "$host" "$path" "$@")"; code="${out%%|*}"
    [ "$code" = "$want" ] && ok "$desc" || bad "$desc" "$want" "$code"
}

# ------------------------------------------------------------- قبل التبديل
rm -f "$DOCS/.switch"
say "قبل التبديل (بلا ملف .switch): لا تغيير على ما يراه الزوار اليوم"
expect_body    "الجذر على النطاق الرئيسي هو المحادثة"                "$APEX" "/"                         "CHAT-APP"
expect_body    "الجذر على chat. هو المحادثة أيضاً"                    "$CHAT" "/"                         "CHAT-APP"
expect_body    "/homepage/ يعرض صفحة الشركة"                         "$APEX" "/homepage/"                "HOMEPAGE"
expect_redirect "/chat يعود إلى الجذر"                               "$APEX" "/chat"                     302 "/"
expect_redirect "/chat/ بشرطة مائلة كذلك"                            "$APEX" "/chat/"                    302 "/"
expect_body    "/login يعرض شاشة الدخول الموحدة على النطاق الرئيسي"      "$APEX" "/login"                    "LOGIN-PAGE"
expect_body    "وعلى chat. كذلك"                                      "$CHAT" "/login"                    "LOGIN-PAGE"
expect_body    "وبشرطة مائلة"                                         "$APEX" "/login/"                   "LOGIN-PAGE"
expect_body    "ومع وجهة العودة next في الاستعلام"                    "$APEX" "/login?next=%2Fworkspace%2F" "LOGIN-PAGE"
expect_redirect "corporate.html يحوّل إلى /homepage/ تحويلاً دائماً" "$APEX" "/corporate.html"           301 "/homepage/"
expect_code    "رابط الدعوة والقيم في الجذر تبقى للمحادثة (بلا تحويل)" "$APEX" "/?invite=abc123"          200
expect_code    "رابط إعادة التعيين في الجذر يبقى للمحادثة"             "$APEX" "/?reset=abc123"           200
expect_body    "/invite/{40} ما زال يصل api/invite-redeem.php"        "$APEX" "/invite/$(printf 'a%.0s' $(seq 1 40))" "INVITE-STUB"
expect_body    "survey.html على الجذر يعمل"                           "$APEX" "/survey.html?s=tok"        "SURVEY"
h="$(hdr "$CHAT" "/" x-robots-tag)"
[ "$h" = "noindex, nofollow" ] && ok "chat. قبل التبديل بترويسة noindex" || bad "chat. بلا فهرسة قبل التبديل" "noindex, nofollow" "${h:-لا ترويسة}"
h="$(hdr "$APEX" "/" x-robots-tag)"
[ -z "$h" ] && ok "النطاق الرئيسي بلا noindex" || bad "النطاق الرئيسي بلا noindex" "لا ترويسة" "$h"
expect_body    "sitemap.xml للنطاق الرئيسي فيه /homepage/"            "$APEX" "/sitemap.xml"              "/homepage/"
expect_body    "robots.txt واحد للنطاقين قبل التبديل"                  "$CHAT" "/robots.txt"               "Sitemap: https://wesalinnovation.sa/sitemap.xml"

say "الأسطر الحالية لم تتأثر"
expect_redirect "www يحوّل إلى النطاق الرئيسي"                       "www.$APEX" "/chat"                 301 "https://$APEX/chat"
out="$(curl -s -o /dev/null -w '%{http_code}|%{redirect_url}' -H "Host: $APEX" "$BASE/x")"
[ "${out%%|*}" = "301" ] && [[ "${out#*|}" == https://$APEX/x ]] && ok "http يحوّل إلى https" || bad "http يحوّل إلى https" "301 → https://$APEX/x" "$out"
expect_code    "تنفيذ سكربت داخل uploads/ محجوب"                     "$APEX" "/uploads/x.php"            403
h="$(hdr "$APEX" "/" x-frame-options)"
[ "$h" = "SAMEORIGIN" ] && ok "ترويسة X-Frame-Options باقية" || bad "X-Frame-Options" "SAMEORIGIN" "${h:-لا ترويسة}"
h="$(hdr "$APEX" "/assets/logo.webp" content-type)"
[[ "$h" == image/webp* ]] && ok "نوع MIME لـ webp باقٍ" || bad "نوع MIME لـ webp" "image/webp" "${h:-لا ترويسة}"

# غياب شاشة الدخول الجديدة عن الخادم: يبقى الدخول القديم في المحادثة بدل رابط منقطع، بعلامة # سليمة (لا %23)
mv "$DOCS/login.html" "$DOCS/login.off"
expect_redirect "/login بلا الشاشة الجديدة يفتح دخول المحادثة"        "$APEX" "/login"                    302 "/#login"
: > "$DOCS/.switch"
expect_redirect "وبعد التبديل يفتحه على chat. بعلامة # سليمة"          "$APEX" "/login"                    302 "https://$CHAT/#login"
rm -f "$DOCS/.switch"
mv "$DOCS/login.off" "$DOCS/login.html"

# حماية رابط corporate.html لو غابت الصفحة الجديدة عن الخادم
mv "$DOCS/homepage/index.html" "$DOCS/homepage/index.off"
expect_body    "corporate.html لا يُحوَّل إن غابت /homepage/"        "$APEX" "/corporate.html"           "OLD-CORPORATE"
mv "$DOCS/homepage/index.off" "$DOCS/homepage/index.html"

# ------------------------------------------------------------- مساحة العمل تحت /workspace
say "مساحة العمل على wesalinnovation.sa/workspace (.htaccess التطبيق نفسه)"
expect_body    "المسار يصل المتحكم الأمامي"                           "$APEX" "/workspace/login"          "WS-FRONT"
expect_body    "وعلى النطاق الفرعي القديم كذلك"                        "workspace.$APEX" "/workspace/login" "WS-FRONT"
expect_body    "الملفات الثابتة تُقدَّم مباشرة"                         "$APEX" "/workspace/build/app.js"   "WS-ASSET"
expect_redirect "www يحوّل إلى النطاق الرئيسي بمسار مساحة العمل"        "www.$APEX" "/workspace/login"      301 "https://$APEX/workspace/login"
out="$(curl -s -o /dev/null -w '%{http_code}|%{redirect_url}' -H "Host: $APEX" "$BASE/workspace/login")"
[ "${out%%|*}" = "301" ] && [[ "${out#*|}" == https://$APEX/workspace/login ]] && ok "http يحوّل إلى https" || bad "http في مساحة العمل يحوّل إلى https" "301 → https://$APEX/workspace/login" "$out"
expect_redirect "الشرطة المائلة الأخيرة تُزال"                          "$APEX" "/workspace/verify/"        301 "/workspace/verify"
h="$(hdr "$APEX" "/workspace/login" content-security-policy)"
[[ "$h" == *"'unsafe-eval'"* ]] && ok "سياسة مساحة العمل تسمح بـ unsafe-eval (Alpine)" || bad "سياسة مساحة العمل" "فيها 'unsafe-eval'" "${h:-لا ترويسة}"
h="$(hdr "$APEX" "/" content-security-policy)"
[[ "$h" != *"'unsafe-eval'"* ]] && ok "وسياسة بقية الموقع لا تتسع لها" || bad "سياسة الجذر" "بلا 'unsafe-eval'" "$h"
h="$(hdr "$APEX" "/workspace/login" permissions-policy)"
[[ "$h" != *"microphone=(self)"* ]] && ok "سياسة الأذونات في مساحة العمل بلا ميكروفون" || bad "سياسة الأذونات في مساحة العمل" "بلا microphone=(self)" "$h"
h="$(hdr "$APEX" "/" permissions-policy)"
[[ "$h" == *"microphone=(self)"* ]] && ok "والمحادثة تحتفظ بميكروفونها" || bad "سياسة أذونات الجذر" "microphone=(self)" "$h"

# ------------------------------------------------------------- بعد التبديل
: > "$DOCS/.switch"
say "بعد التبديل (بوجود .switch)"
expect_body    "الجذر على النطاق الرئيسي صار صفحة الشركة"            "$APEX" "/"                         "HOMEPAGE"
expect_body    "الجذر على chat. ما زال المحادثة"                      "$CHAT" "/"                         "CHAT-APP"
expect_redirect "/chat يذهب إلى chat."                               "$APEX" "/chat"                     302 "https://$CHAT/"
expect_redirect "/chat على chat. لا يدور"                            "$CHAT" "/chat"                     302 "https://$CHAT/"
expect_body    "/login يعرض الشاشة نفسها بعد التبديل بلا تحويل"          "$APEX" "/login"                    "LOGIN-PAGE"
expect_redirect "رابط الدعوة القديم /?invite= يحفظ قيمته"            "$APEX" "/?invite=abc123"           302 "https://$CHAT/?invite=abc123"
expect_redirect "رابط إعادة التعيين القديم /?reset= يحفظ قيمته"       "$APEX" "/?reset=tok9"              302 "https://$CHAT/?reset=tok9"
expect_redirect "رجوع الدعوة /?invited=1&t= يحفظ الاستعلام"          "$APEX" "/?invited=1&trial_until=5&t=ab" 302 "https://$CHAT/?invited=1&trial_until=5&t=ab"
expect_redirect "index.html القديمة تذهب إلى chat."                   "$APEX" "/index.html"               302 "https://$CHAT/"
expect_redirect "survey.html?s= القديم يحفظ رمز الاستبيان"           "$APEX" "/survey.html?s=tok"        302 "https://$CHAT/survey.html?s=tok"
I40="$(printf 'b%.0s' $(seq 1 40))"
expect_redirect "/invite/{40} القديم يذهب إلى chat."                 "$APEX" "/invite/$I40"              302 "https://$CHAT/invite/$I40"
expect_body    "/invite/{40} على chat. يصل api/invite-redeem.php"     "$CHAT" "/invite/$I40"              "INVITE-STUB"
expect_body    "survey.html على chat. يعمل"                           "$CHAT" "/survey.html?s=tok"        "SURVEY"
expect_redirect "/homepage/ يعود إلى الجذر (وقتياً)"                 "$APEX" "/homepage/"                302 "/"
expect_redirect "/homepage بلا شرطة كذلك"                            "$APEX" "/homepage"                 302 "/"
expect_redirect "corporate.html يذهب إلى /homepage/ ثم الجذر"        "$APEX" "/corporate.html"           301 "/homepage/"
expect_body    "الجذر لا يدور رغم قاعدة /homepage/ (كتابة داخلية)"    "$APEX" "/"                         "HOMEPAGE"
expect_code    "api يعمل على النطاق الرئيسي (نموذج التذاكر)"           "$APEX" "/api/auth.php"             200
expect_code    "api يعمل على chat."                                  "$CHAT" "/api/auth.php"             200
expect_body    "sitemap.xml للنطاق الرئيسي: الجذر فقط"                "$APEX" "/sitemap.xml"              "<loc>https://wesalinnovation.sa/</loc>"
expect_body    "sitemap.xml لـ chat. يخصه"                            "$CHAT" "/sitemap.xml"              "<loc>https://chat.wesalinnovation.sa/</loc>"
expect_body    "robots.txt لـ chat. يشير إلى خريطته"                  "$CHAT" "/robots.txt"               "Sitemap: https://chat.wesalinnovation.sa/sitemap.xml"
expect_body    "robots.txt للنطاق الرئيسي كما هو"                     "$APEX" "/robots.txt"               "Sitemap: https://wesalinnovation.sa/sitemap.xml"
h="$(hdr "$CHAT" "/" x-robots-tag)"
[ -z "$h" ] && ok "chat. بعد التبديل قابلة للفهرسة (بلا noindex)" || bad "chat. بعد التبديل بلا noindex" "لا ترويسة" "$h"
expect_redirect "www يبقى يحوّل إلى النطاق الرئيسي بعد التبديل"      "www.$APEX" "/"                     301 "https://$APEX/"
expect_body    "مساحة العمل لا تتأثر بالتبديل"                         "$APEX" "/workspace/login"          "WS-FRONT"

# ------------------------------------------------------------- الرجوع
rm -f "$DOCS/.switch"
say "بعد حذف .switch: رجوع فوري بلا نشر"
expect_body    "الجذر عاد إلى المحادثة"                              "$APEX" "/"                         "CHAT-APP"
expect_code    "رابط الدعوة القديم عاد بلا تحويل"                     "$APEX" "/?invite=abc123"           200
expect_redirect "/chat عاد إلى الجذر"                                "$APEX" "/chat"                     302 "/"

echo
if [ "$fail" -eq 0 ]; then
    printf '\033[32mنجحت كل الفحوص (%d)\033[0m\n' "$pass"; exit 0
fi
printf '\033[31mفشل %d من %d\033[0m\n' "$fail" "$((pass + fail))"
[ -s "$TMP/error.log" ] && { echo "آخر أخطاء Apache:"; tail -5 "$TMP/error.log"; }
exit 1
