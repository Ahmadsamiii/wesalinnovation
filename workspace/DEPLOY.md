# نشر مساحة العمل

مساحة العمل تطبيق Laravel مستقل عن الموقع العام، على النطاق الفرعي
`workspace.wesalinnovation.sa` وبقاعدة بيانات خاصة بها. ينشرها
`workspace/deploy.sh`، ويستدعيه `deploy.sh` الجذري بعد نجاح نشر الموقع العام
**متى وُجد `~/wesal-workspace/shared/.env`**. قبل ذلك لا يتغير شيء في نشر الموقع
العام.

## ترتيب الملفات على Hostinger

| المسار | ما فيه |
|---|---|
| `~/wesal-repo` | نسخة المستودع التي ينشر منها `deploy.sh` الموقع العام |
| `~/wesal-workspace/shared/.env` | إعدادات الإنتاج وأسراره |
| `~/wesal-workspace/shared/storage` | المرفقات والسجلات والجلسات |
| `~/wesal-workspace/releases/<الوقت>` | إصدار كامل لكل نشر، ويُحتفظ بآخر ٥ |
| `~/wesal-workspace/current` | رابط إلى الإصدار العامل |
| `~/domains/wesalinnovation.sa/public_html/workspace` | مجلد النطاق الفرعي، ويصبح رابطاً إلى `current/public` |
| `~/wesal-backups/workspace` | نسخة من القاعدة قبل كل نشر، ويُحتفظ بآخر ١٠ |

الكود والإعدادات خارج `public_html`، فلا يصل إليها المتصفح. يظهر منها المجلد
العام وحده (`public`) عبر الرابط.

Hostinger ينشئ مجلد النطاق الفرعي داخل مجلد الموقع العام، فيرث ملف `.htaccess`
هناك. لذلك:

- `workspace/public/.htaccess` يستبدل سياسة المحتوى الموروثة، لأنها تمنع
  `'unsafe-eval'` الذي تحتاجه Alpine، فتتعطل بدونه القوائم والأزرار. ويحوّل
  `http` إلى `https`.
- التطبيق لا يقبل إلا مضيف `APP_URL`، فالمسار `wesalinnovation.sa/workspace`
  يرد بالخطأ 400.

## ما يلزم الخادم

| المتطلب | التفاصيل |
|---|---|
| PHP | ‎8.4.1 فأحدث لسطر الأوامر وللنطاق الفرعي. السكربت يجد `/opt/alt/php84/usr/bin/php` وحده إن كان `php` أقدم، ويرفع إصدار مجلد النطاق وحده بسطر `DEPLOY_PHP_HANDLER` |
| قاعدة البيانات | MySQL 8 أو MariaDB 10.6 فأحدث، قاعدة مستقلة بترميز `utf8mb4_unicode_ci` |
| أدوات | composer 2، وrsync، وcurl، وmysqldump، وgit. الفحص (الخطوة ٤) يتأكد من وجودها |
| Node | ‎22.12 فأحدث (أو 20.19) لبناء الواجهة، عبر nvm في حسابك (الخطوة ٣) |

## التجهيز أول مرة

كل الأوامر على الخادم عبر SSH. نفّذ الخطوات بالترتيب في جلسة واحدة: وجود
`.env` (الخطوة ٢) يفعّل نشر مساحة العمل مع كل دفع إلى `main`، وسيفشل ذلك
النشر إلى أن يُربط مجلد النطاق (الخطوة ٥).

### ١. النطاق الفرعي وقاعدة البيانات

من hPanel، في لوحة الموقع `wesalinnovation.sa`:

- **Domains ثم Subdomains:** أنشئ النطاق الفرعي `workspace`، واترك مجلده
  الافتراضي `public_html/workspace`.
- **Security ثم SSL:** تأكد أن للنطاق الفرعي شهادة. إن كانت سجلات DNS خارج
  Hostinger، فأضف هناك سجلاً من نوع A باسم `workspace` إلى عنوان الخادم نفسه.
