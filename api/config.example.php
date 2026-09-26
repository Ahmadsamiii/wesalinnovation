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

/* ---------- دعوات النسخة التجريبية وقياس الأداء ----------
 * المدعو برابط /invite/{token} يحصل على تجربة موسّعة بلا حدّ للأسئلة،
 * ثم يُدعى لاستبانة قياس الأداء (survey.html). الإدارة من لوحة التحكم
 * ← تبويب «تجربة المستخدم» (مدير النظام والمشرف). */
define('INVITE_DAILY_LIMIT', 100);     // أقصى دعوات تُرسل في اليوم — 0 يلغي السقف
define('BETA_TRIAL_HOURS',    48);     // مدة التجربة الموسّعة بالساعات من فتح الرابط

/* ---------- الخروج التلقائي ----------
 * يُسجَّل خروج المستخدم بعد هذه المدة دون أي نشاط (حركة الفأرة، اللمس، لوحة
 * المفاتيح، التمرير، الكتابة، القراءة الصوتية)، وتنبّهه الواجهة قبلها بدقيقتين.
 * الواجهة تقرأ المدة من رد الخادم نفسه، فلا نسخة منها في index.html.
 * فريق المنصة (مدير النظام والمشرف ومراجع المحتوى) يرى بيانات كل المستخدمين
 * فمهلته أقصر. الحد الأقصى يُحسب من لحظة الدخول مهما كان النشاط.
 * المراجع: OWASP (15-30 دقيقة للتطبيقات منخفضة الخطورة)، NIST SP 800-63B-4،
 * PCI DSS 4.0 (15 دقيقة)، WCAG 2.2 (تنبيه قبل انتهاء أي مهلة).
 * إن غيّرت المدتين فحدّث بند «الخروج التلقائي» في سياسة الخصوصية (index.html). */
define('IDLE_MINUTES_USER',       30);  // المستفيد
define('IDLE_MINUTES_STAFF',      15);  // مدير النظام والمشرف ومراجع المحتوى
define('SESSION_MAX_HOURS_USER',  24);
define('SESSION_MAX_HOURS_STAFF', 12);

/* ---------- الوكيل العكسي ----------
 * اجعله true فقط إذا كان الموقع خلف Cloudflare أو موازن حِمل يضبط
 * CF-Connecting-IP أو X-Forwarded-For. تفعيله بلا وكيل حقيقي يسمح لأي زائر
 * بانتحال عنوانه وتجاوز كل الحدود أعلاه. */
define('TRUST_PROXY', false);

/* ---------- مزوّد الذكاء الاصطناعي ----------
 * التبديل بين المزوّدين الأربعة إعداد هنا فقط، بلا لمس أي كود. المزوّد
 * النشط دائماً له بديل تلقائي فوري عند الفشل — gemini للاثنين الجديدين
 * لأنه الوحيد المجاني، والقديمين (gemini/openai) يبقيان بديلي بعضهما
 * كما كانا قبل إضافة claude وkimi. */
define('AI_PROVIDER', 'gemini');       // 'gemini' | 'openai' | 'claude' | 'kimi'

// Gemini — المفتاح من https://aistudio.google.com/apikey (يبدأ عادة بـ AIza)
define('GEMINI_KEY',   '');
define('GEMINI_MODEL', 'gemini-3.5-flash-lite');    // Flash-Lite: أسرع وحصته المجانية أكبر بكثير من Flash (التي تقف عند 20 طلباً يومياً)
define('GEMINI_FALLBACKS', 'gemini-3.1-flash-lite,gemini-flash-lite-latest,gemini-3.6-flash');   // بدائل مرتبة، لكل نموذج حصة مستقلة

// OpenAI — اختياري، يُستخدم عند فشل Gemini (أو العكس إن AI_PROVIDER='openai')
define('OPENAI_KEY',   '');
define('OPENAI_MODEL', 'gpt-4o-mini');

// Claude (Anthropic) — اختياري، جودة عالية للسياقات الحساسة. المفتاح من
// https://console.anthropic.com — راجع أسعار النماذج قبل التفعيل، فهو
// مزوّد مدفوع بلا فئة مجانية دائمة كـGemini.
define('CLAUDE_KEY',   '');
define('CLAUDE_MODEL', 'claude-sonnet-5');

// Kimi (Moonshot AI) — اختياري، واجهة متوافقة مع OpenAI. المفتاح من
// https://platform.moonshot.ai — راجع اسم النموذج الحالي في وثائق Moonshot
// قبل التفعيل فهو يتغيّر مع إصداراتهم.
define('KIMI_KEY',      '');
define('KIMI_MODEL',    'kimi-k2-turbo-preview');
define('KIMI_BASE_URL', 'https://api.moonshot.ai/v1');

