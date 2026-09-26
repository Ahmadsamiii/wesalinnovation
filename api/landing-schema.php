<?php
/* ==========================================================================
 *  وصال — سجل محتوى صفحة الهبوط
 *
 *  المرجع الوحيد لكل نص قابل للتحرير في الصفحة الرئيسية وشريط التنقل
 *  والتذييل: منه تُبنى شاشة التحرير، وبه يُتحقَّق من المدخلات، ومنه يأتي
 *  النص الافتراضي بالعربي والإنجليزي عند «الإرجاع للأصل».
 *
 *  النص العربي الافتراضي هنا يطابق حرفياً ما هو مكتوب في index.html (يعرضه
 *  المتصفح قبل وصول رد الخادم) — tools/check-landing.php يتحقق من التطابق.
 *
 *  أنواع الحقول:
 *    text / area  نص بلغتين {ar, en}
 *    url / email / tel / domain  قيمة واحدة بلا لغة {v}
 *  القوائم: بطاقات متكررة تُضاف وتُحذف وتُرتَّب وتُخفى، ولكل بطاقة افتراضية
 *  معرّف ثابت (id) يربط تعديلاتها بنصها الأصلي.
 * ========================================================================== */

/** الأيقونات المسموح اختيارها للبطاقات — كلها رموز موجودة في index.html (#ic-…) */
function landingIcons(): array {
    return [
        'brain' => 'ذكاء', 'mic' => 'صوت', 'eye' => 'رؤية', 'shield' => 'حماية', 'book' => 'كتاب',
        'hand-heart' => 'رعاية', 'settings' => 'إعدادات', 'a11y' => 'وصول', 'zap' => 'سرعة',
        'lightbulb' => 'فكرة', 'heart' => 'قلب', 'users' => 'مستخدمون', 'user' => 'مستخدم',
        'globe' => 'عالمي', 'star' => 'نجمة', 'graduation' => 'تعليم', 'briefcase' => 'عمل',
        'chat' => 'محادثة', 'sparkles' => 'ذكاء اصطناعي', 'clock' => 'وقت', 'check' => 'تحقق',
        'info' => 'معلومة', 'file' => 'ملف', 'clipboard' => 'قائمة', 'search' => 'بحث',
        'volume' => 'استماع', 'phone' => 'جوال', 'mail' => 'بريد', 'home' => 'منزل',
        'bell' => 'تنبيه', 'bookmark' => 'حفظ', 'send' => 'إرسال',
    ];
}

/** حقل نصي بلغتين */
function lpT(string $k, string $label, string $ar, string $en, int $max = 160, string $group = ''): array {
    return ['k' => $k, 'l' => $label, 't' => 'text', 'max' => $max, 'g' => $group, 'd' => ['ar' => $ar, 'en' => $en]];
}
/** نص طويل بلغتين */
function lpA(string $k, string $label, string $ar, string $en, int $max = 600, string $group = ''): array {
    return ['k' => $k, 'l' => $label, 't' => 'area', 'max' => $max, 'g' => $group, 'd' => ['ar' => $ar, 'en' => $en]];
}
/** قيمة واحدة بلا لغة: رابط أو بريد أو جوال أو نطاق */
function lpM(string $k, string $label, string $type, string $v, int $max = 200, string $group = ''): array {
    return ['k' => $k, 'l' => $label, 't' => $type, 'max' => $max, 'g' => $group, 'd' => ['v' => $v]];
}

