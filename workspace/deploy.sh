#!/usr/bin/env bash
#
# نشر مساحة العمل (تطبيق Laravel في workspace/) على الخادم.
#
#     bash workspace/deploy.sh           نشر
#     bash workspace/deploy.sh --check   فحص الخادم فقط، بلا نشر
#
# يستدعيه deploy.sh الجذري تلقائياً بعد نجاح نشر الموقع العام متى جُهّز الخادم
# لمساحة العمل (وُجد shared/.env)، ويُشغَّل وحده أيضاً. تجهيز الخادم أول مرة
# في workspace/DEPLOY.md.
#
# كل نشر إصدار كامل مستقل في releases/، و current رابط رمزي لا يُبدَّل إلا بعد
# نجاح البناء والنسخ الاحتياطي والترحيل، فالفشل قبل ذلك لا يمس ما يعمل الآن.
# ‎.env والتخزين (المرفقات والسجلات والجلسات) في shared/ مشتركة بين الإصدارات.
#
# إعدادات النشر تُقرأ من متغيرات البيئة، وإلا من shared/.env نفسه، لأن النشر
# الآلي عبر SSH لا يمرر متغيرات:
#   DEPLOY_PHP_BIN      مسار PHP 8.4 لسطر الأوامر (يُكتشف في مسارات Hostinger)
#   DEPLOY_COMPOSER_BIN مسار composer 2 (composer2 ثم composer افتراضاً)
#   DEPLOY_DOCROOT      مجلد النطاق الفرعي، وهو رابط رمزي إلى current/public
#   DEPLOY_PHP_HANDLER  إصدار PHP لمجلد النطاق حين يعمل الموقع العام بإصدار أقدم

set -euo pipefail

CHECK_ONLY=0
[ "${1:-}" = "--check" ] && CHECK_ONLY=1

REPO_DIR="${WESAL_REPO:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
SRC="$REPO_DIR/workspace"
BASE="${WORKSPACE_BASE:-$HOME/wesal-workspace}"
SHARED="$BASE/shared"
RELEASES="$BASE/releases"
CURRENT="$BASE/current"
BACKUP_DIR="${WORKSPACE_BACKUPS:-$HOME/wesal-backups/workspace}"
KEEP_RELEASES=5
KEEP_BACKUPS=10
MIN_PHP=8.4.1

# امتدادات PHP التي يطلبها composer.lock للإنتاج، ومعها محرك MySQL.
REQUIRED_EXTENSIONS=(ctype dom fileinfo filter hash iconv json libxml mbstring openssl pcre session tokenizer pdo_mysql)

IN_MAINTENANCE=0
PROBLEMS=0
PHP=php
db_conf=""
probe_file=""

say()  { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
warn() { printf '  \033[33m!\033[0m %s\n' "$*"; }
die()  {
    printf '\n\033[31m✗ %s\033[0m\n' "$*" >&2
    if [ "$IN_MAINTENANCE" -eq 1 ]; then
        printf '  مساحة العمل في وضع الصيانة. بعد الإصلاح: %s %s/artisan up\n' "$PHP" "$CURRENT" >&2
    fi
    printf '\n' >&2
    exit 1
}

cleanup() {
    if [ -n "$db_conf" ]; then rm -f "$db_conf"; fi
    if [ -n "$probe_file" ]; then rm -f "$probe_file"; fi
}
trap cleanup EXIT

# عائق يمنع النشر: يوقفه فوراً، ويُعَدّ في وضع الفحص ليكتمل التقرير.
problem() {
    if [ "$CHECK_ONLY" -eq 1 ]; then
        printf '  \033[31m✗\033[0m %s\n' "$*"
        PROBLEMS=$((PROBLEMS + 1))
    else
        die "$*"
    fi
}

# صحيح إن كان الإصدار الأول مساوياً للثاني أو أحدث منه.
version_at_least() {
    [ "$(printf '%s\n%s\n' "$2" "$1" | sort -V | head -n 1)" = "$2" ]
}

# قيمة من shared/.env بلا علامات التنصيص المحيطة. لا تفشل أبداً (مفتاح أو ملف
# غائب يعني قيمة فارغة) كي لا يخرج set -e بصمت بلا رسالة.
env_value() {
    [ -f "$SHARED/.env" ] || return 0
    { grep -E "^$1=" "$SHARED/.env" || true; } | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'\$//"
}

# إعداد نشر: متغير البيئة أولاً، ثم shared/.env، ثم القيمة الافتراضية.
deploy_setting() {
    local value="${!1:-}"
    [ -n "$value" ] || value="$(env_value "$1")"
    printf '%s' "${value:-${2:-}}"
}

# ------------------------------------------------------------ الفحوص
say "١/٦ فحص الخادم"

[ -f "$SRC/artisan" ] || die "لا يوجد تطبيق مساحة العمل في: $SRC"

# حارس الوجهة كما في سكربت الموقع العام: النشر في مجلد خطأ يدمر موقعاً آخر.
case "$BASE" in
  *workspace*) ;;
  *) die "الوجهة لا تخص مساحة العمل، فرُفض النشر: $BASE" ;;
