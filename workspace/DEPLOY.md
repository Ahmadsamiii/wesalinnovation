# نشر مساحة العمل

مساحة العمل تطبيق Laravel مستقل عن الموقع العام، على نطاق فرعي
(`workspace.wesalinnovation.sa`) وقاعدة بيانات خاصة بها. ينشرها
`workspace/deploy.sh`، ويستدعيه `deploy.sh` الجذري تلقائياً بعد نجاح نشر
الموقع العام **متى جُهّز الخادم لها** (وُجد `shared/.env`). قبل التجهيز لا
يتغير شيء في نشر الموقع العام.

## ما يلزم الخادم

| المتطلب | التفاصيل |
|---|---|
| PHP | ‎8.4.1 فأحدث لسطر الأوامر وللنطاق الفرعي معاً، بالامتدادات: ctype, dom, fileinfo, filter, hash, iconv, json, libxml, mbstring, openssl, pcre, session, tokenizer, pdo_mysql |
| قاعدة البيانات | MySQL 8 أو MariaDB 10.6+، قاعدة مستقلة بترميز `utf8mb4_unicode_ci` |
| أدوات | composer 2، rsync، curl، mysqldump، git |
| Node | ‎22.12+ (أو 20.19+) لبناء الواجهة. إن لم يتوفر: nvm في حسابك بلا صلاحيات جذر (الخطوة ٥) |
| النطاق | نطاق فرعي بشهادة SSL |

إن كان `php` في سطر الأوامر إصداراً أقدم، مرّر مسار PHP 8.4 عند كل تشغيل:
`PHP_BIN=/opt/alt/php84/usr/bin/php` (المسار يختلف باختلاف الاستضافة).

## التجهيز أول مرة

كل الأوامر على الخادم عبر SSH، والمسارات الافتراضية أدناه هي ما يتوقعه
السكربت. غيّرها بمتغير `WORKSPACE_BASE` إن اختلفت.

### ١. النطاق الفرعي وقاعدة البيانات

من لوحة الاستضافة:

- أنشئ النطاق الفرعي `workspace.wesalinnovation.sa` وفعّل له SSL.
- اضبط إصدار PHP للنطاق على 8.4.
- أنشئ قاعدة بيانات ومستخدماً لها. لا تستخدم قاعدة الموقع العام: فصل بيانات
  العقود والفواتير مقصود.

### ٢. ملف الإعدادات المشترك

```bash
BASE=~/domains/workspace.wesalinnovation.sa
mkdir -p $BASE/shared
cp ~/wesal-repo/workspace/.env.example $BASE/shared/.env
chmod 600 $BASE/shared/.env
php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;'   # قيمة APP_KEY
```

عدّل في `$BASE/shared/.env`:

| المفتاح | القيمة |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | ناتج الأمر أعلاه. **لا يتغير بعدها أبداً**: تغييره يبطل الجلسات وروابط الدعوات |
| `APP_URL` | `https://workspace.wesalinnovation.sa` |
| `DB_CONNECTION` | `mysql`، ومعه `DB_HOST` و`DB_PORT` و`DB_DATABASE` و`DB_USERNAME` و`DB_PASSWORD` (بلا علامة `"` في كلمة المرور) |
| `SESSION_SECURE_COOKIE` | `true` |
| `LOG_LEVEL` | `warning` |
| `MAIL_MAILER` | `smtp`، ومعه `MAIL_HOST` و`MAIL_PORT` و`MAIL_USERNAME` و`MAIL_PASSWORD` و`MAIL_FROM_ADDRESS`. بلا بريد لا تصل الدعوات ولا استعادة كلمة المرور |
| `COMPANY_*` | بيانات المنشأة في رأس الفاتورة: الاسم والرقم الضريبي والسجل التجاري والعنوان |
| `PLATFORM_DB_*` | اختياري: قاعدة الموقع العام للقراءة فقط (الخطوة ٨) |

### ٣. أول نشر

```bash
cd ~/wesal-repo && bash workspace/deploy.sh
```

يبني الإصدار في `releases/`، وينسخ القاعدة احتياطياً، ويرحّل، ثم يوجّه
`current` إليه.

### ٤. توجيه النطاق إلى الإصدار الحالي

جذر النطاق يجب أن يكون `current/public` لا مجلد التطبيق كله (فيه `.env`
والكود):

