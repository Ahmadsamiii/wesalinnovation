#!/usr/bin/env bash
#
# نشر وصال إلى الإنتاج.
#
# يُشغَّل على الخادم من داخل نسخة المستودع:
#     bash deploy.sh
#
# مبدأ التصميم: يرفض النشر عند أدنى شك بدل أن ينشر حالة نصف سليمة. كل فحص
# هنا وقع عطله فعلاً مرة واحدة على الأقل في هذا المشروع.

set -euo pipefail

REPO_DIR="${WESAL_REPO:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
TARGET="${WESAL_TARGET:-$HOME/domains/wesalinnovation.sa/public_html}"
BRANCH="${WESAL_BRANCH:-main}"
HEALTH_URL="${WESAL_HEALTH_URL:-https://wesalinnovation.sa/api/auth.php}"
BACKUP_DIR="${WESAL_BACKUPS:-$HOME/wesal-backups}"
KEEP_BACKUPS=5

# ما يُنشر — قائمة سماح صريحة. أي ملف جديد في المستودع لا يصل الإنتاج حتى
# يُضاف هنا عمداً؛ عكسها (قائمة منع) يسرّب الجديد افتراضياً.
SYNC_DIRS=(api assets fonts partners tools)
SYNC_FILES=(.htaccess index.html corporate.html survey.html ticket.html schema.sql)

# ما لا يُلمس أبداً: الإعدادات فيها أسرار الإنتاج وليست في المستودع أصلاً،
# والمرفوعات بيانات مستخدمين لا نسخة منها في أي مكان آخر.
PROTECTED=(--exclude=config.php --exclude=uploads/ --exclude=storage/)

say()  { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
die()  { printf '\n\033[31m✗ %s\033[0m\n\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------- الفحوص
say "١/٥ فحص المصدر"

cd "$REPO_DIR"
[ -d .git ] || die "لا يوجد مستودع git في: $REPO_DIR"
[ -d "$TARGET" ] || die "مجلد الوجهة غير موجود: $TARGET"

# حارس الوجهة: نشر على النطاق الخطأ يدمر موقعاً آخر على نفس الاستضافة.
case "$TARGET" in
  *wesalinnovation.sa*) ;;
  *) die "الوجهة لا تخص wesalinnovation.sa — رفض النشر: $TARGET" ;;
esac
ok "الوجهة: $TARGET"

[ -z "$(git status --porcelain)" ] || die "الشجرة غير نظيفة. احفظ تغييراتك أو تراجع عنها قبل النشر."
ok "الشجرة نظيفة"

current="$(git rev-parse --abbrev-ref HEAD)"
[ "$current" = "$BRANCH" ] || die "أنت على الفرع '$current' لا '$BRANCH'. بدّل الفرع أو مرّر WESAL_BRANCH."

git fetch origin "$BRANCH" --quiet 2>/dev/null \
  || die "تعذّر الوصول إلى origin. تحقّق من اتصال الخادم بـ GitHub ومن صلاحية مفتاح النشر."
local_sha="$(git rev-parse HEAD)"
remote_sha="$(git rev-parse "origin/$BRANCH")"
[ "$local_sha" = "$remote_sha" ] || die "الفرع '$BRANCH' غير متزامن مع origin. شغّل: git pull origin $BRANCH"
ok "متزامن مع origin/$BRANCH — ${local_sha:0:7}"

# ------------------------------------------------- فحص سلامة ملفات PHP
say "٢/٥ فحص ملفات PHP"