esac

APP_URL=""
if [ -f "$SHARED/.env" ]; then
    env_ok=1
    [ -n "$(env_value APP_KEY)" ] || { problem "APP_KEY فارغ في .env (DEPLOY.md، الخطوة ٢)."; env_ok=0; }
    [ "$(env_value APP_ENV)" = "production" ] || { problem "APP_ENV في .env ليس production."; env_ok=0; }
    [ "$(env_value APP_DEBUG)" = "false" ] || { problem "APP_DEBUG في .env ليس false، فتظهر تفاصيل الأخطاء لأي زائر."; env_ok=0; }
    [ "$(env_value DB_CONNECTION)" = "mysql" ] || { problem "DB_CONNECTION في .env ليس mysql."; env_ok=0; }
    APP_URL="$(env_value APP_URL)"
    case "$APP_URL" in
      https://*/*) problem "APP_URL في .env يجب أن يكون النطاق وحده، بلا مسار ولا شرطة مائلة في آخره."; env_ok=0 ;;
      https://?*) ;;
      *) problem "APP_URL في .env يجب أن يبدأ بـ https://"; env_ok=0 ;;
    esac
    case "$(env_value DB_PASSWORD)" in
      *'"'*) problem "كلمة مرور قاعدة البيانات فيها علامة تنصيص مزدوجة لا يقبلها mysqldump، فغيّرها."; env_ok=0 ;;
    esac
    [ "$env_ok" -eq 0 ] || ok ".env سليم ($APP_URL)"
else
    problem "لا يوجد $SHARED/.env، فأنشئه أولاً (DEPLOY.md، الخطوة ٢)."
fi

# PHP لسطر الأوامر: المحدد في DEPLOY_PHP_BIN وحده، وإلا أول ما يصلح من php في
# المسار ومسارات Hostinger (CloudLinux)، فإصدار php فيها قد يختلف عن إصدار الموقع.
php_ok() { "$1" -r "exit(version_compare(PHP_VERSION, '$MIN_PHP', '>=') ? 0 : 1);" 2>/dev/null; }
configured_php="$(deploy_setting DEPLOY_PHP_BIN "${PHP_BIN:-}")"
PHP=""
if [ -n "$configured_php" ]; then
    if php_ok "$configured_php"; then
        PHP="$configured_php"
    else
        problem "DEPLOY_PHP_BIN ($configured_php) لا يعمل أو أقدم من PHP $MIN_PHP."
    fi
else
    for candidate in php /opt/alt/php85/usr/bin/php /opt/alt/php84/usr/bin/php /usr/bin/php8.5 /usr/bin/php8.4; do
        if command -v "$candidate" >/dev/null 2>&1 && php_ok "$candidate"; then
            PHP="$(command -v "$candidate")"
            break
        fi
    done
    [ -n "$PHP" ] || problem "لم أجد PHP $MIN_PHP فأحدث. اضبط DEPLOY_PHP_BIN في .env بمساره (مثل /opt/alt/php84/usr/bin/php)."
fi

if [ -n "$PHP" ]; then
    # composer وأوامره (@php) تستدعي php من المسار، فيتقدمها الإصدار المختار.
    PATH="$(dirname "$PHP"):$PATH"
    missing=""
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        "$PHP" -r "exit(extension_loaded('$ext') ? 0 : 1);" || missing="$missing $ext"
    done
    if [ -z "$missing" ]; then
        ok "PHP $("$PHP" -r 'echo PHP_VERSION;') بكل الامتدادات ($PHP)"
    else
        problem "امتدادات PHP ناقصة:$missing"
    fi
else
    PHP=php
fi

COMPOSER="$(deploy_setting DEPLOY_COMPOSER_BIN "${COMPOSER_BIN:-}")"
if [ -n "$COMPOSER" ]; then
    COMPOSER="$(command -v "$COMPOSER" || printf '%s' "$COMPOSER")"