- **Databases ثم Management:** أنشئ قاعدة بيانات ومستخدماً لها. يضيف Hostinger
  بادئة مثل `u123456789_`، فاحفظ الاسمين كاملين. لا تضع في كلمة المرور علامة
  التنصيص `"`. لا تستخدم قاعدة الموقع العام: فصل بيانات العقود والفواتير مقصود.

### ٢. ملف الإعدادات

```bash
mkdir -p ~/wesal-workspace/shared
cd ~/wesal-workspace/shared
cp ~/wesal-repo/workspace/.env.example .env
chmod 600 .env
sed -i \
  -e 's/^APP_ENV=.*/APP_ENV=production/' \
  -e 's/^APP_DEBUG=.*/APP_DEBUG=false/' \
  -e 's|^APP_URL=.*|APP_URL=https://workspace.wesalinnovation.sa|' \
  -e 's/^LOG_LEVEL=.*/LOG_LEVEL=warning/' \
  -e 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' \
  -e 's/^# DB_HOST=.*/DB_HOST=localhost/' \
  -e 's/^# \(DB_[A-Z]*=\)/\1/' \
  -e 's/^# SESSION_SECURE_COOKIE=/SESSION_SECURE_COOKIE=/' \
  .env
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
nano .env
```

الأوامر تضبط قيم الإنتاج ومفتاح التشفير. أكمل في `nano` ما يخصك:

| المفتاح | القيمة |
|---|---|
| `DB_DATABASE` و`DB_USERNAME` و`DB_PASSWORD` | من الخطوة ١. ضع كلمة المرور بين علامتي تنصيص إن كان فيها `#` أو مسافة |
| `MAIL_MAILER` | `smtp`، ومعه `MAIL_HOST` و`MAIL_PORT` و`MAIL_SCHEME` و`MAIL_USERNAME` و`MAIL_PASSWORD` و`MAIL_FROM_ADDRESS`. إن كان بريد النطاق على Hostinger: `smtp.hostinger.com` والمنفذ `465` و`MAIL_SCHEME=smtps`. بلا بريد لا تصل الدعوات ولا رسائل استعادة كلمة المرور |
| `COMPANY_*` | بيانات المنشأة في رأس الفاتورة: الاسم والرقم الضريبي والسجل التجاري والعنوان |
| `PLATFORM_DB_*` | اختياري: قاعدة الموقع العام للقراءة فقط (الخطوة ٩) |

`APP_KEY` **لا يتغير بعد اليوم**: تغييره يبطل الجلسات وروابط الدعوات.

### ٣. Node عبر nvm

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
source ~/.nvm/nvm.sh && nvm install 22
```

السكربت يحمّل nvm بنفسه في جلسات النشر غير التفاعلية.

### ٤. فحص الخادم

```bash
cd ~/wesal-repo && git pull origin main && bash workspace/deploy.sh --check
```

الفحص لا يغيّر شيئاً. يطبع ✓ لما يعمل، و! للتنبيه، و✗ لما يمنع النشر. في هذه
المرحلة يذكر أن مجلد النطاق لم يُربط بعد، وهذا متوقع حتى الخطوة ٥. أصلح أي
عائق غيره من قسم «استكشاف الأعطال» أدناه، وأضف ما يلزم من هذه الإعدادات في آخر
`.env`:

| المفتاح | متى يلزم |
|---|---|
| `DEPLOY_PHP_HANDLER=application/x-httpd-alt-php84` | حين يقول الفحص إن النطاق يعمل بإصدار PHP أقدم من 8.4.1. يرفع إصدار مجلد مساحة العمل وحده، ولا يمس الموقع العام |
| `DEPLOY_COMPOSER_BIN=<المسار الكامل>` | حين يقول الفحص إن composer يعمل بإصدار PHP قديم أو ليس الإصدار 2 |
| `DEPLOY_PHP_BIN=<المسار الكامل>` | حين لا يجد الفحص PHP 8.4.1 فأحدث |
| `DEPLOY_DOCROOT=<المسار الكامل>` | حين اخترت للنطاق الفرعي مجلداً غير `public_html/workspace` |

### ٥. ربط مجلد النطاق بالإصدار الحالي

```bash
cd ~/domains/wesalinnovation.sa/public_html
mv workspace ~/wesal-workspace/hpanel-folder
ln -s ~/wesal-workspace/current/public workspace
```

المجلد الذي أنشأه hPanel يُحفظ جانباً. لن يعرض النطاق شيئاً حتى أول نشر.

### ٦. أول نشر

```bash
cd ~/wesal-repo && bash workspace/deploy.sh
```

يبني الإصدار في `releases/`، وينسخ القاعدة احتياطياً، ويرحّلها، ثم يوجّه
`current` إليه، ويتحقق أن `https://workspace.wesalinnovation.sa/up` يرد بأن
التطبيق يعمل.

