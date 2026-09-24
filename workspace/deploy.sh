#!/usr/bin/env bash
#
# نشر مساحة العمل (تطبيق Laravel في workspace/) على الخادم.
#
#     bash workspace/deploy.sh
#
# يستدعيه deploy.sh الجذري تلقائياً بعد نجاح نشر الموقع العام متى جُهّز الخادم
# لمساحة العمل (وُجد shared/.env)، ويُشغَّل وحده أيضاً. تجهيز الخادم أول مرة
# في workspace/DEPLOY.md.
#
# كل نشر إصدار كامل مستقل في releases/، و current رابط رمزي لا يُبدَّل إلا بعد
# نجاح البناء والنسخ الاحتياطي والترحيل؛ فشلٌ قبل ذلك لا يمس ما يعمل الآن.
# ‎.env والتخزين (المرفقات والسجلات والجلسات) في shared/ مشتركة بين الإصدارات.

set -euo pipefail

REPO_DIR="${WESAL_REPO:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
SRC="$REPO_DIR/workspace"
BASE="${WORKSPACE_BASE:-$HOME/domains/workspace.wesalinnovation.sa}"
SHARED="$BASE/shared"
RELEASES="$BASE/releases"
CURRENT="$BASE/current"
BACKUP_DIR="${WORKSPACE_BACKUPS:-$HOME/wesal-backups/workspace}"
PHP="${PHP_BIN:-php}"
COMPOSER="${COMPOSER_BIN:-composer}"
KEEP_RELEASES=5
KEEP_BACKUPS=10

# امتدادات PHP التي يطلبها composer.lock للإنتاج، ومعها محرك MySQL.
REQUIRED_EXTENSIONS=(ctype dom fileinfo filter hash iconv json libxml mbstring openssl pcre session tokenizer pdo_mysql)

IN_MAINTENANCE=0

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

# قيمة من shared/.env بلا علامات التنصيص المحيطة.
# لا تفشل أبداً (مفتاح غائب = قيمة فارغة) كي لا يخرج set -e بصمت بلا رسالة.
env_value() {
    { grep -E "^$1=" "$SHARED/.env" || true; } | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'\$//"
}

# ------------------------------------------------------------ الفحوص
say "١/٦ فحص الخادم"

[ -f "$SRC/artisan" ] || die "لا يوجد تطبيق مساحة العمل في: $SRC"

# حارس الوجهة كما في سكربت الموقع العام: نشر في مجلد خطأ يدمر موقعاً آخر.
case "$BASE" in
  *workspace*) ;;
  *) die "الوجهة لا تخص مساحة العمل — رفض النشر: $BASE" ;;
esac

[ -f "$SHARED/.env" ] || die "لا يوجد $SHARED/.env — جهّز الخادم أولاً (workspace/DEPLOY.md)."
[ -n "$(env_value APP_KEY)" ] || die "APP_KEY فارغ في .env. ولّده: $PHP $SRC/artisan key:generate --show"
[ "$(env_value APP_ENV)" = "production" ] || die "APP_ENV في .env ليس production."
[ "$(env_value APP_DEBUG)" = "false" ] || die "APP_DEBUG في .env ليس false؛ يكشف تفاصيل الأخطاء لأي زائر."
[ "$(env_value DB_CONNECTION)" = "mysql" ] || die "DB_CONNECTION في .env ليس mysql."
APP_URL="$(env_value APP_URL)"
case "$APP_URL" in
  https://*) ;;
  *) die "APP_URL في .env يجب أن يبدأ بـ https://" ;;
esac
case "$(env_value DB_PASSWORD)" in
  *'"'*) die "كلمة مرور قاعدة البيانات تحوي علامة تنصيص مزدوجة لا يقبلها ملف إعداد mysqldump؛ غيّرها." ;;
esac
ok ".env سليم — $APP_URL"

"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' \
  || die "PHP $("$PHP" -r 'echo PHP_VERSION;') أقدم من 8.4.1. مرّر PHP_BIN بمسار PHP 8.4 على الخادم."
missing=""
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    "$PHP" -r "exit(extension_loaded('$ext') ? 0 : 1);" || missing="$missing $ext"
done
[ -z "$missing" ] || die "امتدادات PHP ناقصة:$missing"
ok "PHP $("$PHP" -r 'echo PHP_VERSION;') بكل الامتدادات"

command -v "$COMPOSER" >/dev/null 2>&1 || die "composer غير موجود. ثبّته أو مرّر COMPOSER_BIN."
# nvm يُحمَّل في الجلسات التفاعلية فقط، وجلسة النشر عبر SSH ليست منها.
if ! command -v npm >/dev/null 2>&1 && [ -s "$HOME/.nvm/nvm.sh" ]; then
    set +u
    # shellcheck disable=SC1091
    . "$HOME/.nvm/nvm.sh" >/dev/null
    set -u
fi
command -v npm >/dev/null 2>&1 \
  || die "npm غير موجود لبناء الواجهة. ثبّت Node في حسابك بلا صلاحيات جذر عبر nvm (https://github.com/nvm-sh/nvm) ثم: nvm install 22"
node -e 'const [a, b] = process.versions.node.split(".").map(Number); process.exit(a > 22 || (a === 22 && b >= 12) || (a === 20 && b >= 19) ? 0 : 1)' \
  || die "Node $(node -v) أقدم مما يحتاجه Vite 8 (20.19 أو 22.12 فأحدث)."
command -v mysqldump >/dev/null 2>&1 || die "mysqldump غير موجود — لا ترحيل بلا نسخة احتياطية."
command -v curl >/dev/null 2>&1 || die "curl غير موجود للتحقق بعد النشر."
ok "composer وNode $(node -v) وmysqldump وcurl"