else
    COMPOSER="$(command -v composer2 || command -v composer || true)"
fi
# composer ملف PHP في الغالب، فيُشغَّل بالإصدار المختار لا بما يقع في المسار.
run_composer() {
    if head -n 1 "$COMPOSER" 2>/dev/null | grep -q php; then
        "$PHP" "$COMPOSER" "$@"
    else
        "$COMPOSER" "$@"
    fi
}
if [ -n "$COMPOSER" ] && [ -f "$COMPOSER" ]; then
    composer_about="$(run_composer --version --no-ansi 2>&1 || true)"
    composer_major="$(printf '%s\n' "$composer_about" | sed -n 's/^Composer version \([0-9]*\)\..*/\1/p' | head -n 1)"
    composer_php="$(printf '%s\n' "$composer_about" | sed -n 's/^PHP version \([0-9][0-9.]*\).*/\1/p' | head -n 1)"
    if [ "$composer_major" != "2" ]; then
        problem "composer في $COMPOSER لا يعمل أو ليس الإصدار 2. اضبط DEPLOY_COMPOSER_BIN (DEPLOY.md، استكشاف الأعطال)."
    elif [ -n "$composer_php" ] && ! version_at_least "$composer_php" "$MIN_PHP"; then
        problem "composer في $COMPOSER يعمل بـ PHP $composer_php. اضبط DEPLOY_COMPOSER_BIN (DEPLOY.md، استكشاف الأعطال)."
    else
        ok "composer 2 ($COMPOSER)"
    fi
else
    problem "لم أجد composer. اضبط DEPLOY_COMPOSER_BIN في .env بمساره."
fi

# nvm يُحمَّل في الجلسات التفاعلية فقط، وجلسة النشر عبر SSH ليست منها.
if ! command -v npm >/dev/null 2>&1 && [ -s "$HOME/.nvm/nvm.sh" ]; then
    set +u
    # shellcheck disable=SC1091
    . "$HOME/.nvm/nvm.sh" >/dev/null
    set -u
fi
if ! command -v npm >/dev/null 2>&1; then
    problem "npm غير موجود لبناء الواجهة. ثبّت Node عبر nvm (DEPLOY.md، الخطوة ٣)."
elif node -e 'const [a, b] = process.versions.node.split(".").map(Number); process.exit(a > 22 || (a === 22 && b >= 12) || (a === 20 && b >= 19) ? 0 : 1)'; then
    ok "Node $(node -v)"
else
    problem "Node $(node -v) أقدم مما يحتاجه Vite 8 (20.19 أو 22.12 فأحدث)."
fi

for tool in mysqldump curl rsync git; do
    command -v "$tool" >/dev/null 2>&1 || problem "$tool غير موجود على الخادم."
done

# كلمة مرور القاعدة في ملف مؤقت مقفل لا في سطر الأوامر، حيث يراها أي مستخدم عبر ps.
db_conf="$(mktemp)"
chmod 600 "$db_conf"
{
    printf '[client]\n'
    [ -z "$(env_value DB_HOST)" ] || printf 'host=%s\n' "$(env_value DB_HOST)"
    [ -z "$(env_value DB_PORT)" ] || printf 'port=%s\n' "$(env_value DB_PORT)"
    printf 'user=%s\n' "$(env_value DB_USERNAME)"
    printf 'password="%s"\n' "$(env_value DB_PASSWORD | sed 's/\\/\\\\/g')"
} > "$db_conf"

# الاتصال بالقاعدة قبل البناء: كلمة مرور خاطئة تُكتشف الآن لا بعد دقائق.
mysql_client="$(command -v mysql || command -v mariadb || true)"
if [ -f "$SHARED/.env" ] && [ -n "$mysql_client" ]; then
    if "$mysql_client" --defaults-extra-file="$db_conf" -e 'SELECT 1' "$(env_value DB_DATABASE)" >/dev/null 2>&1; then
        ok "قاعدة البيانات $(env_value DB_DATABASE) تقبل الاتصال"
    else
        problem "تعذّر الاتصال بقاعدة البيانات $(env_value DB_DATABASE) بإعدادات .env (المضيف والمستخدم وكلمة المرور)."
    fi
fi

PHP_HANDLER="$(deploy_setting DEPLOY_PHP_HANDLER '')"
case "$PHP_HANDLER" in
  '' ) ;;
  application/x-httpd-*) ok "إصدار PHP لمجلد النطاق: $PHP_HANDLER" ;;
  *) problem "DEPLOY_PHP_HANDLER يجب أن يبدأ بـ application/x-httpd- (مثل application/x-httpd-alt-php84)." ;;