function landingSchema(): array {
    static $s = null;
    if ($s !== null) return $s;
    $s = [
        [
            'id' => 'nav', 'label' => 'شريط التنقّل', 'hideable' => false,
            'desc' => 'الشريط الثابت أعلى كل صفحة وقائمة الجوال: روابط الأقسام وزرّا الدخول والتجربة.',
            'fields' => [
                lpT('home', 'رابط الرئيسية', 'الرئيسية', 'Home', 30),
                lpT('chat', 'رابط المحادثة', 'محادثة مع وصال', 'Chat with Wesal', 30),
                lpT('res', 'رابط الدليل', 'الدليل', 'Guide', 30),
                lpT('about', 'رابط «عن وصال»', 'عن وصال', 'About', 30),
                lpT('contact', 'رابط التواصل', 'تواصل معنا', 'Contact us', 30),
                lpT('login', 'زر الدخول', 'تسجيل الدخول', 'Sign in', 30),
                lpT('cta', 'زر التجربة', 'جرّب الآن', 'Try it now', 30),
            ],
            'lists' => [],
        ],
        [
            'id' => 'hero', 'label' => 'الواجهة الرئيسية', 'hideable' => false,
            'desc' => 'أول ما يراه الزائر: الشارة والعنوان (يُكتب أمام الزائر حرفاً حرفاً) والنص وحقل السؤال وأمثلته والأزرار ومؤشرات الثقة.',
            'fields' => [
                lpT('chip', 'الشارة العلوية', 'مساعد ذكي للأشخاص ذوي الإعاقة في السعودية', 'An AI assistant for people with disabilities in Saudi Arabia', 80),
                lpT('title', 'العنوان الرئيسي', 'نفهمك ونسهّل وصولك', 'We understand you and make access easier', 90),
                lpA('sub', 'النص التعريفي',
                    'اسأل بأسلوبك عن حقوقك والخدمات المتاحة لك والتقنيات المساعدة، كتابةً أو بصوتك. يجيبك وصال بلغة واضحة من مصادر سعودية رسمية، ويمكنك قراءة الإجابة أو الاستماع إليها. تركّز النسخة التجريبية حالياً على الإعاقة الحركية والبصرية.',
                    'Ask in your own words about your rights, the services available to you and assistive technology, by typing or speaking. Wesal answers in clear language from official Saudi sources, and you can read the answer or listen to it. The beta currently focuses on physical and visual disabilities.', 420),
                lpT('ph', 'نص حقل السؤال', 'اسأل عن حقوقك أو خدماتك...', 'Ask about your rights or services…', 60),
                lpT('cta1', 'زر الإجراء الأول', 'جرّب الآن مجاناً', 'Try it free now', 30),
                lpT('cta2', 'زر الإجراء الثاني', 'كيف يعمل وصال', 'How Wesal works', 30),
            ],
            'lists' => [
                ['k' => 'trust', 'l' => 'مؤشرات الثقة', 'item' => 'مؤشر', 'min' => 0, 'max' => 4, 'icon' => false,
                 'fields' => [
                     lpT('t', 'العنوان', '', '', 24),
                     lpT('s', 'الوصف', '', '', 40),
                 ],
                 'items' => [
                     ['id' => 'beta',    'f' => ['t' => ['ar' => 'نسخة تجريبية', 'en' => 'Beta'],           's' => ['ar' => 'مفتوحة للجميع', 'en' => 'Open to everyone']]],
                     ['id' => 'sources', 'f' => ['t' => ['ar' => 'مصادر رسمية', 'en' => 'Official sources'], 's' => ['ar' => 'من جهات سعودية معتمدة', 'en' => 'From accredited Saudi bodies']]],
                     ['id' => 'always',  'f' => ['t' => ['ar' => '24/7', 'en' => '24/7'],                   's' => ['ar' => 'متاح في أي وقت', 'en' => 'Available anytime']]],
                 ]],
                // تُكتب واحداً واحداً داخل حقل السؤال بعد ظهور الصفحة، دورة واحدة ثم يعود
                // «نص حقل السؤال». لا تظهر لمن فعّل «تقليل الحركة»؛ حذفها كلها يوقف الحركة.
                ['k' => 'examples', 'l' => 'أمثلة تُكتب في حقل السؤال', 'item' => 'مثال', 'min' => 0, 'max' => 6, 'icon' => false,
                 'fields' => [
                     lpT('q', 'السؤال', '', '', 60),
                 ],
                 'items' => [
                     ['id' => 'card',    'f' => ['q' => ['ar' => 'كيف أحصل على بطاقة إثبات الإعاقة؟', 'en' => 'How do I get a disability ID card?']]],
                     ['id' => 'work',    'f' => ['q' => ['ar' => 'ما حقوقي في العمل؟', 'en' => 'What are my rights at work?']]],
                     ['id' => 'vision',  'f' => ['q' => ['ar' => 'ما التقنيات المساعدة للإعاقة البصرية؟', 'en' => 'What assistive tech helps with visual disabilities?']]],
                     ['id' => 'support', 'f' => ['q' => ['ar' => 'كيف أتقدّم بطلب دعم مالي؟', 'en' => 'How do I apply for financial support?']]],
                 ]],
            ],
        ],
        [
            'id' => 'features', 'label' => 'المزايا', 'hideable' => true,
            'desc' => 'بطاقات المزايا تحت الواجهة الرئيسية، ولكل بطاقة أيقونة وعنوان ووصف.',
            'fields' => [
                lpT('label', 'العنوان الصغير', 'المزايا', 'Features', 40),
                lpT('title', 'عنوان القسم', 'صمّمنا وصال ليناسب طريقتك في الوصول إلى المعلومة', 'We built Wesal around the way you reach information', 100),
                lpA('sub', 'وصف القسم', 'يعمل وصال مع قارئ الشاشة ولوحة المفاتيح، ويتيح لك السؤال بصوتك والاستماع إلى الإجابة وضبط طريقة العرض.',
                    'Wesal works with screen readers and keyboards, and lets you ask by voice, listen to answers and adjust how the page looks.', 240),
            ],
            'lists' => [
                ['k' => 'items', 'l' => 'بطاقات المزايا', 'item' => 'ميزة', 'min' => 0, 'max' => 12, 'icon' => true,
                 'fields' => [
                     lpT('title', 'العنوان', '', '', 60),
                     lpA('desc', 'الوصف', '', '', 220),
                 ],
                 'items' => [
                     ['id' => 'smart', 'i' => 'brain', 'f' => [
                         'title' => ['ar' => 'إجابات بلغة واضحة', 'en' => 'Answers in plain language'],
                         'desc'  => ['ar' => 'اكتب سؤالك كما تقوله عادةً، ويبدأ وصال بالخلاصة ثم الخطوات والجهة المسؤولة، ويمكنك طلب التفصيل متى شئت.', 'en' => 'Ask the way you normally would. Wesal starts with the short answer, then the steps and the responsible authority, and you can ask for more detail at any time.']]],
                     ['id' => 'voice', 'i' => 'mic', 'f' => [
                         'title' => ['ar' => 'اسأل بصوتك واستمع للإجابة', 'en' => 'Ask by voice, listen to the answer'],
                         'desc'  => ['ar' => 'انطق سؤالك بدل كتابته، واستمع إلى الإجابة بالسرعة التي تناسبك.', 'en' => 'Say your question instead of typing it, and listen to the answer at the speed that suits you.']]],
                     ['id' => 'accessible', 'i' => 'eye', 'f' => [
                         'title' => ['ar' => 'واجهة تتكيّف معك', 'en' => 'An interface that adapts to you'],
                         'desc'  => ['ar' => 'كبّر الخط، وفعّل التباين العالي أو الوضع الداكن، وخفّف الحركة من إعدادات الوصول.', 'en' => 'Enlarge the text, switch on high contrast or dark mode, and reduce motion from the accessibility settings.']]],
                     ['id' => 'trusted', 'i' => 'shield', 'f' => [
                         'title' => ['ar' => 'معلومات من مصادر رسمية', 'en' => 'Information from official sources'],
                         'desc'  => ['ar' => 'يستند وصال إلى أنظمة الجهات الرسمية السعودية وخدماتها، وإذا لاحظت معلومة غير دقيقة فأبلغنا من الزر أسفل الإجابة.', 'en' => 'Wesal draws on the regulations and services of official Saudi bodies. If something looks wrong, report it with the button under the answer.']]],
                     ['id' => 'library', 'i' => 'book', 'f' => [
                         'title' => ['ar' => 'دليل مرتّب حسب الموضوع', 'en' => 'A guide organised by topic'],
                         'desc'  => ['ar' => 'أدلة مختصرة في الحقوق والتعليم والصحة والتوظيف والتقنيات المساعدة، ويمكنك سؤال وصال عن أي دليل منها مباشرة.', 'en' => 'Short guides on rights, education, health, employment and assistive technology, and you can ask Wesal about any of them directly.']]],
                     ['id' => 'privacy', 'i' => 'hand-heart', 'f' => [
                         'title' => ['ar' => 'خصوصيتك محفوظة', 'en' => 'Your privacy is respected'],
                         'desc'  => ['ar' => 'لا إعلانات في وصال ولا نبيع بياناتك، ويمكنك تنزيل نسخة منها أو حذفها في أي وقت من حسابك.', 'en' => 'There are no ads on Wesal and we never sell your data. You can download a copy or delete it at any time from your account.']]],
                 ]],
            ],
        ],
        [
            'id' => 'how', 'label' => 'آلية العمل', 'hideable' => true,
            'desc' => 'خطوات مرقّمة تشرح كيف يعمل وصال، ويُضاف الترقيم تلقائياً حسب الترتيب.',
            'fields' => [
                lpT('label', 'العنوان الصغير', 'آلية العمل', 'How it works', 40),
                lpT('title', 'عنوان القسم', 'كيف يعمل وصال؟', 'How does Wesal work?', 100),
            ],
            'lists' => [
                ['k' => 'steps', 'l' => 'الخطوات', 'item' => 'خطوة', 'min' => 0, 'max' => 6, 'icon' => false,
                 'fields' => [
                     lpT('title', 'العنوان', '', '', 60),
                     lpA('desc', 'الوصف', '', '', 200),
                 ],
                 'items' => [
                     ['id' => 'ask', 'f' => [
                         'title' => ['ar' => 'اسأل سؤالك', 'en' => 'Ask your question'],
                         'desc'  => ['ar' => 'اكتب سؤالك أو انطقه بالعربية، بالفصحى أو بلهجتك.', 'en' => 'Type or say your question in Arabic, in formal Arabic or your own dialect.']]],
                     ['id' => 'search', 'f' => [
                         'title' => ['ar' => 'وصال يبحث لك', 'en' => 'Wesal searches for you'],
                         'desc'  => ['ar' => 'يبحث وصال في قاعدته المعرفية المبنية من مصادر رسمية عن المعلومات المرتبطة بسؤالك.', 'en' => 'Wesal searches its knowledge base, built from official sources, for information related to your question.']]],
                     ['id' => 'answer', 'f' => [
                         'title' => ['ar' => 'احصل على الإجابة', 'en' => 'Get your answer'],
                         'desc'  => ['ar' => 'تصلك إجابة مختصرة تبدأ بالخلاصة، ويمكنك طلب التفصيل أو الاستماع إليها.', 'en' => 'You get a short answer that starts with the key point, and you can ask for more detail or listen to it.']]],
                 ]],
            ],
        ],
        [
            'id' => 'sources', 'label' => 'مصادر البيانات', 'hideable' => true,
            'desc' => 'الجهات الرسمية التي يستند إليها وصال، ولكل جهة اسم ونطاق ووصف قصير. يظهر شعار الجهة تلقائياً من نطاقها متى توفّر ملفه في الموقع، وإلا ظهر مكانه رمز عام.',
            'fields' => [
                lpT('label', 'العنوان الصغير', 'مصادر البيانات', 'Data sources', 40),
                lpT('title', 'عنوان القسم', 'من أين يجيب وصال؟', 'Where do Wesal answers come from?', 100),
                lpA('sub', 'وصف القسم', 'بُنيت قاعدة وصال المعرفية من الأنظمة والخدمات المنشورة لدى الجهات الرسمية السعودية. إجابات وصال إرشادية، والمرجع النهائي هو الجهة الرسمية نفسها.',
                    "Wesal's knowledge base is built from the published regulations and services of official Saudi bodies. Wesal's answers are for guidance, and the official body itself is always the final reference.", 360),
                lpT('logos', 'ملاحظة أسفل الشعارات', 'الشعارات ملك لجهاتها، ونعرضها هنا للتعريف بمصادر المعلومات فقط.',
                    'Logos belong to their respective bodies and appear here only to identify the sources of information.', 160),
            ],
            'lists' => [
                ['k' => 'items', 'l' => 'الجهات', 'item' => 'جهة', 'min' => 0, 'max' => 16, 'icon' => false,
                 'fields' => [
                     lpT('name', 'اسم الجهة', '', '', 80),
                     lpM('domain', 'النطاق', 'domain', '', 60),
                     lpT('note', 'الوصف', '', '', 100),
                 ],
                 'items' => [
                     ['id' => 'hrsd', 'f' => ['name' => ['ar' => 'وزارة الموارد البشرية والتنمية الاجتماعية', 'en' => 'Ministry of Human Resources and Social Development'], 'domain' => ['v' => 'hrsd.gov.sa'], 'note' => ['ar' => 'بطاقة إثبات الإعاقة والإعانات والتأهيل', 'en' => 'Disability card, allowances, rehabilitation']]],
                     ['id' => 'apd', 'f' => ['name' => ['ar' => 'هيئة رعاية الأشخاص ذوي الإعاقة', 'en' => 'Authority for the Care of Persons with Disabilities'], 'domain' => ['v' => 'apd.gov.sa'], 'note' => ['ar' => 'التمكين والسياسات والمبادرات', 'en' => 'Empowerment, policies and initiatives']]],
                     ['id' => 'kscdr', 'f' => ['name' => ['ar' => 'مركز الملك سلمان لأبحاث الإعاقة', 'en' => 'King Salman Center for Disability Research'], 'domain' => ['v' => 'kscdr.org.sa'], 'note' => ['ar' => 'الأبحاث والمكتبة العلمية', 'en' => 'Research and scientific library']]],
                     ['id' => 'moh', 'f' => ['name' => ['ar' => 'وزارة الصحة', 'en' => 'Ministry of Health'], 'domain' => ['v' => 'moh.gov.sa'], 'note' => ['ar' => 'الخدمات الصحية والتأهيلية', 'en' => 'Health and rehabilitation services']]],
                     ['id' => 'moe', 'f' => ['name' => ['ar' => 'وزارة التعليم', 'en' => 'Ministry of Education'], 'domain' => ['v' => 'moe.gov.sa'], 'note' => ['ar' => 'التعليم الشامل والدمج', 'en' => 'Inclusive education and integration']]],
                     ['id' => 'hrdf', 'f' => ['name' => ['ar' => 'صندوق تنمية الموارد البشرية (هدف)', 'en' => 'Human Resources Development Fund (HADAF)'], 'domain' => ['v' => 'hrdf.org.sa'], 'note' => ['ar' => 'التوظيف والتمكين المهني', 'en' => 'Employment and career empowerment']]],
                     ['id' => 'mygov', 'f' => ['name' => ['ar' => 'المنصة الوطنية الموحدة', 'en' => 'Unified National Platform'], 'domain' => ['v' => 'my.gov.sa'], 'note' => ['ar' => 'الخدمات الحكومية الإلكترونية', 'en' => 'Government e-services']]],
                 ]],
            ],
        ],
        [
            'id' => 'privacy', 'label' => 'حماية البيانات', 'hideable' => true,
            'desc' => 'التزامات الخصوصية، ولكل بطاقة أيقونة وعنوان ووصف.',
            'fields' => [
                lpT('label', 'العنوان الصغير', 'حماية البيانات', 'Data protection', 40),
                lpT('title', 'عنوان القسم', 'بياناتك محمية وأنت صاحب القرار فيها', 'Your data is protected and you stay in control', 100),
            ],
            'lists' => [
                ['k' => 'items', 'l' => 'البطاقات', 'item' => 'بطاقة', 'min' => 0, 'max' => 9, 'icon' => true,
                 'fields' => [
                     lpT('title', 'العنوان', '', '', 60),
                     lpA('desc', 'الوصف', '', '', 240),
                 ],
                 'items' => [
                     ['id' => 'encryption', 'i' => 'shield', 'f' => [
                         'title' => ['ar' => 'اتصال مشفّر ووصول محدود', 'en' => 'Encrypted, with limited access'],
                         'desc'  => ['ar' => 'يصل اتصالك بالمنصة مشفّراً، ولا نخزّن كلمة مرورك بصيغة يمكن قراءتها، ولا يطّلع على البيانات من فريقنا إلا من يحتاج إليها في عمله.', 'en' => 'Your connection is encrypted, your password is never stored in readable form, and only team members who need the data for their work can access it.']]],
                     ['id' => 'control', 'i' => 'settings', 'f' => [
                         'title' => ['ar' => 'بياناتك تحت تصرّفك', 'en' => 'Your data, your call'],
                         'desc'  => ['ar' => 'من صفحة حسابك تستطيع تنزيل نسخة من بياناتك، أو مسح محادثاتك، أو حذف حسابك نهائياً، دون الحاجة إلى مراسلتنا.', 'en' => 'From your account page you can download a copy of your data, clear your chats or delete your account permanently, without having to contact us.']]],
                     ['id' => 'transparency', 'i' => 'eye', 'f' => [
                         'title' => ['ar' => 'وضوح في ما نجمعه', 'en' => 'Clear about what we collect'],
                         'desc'  => ['ar' => 'لا نبيع بياناتك ولا نشاركها مع معلنين، وتوضّح سياسة الخصوصية ما نجمعه وسبب جمعه ومدة الاحتفاظ به.', 'en' => 'We never sell your data or share it with advertisers, and our privacy policy explains what we collect, why, and how long we keep it']]],
                 ]],
            ],
        ],
        [
            'id' => 'cta', 'label' => 'شريط الدعوة', 'hideable' => true,
            'desc' => 'الشريط الملوّن في آخر الصفحة يدعو الزائر للبدء.',
            'fields' => [
                lpT('title', 'العنوان', 'هل لديك سؤال عن حقوقك أو خدماتك؟', 'Have a question about your rights or services?', 90),
                lpT('sub', 'النص', 'اسأل وصال الآن مجاناً، ولا تحتاج إلى حساب لتبدأ.', 'Ask Wesal now for free. You do not need an account to start.', 160),
                lpT('btn', 'نص الزر', 'ابدأ المحادثة', 'Start a chat', 30),
            ],
            'lists' => [],
        ],
        [
            'id' => 'footer', 'label' => 'التذييل', 'hideable' => false,
            'desc' => 'أسفل كل صفحة. بيانات الاتصال تظهر أيضاً في صفحة «تواصل معنا».',
            'fields' => [
                lpA('about', 'النبذة', 'وصال مساعد ذكي سعودي يجيب الأشخاص ذوي الإعاقة وأسرهم عن حقوقهم والخدمات المتاحة لهم، استناداً إلى مصادر رسمية.',
                    'Wesal is a Saudi AI assistant that answers people with disabilities and their families about their rights and the services available to them, based on official sources.', 300, 'الهوية'),
                lpM('x', 'حساب إكس (X)', 'url', 'https://x.com/Wesalhub', 200, 'حسابات التواصل الاجتماعي'),
                lpM('instagram', 'حساب إنستقرام', 'url', 'https://www.instagram.com/wesalhub', 200, 'حسابات التواصل الاجتماعي'),
                lpM('linkedin', 'حساب لينكد إن', 'url', 'https://www.linkedin.com/company/wesalksa0', 200, 'حسابات التواصل الاجتماعي'),
                lpT('quickTitle', 'عنوان العمود', 'روابط سريعة', 'Quick links', 30, 'عمود الروابط السريعة'),
                lpT('lHome', 'رابط الرئيسية', 'الرئيسية', 'Home', 30, 'عمود الروابط السريعة'),
                lpT('lChat', 'رابط المحادثة', 'محادثة مع وصال', 'Chat with Wesal', 30, 'عمود الروابط السريعة'),
                lpT('lGuide', 'رابط الدليل', 'دليل وصال', 'Wesal guide', 30, 'عمود الروابط السريعة'),
                lpT('lAbout', 'رابط «عن وصال»', 'عن وصال', 'About', 30, 'عمود الروابط السريعة'),
                lpT('supportTitle', 'عنوان العمود', 'الدعم', 'Support', 30, 'عمود الدعم'),
                lpT('lA11y', 'رابط إمكانية الوصول', 'إمكانية الوصول', 'Accessibility', 30, 'عمود الدعم'),
                lpT('lTickets', 'رابط تذاكر الدعم', 'تذاكر الدعم الفني', 'Support tickets', 30, 'عمود الدعم'),
                lpT('lContact', 'رابط التواصل', 'تواصل معنا', 'Contact us', 30, 'عمود الدعم'),
                lpT('lDash', 'رابط لوحة التحكم', 'لوحة التحكم', 'Dashboard', 30, 'عمود الدعم'),
                lpT('lLogin', 'رابط الدخول', 'تسجيل الدخول', 'Sign in', 30, 'عمود الدعم'),
                lpT('lFaq', 'رابط الأسئلة الشائعة', 'الأسئلة الشائعة', 'FAQ', 30, 'عمود الدعم'),
                lpT('lPrivacy', 'رابط سياسة الخصوصية', 'سياسة الخصوصية', 'Privacy policy', 30, 'عمود الدعم'),
                lpT('lTerms', 'رابط شروط الاستخدام', 'شروط الاستخدام', 'Terms of use', 30, 'عمود الدعم'),
                lpT('contactTitle', 'عنوان العمود', 'تواصل', 'Contact', 30, 'عمود التواصل'),
                lpM('email', 'البريد الإلكتروني', 'email', 'info@wesalinnovation.sa', 120, 'عمود التواصل'),
                lpM('phone', 'رقم الجوال', 'tel', '+966 50 112 0161', 24, 'عمود التواصل'),
                lpT('hours', 'ساعات العمل', 'الأحد إلى الخميس، 8 صباحاً إلى 6 مساءً', 'Sunday to Thursday, 8 AM to 6 PM', 60, 'عمود التواصل'),
                lpT('copy', 'حقوق النشر', '© 2026 وصال الابتكار لتقنية المعلومات. جميع الحقوق محفوظة.', '© 2026 Wesal Innovation. All rights reserved.', 90, 'السطر الأخير'),
                lpT('tagline', 'السطر الختامي', 'صُمّمت المنصة وفق إرشادات الوصول الرقمي WCAG 2.2', 'Designed to the WCAG 2.2 accessibility guidelines', 120, 'السطر الأخير'),
            ],
            'lists' => [],
        ],
    ];
    return $s;
}

/** الحقول ذات القيمة الواحدة بلا لغة */
function lpIsMono(string $type): bool { return in_array($type, ['url', 'email', 'tel', 'domain'], true); }

/** القسم بمعرّفه */
function lpSection(string $id): ?array {
    foreach (landingSchema() as $s) if ($s['id'] === $id) return $s;
    return null;
}
