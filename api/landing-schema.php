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
            'desc' => 'أول ما يراه الزائر: الشارة والعنوان والنص وحقل السؤال والأزرار ومؤشرات الثقة.',
            'fields' => [
                lpT('chip', 'الشارة العلوية', 'منصة ذكاء اصطناعي مُيسّرة للجميع', 'An accessible AI platform for everyone', 80),
                lpT('title', 'العنوان الرئيسي', 'وصال — نفهمك ونسهّل وصولك', 'Wesal — we understand you and ease your access', 90),
                lpA('sub', 'النص التعريفي',
                    'اسأل بلغتك عن حقوقك أو خدماتك أو التقنيات المساعدة، ويجيبك وصال بوضوح من مصادر رسمية سعودية مع ذكر المصدر — قراءةً أو استماعاً. النسخة التجريبية تركّز حالياً على الإعاقة الحركية والبصرية.',
                    'Ask in your own words about your rights, services or assistive technology, and Wesal answers clearly from official Saudi sources, naming each one — to read or to listen. The beta currently focuses on physical and visual disabilities.', 420),
                lpT('ph', 'نص حقل السؤال', 'اسأل وصال عن أي شيء...', 'Ask Wesal anything…', 60),
                lpT('cta1', 'زر الإجراء الأول', 'جرّب الآن مجاناً', 'Try it free now', 30),
                lpT('cta2', 'زر الإجراء الثاني', 'كيف يشتغل وصال', 'How Wesal works', 30),
            ],
            'lists' => [
                ['k' => 'trust', 'l' => 'مؤشرات الثقة', 'item' => 'مؤشر', 'min' => 0, 'max' => 4, 'icon' => false,
                 'fields' => [
                     lpT('t', 'العنوان', '', '', 24),
                     lpT('s', 'الوصف', '', '', 40),
                 ],
                 'items' => [
                     ['id' => 'beta',    'f' => ['t' => ['ar' => 'نسخة تجريبية', 'en' => 'Beta'],           's' => ['ar' => 'مفتوحة للجميع', 'en' => 'Open to everyone']]],
                     ['id' => 'sources', 'f' => ['t' => ['ar' => 'بمصادر رسمية', 'en' => 'Official sources'], 's' => ['ar' => 'مع كل إجابة', 'en' => 'with every answer']]],
                     ['id' => 'always',  'f' => ['t' => ['ar' => '24/7', 'en' => '24/7'],                   's' => ['ar' => 'متاح', 'en' => 'Available']]],
                 ]],
            ],
        ],
        [
            'id' => 'features', 'label' => 'المزايا', 'hideable' => true,
            'desc' => 'بطاقات المزايا تحت الواجهة الرئيسية — لكل بطاقة أيقونة وعنوان ووصف.',
            'fields' => [
                lpT('label', 'العنوان الصغير', 'المزايا', 'Features', 40),
                lpT('title', 'عنوان القسم', 'تجربة ذكية مصممة لتكون في متناول الجميع', "A smart experience designed to be within everyone's reach", 100),
                lpA('sub', 'وصف القسم', 'نجمع بين قوة الذكاء الاصطناعي وأفضل معايير إمكانية الوصول لنقدم تجربة فريدة ومُيسّرة',
                    'We combine the power of AI with the best accessibility standards to deliver a unique, accessible experience', 240),
            ],
            'lists' => [
                ['k' => 'items', 'l' => 'بطاقات المزايا', 'item' => 'ميزة', 'min' => 0, 'max' => 12, 'icon' => true,
                 'fields' => [
                     lpT('title', 'العنوان', '', '', 60),
                     lpA('desc', 'الوصف', '', '', 220),
                 ],
                 'items' => [
                     ['id' => 'smart', 'i' => 'brain', 'f' => [
                         'title' => ['ar' => 'إجابات ذكية ومبسّطة', 'en' => 'Smart, simplified answers'],
                         'desc'  => ['ar' => 'يفهم وصال أسئلتك ويقدّم إجابات واضحة ومبسّطة تناسب احتياجاتك المعلوماتية', 'en' => 'Wesal understands your questions and gives clear, simple answers that fit your information needs']]],
                     ['id' => 'voice', 'i' => 'mic', 'f' => [
                         'title' => ['ar' => 'تفاعل صوتي طبيعي', 'en' => 'Natural voice interaction'],
                         'desc'  => ['ar' => 'تحدّث مع وصال بصوتك واستمع للإجابات — مصمم لسهولة الاستخدام لجميع القدرات', 'en' => 'Talk to Wesal and listen to the answers — designed to be easy for every ability']]],
                     ['id' => 'accessible', 'i' => 'eye', 'f' => [
                         'title' => ['ar' => 'واجهة مُيسّرة بالكامل', 'en' => 'Fully accessible interface'],
                         'desc'  => ['ar' => 'تحكم بحجم الخط والتباين والألوان والحركة لتجربة تناسبك تماماً', 'en' => 'Control font size, contrast, colours and motion for an experience that fits you perfectly']]],
                     ['id' => 'trusted', 'i' => 'shield', 'f' => [
                         'title' => ['ar' => 'معلومات موثوقة وآمنة', 'en' => 'Trusted, safe information'],
                         'desc'  => ['ar' => 'إجاباتنا من الجهات الرسمية السعودية، ومع كل إجابة اسم مصدرها — وزر إبلاغ فوري عن أي معلومة غير دقيقة', 'en' => 'Our answers come from official Saudi entities, each naming its source — with an instant button to report anything inaccurate']]],
                     ['id' => 'library', 'i' => 'book', 'f' => [
                         'title' => ['ar' => 'مكتبة معرفية شاملة', 'en' => 'Comprehensive knowledge library'],
                         'desc'  => ['ar' => 'آلاف الموارد والأدلة الإرشادية المصنّفة والمُيسّرة لسهولة الوصول', 'en' => 'Thousands of categorised, accessible resources and guides, easy to reach']]],
                     ['id' => 'privacy', 'i' => 'hand-heart', 'f' => [
                         'title' => ['ar' => 'خصوصية في صميم التصميم', 'en' => 'Privacy by design'],
                         'desc'  => ['ar' => 'بياناتك لك وحدك: بلا إعلانات، بلا مشاركة مع أي طرف، وتقدر تصدّرها أو تحذفها في أي وقت من حسابك', 'en' => 'Your data is yours alone: no ads, no sharing with anyone, and you can export or delete it any time from your account']]],
                 ]],
            ],
        ],
        [
            'id' => 'how', 'label' => 'آلية العمل', 'hideable' => true,
            'desc' => 'خطوات مرقّمة تشرح كيف يعمل وصال — الترقيم تلقائي حسب الترتيب.',
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
                         'desc'  => ['ar' => 'اكتب أو انطق سؤالك بالعربية — وصال يفهم لغتك الطبيعية', 'en' => 'Type or speak your question in Arabic — Wesal understands natural language']]],
                     ['id' => 'search', 'f' => [
                         'title' => ['ar' => 'وصال يبحث لك', 'en' => 'Wesal searches for you'],
                         'desc'  => ['ar' => 'يحلل وصال سؤالك ويبحث في قاعدة المعرفة عن أفضل إجابة', 'en' => 'Wesal analyses your question and searches its knowledge base for the best answer']]],
                     ['id' => 'answer', 'f' => [
                         'title' => ['ar' => 'احصل على الإجابة', 'en' => 'Get your answer'],
                         'desc'  => ['ar' => 'تحصل على إجابة واضحة ومبسّطة مع إمكانية طلب مزيد من التفصيل', 'en' => 'You get a clear, simple answer — and can ask for more detail']]],
                 ]],
            ],
        ],
        [
            'id' => 'sources', 'label' => 'مصادر البيانات', 'hideable' => true,
            'desc' => 'الجهات الرسمية التي يجيب منها وصال — لكل جهة اسم ونطاق ووصف قصير.',
            'fields' => [
                lpT('label', 'العنوان الصغير', 'مصادر البيانات', 'Data sources', 40),
                lpT('title', 'عنوان القسم', 'من أين يجيب وصال؟', 'Where do Wesal answers come from?', 100),
                lpA('sub', 'وصف القسم', 'قاعدة وصال المعرفية مبنية من أنظمة وخدمات الجهات الرسمية السعودية، ومع كل إجابة نذكر مصدرها. الإجابات إرشادية، والمرجع النهائي يبقى الجهة الرسمية نفسها.',
                    "Wesal's knowledge base is built from the regulations and services of official Saudi entities, and every answer names its source. Answers are guidance — the official entity remains the final reference.", 360),
            ],
            'lists' => [
                ['k' => 'items', 'l' => 'الجهات', 'item' => 'جهة', 'min' => 0, 'max' => 16, 'icon' => false,
                 'fields' => [
                     lpT('name', 'اسم الجهة', '', '', 80),
                     lpM('domain', 'النطاق', 'domain', '', 60),
                     lpT('note', 'الوصف', '', '', 100),
                 ],
                 'items' => [
                     ['id' => 'hrsd', 'f' => ['name' => ['ar' => 'وزارة الموارد البشرية والتنمية الاجتماعية', 'en' => 'Ministry of Human Resources and Social Development'], 'domain' => ['v' => 'hrsd.gov.sa'], 'note' => ['ar' => 'بطاقة الإعاقة، الإعانات، التأهيل', 'en' => 'Disability card, allowances, rehabilitation']]],
                     ['id' => 'apd', 'f' => ['name' => ['ar' => 'هيئة رعاية الأشخاص ذوي الإعاقة', 'en' => 'Authority for the Care of Persons with Disabilities'], 'domain' => ['v' => 'apd.gov.sa'], 'note' => ['ar' => 'التمكين والسياسات والمبادرات', 'en' => 'Empowerment, policies and initiatives']]],
                     ['id' => 'kscdr', 'f' => ['name' => ['ar' => 'مركز الملك سلمان لأبحاث الإعاقة', 'en' => 'King Salman Center for Disability Research'], 'domain' => ['v' => 'kscdr.org.sa'], 'note' => ['ar' => 'الأبحاث والمكتبة العلمية', 'en' => 'Research and scientific library']]],
                     ['id' => 'moh', 'f' => ['name' => ['ar' => 'وزارة الصحة', 'en' => 'Ministry of Health'], 'domain' => ['v' => 'moh.gov.sa'], 'note' => ['ar' => 'الخدمات الصحية والتأهيلية', 'en' => 'Health and rehabilitation services']]],
                     ['id' => 'moe', 'f' => ['name' => ['ar' => 'وزارة التعليم', 'en' => 'Ministry of Education'], 'domain' => ['v' => 'moe.gov.sa'], 'note' => ['ar' => 'التعليم الشامل والدمج', 'en' => 'Inclusive education and integration']]],
                     ['id' => 'hrdf', 'f' => ['name' => ['ar' => 'هدف — صندوق تنمية الموارد البشرية', 'en' => 'HADAF — Human Resources Development Fund'], 'domain' => ['v' => 'hrdf.org.sa'], 'note' => ['ar' => 'التوظيف والتمكين المهني', 'en' => 'Employment and career empowerment']]],
                     ['id' => 'mygov', 'f' => ['name' => ['ar' => 'المنصة الوطنية الموحدة', 'en' => 'Unified National Platform'], 'domain' => ['v' => 'my.gov.sa'], 'note' => ['ar' => 'الخدمات الحكومية الإلكترونية', 'en' => 'Government e-services']]],
                 ]],
            ],
        ],
        [
            'id' => 'privacy', 'label' => 'حماية البيانات', 'hideable' => true,
            'desc' => 'التزامات الخصوصية — لكل بطاقة أيقونة وعنوان ووصف.',
            'fields' => [
                lpT('label', 'العنوان الصغير', 'حماية البيانات', 'Data protection', 40),
                lpT('title', 'عنوان القسم', 'بياناتك محمية — وبيدك', 'Your data, protected and in your hands', 100),
            ],
            'lists' => [
                ['k' => 'items', 'l' => 'البطاقات', 'item' => 'بطاقة', 'min' => 0, 'max' => 9, 'icon' => true,
                 'fields' => [
                     lpT('title', 'العنوان', '', '', 60),
                     lpA('desc', 'الوصف', '', '', 240),
                 ],
                 'items' => [
                     ['id' => 'encryption', 'i' => 'shield', 'f' => [
                         'title' => ['ar' => 'تشفير وتقييد وصول', 'en' => 'Encryption & access control'],
                         'desc'  => ['ar' => 'اتصال مشفّر، كلمات مرور مجزّأة لا يطّلع عليها أحد، وصلاحيات أدوار تحصر الوصول الإداري في أضيق نطاق', 'en' => 'Encrypted connections, hashed passwords nobody can read, and role permissions that keep admin access as narrow as possible']]],
                     ['id' => 'control', 'i' => 'settings', 'f' => [
                         'title' => ['ar' => 'تحكم كامل ببياناتك', 'en' => 'Full control of your data'],
                         'desc'  => ['ar' => 'من حسابك: نزّل نسخة من بياناتك، امسح محادثاتك، أو احذف حسابك نهائياً — بدون مراسلات ولا انتظار', 'en' => 'From your account: download a copy of your data, clear your chats, or delete your account for good — no emails, no waiting']]],
                     ['id' => 'transparency', 'i' => 'eye', 'f' => [
                         'title' => ['ar' => 'شفافية كاملة', 'en' => 'Full transparency'],
                         'desc'  => ['ar' => 'لا نبيع بياناتك ولا نشاركها مع معلنين، ونوضح في سياسة الخصوصية ما نجمعه ولماذا وكم نحتفظ به', 'en' => 'We never sell your data or share it with advertisers, and our privacy policy explains what we collect, why, and how long we keep it']]],
                 ]],
            ],
        ],
        [
            'id' => 'cta', 'label' => 'شريط الدعوة', 'hideable' => true,
            'desc' => 'الشريط الملوّن في آخر الصفحة يدعو الزائر للبدء.',
            'fields' => [
                lpT('title', 'العنوان', 'ابدأ رحلتك المعرفية مع وصال اليوم', 'Start your journey with Wesal today', 90),
                lpT('sub', 'النص', 'اسأل أي سؤال واحصل على إجابات واضحة ومُيسّرة — مجاناً', 'Ask anything and get clear, accessible answers — free', 160),
                lpT('btn', 'نص الزر', 'ابدأ الآن', 'Start now', 30),
            ],
            'lists' => [],
        ],
        [
            'id' => 'footer', 'label' => 'التذييل', 'hideable' => false,
            'desc' => 'أسفل كل صفحة. بيانات الاتصال تظهر أيضاً في صفحة «تواصل معنا».',
            'fields' => [
                lpA('about', 'النبذة', 'منصة ذكاء اصطناعي سعودية مُيسّرة تجيب الأشخاص ذوي الإعاقة من مصادر رسمية موثوقة، مع ذكر المصدر في كل إجابة',
                    'A Saudi accessible AI platform that answers people with disabilities from trusted official sources, naming the source in every answer', 300, 'الهوية'),
                lpM('x', 'حساب إكس (X)', 'url', 'https://x.com/Wesalhub', 200, 'حسابات التواصل الاجتماعي'),
                lpM('instagram', 'حساب إنستقرام', 'url', 'https://www.instagram.com/wesalhub', 200, 'حسابات التواصل الاجتماعي'),
                lpM('linkedin', 'حساب لينكد إن', 'url', 'https://www.linkedin.com/company/wesalksa0', 200, 'حسابات التواصل الاجتماعي'),
                lpT('quickTitle', 'عنوان العمود', 'روابط سريعة', 'Quick links', 30, 'عمود الروابط السريعة'),
                lpT('lHome', 'رابط الرئيسية', 'الرئيسية', 'Home', 30, 'عمود الروابط السريعة'),
                lpT('lChat', 'رابط المحادثة', 'محادثة مع وصال', 'Chat with Wesal', 30, 'عمود الروابط السريعة'),
                lpT('lGuide', 'رابط الدليل', 'الدليل الشامل', 'Complete guide', 30, 'عمود الروابط السريعة'),
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
                lpT('hours', 'ساعات العمل', 'الأحد - الخميس، 8 ص - 6 م', 'Sunday – Thursday, 8 AM – 6 PM', 60, 'عمود التواصل'),
                lpT('copy', 'حقوق النشر', '© 2026 وصال — جميع الحقوق محفوظة', '© 2026 Wesal — All rights reserved', 90, 'السطر الأخير'),
                lpT('tagline', 'السطر الختامي', 'منصة سعودية · إجابات بمصادر رسمية · وصول مُيسّر WCAG 2.2', 'A Saudi platform · Answers from official sources · Accessible to WCAG 2.2', 120, 'السطر الأخير'),
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
