<?php
/* ==========================================================================
 *  وصال — نموذج ملف الإعدادات
 *
 *  انسخه إلى api/config.php واملأ القيم:
 *      cp api/config.example.php api/config.php
 *
 *  ملف config.php مستثنى في .gitignore ومحجوب في api/.htaccess —
 *  لا تضع فيه أي شيء ولا ترفعه إلى Git أبداً.
 * ========================================================================== */

/* ---------- قاعدة البيانات ---------- */
define('DB_HOST', 'localhost');
define('DB_NAME', '');                 // اسم قاعدة البيانات
define('DB_USER', '');                 // مستخدم يملك صلاحيات SELECT/INSERT/UPDATE/DELETE/CREATE
define('DB_PASS', '');

/* ---------- الموقع ---------- */
define('SITE_NAME', 'وصال');
define('SITE_URL',  'https://wesalinnovation.sa');   // بلا شرطة مائلة في النهاية — تُستخدم في روابط الدعوات

/* ---------- وضع التطوير ----------
 * true يعرض رسائل الأخطاء الفعلية في ردود JSON. اتركه false في الإنتاج دائماً. */
define('APP_DEBUG', false);

/* ---------- رصيد الأسئلة ----------
 * هذه القيم يجب أن تطابق الثوابت المقابلة في index.html
 * (GUEST_LIMIT و USER_TOKENS و RENEW_MS) وإلا اختلف ما يراه المستخدم عمّا يطبّقه الخادم. */
define('GUEST_LIMIT',  5);             // أسئلة تجريبية للزائر قبل إنشاء حساب
define('USER_TOKENS', 30);             // رصيد المستخدم المسجّل في كل دورة
define('RENEW_HOURS',  6);             // طول دورة التجديد بالساعات

/* ---------- سقوف الحماية من إساءة الاستخدام ----------
 * هذه هي الحماية الفعلية لفاتورة مزوّد الذكاء الاصطناعي.
 * ضع 0 لتعطيل أي سقف منها (غير موصى به في الإنتاج). */
define('CHAT_DAILY_IP_LIMIT',   120);  // أقصى نقاط أسئلة من عنوان IP واحد في اليوم
define('CHAT_DAILY_TOTAL_LIMIT', 0);   // سقف يومي لكل المنصة — اضبطه على رقم يناسب ميزانيتك

/* ---------- الوكيل العكسي ----------
 * اجعله true فقط إذا كان الموقع خلف Cloudflare أو موازن حِمل يضبط
 * CF-Connecting-IP أو X-Forwarded-For. تفعيله بلا وكيل حقيقي يسمح لأي زائر
 * بانتحال عنوانه وتجاوز كل الحدود أعلاه. */
define('TRUST_PROXY', false);

/* ---------- مزوّد الذكاء الاصطناعي ---------- */
define('AI_PROVIDER', 'gemini');       // 'gemini' أو 'openai' — والآخر يُستخدم كبديل تلقائي

// Gemini — المفتاح من https://aistudio.google.com/apikey (يبدأ عادة بـ AIza)
define('GEMINI_KEY',   '');
define('GEMINI_MODEL', 'gemini-3.5-flash-lite');    // Flash-Lite: أسرع وحصته المجانية أكبر بكثير من Flash (التي تقف عند 20 طلباً يومياً)
define('GEMINI_FALLBACKS', 'gemini-3.1-flash-lite,gemini-flash-lite-latest,gemini-3.6-flash');   // بدائل مرتبة، لكل نموذج حصة مستقلة

// OpenAI — اختياري، يُستخدم عند فشل Gemini
define('OPENAI_KEY',   '');
define('OPENAI_MODEL', 'gpt-4o-mini');

/* ---------- البريد ----------
 * يعتمد على دالة mail() في الاستضافة. اجعل MAIL_FROM على نطاق الموقع نفسه
 * وإلا رفضت أغلب الخوادم الرسائل أو صنّفتها مزعجة. */
define('MAIL_FROM',      'no-reply@wesalinnovation.sa');
define('MAIL_FROM_NAME', 'وصال');
define('CONTACT_TO',     'info@wesalinnovation.sa');        // وجهة رسائل «تواصل معنا»

/* مجلد التخزين الخاص (ملفات قاعدة المعرفة والمكتبات المجلوبة). يُفضَّل خارج المجلد العام،
   مثل: /home/USER/domains/wesalinnovation.sa/storage — وإن تُرك يُستخدم storage/ داخل المشروع محمياً بـ .htaccess */
// define('STORAGE_DIR', '/home/USER/domains/wesalinnovation.sa/storage');
