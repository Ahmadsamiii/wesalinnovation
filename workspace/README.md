# مساحة عمل وصال

نظام إدارة المشاريع الداخلي لوصال الابتكار: المشاريع والمهام، والعقود والفواتير
وأوامر الشراء، والشهادات والإفادات، والتقارير. تطبيق Laravel مستقل تماماً عن
منصة وصال العامة في جذر المستودع: قاعدة بيانات منفصلة وحسابات منفصلة ونطاق
منفصل (`workspace.wesalinnovation.sa`)، عزلاً للبيانات المالية والعقود.

## المتطلبات

- PHP **8.4.1** فأحدث (تشترطه حزم Symfony 8 المقفلة في `composer.lock`)، مع
  `pdo_mysql` (أو `pdo_sqlite` محلياً) و`mbstring` و`intl` و`fileinfo`
- Composer 2
- Node.js 20.19 أو 22.12 فأحدث — لتجميع الواجهة فقط، لا يلزم وقت التشغيل
- MySQL 5.7 فأحدث في الإنتاج، وSQLite للتطوير المحلي

## التشغيل محلياً

```bash
cd workspace
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm ci && npm run build
php artisan serve
```

`--seed` ينشئ حساباً تجريبياً لكل دور: `{الدور}@wesalinnovation.sa` بكلمة
المرور `password`، مثل `pm@wesalinnovation.sa`. للتطوير المحلي فقط — لا تشغّل
البذرة على قاعدة إنتاج.

## الأدوار

سبعة أدوار معرّفة مع تبويبات لوحة كل منها في مصدر واحد:
`config/roles.php`. البذرة ولوحة التحكم تقرآن منه، فلا تُضف دوراً في أي مكان
آخر.

| المفتاح | الدور |
|---|---|
| `executive` | المدير التنفيذي |
| `pm` | مدير المشاريع |
| `finance` | المدير المالي |
| `sysadmin` | مدير النظام |
| `medical` | المدير الطبي |
| `team_member` | عضو الفريق |
| `client` | العميل |

لا يوجد تسجيل ذاتي: كل حساب ينشئه مدير النظام.

## الاختبارات والأسلوب

```bash
php artisan test
vendor/bin/pint
```

الاختبارات لا تحتاج تجميع الواجهة (`withoutVite()` في `tests/TestCase.php`).
سير `.github/workflows/workspace.yml` في جذر المستودع يشغّل الأسلوب
والاختبارات والتجميع على كل طلب دمج يمس `workspace/`.

## ملاحظات

- اللغة العربية واتجاه RTL وتوقيت الرياض افتراضات في `config/app.php` نفسه،
  لا في `.env` وحده.
- `storage/` مرفوع بهيكله فقط؛ ملفات `.gitignore` داخله تستثني محتواه.
- `deploy.sh` في جذر المستودع لا ينشر هذا المجلد.