esac

# النطاق يعرض current/public عبر رابط رمزي، وإلا فلا يصل الإصدار المنشور إلى أحد
# وتفشل خطوة التحقق بعد الترحيل. يُفحص الرابط نفسه لأن current قد لا يوجد بعد.
DOCROOT="$(deploy_setting DEPLOY_DOCROOT "$HOME/domains/wesalinnovation.sa/public_html/workspace")"
docroot_target="$(readlink "$DOCROOT" 2>/dev/null || true)"
if [ "$DOCROOT" = "$CURRENT/public" ] || [ "$docroot_target" = "$CURRENT/public" ] \
   || { [ -e "$DOCROOT" ] && [ "$(readlink -f "$DOCROOT")" = "$(readlink -f "$CURRENT/public" 2>/dev/null || true)" ]; }; then
    ok "مجلد النطاق مربوط بالإصدار الحالي ($DOCROOT)"
elif [ -e "$DOCROOT" ] || [ -L "$DOCROOT" ]; then
    problem "مجلد النطاق $DOCROOT لم يُربط بـ $CURRENT/public بعد (DEPLOY.md، الخطوة ٥)."
else
    problem "مجلد النطاق $DOCROOT غير موجود. أنشئ النطاق الفرعي من hPanel، أو اضبط DEPLOY_DOCROOT في .env."
fi

# إصدار PHP الذي يشغّل به الخادمُ النطاقَ قد يختلف عن إصدار سطر الأوامر: ملف
# مؤقت في مجلد النطاق يطبع الإصدار ثم يُحذف. في وضع الفحص فقط.
check_web_php() {
    if [ -z "$APP_URL" ]; then
        return 0
    fi
    if [ ! -d "$DOCROOT" ]; then
        warn "إصدار PHP الذي يشغّل النطاق يُعرف بعد أول نشر، فمجلده الآن رابط إلى إصدار لم يُبنَ."
        return 0
    fi
    probe_file="$DOCROOT/wesal-php-probe-$$-$RANDOM.php"
    printf '<?php echo PHP_VERSION;\n' > "$probe_file"
    web_php="$(curl -s --max-time 20 "$APP_URL/$(basename "$probe_file")" || true)"
    rm -f "$probe_file"
    probe_file=""

    if [ "${web_php:0:5}" = '<?php' ]; then
        problem "النطاق يعرض ملفات PHP نصاً بلا تشغيل. راجع DEPLOY_PHP_HANDLER (DEPLOY.md، استكشاف الأعطال)."
    elif ! printf '%s' "$web_php" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$'; then
        warn "لم يرد $APP_URL على ملف الفحص، فلم أعرف إصدار PHP للنطاق. تحقق من النطاق الفرعي وشهادة SSL في hPanel."
    elif version_at_least "$web_php" "$MIN_PHP"; then
        ok "النطاق يعمل بـ PHP $web_php"
    elif [ -n "$PHP_HANDLER" ] && grep -q "^AddHandler $PHP_HANDLER " "$DOCROOT/.htaccess" 2>/dev/null; then
        problem "النطاق يعمل بـ PHP $web_php رغم DEPLOY_PHP_HANDLER، فالإصدار المحدد غير متاح على الخادم."
    elif [ -n "$PHP_HANDLER" ]; then
        warn "النطاق يعمل الآن بـ PHP $web_php، ويرفعه DEPLOY_PHP_HANDLER من أول نشر."
    else
        problem "النطاق يعمل بـ PHP $web_php، ومساحة العمل تحتاج $MIN_PHP فأحدث. اضبط DEPLOY_PHP_HANDLER (DEPLOY.md، استكشاف الأعطال)."
    fi
}

if [ "$CHECK_ONLY" -eq 1 ]; then
    check_web_php
    if [ "$PROBLEMS" -eq 0 ]; then
        say "الخادم جاهز للنشر."
        exit 0
    fi
    say "عوائق تمنع النشر: $PROBLEMS، وتفاصيلها أعلاه."
    exit 1
fi