# لا إعادة بناء لما لم يتغير: كل دفع إلى main يستدعي هذا السكربت ولو لم يمس
# مساحة العمل. بصمة شجرة workspace/ في git تحسم ذلك.
tree="$(git -C "$REPO_DIR" rev-parse HEAD:workspace)"
if [ -L "$CURRENT" ] && [ "${WORKSPACE_FORCE_DEPLOY:-0}" != "1" ] \
   && grep -q "\"tree\":\"$tree\"" "$SHARED/storage/app/release.json" 2>/dev/null; then
    say "لا تغيير في مساحة العمل منذ آخر نشر — لا شيء يُنشر (للإجبار: WORKSPACE_FORCE_DEPLOY=1)."
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
ok "نسخة الكود → releases/$stamp"

(cd "$release" && "$COMPOSER" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress) \
  || die "فشل composer install — الإصدار الحالي لم يُمس."
ok "اعتماديات PHP"

(cd "$release" && npm ci --no-audit --no-fund --loglevel=error && npm run build --silent >/dev/null && rm -rf node_modules) \
  || die "فشل بناء الواجهة — الإصدار الحالي لم يُمس."
[ -f "$release/public/build/manifest.json" ] || die "البناء لم يُنتج public/build/manifest.json"
ok "الواجهة"

"$PHP" "$release/artisan" --version >/dev/null 2>&1 || die "الإصدار الجديد لا يقلع — الإصدار الحالي لم يُمس."
ok "الإصدار يقلع"

# --------------------------------------------------- نسخة قاعدة البيانات
say "٣/٦ نسخة احتياطية لقاعدة البيانات"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
# كلمة المرور في ملف مؤقت مقفل لا في سطر الأوامر، حيث يراها أي مستخدم عبر ps.
db_conf="$(mktemp)"
chmod 600 "$db_conf"
trap 'rm -f "$db_conf"' EXIT
{
    printf '[client]\n'
    printf 'host=%s\n' "$(env_value DB_HOST)"
    printf 'port=%s\n' "$(env_value DB_PORT)"
    printf 'user=%s\n' "$(env_value DB_USERNAME)"
    printf 'password="%s"\n' "$(env_value DB_PASSWORD | sed 's/\\/\\\\/g')"
} > "$db_conf"

dump="$BACKUP_DIR/db-$stamp.sql.gz"
if ! mysqldump --defaults-extra-file="$db_conf" --single-transaction --quick --no-tablespaces "$(env_value DB_DATABASE)" | gzip > "$dump"; then
    rm -f "$dump"
    die "فشل النسخ الاحتياطي لقاعدة البيانات — أُلغي النشر قبل الترحيل."
fi
[ -s "$dump" ] || die "النسخة الاحتياطية فارغة — أُلغي النشر قبل الترحيل."
ok "$(du -h "$dump" | cut -f1) → $dump"

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
        warn "تعذّر وضع الإصدار الحالي في الصيانة (معطوب؟) — يُكمل النشر ليحل محله."
    fi
fi

"$PHP" "$release/artisan" migrate --force --no-interaction \
  || die "فشل الترحيل؛ الإصدار السابق ما زال الحالي. نسخة القاعدة قبله: $dump"
ok "الترحيلات"

# القوالب المترجمة في التخزين المشترك؛ ما يخص الإصدار (الإعدادات والمسارات) جديد أصلاً.
"$PHP" "$release/artisan" view:clear >/dev/null
for step in config:cache route:cache view:cache event:cache; do
    "$PHP" "$release/artisan" "$step" --no-interaction >/dev/null || die "فشل $step؛ الإصدار السابق ما زال الحالي."
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

commit="$(git -C "$REPO_DIR" rev-parse HEAD 2>/dev/null || echo unknown)"
branch="$(git -C "$REPO_DIR" rev-parse --abbrev-ref HEAD 2>/dev/null || echo unknown)"
printf '{"commit":"%s","branch":"%s","tree":"%s","deployed_at":"%s","release":"%s"}\n' \
    "$commit" "$branch" "$tree" "$(date -Iseconds)" "$stamp" > "$SHARED/storage/app/release.json"

# ------------------------------------------------------------ التحقق
say "٥/٦ التحقق"

if [ "$(readlink "$BASE/public_html" 2>/dev/null || true)" != "$CURRENT/public" ]; then
    warn "public_html لا يشير إلى $CURRENT/public — النطاق لا يعرض هذا الإصدار (workspace/DEPLOY.md، الخطوة ٤)."
fi

rollback_hint=""
if [ -n "$previous" ]; then
    rollback_hint=" للتراجع: ln -sfn $previous $CURRENT"
fi

code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$APP_URL/up" || true)"
[ "$code" = "200" ] || die "لم يرد $APP_URL/up بـ 200 (الرد: $code).$rollback_hint"
ok "$APP_URL/up يرد 200"

code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$APP_URL/login" || true)"
[ "$code" = "200" ] || die "صفحة الدخول لا ترد بـ 200 (الرد: $code).$rollback_hint"
ok "صفحة الدخول تعمل"

# ------------------------------------------------------------ التنظيف
say "٦/٦ تنظيف الإصدارات القديمة"

ls -1dt "$RELEASES"/*/ 2>/dev/null | tail -n +$((KEEP_RELEASES + 1)) | while read -r old; do
    [ "${old%/}" = "$(readlink "$CURRENT")" ] && continue
    rm -rf "$old" && printf '  حُذف: %s\n' "$(basename "$old")"
done || true
ok "يُحتفظ بآخر $KEEP_RELEASES إصدارات"

say "تم نشر مساحة العمل — ${commit:0:7}"
[ -n "$previous" ] && printf 'للتراجع إلى الإصدار السابق:\n  ln -sfn %s %s\n\n' "$previous" "$CURRENT"
exit 0