php_bad=0
# قائمة سماح، لا find مع process substitution: بعض بيئات الاستضافة المقيّدة
# (CageFS على CloudLinux مثلاً) لا توفّر /dev/fd، فتفشل قراءة <(...) بصمت —
# تطبع الحلقة صفر ملفات والسكربت يواصل وكأن الفحص نجح. glob مباشر يعمل في
# كل بيئة بلا استثناء ولا يتأثر بمسافات في الأسماء.
for f in api/*.php tools/*.php; do
    [ -f "$f" ] || continue
    # بايت واحد خارج وسوم PHP — مسافة أو حرف شارد من تحرير يدوي — يُطبع قبل
    # كل رد فيكسر تحليله في المتصفح، بينما تبقى الحالة 200. عطّل هذا تسجيل
    # الدخول عن كل المستخدمين مرة، وكلّف ساعة تشخيص.
    if [ "$(head -c 5 "$f")" != "<?php" ]; then
        printf '  \033[31m✗\033[0m بايتات دخيلة قبل <?php في: %s\n' "$f"
        php_bad=1
    fi
    if ! php -l "$f" >/dev/null 2>&1; then
        printf '  \033[31m✗\033[0m خطأ نحوي في: %s\n' "$f"
        # || true ضروري: pipefail مع فشل php -l يُسقط السكربت هنا قبل أن
        # يبلغ رسالته الجامعة، فيتوقف عند أول ملف ولا يعرض بقية المعطوبة.
        php -l "$f" 2>&1 | head -2 | sed 's/^/      /' || true
        php_bad=1
    fi
done

[ "$php_bad" -eq 0 ] || die "ملفات PHP غير سليمة — أُلغي النشر قبل أن يمس الخادم."
ok "كل ملفات PHP تبدأ بـ <?php وخالية من الأخطاء النحوية"

# ------------------------------------------------------- نسخة احتياطية
say "٣/٥ نسخة احتياطية"

mkdir -p "$BACKUP_DIR"
stamp="$(date +%Y%m%d-%H%M%S)"
archive="$BACKUP_DIR/public_html-$stamp.tar.gz"
# المرفوعات مستثناة: قد تكون ضخمة، وrsync لا يمسّها أصلاً.
tar -czf "$archive" -C "$TARGET" --exclude=uploads --exclude=storage . 2>/dev/null || true
[ -s "$archive" ] || die "فشل إنشاء النسخة الاحتياطية — أُلغي النشر."
ok "$(du -h "$archive" | cut -f1) → $archive"

ls -1t "$BACKUP_DIR"/public_html-*.tar.gz 2>/dev/null | tail -n +$((KEEP_BACKUPS + 1)) | while read -r old; do
    rm -f "$old" && printf '  حُذفت نسخة قديمة: %s\n' "$(basename "$old")"
done

# ---------------------------------------------------------------- النشر
say "٤/٥ النشر"

for d in "${SYNC_DIRS[@]}"; do
    [ -d "$REPO_DIR/$d" ] || continue
    # --delete داخل المجلد وحده: ملف حُذف من المستودع يُحذف من الإنتاج،
    # والمحميّ أعلاه لا يُحذف لأن --exclude يحميه من الطرفين.
    rsync -a --delete "${PROTECTED[@]}" "$REPO_DIR/$d/" "$TARGET/$d/"
    ok "$d/"
done

for f in "${SYNC_FILES[@]}"; do
    [ -f "$REPO_DIR/$f" ] || continue
    rsync -a "$REPO_DIR/$f" "$TARGET/$f"
    ok "$f"
done

# ------------------------------------------------------- التحقق بعد النشر
say "٥/٥ التحقق"

cfg="$TARGET/api/config.php"
[ -f "$cfg" ] || die "اختفى api/config.php من الإنتاج! استعد النسخة: $archive"
[ "$(head -c 5 "$cfg")" = "<?php" ] || die "api/config.php لا يبدأ بـ <?php — الموقع معطّل الآن. استعد النسخة: $archive"
ok "الإعدادات سليمة ولم تُمس"

body="$(curl -s --max-time 20 -X POST "$HEALTH_URL" \
        -H 'Content-Type: application/json' -d '{"action":"__health__"}' || true)"

[ -n "$body" ] || die "الخادم لم يرد على $HEALTH_URL. استعد النسخة: $archive"

# لا نكتفي بالحالة 200: العطل الذي كلّفنا ساعة كان 200 بجسم غير قابل للتحليل.
if ! printf '%s' "$body" | php -r 'exit(json_decode(stream_get_contents(STDIN)) === null ? 1 : 0);'; then
    printf '  الرد الخام: %s\n' "$(printf '%s' "$body" | head -c 120)" >&2
    die "رد الخادم ليس JSON صالحاً — الموقع معطّل. استعد النسخة: $archive"
fi
ok "الواجهة البرمجية ترد بـ JSON صالح"

say "تم النشر بنجاح — ${local_sha:0:7}"
printf 'للتراجع:\n  tar -xzf %s -C %s\n\n' "$archive" "$TARGET"

# ------------------------------------------- مساحة العمل (بعد نجاح الموقع)
# تطبيق Laravel مستقل في workspace/ بسكربت نشره الخاص، لا يُنشر إلا إذا جُهّز
# له الخادم (وُجد ملف .env المشترك — workspace/DEPLOY.md). يأتي بعد نشر
# الموقع العام والتحقق منه، ففشله لا يمس الموقع المنشور للتو؛ لكنه يُفشل
# التشغيل كله كي لا يمر دون أن يلاحظه أحد.
WORKSPACE_BASE="${WORKSPACE_BASE:-$HOME/domains/workspace.wesalinnovation.sa}"
if [ -f "$WORKSPACE_BASE/shared/.env" ]; then
    WORKSPACE_BASE="$WORKSPACE_BASE" bash "$REPO_DIR/workspace/deploy.sh" \
      || die "الموقع العام منشور وسليم، لكن نشر مساحة العمل فشل — رسالته أعلاه تذكر حالتها وطريقة التراجع."
fi