### ٧. أول مدير نظام

لا تسجيل ذاتي ولا حسابات تجريبية في الإنتاج. أنشئ أول مدير نظام من سطر
الأوامر، ثم ينشئ هو بقية الحسابات بالدعوات من الواجهة:

```bash
/opt/alt/php84/usr/bin/php ~/wesal-workspace/current/artisan workspace:create-sysadmin you@wesalinnovation.sa --name="الاسم"
```

استعمل مسار PHP الذي طبعه الفحص إن اختلف. الأمر يطبع رابط الدعوة أيضاً،
وصلاحيته ٧ أيام، فيعمل ولو لم يُضبط البريد بعد. الرابط يُستخدم مرة واحدة.

### ٨. النشر التلقائي

لا يلزم شيء آخر: كل دفع إلى `main` يشغّل سير «نشر إلى الإنتاج»، فينشر
`deploy.sh` الموقع العام أولاً ثم مساحة العمل. مفتاح SSH المقيَّد والأمر المفروض
كما هما. إن لم يتغير كود مساحة العمل ولا `.env` منذ آخر نشر ناجح، يكتفي
السكربت بالفحص ولا يعيد البناء.

فشل مساحة العمل لا يمس الموقع العام، لأنه نُشر قبلها وتحقق السكربت منه، لكنه
يُفشل التشغيل في GitHub كي يُلاحظ.

### ٩. اختياري: ربط قاعدة الموقع العام

صفحتا «تكامل الذكاء الاصطناعي» و«تنبيهات الأسئلة عالية الحساسية» تقرآن سجل
أسئلة المساعد (`chat_logs`) من قاعدة الموقع العام، ولا تكتبان فيها شيئاً. إن
سمحت الاستضافة، أنشئ مستخدماً بصلاحية SELECT وحدها على تلك القاعدة، واضبط
`PLATFORM_DB_HOST` و`PLATFORM_DB_DATABASE` و`PLATFORM_DB_USERNAME`
و`PLATFORM_DB_PASSWORD`، ثم انشر: `bash workspace/deploy.sh`.

## استكشاف الأعطال