```bash
cd ~/domains/workspace.wesalinnovation.sa
mv public_html public_html.orig      # ما أنشأته لوحة الاستضافة
ln -s ~/domains/workspace.wesalinnovation.sa/current/public public_html
```

افتح `https://workspace.wesalinnovation.sa/up`؛ يجب أن يرد بصفحة تقول إن
التطبيق يعمل.

### ٥. Node إن لم يكن متوفراً

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/master/install.sh | bash
source ~/.nvm/nvm.sh && nvm install 22
```

السكربت يحمّل nvm بنفسه في جلسات النشر غير التفاعلية.

### ٦. أول مدير نظام

لا تسجيل ذاتي ولا حسابات تجريبية في الإنتاج. أنشئ أول مدير نظام من سطر
الأوامر، ثم ينشئ هو بقية الحسابات بالدعوات من الواجهة:

```bash
php ~/domains/workspace.wesalinnovation.sa/current/artisan workspace:create-sysadmin you@wesalinnovation.sa --name="الاسم"
```

يطبع رابط الدعوة أيضاً (صالح ٧ أيام) لو لم يكن البريد مضبوطاً بعد.

### ٧. النشر التلقائي

بعد وجود `shared/.env` لا يلزم شيء آخر: كل دفع إلى `main` يشغّل سير
«نشر إلى الإنتاج» الحالي، فينشر `deploy.sh` الموقع العام أولاً ثم مساحة العمل.
مفتاح SSH المقيَّد والأمر المفروض كما هما بلا تغيير. دفعٌ لا يمس `workspace/`
يُكتشف ولا يُعاد بسببه بناء شيء.

فشل مساحة العمل لا يمس الموقع العام (نُشر قبلها وتحقق منه)، لكنه يُفشل
التشغيل في GitHub كي يُلاحظ.

### ٨. اختياري: ربط قاعدة الموقع العام

صفحتا «تكامل الذكاء الاصطناعي» و«تنبيهات الأسئلة عالية الحساسية» تقرآن سجل
أسئلة المساعد (`chat_logs`) من قاعدة الموقع العام، ولا تكتبان فيها شيئاً. إن
سمحت الاستضافة، أنشئ مستخدماً بصلاحية SELECT وحدها على تلك القاعدة، ثم اضبط
`PLATFORM_DB_HOST` و`PLATFORM_DB_DATABASE` و`PLATFORM_DB_USERNAME`
و`PLATFORM_DB_PASSWORD`، وأعد النشر بالأمر `WORKSPACE_FORCE_DEPLOY=1 bash workspace/deploy.sh`
لتُحدَّث ذاكرة الإعدادات.

## التشغيل اليومي

- **الحالة**: «صحة النظام» في الواجهة (مدير النظام) تفحص القاعدة والترحيلات
  والتخزين والمساحة والبريد وإعدادات الأمان، وتعرض آخر الأخطاء. «النطاقات
  والنشر» تعرض الإصدار المنشور.
- **الجدولة**: لا يحتاج التطبيق cron ولا عاملاً للمهام الخلفية.
- **التراجع**: السكربت يطبع أمر التراجع بعد كل نشر:
  `ln -sfn <الإصدار السابق> ~/domains/workspace.wesalinnovation.sa/current`.
  الترحيلات لا تُعكس تلقائياً. إن لزم استعادة القاعدة:
  `gunzip < ~/wesal-backups/workspace/db-<الوقت>.sql.gz | mysql -u <المستخدم> -p <القاعدة>`.
- **النسخ الاحتياطية**: نسخة من القاعدة قبل كل نشر في `~/wesal-backups/workspace`
  (آخر ١٠). المرفقات في `shared/storage/app/private`: ضمّنها في نسخ الاستضافة
  الدورية، فالسكربت لا ينسخها.
- **بعد التبديل** قد تخدم طلبات قليلة الإصدار السابق نحو دقيقتين، إلى أن تتجدد
  ذاكرة المسارات في PHP. هذا طبيعي.
- **إدخال المحتوى الصحي المعتمد في قاعدة معرفة المساعد**: من مجلد الإصدار الحالي
  `php artisan content:export-kb /tmp/wesal-kb`، ثم من مجلد الموقع العام
  `php tools/rag/ingest-kb.php /tmp/wesal-kb`.