# لا إعادة بناء لما لم يتغير: كل دفع إلى main يستدعي هذا السكربت ولو لم يمس
# مساحة العمل. يُبنى من جديد إن تغيّر الكود (بصمة شجرة workspace/ في git)، أو
# ‎.env (قيمه تُحفظ في ذاكرة الإعدادات عند البناء فلا يسري تعديلها بدونه)، أو إن
# بقي current على إصدار لم يجتز التحقق فلم يُسجَّل.
tree="$(git -C "$REPO_DIR" rev-parse HEAD:workspace)"
settings="$(sha1sum "$SHARED/.env" | cut -c1-16)"
live="$(basename "$(readlink "$CURRENT" 2>/dev/null || echo none)")"
if [ "${WORKSPACE_FORCE_DEPLOY:-0}" != "1" ] \
   && grep -q "\"tree\":\"$tree\",\"settings\":\"$settings\",.*\"release\":\"$live\"" "$SHARED/storage/app/release.json" 2>/dev/null; then
    say "لا تغيير في مساحة العمل ولا في إعداداتها منذ آخر نشر، فلا شيء يُنشر (للإجبار: WORKSPACE_FORCE_DEPLOY=1)."
    exit 0
fi

# ------------------------------------------------------------ البناء
say "٢/٦ بناء الإصدار"

stamp="$(date +%Y%m%d-%H%M%S)"
release="$RELEASES/$stamp"
mkdir -p "$RELEASES" "$SHARED/storage"

# هيكل مجلد التخزين في أول نشر فقط: المجلدات وملفات .gitignore، لا أي ملف آخر.
rsync -a --ignore-existing --include='*/' --include='.gitignore' --exclude='*' "$SRC/storage/" "$SHARED/storage/"

rsync -a \
    --exclude=/.env --exclude=/storage/ --exclude=/vendor/ --exclude=/node_modules/ \
    --exclude=/public/build/ --exclude=/public/hot --exclude=/tests/ --exclude=/database/database.sqlite \
    "$SRC/" "$release/"
ln -s "$SHARED/.env" "$release/.env"
ln -s "$SHARED/storage" "$release/storage"
ok "نسخة الكود: releases/$stamp"

if [ -n "$PHP_HANDLER" ]; then
    # سطر الإصدار في أول الملف كما يطلب Hostinger، ويخص هذا المجلد وحده.
    { printf 'AddHandler %s .php .php8 .phtml\n\n' "$PHP_HANDLER"; cat "$release/public/.htaccess"; } > "$release/public/.htaccess.new"
    mv "$release/public/.htaccess.new" "$release/public/.htaccess"
    ok "إصدار PHP لمجلد النطاق: $PHP_HANDLER"
fi

(cd "$release" && run_composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress) \
  || die "فشل composer install، والإصدار الحالي لم يُمس."
ok "اعتماديات PHP"

(cd "$release" && npm ci --no-audit --no-fund --loglevel=error && npm run build --silent >/dev/null && rm -rf node_modules) \
  || die "فشل بناء الواجهة، والإصدار الحالي لم يُمس."
[ -f "$release/public/build/manifest.json" ] || die "البناء لم يُنتج public/build/manifest.json"
ok "الواجهة"

"$PHP" "$release/artisan" --version >/dev/null 2>&1 || die "الإصدار الجديد لا يقلع، والإصدار الحالي لم يُمس."
ok "الإصدار يقلع"

# --------------------------------------------------- نسخة قاعدة البيانات
say "٣/٦ نسخة احتياطية لقاعدة البيانات"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

dump="$BACKUP_DIR/db-$stamp.sql.gz"
if ! mysqldump --defaults-extra-file="$db_conf" --single-transaction --quick --no-tablespaces "$(env_value DB_DATABASE)" | gzip > "$dump"; then
    rm -f "$dump"
    die "فشل النسخ الاحتياطي لقاعدة البيانات، فأُلغي النشر قبل الترحيل."
fi
[ -s "$dump" ] || die "النسخة الاحتياطية فارغة، فأُلغي النشر قبل الترحيل."
ok "$(du -h "$dump" | cut -f1): $dump"

ls -1t "$BACKUP_DIR"/db-*.sql.gz 2>/dev/null | tail -n +$((KEEP_BACKUPS + 1)) | while read -r old; do
    rm -f "$old"
done || true

# ---------------------------------------------------- الترحيل والتبديل
say "٤/٦ الترحيل والتبديل"

previous=""
if [ -L "$CURRENT" ]; then
    previous="$(readlink "$CURRENT")"
    # ملف الصيانة في التخزين المشترك، فيراه الإصداران معاً حتى التبديل.
    if "$PHP" "$CURRENT/artisan" down --retry=15 >/dev/null; then
        IN_MAINTENANCE=1
        ok "وضع الصيانة"
    else
        warn "تعذّر وضع الإصدار الحالي في الصيانة (قد يكون معطوباً)، ويكمل النشر ليحل محله."
    fi