| ما يظهر | السبب | الحل |
|---|---|---|
| «النطاق يعمل بـ PHP 8.x»، أو `/up` يرد 500 وفيه `Composer detected issues` | الموقع العام يعمل بإصدار PHP أقدم، ومجلد النطاق يرثه | أضف إلى `.env` السطر `DEPLOY_PHP_HANDLER=application/x-httpd-alt-php84`، ثم انشر |
| «النطاق يعرض ملفات PHP نصاً» | قيمة `DEPLOY_PHP_HANDLER` خاطئة، فلا يشغّل الخادم PHP | صحّحها إلى `application/x-httpd-alt-php84`، أو احذف السطر، ثم انشر |
| «composer يعمل بـ PHP 8.x» أو «ليس الإصدار 2» | composer المثبت على الخادم يعمل بإصدار PHP قديم | نزّله في حسابك: `mkdir -p ~/bin && curl -sS https://getcomposer.org/installer \| /opt/alt/php84/usr/bin/php -- --install-dir="$HOME/bin" --filename=composer`، ثم أضف إلى `.env` السطر `DEPLOY_COMPOSER_BIN=` متبوعاً بناتج `echo ~/bin/composer` |
| «لم أجد PHP 8.4.1 فأحدث» | لا PHP مناسب في المسارات المعروفة | اعرض الإصدارات المتاحة بالأمر `ls /opt/alt/`، ثم أضف `DEPLOY_PHP_BIN=/opt/alt/php84/usr/bin/php` |
| «تعذّر الاتصال بقاعدة البيانات» | اسم القاعدة أو المستخدم أو كلمة المرور في `.env` لا يطابق hPanel | انسخ القيم من hPanel كاملة ببادئتها |
| «لم يرد النطاق على ملف الفحص» | النطاق الفرعي أو شهادته لم يجهزا بعد | انتظر دقائق بعد إنشائه، وتحقق من SSL في hPanel |
| القوائم والأزرار لا تستجيب في المتصفح | مجلد النطاق ليس رابطاً إلى `current/public`، فالملف `.htaccess` هناك ليس ملف مساحة العمل | أعد الخطوة ٥. لا تفعّل «Force HTTPS» ولا تعدّل `.htaccess` من hPanel لهذا المجلد، فالنشر يستبدله |
| «419» عند تسجيل الدخول | فُتح الموقع عبر `http`، أو `APP_URL` لا يطابق الرابط | افتحه عبر `https://workspace.wesalinnovation.sa` وتحقق من `APP_URL` |
| `wesalinnovation.sa/workspace` يرد 400 | مقصود: التطبيق يعمل على نطاقه الفرعي وحده | استعمل `https://workspace.wesalinnovation.sa` |

## التشغيل اليومي

- **الحالة**: «صحة النظام» في الواجهة (مدير النظام) تفحص القاعدة والترحيلات
  والتخزين والمساحة والبريد وإعدادات الأمان، وتعرض آخر الأخطاء. «النطاقات
  والنشر» تعرض الإصدار المنشور.
- **الجدولة**: لا يحتاج التطبيق cron ولا عاملاً للمهام الخلفية.
- **تعديل الإعدادات**: عدّل `~/wesal-workspace/shared/.env` ثم انشر بالأمر
  `bash workspace/deploy.sh` من `~/wesal-repo`. القيم تُحفظ في ذاكرة الإعدادات
  عند البناء، والسكربت يعيد البناء متى تغيّر الملف.
- **التراجع**: السكربت يطبع أمر التراجع بعد كل نشر، وصيغته:
  `ln -sfn ~/wesal-workspace/releases/<الإصدار السابق> ~/wesal-workspace/current`.
  الترحيلات لا تُعكس تلقائياً. إن لزم استعادة القاعدة:
  `gunzip < ~/wesal-backups/workspace/db-<الوقت>.sql.gz | mysql -u <المستخدم> -p <القاعدة>`.
- **النسخ الاحتياطية**: نسخة من القاعدة قبل كل نشر في `~/wesal-backups/workspace`
  (آخر ١٠). المرفقات في `~/wesal-workspace/shared/storage/app/private`: ضمّنها في
  نسخ الاستضافة الدورية، فالسكربت لا ينسخها.
- **بعد التبديل** قد تخدم طلبات قليلة الإصدار السابق نحو دقيقتين، إلى أن تتجدد
  ذاكرة المسارات في PHP. هذا طبيعي.
- **إدخال المحتوى الصحي المعتمد في قاعدة معرفة المساعد**: من
  `~/wesal-workspace/current` نفّذ `/opt/alt/php84/usr/bin/php artisan content:export-kb /tmp/wesal-kb`،
  ثم من `~/domains/wesalinnovation.sa/public_html` نفّذ
  `php tools/rag/ingest-kb.php /tmp/wesal-kb`.