/* ---------- الصوت السعودي: قراءة الإجابات والإدخال الصوتي (api/voice.php) ----------
 * كل ما في هذا القسم اختياري: بلا مفاتيح تبقى الواجهة على صوت المتصفح كما كانت.
 * القراءة بطبقات حسب VOICE_TTS_ORDER، وكل طبقة مجانية بحصة تتجدد:
 *   groq   ستة أصوات سعودية (abdullah، fahad، sultan، lulwa، noura، aisha)، حصة
 *          يومية صغيرة، والنموذج نسخة معاينة قد يتغير اسمها أو حدودها.
 *   azure  صوتان سعوديان (ar-SA-HamedNeural و ar-SA-ZariyahNeural)، حصة شهرية
 *          في الفئة المجانية F0 و20 طلباً في الدقيقة.
 * حين تنفد حصة طبقة تبدأ الردود التالية من الطبقة بعدها حتى تتجدد، ثم صوت
 * المتصفح. الفئتان المجانيتان لا تحاسبان على التجاوز: المزوّد يرفض الطلب فقط.
 * الحصص وشروطها تتغير، فراجعها في لوحة كل مزوّد قبل التفعيل. للفحص بعد
 * الضبط افتح api/diag.php بحساب المشرف. */

// Groq: المفتاح من https://console.groq.com/keys (يبدأ عادة بـ gsk_)، ويشغّل القراءة
// والتفريغ معاً. التفريغ يُستخدم فقط في المتصفحات التي لا تحوّل الكلام إلى نص بنفسها.
define('GROQ_KEY',              '');
define('GROQ_TTS_MODEL',        'canopylabs/orpheus-arabic-saudi');
define('GROQ_TTS_VOICE_MALE',   'abdullah');          // أو fahad أو sultan
define('GROQ_TTS_VOICE_FEMALE', 'noura');             // أو lulwa أو aisha
define('GROQ_STT_MODEL',        'whisper-large-v3');  // whisper-large-v3-turbo أسرع وأقل دقة

// Azure Speech: أنشئ مورد Speech بالفئة Free F0 من https://portal.azure.com ثم خذ
// المفتاح والمنطقة من صفحة «Keys and Endpoint».
define('AZURE_SPEECH_KEY',       '');
define('AZURE_SPEECH_REGION',    'uaenorth');         // منطقة المورد كما في البوابة، أو رابط نقطة مخصصة كاملاً
define('AZURE_TTS_VOICE_MALE',   'ar-SA-HamedNeural');
define('AZURE_TTS_VOICE_FEMALE', 'ar-SA-ZariyahNeural');

define('VOICE_TTS_ORDER',      'groq,azure');  // ترتيب الطبقات، واحذف ما لا تريده
define('VOICE_DAILY_IP_CHARS', 15000);         // أقصى حروف يولّدها المزوّد لعنوان IP واحد في اليوم، 0 يلغي السقف
define('VOICE_DAILY_IP_STT',   60);            // أقصى تسجيلات تُفرَّغ لعنوان IP واحد في اليوم، 0 يلغي السقف
define('VOICE_CACHE_MB',       100);           // سقف الأصوات المحفوظة في STORAGE_DIR/voice بالميجابايت، 0 يلغي الحفظ

/* ---------- البريد ----------
 * بلا SMTP_PASS: يُرسَل عبر mail() المحلي في الاستضافة كما كان دائماً —
 * يعمل غالباً لكن الخوادم المستقبِلة تثق برسالة مصادَق عليها عبر SMTP أكثر.
 * بوضع SMTP_PASS، كل بريد صادر (الدعوات وإعادة تعيين كلمة المرور ونموذج
 * التواصل) يُرسَل عبر اتصال SMTP مصادَق حقيقي بصندوق البريد نفسه، ويسقط
 * تلقائياً لـmail() المحلي عند أي عطل في الاتصال فلا ينقطع الإرسال كلياً. */
define('MAIL_FROM',      'no-reply@wesalinnovation.sa');
define('MAIL_FROM_NAME', 'وصال');
define('CONTACT_TO',     'info@wesalinnovation.sa');        // وجهة رسائل «تواصل معنا»

// SMTP — بيانات Hostinger الافتراضية لصندوق بريد على نطاقك؛ فعّله بوضع
// SMTP_PASS فقط (كلمة مرور صندوق info@wesalinnovation.sa من hPanel ← البريد).
define('SMTP_HOST',       'smtp.hostinger.com');
define('SMTP_PORT',       465);              // أو 587 مع SMTP_ENCRYPTION='tls'
define('SMTP_ENCRYPTION', 'ssl');            // 'ssl' (465) أو 'tls' (587، STARTTLS)
define('SMTP_USER',       'info@wesalinnovation.sa');   // عادة نفس MAIL_FROM أو صندوق مشابه
define('SMTP_PASS',       '');               // فارغ = مُعطَّل، يبقى mail() المحلي هو المسار

/* ---------- صيانة تذاكر الدعم الدورية (Cron) ----------
 * مفتاح سرّي يحمي api/cron-tickets.php من التشغيل عبر رابط عام؛ يُطلب فقط
 * عند استدعائه من متصفح/HTTP لا من الطرفية (Cron الفعلي على الاستضافة لا
 * يحتاجه). ضعه سلسلة عشوائية طويلة ولا تشاركه — فارغاً يعني رفض كل وصول
 * عبر الرابط تلقائياً حتى تضبطه. */
define('CRON_SECRET', '');

/* مجلد التخزين الخاص (ملفات قاعدة المعرفة والمكتبات المجلوبة). يُفضَّل خارج المجلد العام،
   مثل: /home/USER/domains/wesalinnovation.sa/storage — وإن تُرك يُستخدم storage/ داخل المشروع محمياً بـ .htaccess */
// define('STORAGE_DIR', '/home/USER/domains/wesalinnovation.sa/storage');