fi

"$PHP" "$release/artisan" migrate --force --no-interaction \
  || die "فشل الترحيل، والإصدار السابق ما زال الحالي. نسخة القاعدة قبله: $dump"
ok "الترحيلات"

# القوالب المترجمة في التخزين المشترك، أما إعدادات الإصدار ومساراته فجديدة أصلاً.
"$PHP" "$release/artisan" view:clear >/dev/null
for step in config:cache route:cache view:cache event:cache; do
    "$PHP" "$release/artisan" "$step" --no-interaction >/dev/null || die "فشل $step، والإصدار السابق ما زال الحالي."
done
ok "ذاكرة الإعدادات والمسارات والقوالب"

# التبديل في خطوة واحدة: الرابط الجديد باسم مؤقت ثم نقله فوق القديم.
ln -sfn "$release" "$BASE/current.new"
mv -Tf "$BASE/current.new" "$CURRENT"
ok "الإصدار الحالي: $stamp"

if [ "$IN_MAINTENANCE" -eq 1 ]; then
    "$PHP" "$CURRENT/artisan" up >/dev/null
    IN_MAINTENANCE=0
    ok "خرجت من وضع الصيانة"
fi

# ------------------------------------------------------------ التحقق
say "٥/٦ التحقق"

rollback_hint=""
if [ -n "$previous" ]; then
    rollback_hint=" للتراجع: ln -sfn $previous $CURRENT"
fi

# الحالة 200 وحدها لا تكفي: سطر إصدار PHP خاطئ قد يعرض ملف PHP نصاً بالحالة 200.
up="$(curl -s --max-time 20 -w '\n%{http_code}' "$APP_URL/up" || true)"
code="$(printf '%s\n' "$up" | tail -n 1)"
if [ "$code" != "200" ] || ! printf '%s' "$up" | grep -q 'Application up'; then
    case "$up" in
      *'<?php'*) hint="النطاق يعرض ملفات PHP نصاً بلا تشغيل، فقيمة DEPLOY_PHP_HANDLER غير صحيحة." ;;
      *'Composer detected issues'*) hint="النطاق يعمل بإصدار PHP أقدم مما تحتاجه مساحة العمل. اضبط DEPLOY_PHP_HANDLER (DEPLOY.md، استكشاف الأعطال)." ;;
      *) hint="راجع «استكشاف الأعطال» في DEPLOY.md وسجل $SHARED/storage/logs." ;;
    esac
    die "لم يرد $APP_URL/up بأن التطبيق يعمل (الرد: $code). $hint$rollback_hint"
fi
ok "$APP_URL/up: التطبيق يعمل"

code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$APP_URL/login" || true)"
[ "$code" = "200" ] || die "صفحة الدخول لا ترد بـ 200 (الرد: $code).$rollback_hint"
ok "صفحة الدخول تعمل"

# بعد التحقق لا قبله: نشرٌ لم يجتز التحقق يُعاد في المرة التالية ولو لم يتغير الكود.
commit="$(git -C "$REPO_DIR" rev-parse HEAD 2>/dev/null || echo unknown)"
branch="$(git -C "$REPO_DIR" rev-parse --abbrev-ref HEAD 2>/dev/null || echo unknown)"
printf '{"commit":"%s","branch":"%s","tree":"%s","settings":"%s","deployed_at":"%s","release":"%s"}\n' \
    "$commit" "$branch" "$tree" "$settings" "$(date -Iseconds)" "$stamp" > "$SHARED/storage/app/release.json"

# ------------------------------------------------------------ التنظيف
say "٦/٦ تنظيف الإصدارات القديمة"

ls -1dt "$RELEASES"/*/ 2>/dev/null | tail -n +$((KEEP_RELEASES + 1)) | while read -r old; do
    [ "${old%/}" = "$(readlink "$CURRENT")" ] && continue
    rm -rf "$old" && printf '  حُذف: %s\n' "$(basename "$old")"
done || true
ok "يُحتفظ بآخر $KEEP_RELEASES إصدارات"

say "تم نشر مساحة العمل (${commit:0:7})."
[ -n "$previous" ] && printf 'للتراجع إلى الإصدار السابق:\n  ln -sfn %s %s\n\n' "$previous" "$CURRENT"
exit 0
