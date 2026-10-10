/* ==========================================================================
 *  وصال: محتوى صفحة «أسئلة التحكيم» (/judges)
 *
 *  كل نص بلغتين: [عربي، إنجليزي]. مراجع المحتوى، للمحرر وحده ولا تُعرض في الصفحة:
 *    hk    عرض الهاكاثون النهائي (KSCDR_Hackathon_151)
 *    ar    العرض العربي «وصال | Wesal»
 *    repo  المنصة نفسها: الشيفرة وسياسة الخصوصية وشروط الاستخدام
 *    gastat الهيئة العامة للإحصاء 2023
 *  ما لم يثبته مرجع من هذه تُكتب ملاحظته في assets/judges-notes.js، وهو ملف
 *  خاص لا يُرفع إلى Git لأن المستودع عام (انظر README، «أسئلة التحكيم»).
 *  لا تضف حقل v هنا: يفشل tools/check-judges.js إن وُجد.
 *  قواعد README «كتابة النصوص الظاهرة للمستخدم» تسري هنا: لا شرطة طويلة ولا قصيرة.
 *  يتحقق من سلامة الملف: node tools/check-judges.js
 * ========================================================================== */
(function () {
  'use strict';

  var contact = {
    site: 'https://wesalinnovation.sa',
    email: 'info@wesalinnovation.sa',
    phone: '+966 50 112 0161',
    whatsapp: 'https://wa.me/966500039204',
    x: 'https://x.com/Wesalhub',
    linkedin: 'https://www.linkedin.com/company/wesalksa0',
    instagram: 'https://www.instagram.com/wesalhub',
    repo: 'https://github.com/Ahmadsamiii/wesalinnovation',
    hours: ['الأحد إلى الخميس، 8 صباحاً إلى 6 مساءً', 'Sunday to Thursday, 8 AM to 6 PM']
  };

  var project = {
    number: 'KSCDR_Hackathon_151',
    track: ['الحياة اليومية', 'Everyday Life'],
    category: ['شركات ومؤسسات', 'Companies and Institutions'],
    stage: ['نسخة تجريبية عاملة جُرّبت مع مستخدمين', 'Functional beta piloted with users']
  };

  var clusters = [
    { id: 'problem', icon: 'target', t: ['المشكلة والحاجة', 'The problem and the need'], d: ['ما الذي نحله، ولمن، وما حجمه، وكيف تحققنا منه.', 'What we solve, for whom, how big it is and how we validated it.'] },
    { id: 'users', icon: 'users', t: ['الفئة المستهدفة والمستخدمون', 'Users and target groups'], d: ['من نخدم اليوم، ومن بعدهم، ولماذا بدأنا بهم.', 'Who we serve today, who comes next, and why we started with them.'] },
    { id: 'solution', icon: 'spark', t: ['الحل والمنتج', 'The solution and the product'], d: ['ما وصال، وكيف تعمل، وما الجاهز منها اليوم.', 'What Wesal is, how it works and what is ready today.'] },
    { id: 'tech', icon: 'cpu', t: ['التقنية والذكاء الاصطناعي', 'Technology and AI'], d: ['النموذج والاسترجاع والبنية والبيانات والتكلفة والتعطل.', 'The model, retrieval, architecture, data, cost and failure handling.'] },
    { id: 'trust', icon: 'shield', t: ['الموثوقية والمصادر', 'Reliability and sources'], d: ['من أين تأتي المعلومة، وكيف نثبتها، ومن يراجعها.', 'Where information comes from, how we prove it and who reviews it.'] },
    { id: 'safety', icon: 'lock', t: ['الأمان والخصوصية والأخلاقيات', 'Safety, privacy and ethics'], d: ['البيانات الحساسة، والتشخيص، والمسؤولية.', 'Sensitive data, diagnosis and responsibility.'] },
    { id: 'access', icon: 'access', t: ['الإتاحة وتجربة المستخدم', 'Accessibility and user experience'], d: ['كيف صُمّمت المنصة لمن يستخدمها فعلاً.', 'How the platform is designed for the people who use it.'] },
    { id: 'business', icon: 'briefcase', t: ['الأعمال ونموذج الإيرادات', 'Business and revenue model'], d: ['من يدفع، وكم، ومتى نصل إلى التعادل.', 'Who pays, how much, and when we reach break-even.'] },
    { id: 'impact', icon: 'chart', t: ['الأثر والنتائج والتحقق', 'Impact, results and validation'], d: ['ماذا حققنا، وكيف قسناه، وماذا نستهدف.', 'What we achieved, how we measured it and what we target.'] },
    { id: 'future', icon: 'flag', t: ['الاستدامة والتوسع والفريق', 'Sustainability, growth and team'], d: ['الخطة والمخاطر والفريق وما نحتاجه.', 'The plan, the risks, the team and what we need.'] }
  ];

  var prefix = { p: 'problem', u: 'users', s: 'solution', ai: 'tech', te: 'tech', r: 'trust', v: 'safety', a: 'access', b: 'business', i: 'impact', f: 'future' };

  /* أرقام للحفظ: تظهر في شاشة «الأسئلة الشائعة» */
  var facts = [
    { v: ['5.9%', '5.9%'], l: ['نسبة السكان من ذوي الإعاقة', 'Share of the population with a disability'] },
    { v: ['1.35 مليون', '1.35 million'], l: ['عدد الأشخاص ذوي الإعاقة في المملكة', 'People with disabilities in the Kingdom'] },
    { v: ['52.6% و21.8%', '52.6% and 21.8%'], l: ['الحركية والبصرية بين من لديهم إعاقة واحدة', 'Mobility and visual, among single disabilities'] },
    { v: ['TRL 6', 'TRL 6'], l: ['نسخة تجريبية عاملة في بيئة تشغيل حقيقية', 'Functional beta in a real operating environment'] },
    { v: ['أكثر من 200', 'Over 200'], l: ['مستخدم من ذوي الإعاقة في الاختبار', 'Users with disabilities in testing'] },
    { v: ['92%', '92%'], l: ['أتموا مهامهم بنجاح في الاختبار', 'Completed their tasks in testing'] },
    { v: ['95%', '95%'], l: ['أسرع من البحث اليدوي بحسب الاختبار', 'Faster than manual search in testing'] },
    { v: ['أكثر من 40', 'Over 40'], l: ['مصدر رسمي في قاعدة المعرفة', 'Official sources in the knowledge base'] },
    { v: ['أكثر من 120', 'Over 120'], l: ['خدمة في قاعدة المعرفة', 'Services in the knowledge base'] },
    { v: ['29 ريالاً', 'SAR 29'], l: ['أقل سعر للاشتراك الشهري في وصال', 'Lowest monthly subscription price in Wesal'] },
    { v: ['15 إلى 3 ريالات', 'SAR 15 to SAR 3'], l: ['تكلفة المستخدم اليوم وعند الحجم', 'Cost per user today and at volume'] },
    { v: ['36 ألف ريال', 'SAR 36,000'], l: ['الميزانية المقدّرة للإطلاق', 'Estimated launch budget'] },
    { v: ['12 إلى 18 شهراً', '12 to 18 months'], l: ['للتعادل بعد الإطلاق', 'To break-even after launch'] },
    { v: ['1,000', '1,000'], l: ['مستخدم مسجل مستهدف في السنة الأولى', 'Registered users targeted in year one'] }
  ];

  var items = [];

  function Q(id, o, q, s, d) {
    o = o || {};
    items.push({
      id: id,
      c: prefix[id.replace(/[0-9]+$/, '')],
      hot: o.hot || 0,
      com: o.com ? 1 : 0,
      hard: o.hard ? 1 : 0,
      v: null,
      k: o.k || '',
      q: q,
      s: s,
      d: d || null
    });
  }

  /* ---------------------------------------------------------------- 1 المشكلة والحاجة */

  Q('p1', { hot: 2, com: 1 },
    ['وش المشكلة اللي تحلها وصال؟', 'What problem does Wesal solve?'],
    ['الشخص ذو الإعاقة غالباً يعرف حقوقه زين، بس يتعب لين يوصل لها. لازم يدخل عشرات المنصات الحكومية والخاصة، كل منصة لوحدها، وأغلبها مو مصمّمة لاحتياجه. ومنصة وصال تجمع له هذا كله في مكان واحد، موثوق وسهل عليه.', 'A person with a disability usually knows their rights well, but it takes a lot of effort to reach them. They have to go through dozens of government and private platforms, one by one, and most are not built for their needs. Wesal brings all of this together in one place that is trusted and easy to use.'],
    ['مثال: مستخدم كرسي متحرك يعرف الدعم اللي يستحقه. بس كل مرة يدوّر على نقل مناسب لوضعه. ويتأكد إن الأماكن مهيأة له، ويتحقق من إجراء اليوم. ويراجع كل تفصيلة في مصدر مختلف، وغالباً المصدر نفسه مو مصمّم له.\nالفجوة اللي نسدها مو إننا نعرّف الناس بخدمات ما يعرفونها. نقلّل الوقت والجهد والاعتماد على الغير.', 'Example: a wheelchair user knows the support they are entitled to. But every time, they look for suitable transport. They check that places are ready for them and confirm today’s procedure. They check each detail in a different source, and that source is often not built for them.\nThe gap we close is not about telling people about services they did not know. We cut the time, the effort and the need to depend on others.']);

  Q('p8', { com: 1, hard: 1, k: 'لوائح سياسات هيئة الحكومة الرقمية كود المنصات الموحد الشمولية الرقمية DGA Platforms Code regulations policies' },
    ['فيه لوائح وكود منصات من هيئة الحكومة الرقمية تلزم المنصات تكون ميسّرة لذوي الإعاقة. وش المشكلة اللي تحلونها إذن؟', 'The Digital Government Authority has regulations and the Unified Platforms Code that require platforms to be accessible. So what problem are you solving?'],
    ['صح، واللوائح تثبت إن الموضوع أولوية وطنية، وإحنا معها مو ضدها. بس هي تضبط كل منصة حكومية لوحدها، وتبقى ثلاث فجوات: القطاع الخاص، وأي إجراء يناسب حالة الشخص، والوقت. والدليل من تجربتنا: مع أكثر من 200 مستخدم، وصلوا لمعلوماتهم أسرع بـ95% مع وصال، حتى مع وجود المواقع الرسمية. نحن نكمّل المنصات ما نستبدلها.', 'Yes, and the regulations show this is a national priority. We are with them, not against them. But they govern each government platform on its own, and three gaps remain: the private sector, which procedure fits a person’s case, and time. Our proof: with more than 200 users, people reached their information 95% faster with Wesal, even with the official sites available. We complete the platforms, we do not replace them.'],
    ['• القطاع الخاص: الكود للمنصات الحكومية، والشخص يتعامل أيضاً مع الطيران والبنوك والاتصالات.\n• الإجراء المناسب: المنصة الميسّرة تخلّي الشخص يدخل ويستخدمها. بس ما تقول له أي إجراء يناسب حالته (مثل نوع كرسيه)، ولا تجمع له خطوات أكثر من جهة.\n• الوقت: الجهات تحصل على شهادة الالتزام بالكود واحدة واحدة، والشخص يحتاج الجواب اليوم.\n• نكمّل ولا نستبدل: ننقل المستخدم للصفحة الرسمية ونوريه المصدر، ونلتزم بمعيار WCAG 2.2 لإتاحة المواقع، ونقدر نعطي الجهات مؤشرات عن عوائق المستخدمين.', '• Private sector: the Code is for government platforms, and people also deal with airlines, banks and telecoms.\n• The right procedure: an accessible platform lets the person enter and use it. But it does not tell them which procedure fits their case (their chair type, for example), or gather steps from more than one entity.\n• Time: implementation is gradual. Entities get their certificate of compliance with the Code one by one, and the person needs the answer today.\n• We add, we do not replace: we send the user to the official page and show the source. We follow WCAG 2.2, the standard for accessible websites. And we can give entities indicators on the barriers users face.']);

  Q('p3', { com: 1, hard: 1, k: 'بوابات حكومية أبشر government portals gap' },
    ['المعلومات موجودة أصلاً في المنصات الحكومية، وش الفجوة الفعلية؟', 'The information already exists on government platforms, so what is the real gap?'],
    ['الفجوة في الوصول للمعلومة، مو في وجودها. المعلومة موزعة بين مواقع الطيران والمطارات والمنصات الرسمية، ولازم الشخص يقارن بينها بنفسه. وتختلف حسب نوع الإعاقة. ومصادرها ممكن ما تشتغل مع قارئ الشاشة، يعني البرنامج اللي يقرأ الصفحة بصوت. وإحنا ما نقول إننا نصنع المعلومة. نجمعها ونبسّطها ونذكر مصدرها.', 'The gap is in reaching the information, not in whether it exists. It is spread across airline sites, airport sites and official platforms, and the person has to compare them by hand. It differs by type of disability. Its sources may also not work with a screen reader, the software that reads a page aloud. We do not say we create the information. We gather it, simplify it and show its source.'],
    ['البدائل اليوم: مواقع شركات الطيران والمطارات، والمنصات الحكومية، وسؤال الناس.\nوالمشكلة فيها: المستخدم يدوّر ويقارن ويتأكد بنفسه. فيطول الوقت، ويزيد احتياجه لمساعدة غيره.', 'Today’s alternatives: airline and airport websites, official government platforms, and asking other people.\nThe problem with them: the user searches, compares and checks everything alone. This takes longer, and the user needs more help from others.']);

  Q('p2', { com: 1, k: 'نسبة حجم عدد إحصاء GASTAT census' },
    ['كم حجم المشكلة وكم عدد اللي تخدمونهم؟', 'How big is the problem and how many people does it affect?'],
    ['حسب الهيئة العامة للإحصاء، حوالي 1.35 مليون شخص عندهم إعاقة، يعني 5.9% من سكان المملكة. ومن عندهم إعاقة وحدة بس، الحركية 52.6% والبصرية 21.8%. وهذي أكبر فئتين بفارق كبير.', 'According to the General Authority for Statistics, about 1.35 million people have a disability. That is 5.9% of the Kingdom’s population. Among people with only one disability, 52.6% have a mobility disability and 21.8% a visual one. These are the two largest groups by a wide margin.'],
    ['المصدر: الهيئة العامة للإحصاء، منشور إحصاءات الإعاقة 2023، بناءً على تعداد السكان 2022.', 'Source: General Authority for Statistics, Disability Statistics Publication 2023, based on the 2022 Census.']);

  Q('p5', { k: 'دراسة احتياج استبيان validation needs study survey' },
    ['كيف تأكدتوا إن الحاجة موجودة؟', 'How did you validate that the need is real?'],
    ['تأكدنا بالدراسة وبالتجربة. سوينا دراسة احتياج مع المستفيدين وأصحاب العلاقة، عشان نتأكد من حجم المشكلة وإن الحل مناسب. وبعدها جرّبنا نسخة تجريبية شغالة مع أكثر من 200 مستخدم من ذوي الإعاقة بمهام حقيقية.', 'We confirmed it with a study and a real test. First, we ran a needs study with the people we serve and others involved, to check the size of the problem and whether our solution fits. Then we tested a working trial version with more than 200 users with disabilities on real tasks.'],
    ['رابط نموذج الدراسة موجود في عرضنا العربي، على شكل رمز QR تمسحه بالجوال.', 'The link to the study form is in our Arabic presentation, as a QR code you scan with your phone.']);

  Q('p4', { k: 'خالد persona قصة مستخدم' },
    ['من هو خالد وش قصته؟', 'Who is Khaled and what is his story?'],
    ['خالد شخصية تمثيلية: شاب سعودي عمره 24 سنة، يستخدم كرسي متحرك، وجاته فرصة يحضر مؤتمر برا مدينته. اكتشف إن الرحلة أكبر من حجز تذكرة: هل يشحن كرسيه؟ وش نوع البطارية المسموح؟ ومن المسؤول لو ما وصل الكرسي؟ دخل على وصال وكتب حالته بجملة وحدة، وخلال ثواني جاته إجابة تناسب حالته، مبنية على أنظمة الهيئة العامة للطيران المدني.', 'Khaled is an example character: a 24-year-old Saudi man who uses a wheelchair and got the chance to attend a conference outside his city. He found the trip was more than booking a ticket: must he ship his chair? Which battery type is allowed? Who is responsible if the chair does not arrive? He described his case to Wesal in one sentence, and within seconds got an answer that fit his case, based on the rules of the General Authority of Civil Aviation.'],
    ['خالد يمثّل احتياج المستخدم. يبغى يعرف بالضبط وش يسوي وش الخدمات المتاحة له، بدون ما يسأل أحد أو يدوّر في مواقع كثيرة.\nوهذي جملته: «أبغى أعرف بالضبط وش أسوي وش الخدمات المتاحة لي».', 'Khaled stands for the user’s need: to know exactly what to do and which services are available to him, without asking anyone or searching many websites.\nHis line: “I want to know exactly what I need to do and what services are available to me.”']);

  Q('p7', {},
    ['وش أثر المشكلة على الشخص في حياته اليومية؟', 'What does the problem do to a person in daily life?'],
    ['المشكلة لها أربعة آثار على حياة الشخص. أول شي، يضيع وقته: تأكيد معلومة وحدة ممكن ياخذ ساعات. ثاني شي، يفقد استقلاليته، لأنه يعتمد على مساعدة الناس وتخمينهم. ثالث شي، المعلومة مو مضمونة، لأن التعليمات أحياناً قديمة أو غير دقيقة. ورابع شي، المصادر متفرقة بين مواقع المطار وشركات الطيران والجهات الرسمية.', 'The problem has four effects on a person’s daily life. First, wasted time: confirming one fact can take hours. Second, the person loses independence, because they depend on other people’s help and guesses. Third, the information is not guaranteed, because instructions are sometimes outdated or inaccurate. Fourth, the sources are scattered across airport sites, airlines and official bodies.'],
    ['ما فيه اليوم مرجع واحد يجمع هذي الإجراءات ويضمن إن الشخص يسوي أموره بنفسه وبأمان. والمشكلة مو إن المعلومة ناقصة. المشكلة في البحث عنها، اللي يصير عائق.', 'Today there is no single reference that gathers these procedures and makes sure the person can manage on their own, safely. The problem is not that the information is missing. The problem is the search for it, which becomes a barrier.']);

  /* ---------------------------------------------------------------- 2 الفئة المستهدفة والمستخدمون */

  Q('u1', { com: 1 },
    ['من المستخدم الأساسي لوصال؟', 'Who is Wesal’s primary user?'],
    ['مستخدمونا الأساسيون هم الأشخاص ذوو الإعاقة في المملكة. والفئات الأساسية عندنا: الحركية والبصرية والسمعية والإدراكية. بس النسخة الحالية تركّز على الحركية والبصرية. ومثالنا خالد، عمره 24 سنة، يستخدم كرسي متحرك ويدير أموره بنفسه. ويحتاج مساعدة لما الإجراءات ما تكون واضحة.', 'Our primary users are people with disabilities in the Kingdom. Our main groups are mobility, visual, hearing and cognitive disabilities. But the current version focuses on mobility and visual disabilities. Our example is Khaled, who is 24, uses a wheelchair and manages his own affairs. He needs help when procedures are not clear.'],
    ['بقية الفئات نضيفها تدريجياً. وتفاصيل أكثر في سؤال «وش عن الصم وضعاف السمع والإعاقة الإدراكية؟».', 'We add the other groups step by step. For more, see the question “What about deaf and hard of hearing users and cognitive disabilities?”']);

  Q('u3', { com: 1, k: 'حركية بصرية ابدأ ليش لماذا الفئتين mobility visual scope' },
    ['ليش تبدؤون بالإعاقة الحركية والبصرية؟', 'Why start with mobility and visual disabilities?'],
    ['لأنهم أكبر فئتين بين اللي عندهم إعاقة وحدة: 52.6% و21.8%، حسب الهيئة العامة للإحصاء. ولأن المنصة الحالية فيها نص وصوت وشاشات تناسب احتياجهم، فتخدمهم زين. قررنا نتقن خدمة فئتين في البداية، بدل ما نوعد الكل بخدمة ناقصة.', 'Because they are the two largest groups among people with a single disability: 52.6% and 21.8%, according to the General Authority for Statistics. And because our platform today has text, voice and screens that suit their needs, so it serves them well. We chose to do two groups well to begin with, instead of promising everyone an incomplete service.']);

  Q('u4', { com: 1, hard: 1, k: 'صم سمعية لغة الإشارة إدراكية deaf sign language cognitive' },
    ['وش عن الصم وضعاف السمع والإعاقة الإدراكية؟', 'What about deaf and hard of hearing users and cognitive disabilities?'],
    ['الدعم الحالي لفئتين بس: الحركية والبصرية، لأنهم اللي اختبرنا المنصة معهم. الإعاقة السمعية تحتاج دعم لغة الإشارة، وهو مو متوفر الحين. ونشتغل على إضافته مع مختصين. والإعاقة الإدراكية نضيف دعمها بعد ما نختبره مع المستخدمين والجمعيات الشريكة. وما نضيف أي فئة إلا لما نتأكد إنها مخدومة صح.', 'Right now we support two groups only: mobility and visual disabilities, because those are the groups we tested with. Hearing disabilities need sign language support, which is not available yet. We are working on adding it with specialists. For cognitive disabilities, we add support after testing it with users and partner associations. We add a group only when we are sure it is served properly.'],
    ['خطتنا الزمنية تحط توسيع الفئات وتطوير أدوات تسهّل الوصول الرقمي في 2029.\nوفي المحادثة وضع «مبسّط» يفيد بعض المستخدمين، بس ما نعلن دعم شي ما اختبرناه.', 'Our timeline puts expanding the groups and building tools that make digital access easier in 2029. The chat also has a “simple” mode that helps some users, but we do not announce support for what we have not tested.']);

  Q('u2', {},
    ['من المستخدمون الثانويون؟', 'Who are the secondary users?'],
    ['المستخدمون الثانويون هم: الجمعيات والمنظمات، والجهات الحكومية، والمختصون، والمدارس والجامعات، ومقدمو الرعاية، وأولياء الأمور. احتياجهم قريب من احتياج المستخدم الأساسي. يبغون إجابة سريعة وسهلة ومعروف مصدرها.', 'The secondary users are associations and organizations, government entities, specialists, schools and universities, caregivers and parents. Their need is close to the primary user’s. They want a fast, easy answer with a known source.'],
    ['الجهات تستفيد أيضاً من مؤشرات عن العوائق اللي يواجهها المستخدمون. تساعدها ترفع جاهزية خدماتها الرقمية، عشان تصير أسهل لذوي الإعاقة.', 'Entities also benefit from indicators of the barriers users face. These help them get their digital services ready, so that they are easier for people with disabilities.']);

  Q('u5', {},
    ['هل تخدم وصال الأسر ومقدمي الرعاية؟', 'Does Wesal serve families and caregivers?'],
    ['إي، نخدمهم. لأن أسئلتهم نفس أسئلة المستخدم: وش الحقوق؟ وين أقدّم؟ وش المستندات؟ ومع من أتواصل؟ والإجابات لها نفس المصادر الموثقة. وعندنا خطة لأدوات متقدمة للمختصين ومقدمي الرعاية، ضمن باقة أعلى.', 'Yes, we serve them. Their questions are the same as the user’s: what are the rights? Where do I apply? Which documents do I need? Whom do I contact? The answers have the same documented sources. We also plan advanced tools for specialists and caregivers in a higher plan.']);

  Q('u6', {},
    ['ليش لازم تختلف الإجابة من شخص لشخص؟', 'Why should the answer differ from one person to another?'],
    ['لأن اللي يناسب شخص ما يناسب غيره. مثلاً: اللي يناسب مستخدم الكرسي اليدوي ممكن ما يناسب مستخدم الكرسي الكهربائي. في النسخة الحالية المستخدم يختار أسلوب الجواب، مبسّط أو مفصّل، ويقدر يضيف بياناته الاختيارية عشان الجواب يناسبه أكثر. والملف الشخصي الكامل لاحتياجه ميزة مخطط لها.', 'Because what fits one person may not fit another. For example, what suits a manual wheelchair user may not suit an electric wheelchair user. In the current version the user picks the answer style, simple or detailed, and can add optional profile details so the answer fits them better. A full personal profile of their needs is a planned feature.']);

  /* ---------------------------------------------------------------- 3 الحل والمنتج */

  Q('s1', { hot: 1, com: 1, k: 'pitch تعريف جملة واحدة one line' },
    ['وش وصال في جملة وحدة؟', 'What is Wesal in one sentence?'],
    ['وصال مساعد ذكاء اصطناعي سعودي، يساعد الأشخاص ذوي الإعاقة يوصلون للخدمات والإجراءات الرقمية المعقدة بخطوات سهلة وموثوقة.', 'Wesal is a Saudi AI assistant that helps people with disabilities reach complex digital services and procedures through easy, trustworthy steps.'],
    ['وصال منصة سعودية تخلّي المعلومات والخدمات الرقمية أسهل: أسهل في الوصول، وفي الفهم، وفي التنفيذ. تحوّل المعلومات المعقدة والمتفرقة إلى إرشاد مناسب لكل شخص، وميسّر وموثوق، ويكمّل المستخدم خطوته الجاية بنفسه.', 'Wesal is a Saudi platform that makes digital information and services easier: easier to reach, to understand and to act on. It turns complex, scattered information into guidance that fits each person, is accessible and trustworthy, so users take their next step on their own.']);

  Q('s2', { com: 1, k: 'خطوات المراحل flow steps pipeline' },
    ['كيف تشتغل وصال خطوة بخطوة؟', 'How does Wesal work, step by step?'],
    ['خمس خطوات بسيطة. تسأل بالكتابة أو بصوتك. وصال تفهم قصدك واحتياجك. تجيب المعلومة من مصادرنا السعودية المنتقاة. تبسّط الجواب وترتبه حسب احتياجك وتفضيلاتك. وتعطيك جواب ميسّر، نص أو صوت، مع الخطوة الجاية.', 'Five simple steps. You ask, by typing or by voice. Wesal understands what you mean and what you need. It finds the information in our selected Saudi sources. It simplifies the answer and arranges it for your needs and preferences. Then it gives you an accessible answer, as text or voice, with the next steps.']);

  Q('s7', { hot: 10, com: 1, hard: 1, k: 'جاهز مخطط roadmap MVP beta live planned' },
    ['وش الجاهز اليوم ووش المخطط؟', 'What is ready today and what is planned?'],
    ['الجاهز اليوم نسخة تجريبية شغالة فعلاً على wesalinnovation.sa، جرّبها أكثر من 200 مستخدم حقيقي. فيها محادثة بمصادر رسمية، وصوت سعودي، وإعدادات وصول سريعة. والمخطط شي ما سويناه بعد: ربط مباشر مع الجهات الخدمية، وفئات إعاقة إضافية، وملف وصول شخصي كامل، وتحليل المستندات، وتوسع إقليمي.', 'Ready today: a working beta at wesalinnovation.sa, tried by more than 200 real users. It has a chat with official sources, a Saudi voice and quick accessibility settings. Planned means not done yet: direct links with service entities, more disability groups, a full personal accessibility profile, document analysis and regional expansion.'],
    ['وفي الجاهز كمان: لوحة تحكم واستبيانات، وأدوار للمستخدمين، وجواب يظهر تدريجياً. ونقولها بوضوح: أي شي ما اختبرناه مع المستخدمين نسميه مخطط.', 'Also ready: a control panel and surveys, user roles, and answers that appear step by step. We say it plainly: anything we have not tested with users, we call planned.']);

  Q('s8', { com: 1, k: 'مقارنة منافسين government portals general search comparison' },
    ['وش يفرق وصال عن البوابات الحكومية والبحث العام؟', 'How does Wesal differ from government portals and general search?'],
    ['الفرق في ثلاث نقاط. أولاً: وصال تعطيك طريق واحد بخطوات تنفّذها، والبوابات معلومات موزعة على خدمات منفصلة، والبحث العام نتائج كثيرة تدوّر فيها بنفسك. ثانياً: سهولة الوصول لذوي الإعاقة مبنية عندنا من الأساس. في البوابات تختلف من منصة لمنصة، والبحث العام مو موجّه للإعاقة. ثالثاً: معلوماتنا سعودية وبالعربي.', 'Three differences. First: Wesal gives you one path with steps you can follow, while portals hold information spread across separate services, and general search gives many results you sort out yourself. Second: accessibility for people with disabilities is built in from the start. In portals it varies from one platform to another, and general search is not made for disability. Third: our information is Saudi and in Arabic.'],
    ['الخلاصة: رحلة وحدة بدل معلومات متفرقة، وسهولة وصول من أول التصميم، ومعرفة سعودية موثوقة تقدر تنفذها.', 'In short: one journey instead of scattered information, accessibility from the first design, and trusted Saudi knowledge you can act on.']);

  Q('s3', { k: 'ميزات features' },
    ['وش ميزات وصال؟', 'What are Wesal’s features?'],
    ['في عرضنا العربي ست ميزات لوصال: معرفة سعودية متخصصة، ومرافق ذكي يمشي معك في الإجراء خطوة بخطوة، وتحليل للعوائق الرقمية. وملف وصول شخصي، ومطابقة ذكية للخدمة المناسبة لك. وذاكرة تحفظ رحلتك، فتكمّل الإجراءات من غير ما تعيد الخطوات.', 'Our Arabic deck lists six Wesal features: specialized Saudi knowledge, a smart companion that walks you through a procedure step by step, and analysis of digital barriers. Also a personal accessibility profile, smart matching to the right service, and a memory of your journey, so you finish procedures without repeating steps.']);

  Q('s4', { k: 'إجابة شكل الرد answer format مبسط مفصل' },
    ['كيف تبدو الإجابة اللي يشوفها المستخدم؟', 'What does the answer the user sees look like?'],
    ['الجواب يبدأ بخلاصة في سطر واحد، وبعدها الخطوات في نقاط قصيرة. بعدها الجهة المسؤولة وقناتها الرسمية بالاسم، وسؤال متابعة واحد. وتحته ثلاثة أسئلة مقترحة للمتابعة، ومصادر رسمية لو الجواب مبني على صفحات رسمية.', 'The answer starts with a one-line summary, then the steps in short points. Next comes the responsible entity and its official channel by name, and one follow-up question. Below it are three suggested follow-up questions and official sources, when the answer is built on official pages.'],
    ['للمستخدم وضعين للجواب. الوضع المبسّط حوالي 120 كلمة بجمل قصيرة جداً. والوضع المفصّل فيه الخطوات والجهة المسؤولة عن كل خطوة.', 'Users have two modes for the answer. Simple mode is about 120 words in very short sentences. Detailed mode gives the steps and the entity responsible for each step.']);

  Q('s6', { k: 'لوحة التحكم dashboard مساحة عمل workspace' },
    ['وش في المنصة غير المحادثة؟', 'What is in the platform besides the chat?'],
    ['غير المحادثة، فيه حساب شخصي وملف اختياري. المحادثات محفوظة في متصفح المستخدم. ويقدر المستخدم يرفع طلب دعم فني، وينزّل بياناته، ويحذف حسابه. وللفريق لوحة تحكم: المستخدمين والأدوار والرسائل والتذاكر والدعوات والاستبيانات، وتعديل محتوى الصفحة الرئيسية.', 'Besides the chat, there is a personal account with an optional profile. Chats are saved in the user’s browser. Users can send a support request, download their data and delete their account. The team has a dashboard: users, roles, messages, tickets, invitations, surveys and editing the home page content.'],
    ['وفيه أيضاً مساحة عمل داخلية لإدارة المشاريع. تستخدم نفس حساب المنصة ونفس الدور ونفس تسجيل الدخول. وعرضنا العربي يوضح: المحادثة الذكية، والموارد، ولوحة التحكم.', 'There is also an internal workspace for managing projects. It uses the same account, role and login as the platform. Our Arabic presentation shows the smart chat, resources and the dashboard.']);

  /* ---------------------------------------------------------------- 4 التقنية والذكاء الاصطناعي */

  Q('ai1', { hot: 4, com: 1, hard: 1, k: 'وش النموذج Gemini OpenAI Claude wrapper غلاف تدريب ضبط fine-tune LoRA نموذجنا model train' },
    ['وش النموذج اللي تستخدمونه، وهل دربتوا نموذجكم الخاص؟', 'Which model do you use, and did you train your own?'],
    ['نبني نموذج وصال على مرحلتين. الأولى شغالة الحين: نموذج ذكاء اصطناعي قوي، مربوط بمصادرنا السعودية الرسمية، ومقيّد بقواعدنا عشان ما يخترع ولا يشخّص. والثانية قيد العمل: نضبط نموذجاً مفتوحاً على بيانات الإعاقة في السعودية، ليصير نموذج وصال الخاص.', 'We are building the Wesal model in two stages. The first works today: a strong AI model, connected to our official Saudi sources and held by our rules so it does not make things up or diagnose. The second is in progress: we are tuning an open model on Saudi disability data so it becomes Wesal’s own model.'],
    ['• الحين النموذج الأساسي Gemini من قوقل، وعندنا نماذج احتياطية، ونقدر نبدّله بنموذج ثاني (مثل OpenAI أو Claude) بإعداد واحد.\n• ما نبدأ من الصفر، لأن القيمة في بياناتنا ومصادرنا الرسمية وقواعد السلامة عندنا، وعليها نبني نموذجنا.\n• وتبديل النموذج الأساسي ما يضيّع شغلنا، بل يستفيد منه.\n• وهدفنا نقلل اعتمادنا على مزوّد خارجي.', '• Right now the main model is Google’s Gemini, we keep backup models, and one setting switches it to another one, such as OpenAI or Claude.\n• We do not start from scratch, because the value is in our data, our official sources and our safety rules, and we build our own model on them.\n• Swapping the base model does not waste our work, it benefits from it.\n• Our goal is to depend less on an outside provider.']);

  Q('ai2', { hot: 3, com: 1, hard: 1, k: 'ChatGPT شات جي بي تي كلود Claude Gemini عام general AI chatbot الفرق وش تفرقون' },
    ['وش تفرقون عن ChatGPT أو كلود أو غيره؟', 'How are you different from ChatGPT, Claude or the others?'],
    ['ChatGPT وClaude وغيرها نماذج قوية، وأنا أستخدمها كل يوم في مهامي الشخصية. لكنها لكل الناس: ما تضمن لك دقة إجراءات السعودية، ولا تلتزم بمصدر رسمي، وممكن تجاوب وهي مو متأكدة. وصال تجاوبك من مصادر سعودية رسمية، وتورّيك المصدر، وتقول «ما أدري» بدل ما تخترع. وهي مخصصة لمجتمع ذوي الإعاقة في المملكة، شي مننا وفينا، يفهمنا ويفهم وش نبي بالضبط.', 'ChatGPT, Claude and others are strong models, and I use them every day for my personal tasks. But they are built for everyone: they do not guarantee the accuracy of Saudi procedures, they do not commit to an official source, and they may answer when they are not sure. Wesal answers from official Saudi sources, shows you the source, and says “I don’t know” instead of making things up. And it is made for the disability community in Saudi Arabia, one of our own, one that understands us and exactly what we need.'],
    ['الفروق باختصار:\n• المعلومة: من مصادر رسمية سعودية، مو من الإنترنت العام، ونورّيك المصدر تحت الإجابة.\n• الأمانة: لو ما لقينا مصدر نقول «ما أدري» ونوجّهك للجهة الرسمية.\n• الحدود: ما نشخّص ولا نعطي رأي طبي أو قانوني.\n• الإتاحة: صوت سعودي، وقارئ شاشة، وتكبير الخط، ووضع مبسّط بجمل قصيرة.\n• المراجعة: فريقنا الطبي يراجع المحتوى الصحي قبل النشر.\n• التجربة: جرّبناها مع أكثر من 200 مستخدم من ذوي الإعاقة، و92% أنجزوا مهامهم.\nوبصراحة: نستخدم نماذج مثلهم من جوّا، وما نقول إن ذكاءنا أقوى منهم. نقول إن المصدر والأمان والإتاحة عندنا أنسب لهذا المجال.', 'The differences in short:\n• Information: from official Saudi sources, not the open internet, and we show the source under the answer.\n• Honesty: if we find no source we say “I don’t know” and point you to the official entity.\n• Limits: we do not diagnose or give medical or legal opinions.\n• Accessibility: Saudi voice, screen reader support, bigger text and a simple mode with short sentences.\n• Review: our medical team reviews health content before publishing.\n• Testing: more than 200 users with disabilities tried it, and 92% completed their tasks.\nTo be frank: we use models like theirs under the hood, and we do not claim our AI is smarter. We say our sources, safety and accessibility fit this field better.']);

  Q('ai3', { hot: 5, com: 1, hard: 1, k: 'هلوسة hallucination اختلاق five layers خمس طبقات دقة' },
    ['كيف تضمنون إن الإجابة صحيحة وما تهلوس؟', 'How do you make sure an answer is correct and not made up?'],
    ['نخلّي النموذج يجاوب من مصادرنا الرسمية، مو من راسه. وإذا ما لقى مصدر، يقول إنه مو متأكد ويوجّهك للجهة الرسمية. وتحت كل جواب نعرض المصدر، عشان تتأكد بنفسك. وعندنا خمس طبقات حماية بالمجموع، والرجوع للمصادر أولها.', 'We make the model answer from our official sources, not by guessing. If it finds no source, it says it is not sure and points you to the official authority. Under each answer we show the source, so you can check it yourself. We have five layers of protection in all, and going back to the sources is the first one.'],
    ['الطبقة الأولى هي الرجوع للمصادر الرسمية. والأربع الباقية:\n• قواعد تمنع النموذج يخترع أرقام أو روابط.\n• نظامنا، مو النموذج، هو اللي يحدد المصدر اللي يظهر لك.\n• فريقنا الطبي يراجع المحتوى الصحي.\n• وأي مستخدم يبلّغ عن خطأ بضغطة.', 'The first layer is going back to the official sources. The other four:\n• Rules that stop the model from inventing numbers or links.\n• Our system, not the model, decides which source is shown.\n• Our medical team reviews health content.\n• Any user can report an error with one tap.']);

  Q('te1', { com: 1, k: 'architecture stack PHP MySQL SSE بنية تقنية معمارية' },
    ['وش البنية التقنية للنظام؟', 'What is the technical architecture?'],
    ['وصال موقع بسيط يشتغل من المتصفح، بدون تحميل ولا تثبيت. خلفه سيرفر، يعني الجهاز اللي يشغّل الموقع، وقاعدة بيانات عندنا. وفيه ربط مباشر مع نموذج الذكاء الاصطناعي اللي يكتب الجواب. والجواب يطلع لك كلمة كلمة. ومصادرنا الرسمية محفوظة في قاعدة البيانات، ومنها يجي الجواب.', 'Wesal is a simple website that runs in the browser, with nothing to download or install. Behind it are a server, which is the computer that runs the site, and a database of ours. It connects directly to the AI model that writes the answer. The answer appears word by word. Our official sources are stored in the database, and the answers come from them.'],
    ['للتقنيين: الخادم مكتوب بلغة PHP، وقاعدة البيانات MySQL، والربط مع النموذج عن طريق واجهة برمجية (API).', 'For technical readers: the server runs on PHP, the database is MySQL, and the model is connected through an API.']);

  Q('te3', { com: 1, hard: 1, k: 'بيانات تخزين استضافة داخل المملكة data location hosting residency PDPL' },
    ['وين تنحفظ البيانات؟ وهل تطلع برا السعودية؟', 'Where is the data stored? Does it leave Saudi Arabia?'],
    ['بيانات الحسابات ومصادرنا على استضافتنا. لكن عشان النموذج يجاوب، نرسل نص السؤال وآخر رسائل المحادثة للشركة اللي تقدّمه. فالنص يتعالج عند هذي الشركة، والصوت يتعالج عند الشركة اللي تقدّم خدمة الصوت. وهذا النص نرسله بدون اسم المستخدم ولا إيميله. أما قائمة المحادثات المحفوظة فتبقى في متصفح المستخدم نفسه.', 'Account data and our sources are on our hosting. But the model needs the question text and the last few messages to answer. We send them to the company that provides the model. That company processes the text, and the voice service company processes the voice. We send this text without the user’s name or email. The saved chat list stays in the user’s own browser.']);

  Q('ai4', { com: 1, k: 'RAG retrieval embedding vector استرجاع متجهات قاعدة المعرفة cosine' },
    ['وش هو RAG وكيف يشتغل عندكم؟', 'What is RAG and how does it work for you?'],
    ['RAG معناه إننا نجيب المعلومة من مصادرنا الرسمية أول، وبعدين النموذج يجاوب منها. هو مثل طالب يجاوب والكتاب مفتوح قدامه. وعندنا يمشي كذا: نقارن معنى سؤالك بمقاطع من مصادرنا، ونختار أقرب أربعة مقاطع، ونعطيها للنموذج. وإذا ما لقينا مقطع قريب كفاية، ما نعرض مصدر.', 'RAG means we get the information from our official sources first, and then the model answers from it. It is like a student answering with the book open in front of them. Here is how it works for us. We compare the meaning of your question with pieces of our sources. We pick the four closest pieces and give them to the model. If no piece is close enough, we show no source.']);

  Q('ai6', { com: 1, k: 'دقة accuracy قياس تقييم evaluation' },
    ['كم دقة الإجابات وكيف تقيسونها؟', 'How accurate are the answers and how do you measure it?'],
    ['ما عندنا رقم دقة واحد، وما نبي نقول رقماً ما قسناه. اللي قسناه: جرّبنا وصال مع أكثر من 200 مستخدم بمهام حقيقية. 92% منهم خلّصوا مهامهم، وكانوا أسرع بـ95% من البحث بأنفسهم. وكل إجابة نقدر نرجعها لمصدرها.', 'We do not have one accuracy number, and we will not give a number we did not measure. What we did measure: more than 200 users tried Wesal on real tasks. 92% of them finished their tasks, and they were 95% faster than searching by themselves. And we can trace every answer back to its source.']);

  Q('ai5', { k: 'تعلم تدريب محادثات المستخدمين خصوصية learn train user data conversations' },
    ['هل تتعلم وصال من محادثات المستخدمين؟ وهل تدرّبون عليها؟', 'Does Wesal learn from users’ conversations? Do you train on them?'],
    ['لا، ما نعيد تدريب النموذج على كلام المستخدمين. نحفظ نص الأسئلة والأجوبة بدون اسم ولا حساب، عشان نحسّن الإجابات. أما المستخدم اللي سجّل دخوله، فما نحفظ كلامه إلا لو وافق. وما نبيع البيانات.', 'No, we do not retrain the model on users’ words. We save the text of questions and answers with no name or account, to improve the answers. For users who are signed in, we save their words only if they agree. We do not sell data.']);

  Q('te4', { com: 1, k: 'حماية أمن اختراق security HTTPS تشفير كلمات المرور' },
    ['كيف تحمون النظام من الاختراق؟', 'How do you protect the system from attacks?'],
    ['الاتصال بالموقع محمي، وكلمة المرور ما تنحفظ نفسها. وكل شخص له صلاحيات حسب دوره، والنظام هو اللي يفرضها. وإذا توقف الشخص عن الاستخدام، ينتهي دخوله تلقائياً: بعد 30 دقيقة للمستخدم، و15 دقيقة لفريقنا. وفيه حدود على عدد الطلبات، وفحص للصور المرفوعة، وسجل لكل عملية إدارية.', 'The connection to the site is protected, and we do not save passwords as they are. Each person has permissions based on their role, and our system enforces them. If someone stops using it, their session ends automatically: after 30 minutes for users and 15 minutes for our team. There are also limits on requests, checks on uploaded images, and a log of every admin action.']);

  Q('te5', { k: 'تعطل fallback failover rate limit حصة quota المزوّد' },
    ['وش يصير لو وقف المزوّد أو طاحت الخدمة؟', 'What happens if the provider goes down?'],
    ['لو وقف المزوّد، عندنا ثلاث طبقات احتياط. أول شي: نماذج احتياطية عند نفس المزوّد. وبعدها مزوّدين ثانيين، لو فعّلناهم. وإذا انقطع وصول الرد كلمة كلمة، المتصفح يطلبه كامل مرة وحدة. وآخر شي: معلومات بسيطة محفوظة داخل الصفحة نفسها، نجاوب منها.', 'If the provider goes down, we have three backup layers. First, backup models with the same provider. Then other providers, if we turn them on. If the reply stops arriving word by word, the browser asks for the full reply at once. Last, some simple information saved inside the page itself, and we answer from it.']);

  Q('te6', { k: 'توسع ضغط مستخدمين scale load cloud سحابية' },
    ['هل النظام يتحمّل مستخدمين كثير؟', 'Can the system handle many users?'],
    ['اليوم المنصة على استضافة ويب، وتكفي لمرحلة التجربة. جرّبها أكثر من 200 مستخدم. ولما نكبر، ننقلها لخدمة سحابية تكبر حسب الضغط عليها. وهذا ضمن ميزانية الخدمة السحابية: 8,000 ريال. وعندنا حدود لكل شخص، عشان ما أحد يستهلك الموارد كلها.', 'Today the platform runs on web hosting, and it is enough for the trial stage. More than 200 users tried it. When we grow, we will move it to a cloud service that grows with the load. This is covered by the cloud service budget: SAR 8,000. We also set limits per person, so nobody uses up all the resources.']);

  Q('te7', { com: 1, k: 'تكلفة التشغيل cost حدود سقف limits' },
    ['كم تكلفة التشغيل؟', 'How much does it cost to run?'],
    ['نقدّر التكلفة بحوالي 15 ريال لكل مستخدم الحين، وتنزل إلى 3 ريال لما نكبر. ونحمي الفاتورة بحدود لكل شخص وسقف يومي. ونحفظ الأصوات اللي تتكرر، عشان ما ندفع عليها مرتين.', 'Our estimate is about SAR 15 per user now. It drops to SAR 3 as we grow. We protect the bill with limits for each person and a daily cap. We also save voices that repeat, so we do not pay for them twice.']);

  Q('te2', { k: 'طورنا بنينا جاهز built ready-made' },
    ['وش اللي طوّرتوه أنتم، ووش اللي جاهز؟', 'What did you build yourselves and what is ready-made?'],
    ['طوّرنا المنصة كلها: الواجهة، والخادم اللي يشتغل خلفها، ولوحة التحكم، والحسابات والأدوار. وطوّرنا كمان طريقة ربط كل إجابة بمصدرها وعرضها. واللي استخدمناه جاهز: نموذج الذكاء الاصطناعي، وأصوات قراءة الإجابات، والاستضافة اللي يشتغل عليها الموقع.', 'We built the whole platform: the interface, the server that runs behind it, the dashboard, and the accounts and roles. We also built the way each answer is linked to its source and shown. What we used ready-made: the AI model, the voices that read answers aloud, and the hosting. Hosting is where the website runs.']);

  Q('ai7', { k: 'نموذج سعودي عربي ALLaM Saudi Arabic model' },
    ['ليش ما استخدمتوا نموذج سعودي أو عربي؟', 'Why not use a Saudi or Arabic model?'],
    ['لأننا نبي الأفضل للمستخدم من النماذج المتاحة اللي تشتغل معنا بثبات وسرعة. ومنصة وصال مبنية بحيث نبدّل النموذج بتغيير إعداد واحد. فلو في نموذج سعودي مناسب ومتاح، نجرّبه ونقارنه، ونستخدمه لو طلع أحسن.', 'We want what is best for the user among the available models that run in a stable and fast way. The Wesal platform is built so we can swap the model by changing one setting. So if there is a suitable Saudi model that is available, we will test it and compare it. If it proves better, we will use it.']);

  Q('te8', { k: 'صوت tts stt voice Whisper Azure Groq املاء dictation' },
    ['كيف يشتغل الصوت؟', 'How does voice work?'],
    ['تقدر تسأل بصوتك أو تكتب. والإجابة تنقرأ لك بصوت سعودي. وإذا ما اشتغل الصوت السعودي، يقرأها المتصفح بصوت جهازك. وإذا متصفحك ما يقدر يحوّل كلامك إلى كتابة، نحوّل تسجيلك إلى نص ونمسح التسجيل. ما نحتفظ فيه.', 'You can ask by voice or by typing. The answer is read to you in a Saudi voice. If the Saudi voice does not work, the browser reads it with your device voice. If your browser cannot turn your speech into text, we do it for you. We turn your recording into text, then delete the recording. We do not keep it.']);

  Q('ai8', { hard: 1, k: 'تلاعب prompt injection jailbreak حقن' },
    ['هل أحد يقدر يتلاعب بالمساعد؟', 'Can someone manipulate the assistant?'],
    ['عندنا حماية تصعّب هذا. تعليمات المساعد تمنعه يكشف تفاصيله التقنية، وترجّع الحديث للموضوع. والمصادر اللي تظهر للمستخدم يحددها نظامنا، مو نموذج الذكاء الاصطناعي، فما أحد يقدر يخلّيه يخترع جهة رسمية. وفيه حدود على عدد الأسئلة تمنع الإساءة.', 'We have protections that make this hard. The assistant’s instructions stop it from revealing its technical details and bring the chat back to the topic. Our system, not the AI model, decides which sources are shown, so nobody can make it invent an official entity. We also limit the number of questions to prevent abuse.']);

  Q('ai9', { k: 'لهجة سعودية عربي dialect Saudi Arabic' },
    ['كيف يتعامل مع العربي واللهجات؟', 'How does it handle Arabic and dialects?'],
    ['يجاوب بعربي واضح ولهجة سعودية بيضاء، يعني بسيطة ومفهومة. جمله قصيرة، ويبدأ بالخلاصة. والموقع نفسه مكتوب بفصحى مبسّطة، والأصوات اللي تقرأ الإجابات سعودية. وفيه إنجليزي كمان.', 'It answers in clear Arabic, in a simple Saudi dialect that is easy to understand. Its sentences are short, and it starts with the summary. The website itself is written in simple standard Arabic. The voices that read the answers are Saudi. English is supported too.'],
    ['نموذج الذكاء الاصطناعي يفهم الفصحى واللهجات. بس ما اختبرنا كل لهجة.', 'The AI model understands standard Arabic and dialects. But we have not tested every dialect.']);

  Q('te9', { k: 'ربط API تكامل جهات الكود مفتوح GitHub open source integration' },
    ['هل تقدرون تربطون مع جهات ثانية؟ وهل الكود مفتوح؟', 'Can other entities integrate with you? Is the code open?'],
    ['حالياً ما عندنا ربط مباشر تقدر الجهات تستخدمه. والربط مخطط في 2027 مع أول جهتين أو ثلاث جهات خدمية. أما الكود، فموجود على GitHub، وهو موقع يحفظ فيه المبرمجون برامجهم.', 'For now, we do not have a direct connection that other organizations can use. Integration is planned for 2027, with the first two or three service organizations. As for the code, it is on GitHub, a website where programmers keep their programs.']);

  Q('te10', { k: 'مراقبة monitoring لوحة التحكم logs أعطال' },
    ['كيف تراقبون النظام وتعرفون إذا فيه مشكلة؟', 'How do you monitor the system and know when something is wrong?'],
    ['عندنا لوحة تحكم تورّينا إذا الردود شغالة زين وسريعة. وتورّينا كم مرة انقطع الرد، ولأي سبب. ونسجّل كل عملية إدارية. وإذا بلّغ مستخدم عن خطأ، يوصل بلاغه لمراجع المحتوى على شكل تذكرة.', 'We have a dashboard that shows whether replies are working well and fast. It also shows how often a reply was cut off, and why. We keep a record of every admin action. When a user reports an error, the report reaches the content reviewer as a ticket.']);

  Q('te11', { k: 'تطبيق جوال app mobile APK ios android' },
    ['هل لوصال تطبيق جوال؟', 'Does Wesal have a mobile app?'],
    ['لا، ما عندنا تطبيق جوال حالياً. وصال اليوم موقع يشتغل على الجوال والكمبيوتر من المتصفح. ما تحتاج تنزّل شي ولا تثبّت شي. وما أعلنّا عن تطبيق.', 'No, we do not have a mobile app right now. Today Wesal is a website. It works on phone and computer from the browser. You do not need to download or install anything. We have not announced an app.']);

  /* ---------------------------------------------------------------- 5 الموثوقية والمصادر */

  Q('r1', { com: 1, k: 'مصادر جهات هيئة رعاية وزارة sources entities' },
    ['من وين تجي معلومات وصال؟', 'Where does Wesal’s information come from?'],
    ['معلوماتنا من مصادر سعودية رسمية ننتقيها. وذكرنا في عرضنا أكثر من 40 مصدر رسمي وأكثر من 120 خدمة. ومن أمثلة الجهات: وزارة الموارد البشرية والتنمية الاجتماعية، وهيئة رعاية الأشخاص ذوي الإعاقة، ووزارة الصحة.', 'Our information comes from official Saudi sources that we select. Our presentation cites more than 40 official sources and more than 120 services. Example entities: the Ministry of Human Resources and Social Development, the Disability Care Authority and the Ministry of Health.'],
    ['وهذي أمثلة أخرى من الجهات:\n• صندوق تنمية الموارد البشرية «هدف»\n• وزارة التعليم\n• بنك التنمية الاجتماعية\n• مركز الملك سلمان لأبحاث الإعاقة', 'More example entities:\n• The Human Resources Development Fund (Hadaf)\n• The Ministry of Education\n• The Social Development Bank\n• The King Salman Center for Disability Research']);

  Q('r5', { hot: 6, com: 1, k: 'لا يوجد مصدر no source لا أعرف uncertain' },
    ['وش يصير لو ما فيه مصدر موثوق للإجابة؟', 'What happens when no reliable source exists for an answer?'],
    ['وصال تقول بوضوح إن ما فيه مصدر موثوق، وتوجّه المستخدم للجهة الرسمية بدل ما تخمّن. وإذا الجواب مو مبني على صفحة رسمية، ما تظهر تحته بطاقات مصدر. كذا المستخدم يعرف إن اللي قراه إرشاد عام.', 'Wesal says plainly that there is no reliable source, and points the user to the official entity instead of guessing. If an answer is not based on an official page, no source cards appear under it. That way, the user knows that what they read is general guidance.'],
    ['تعليمات نموذج الذكاء الاصطناعي عندنا تقول:\n• إذا المعلومة مو مؤكدة، قلها بصراحة ووجّه الشخص للجهة الرسمية\n• لا تخترع أرقام ولا مبالغ ولا نسب ولا روابط', 'The instructions for our AI model say:\n• If the information is not certain, say so frankly and direct the person to the official entity\n• Do not invent numbers, amounts, percentages or links']);

  Q('r2', { com: 1, k: 'مصدر الإجابة source card citation توثيق' },
    ['كيف يشوف المستخدم مصدر الإجابة؟', 'How does the user see the source of an answer?'],
    ['تحت كل جواب مبني على صفحات رسمية بطاقة لكل جهة، ثلاث على الأكثر. البطاقة فيها شعار الجهة واسمها وعنوان موقعها، وتفتح للمستخدم الصفحة نفسها اللي أخذنا منها المعلومة. والجواب اللي مو مبني على مصدر رسمي ما يظهر تحته شي.', 'Under every answer based on official pages, there is a card for each entity, three at most. Each card shows the entity’s logo, name and website address, and opens the exact page we took the information from. An answer not based on an official source shows nothing underneath.'],
    ['نموذج الذكاء الاصطناعي يكتب أرقام المصادر اللي استخدمها، بدون ما تظهر للمستخدم. والنظام يقبل منها بس اللي جابها هو بنفسه. فما تظهر جهة ما أخذنا منها شي، مهما كتب النموذج.', 'The AI model notes the numbers of the sources it used, without showing them to the user. The system accepts only the sources it fetched itself. So an entity we took nothing from never appears, whatever the model writes.']);

  Q('r4', { hard: 1 },
    ['من يراجع المحتوى الصحي ومن يتحمل مسؤولية دقته؟', 'Who reviews the health content and who is accountable for its accuracy?'],
    ['حسب عرضنا، المحتوى الصحي والمتعلق بالإعاقة يكتبه أو يراجعه طبيب مرخّص وصيدلي إكلينيكي قبل النشر. ونعيد فحصه دورياً. ومن الفريق: لمياء الشهراني للإشراف الطبي، وهند آل مفرح لتطوير المحتوى الطبي ومراجعته. ووصال ما تقدم تشخيص ولا قرار علاج.', 'According to our presentation, health and disability content is written or reviewed by a licensed physician and a clinical pharmacist before publication. We also re-check it periodically. On the team, Lamia Alshahrani supervises the medical side, and Hind Al Mufarrih develops and reviews the medical content. Wesal gives no diagnosis or treatment decision.']);

  Q('r3', {},
    ['كيف تبقون المعلومات محدّثة؟', 'How do you keep information up to date?'],
    ['نعيد فحص المحتوى دورياً، ونقارنه بالمصادر الرسمية المحدّثة. ونحدّث مصادرنا بإعادة تحميل الصفحات اللي تغيّرت، ويحل الجديد محل القديم. وملاحظات المستخدمين وبلاغات الأخطاء نحوّلها لتحسينات.', 'We re-check the content periodically against the updated official sources. We refresh our sources by loading the changed pages again, and the new version replaces the old one. User feedback and error reports turn into improvements.']);

  Q('r6', { k: 'بلاغ خطأ report error feedback مراجع reviewer' },
    ['كيف يبلّغ المستخدم عن خطأ وش يصير بعدها؟', 'How does a user report an error and what happens next?'],
    ['تحت كل جواب زر «أبلغ عن خطأ». البلاغ فيه نص الجواب ومصادره وروابطه، فالمراجع يعرف أي صفحة يراجع. وبعدها يصير تذكرة متابعة عند مراجع المحتوى، ويتابعها لين يصحح المعلومة ويقفلها. ونستفيد من ملاحظات المستخدم بعد كل تفاعل لتحسين الإجابات.', 'Under every answer there is a “Report an error” button. The report includes the answer text, its sources and links, so the reviewer knows which page to check. It then becomes a follow-up ticket with the content reviewer, who tracks it until the information is corrected and the ticket is closed. We also use user feedback after each interaction to improve answers.']);

  /* ---------------------------------------------------------------- 6 الأمان والخصوصية والأخلاقيات */

  Q('v1', { com: 1, k: 'بيانات احتفاظ حذف retention data collected سياسة الخصوصية' },
    ['وش البيانات اللي تجمعونها وكم تحتفظون فيها؟', 'What data do you collect and how long do you keep it?'],
    ['نجمع بيانات الحساب: الاسم والإيميل والجوال وتاريخ الميلاد. وبيانات اختيارية يضيفها المستخدم: المدينة ونوع الإعاقة والاهتمامات. ومحادثات المستخدم المسجّل ما نحفظها إلا إذا وافق، وبدون ربطها بحسابه. ونحتفظ بالبيانات ما دام الحساب موجود، وبعد حذفه نشيل بياناته الشخصية خلال 30 يوم على الأكثر.', 'We collect account data: name, email, phone and date of birth, plus optional data the user adds: city, disability type and interests. We save a signed-in user’s chats only if they agree, and never linked to their account. We keep data while the account exists, and after it is deleted we remove its personal data within 30 days at most.'],
    ['• نجمع كمان عنوان الاتصال بالإنترنت (IP) لحماية المنصة.\n• قائمة المحادثات المحفوظة تبقى في متصفح المستخدم نفسه، وما تظهر لمن يدخل بحساب ثاني على نفس الجهاز.\n• المستخدم ينزّل نسخة من بياناته، ويمسح محادثاته، ويحذف حسابه بنفسه.\n• المحادثات تبقى في الإحصاءات بدون أي شي يدل على صاحبها، والزائر بدون حساب مجهول أصلاً.', '• We also collect the internet address (IP) to protect the platform.\n• The saved chat list stays in the user’s own browser and does not show for someone signing in with another account on the same device.\n• Users can download a copy of their data, clear their chats and delete their account themselves.\n• Chats stay in the statistics with nothing that identifies their owner, and a visitor without an account is anonymous to begin with.']);

  Q('v3', { hot: 7, com: 1, k: 'تشخيص طبي استشارة diagnosis medical legal advice' },
    ['هل تقدم وصال تشخيص أو استشارة طبية أو قانونية؟', 'Does Wesal give diagnosis or medical or legal advice?'],
    ['لا. منصة وصال ما تقدّم تشخيص ولا استشارة طبية أو قانونية. هي تساعد المستخدم يفهم حقوقه ومستحقاته، ويعرف كيف يوصل للخدمات. وفي القرارات الطبية والقانونية توجّهه لمختص مرخّص. وشروط الاستخدام تنص إن الإجابات ما تغني عن استشارة المختص.', 'No. Wesal does not give a diagnosis, or medical or legal advice. It helps users understand their rights and what they are entitled to, and how to reach services. For medical and legal decisions, it sends them to a licensed professional. The terms of use say that the answers cannot replace advice from a specialist.']);

  Q('v5', { com: 1, hard: 1 },
    ['هل تلتزمون بنظام حماية البيانات الشخصية وضوابط سدايا؟', 'Do you comply with the Personal Data Protection Law and SDAIA controls?'],
    ['بنينا المنصة على مبادئ حماية البيانات: نجمع أقل بيانات ممكنة، ونحدد مدة الاحتفاظ بها، والمستخدم ينزّل بياناته ويعدّلها ويحذفها. وما نبيع البيانات ولا نشاركها مع معلنين، وسياسة الخصوصية عندنا معلنة. أما التقييم الرسمي للامتثال فخطوة نخطط لها قبل التوسع.', 'We built the platform on data protection principles: we collect the minimum data, we set how long we keep it, and users can download, edit and delete their data. We do not sell data or share it with advertisers, and our privacy policy is public. A formal compliance assessment is a step we plan before expanding.']);

  Q('v7', { com: 1, hard: 1 },
    ['من المسؤول لو الإجابة غلط؟', 'Who is responsible if an answer is wrong?'],
    ['الإجابات إرشادية، والمرجع النهائي هو الجهة الرسمية. وهذا مكتوب في شروط الاستخدام. وما نتحمل مسؤولية قرار يتخذه الشخص بالاعتماد على المساعد وحده. لكن نبذل جهدنا للدقة: المصادر ظاهرة للمستخدم، ونستقبل بلاغات الخطأ، ونراجع المحتوى.', 'The answers are guidance, and the final reference is the official entity. This is written in the terms of use. We are not responsible for a decision someone makes relying on the assistant alone. But we do our best to be accurate: sources are shown to the user, we receive error reports, and we review the content.']);

  Q('v2', { k: 'حماية security تشفير كلمات المرور RBAC session' },
    ['كيف تحمون بيانات الإعاقة والبيانات الحساسة؟', 'How do you protect disability and other sensitive data?'],
    ['بيانات ملفك، ومنها نوع الإعاقة، ما تظهر لأي مستخدم ثاني. والاتصال بالموقع محمي، وكلمة المرور ما تنحفظ نفسها. وكل شخص يشوف بس اللي يخص دوره، والنظام هو اللي يفرض هذا. وإذا ما استخدمت حسابك، يخرجك تلقائياً: بعد 30 دقيقة للمستخدم، و15 دقيقة لحسابات الفريق.', 'Your profile data, including disability type, is never shown to any other user. The connection to the site is protected, and your password is never stored as it is. Each person sees only what belongs to their role, and the system enforces this. If you stop using your account, you are signed out automatically: after 30 minutes for users and 15 minutes for team accounts.'],
    ['• قاعدة البيانات محمية من الأوامر الضارة، وجلسة الدخول محمية أيضاً.\n• نحتفظ بسجل يوثّق الدخول وتغيير الصلاحيات وتصدير البيانات.\n• عند كل خروج نمسح من المتصفح اللي يخص الحساب.\n• نفحص الصور المرفوعة ونمنع أي كود برمجي فيها.', '• The database is protected from harmful commands, and the sign-in session is protected too.\n• We keep a log of sign-ins, permission changes and data exports.\n• Every time someone signs out, we clear the account\'s data from the browser.\n• We check uploaded images and block any code inside them.']);

  Q('v4', {},
    ['كيف تتعاملون مع الحالات الحساسة أو الخطيرة؟', 'How do you handle sensitive or high-risk cases?'],
    ['في الحالات الحساسة توجّه منصة وصال المستخدم للجهة الرسمية المعنية أو لمختص مؤهل. ما تتولى الأمر بنفسها، لأنها ما تحل محل قنوات الدعم الرسمية. وتذكّر المستخدم يراجع مختص مرخّص قبل أي قرار طبي.', 'In sensitive cases, Wesal sends the user to the relevant official entity or a qualified specialist. It does not handle the matter itself, because it does not replace official support channels. It also reminds the user to see a licensed professional before any medical decision.']);

  Q('v6', { k: 'بيع بيانات إعلانات مزود النموذج data sale ads provider' },
    ['هل تبيعون البيانات؟ وش اللي يُرسل لمزوّد الذكاء الاصطناعي؟', 'Do you sell data? And what is sent to the AI provider?'],
    ['لا، ما نبيع البيانات ولا نشاركها مع معلنين. وما فيه إعلانات في وصال. عشان يكتب المساعد الجواب، نرسل لمزوّد الذكاء الاصطناعي نص السؤال وآخر رسائل المحادثة. وما نرسل الاسم ولا الإيميل ولا بيانات الملف الشخصي. وسياسة الخصوصية تنص على ذلك.', 'No, we do not sell data or share it with advertisers. There are also no ads in Wesal. To write an answer, we send the AI provider the question text and the most recent chat messages. We do not send the name, email or profile data. The privacy policy says this.']);

  Q('v8', { k: 'تحيز bias لغة محترمة respectful language' },
    ['كيف تتجنبون التحيز واللغة غير المحترمة؟', 'How do you avoid bias and disrespectful language?'],
    ['تعليمات المساعد تلزمه يخاطب الشخص باحترام وبصيغة «الشخص ذو الإعاقة». وتمنع ألفاظ مثل «معاق» و«عاجز». وتطلب منه يعامل الشخص كإنسان له حقوق وخيارات، مو كحالة. والتصميم يقوده تخصص علم النفس وتخصص الهندسة الطبية الحيوية. ونختبر الميزات مع مستخدمين وجمعيات شريكة قبل التوسع.', 'The assistant\'s instructions require it to speak to the person with respect and say “a person with a disability”. They forbid words like “handicapped” and “helpless”. They also ask it to treat the person as a human with rights and choices, not as a case. The design is led by psychology expertise and biomedical engineering expertise. We test features with users and partner associations before we expand.']);

  /* ---------------------------------------------------------------- 7 الإتاحة وتجربة المستخدم */

  Q('a1', { com: 1, k: 'WCAG معايير الإتاحة الوصول standards accessibility كود المنصات Platforms Code' },
    ['وش معايير الإتاحة اللي تمشون عليها؟', 'Which accessibility standards do you follow?'],
    ['نمشي على معايير WCAG 2.2 الدولية، وهي تخلّي المواقع سهلة الاستخدام لذوي الإعاقة. والجهات الحكومية تعتمد عليها في منصاتها. وكل تعديل على الموقع نفحصه بقائمة: لوحة المفاتيح وحدها، وقارئ الشاشة، والتكبير حتى 200%. وفيه صوت سعودي ووضع مبسّط بجمل قصيرة.', 'We follow WCAG 2.2, the international accessibility standards. They make websites easier to use for people with disabilities. Government entities rely on them for their platforms. Every change we make to the site is checked against a list: it must work with the keyboard alone, with a screen reader, and with zoom up to 200%. The platform also has a Saudi voice and a simple mode with short sentences.'],
    ['• نفحص كمان التباين العالي والوضع الداكن وتقليل الحركة.\n• وكل ميزة نختبرها مع مستخدمين من ذوي الإعاقة وجمعيات شريكة قبل ما نوسّع.', '• We also check high contrast, dark mode and reduced motion.\n• And we test every feature with users with disabilities and partner associations before we expand.']);

  Q('a4', { com: 1 },
    ['كيف شارك الأشخاص ذوو الإعاقة في التصميم؟', 'How did people with disabilities take part in the design?'],
    ['شاركوا بالاختبار: نختبر كل ميزة مع مستخدمين من ذوي الإعاقة وجمعيات شريكة قبل ما نوسّع، وأكثر من 200 مستخدم جرّبوا النسخة التجريبية. وتصميم التجربة والهوية قاده تخصص علم النفس، مع مراعاة اختلاف الإعاقات وراحة المستخدم. ومعه تخصص الهندسة الطبية الحيوية للتقنيات المساعدة.', 'They took part through testing: we test every feature with users with disabilities and partner associations before we expand, and more than 200 users tried the beta. The design of the experience and identity was led by psychology expertise, with attention to different disabilities and user comfort, together with biomedical engineering expertise for assistive technology.']);

  Q('a2', { k: 'إعدادات الوصول حجم الخط تباين dark mode font size contrast' },
    ['وش إعدادات الوصول السريعة؟', 'What quick accessibility settings are available?'],
    ['زر واحد في الشريط العلوي يفتح لوحة «إعدادات سريعة». فيها: حجم الخط، والتباين العالي، والوضع الداكن، وتقليل الحركة. وفيها رابط لصفحة الإعدادات الكاملة. والموقع يتبع إعدادات الجهاز أول. وإذا اختار المستخدم شي، يتقدم اختياره على إعدادات الجهاز ويبقى محفوظ.', 'One button in the top bar opens a “quick settings” panel. It has font size, high contrast, dark mode and reduced motion. It also has a link to the full settings page. The site follows the device settings first. If the user chooses something, that choice comes first and is saved.'],
    ['• ما فيه زر يطفو على الصفحة ويغطي المحتوى.\n• اللوحة تجي مباشرة بعد زرها في ترتيب القراءة.\n• وزر Esc يقفلها ويرجّعك للزر اللي فتحتها منه.', '• There is no floating button that covers the content.\n• The panel comes right after its button in the reading order.\n• The Escape key closes it and takes you back to the button you opened it from.']);

  Q('a3', {},
    ['كيف تشتغل وصال مع قارئات الشاشة ولوحة المفاتيح؟', 'How does Wesal work with screen readers and the keyboard?'],
    ['تقدر تتنقل في الموقع كله بلوحة المفاتيح، من أول عنصر لآخر عنصر. والعناصر اللي تنفتح وتنطوي تخبر قارئ الشاشة بحالتها. والعنوان فيه نص متحرك، بس نصه كامل موجود في الصفحة، فقارئ الشاشة يقراه كامل. وقبل الخروج التلقائي تطلع رسالة تنبيه.', 'You can move through the whole site with the keyboard, from the first element to the last. Elements that open and close tell a screen reader their state. The heading has moving text, but its full text is in the page, so a screen reader reads it whole. Before automatic sign-out, a warning message appears.'],
    ['• الرسالة تطلع قبل الخروج التلقائي بدقيقتين، ونصها «هل ما زلت هنا؟».\n• وأي حركة منك تمدّد الجلسة.\n• وهذا حسب معيار «التوقيت القابل للتعديل» في معايير الإتاحة الدولية WCAG (البند 2.2.1).', '• The message appears two minutes before automatic sign-out. It says, “Are you still there?”\n• Any activity from you extends the session.\n• This follows the WCAG criterion “Timing Adjustable” (2.2.1), part of the international accessibility standards.']);

  Q('a5', { k: 'كتابة قراءة صعوبة voice simple mode مبسط' },
    ['وش تقدمون لمن يصعب عليه الكتابة أو القراءة؟', 'What do you offer people who find typing or reading difficult?'],
    ['من يصعب عليه الكتابة يسأل بصوته، ومن يصعب عليه القراءة يسمع الإجابة بصوت سعودي. وفيه وضع مبسّط بجمل قصيرة جداً. ويقدر يكبّر الخط ويرفع التباين. وزر Esc يوقف الرد أو قراءته. وهذا يخدم بالذات ذوي الإعاقة الحركية والبصرية.', 'Someone who finds typing hard can ask by voice. Someone who finds reading hard can listen to the answer in a Saudi voice. There is also a simple mode with very short sentences. They can make the text bigger and raise the contrast. The Escape key stops a reply, or stops it being read aloud. This helps people with mobility and visual disabilities in particular.']);

  /* ---------------------------------------------------------------- 8 الأعمال ونموذج الإيرادات */

  Q('b1', { com: 1, k: 'من يدفع payers B2B B2G B2C customers عملاء' },
    ['من اللي يدفع مقابل وصال؟', 'Who pays for Wesal?'],
    ['ست فئات تدفع لوصال، مو الأفراد بس: الأشخاص ذوو الإعاقة وأسرهم، ومراكز التأهيل ومدارس ذوي الاحتياجات الخاصة، والجهات الحكومية والتعليمية. وكمان الشركات ضمن مسؤوليتها الاجتماعية، والجمعيات الداعمة باشتراكات جماعية، ومطورو المنصات اللي يربطون منصاتهم بوصال.', 'Six groups pay for Wesal, not just individuals: people with disabilities and their families, rehabilitation centers and special needs schools, and government and education entities. Also companies, as part of their social responsibility, supporting associations through group subscriptions, and platform developers who connect their platforms to Wesal.']);

  Q('b2', { com: 1 },
    ['وش الباقات والأسعار؟', 'What are the plans and prices?'],
    ['عندنا أربع باقات: الأساسية 29 ريال في الشهر، والاحترافية 79، والمتقدمة 179، وباقة المؤسسات تبدأ من 479 ريال في الشهر. الأساسية فيها بحث ذكي مبسّط، ودعم قارئات الشاشة، وتحويل النص لصوت، وحفظ آخر محادثة. والاحترافية تضيف مساعد صوتي تتحاور معه، وحفظ غير محدود للمحادثات، وتوصيات حسب نوع الإعاقة.', 'We have four plans: Basic at SAR 29 a month, Professional at SAR 79, Advanced at SAR 179, and the enterprise plan from SAR 479 a month. Basic has simple smart search, screen reader support, text to speech and saving your last chat. Professional adds a voice assistant you can talk with, unlimited saved chats, and recommendations by disability type.'],
    ['• المتقدمة تضيف: تحليل المستندات وتلخيصها، ودعم بأولوية، وأدوات للمختصين ومقدمي الرعاية.\n• باقة المؤسسات فيها: حسابات متعددة، ولوحة تحكم للإدارة، ومصادر معلومات مخصصة، ومدير حساب مخصص لها.', '• Advanced adds document analysis and summaries, priority support, and tools for specialists and caregivers.\n• Institutions get multiple accounts, an admin dashboard, custom information sources and a dedicated account manager.']);

  Q('b4', { hot: 8, com: 1, k: 'تعادل break-even unit economics تكلفة المستخدم margin' },
    ['كم تكلفة المستخدم ومتى تتعادلون؟', 'What is the cost per user and when do you break even?'],
    ['تكلفة المستخدم اليوم حوالي 15 ريال، وتنزل إلى 3 ريال لما نكبر. ونقدّر نتعادل خلال 12 إلى 18 شهر بعد الإطلاق، يعني إيراداتنا تغطي تكاليفنا. وهذا يصير لما نوصل 1,000 مستخدم نشط، أو نتعاقد مع 3 جهات حكومية أو مؤسسية. والأهم اشتراكات المؤسسات: ربحها عالي، وتغطي تكلفة السحابة والذكاء الاصطناعي.', 'The cost per user is about SAR 15 today, and it falls to SAR 3 as we grow. We estimate break-even 12 to 18 months after launch, which means our income covers our costs. This happens when we reach 1,000 active users or sign 3 government or institutional contracts. The most important factor is institution subscriptions: their margin is high, and they cover our cloud and AI costs.'],
    ['ليش تنزل التكلفة؟ لأن منصة وصال تشتغل على السحابة، يعني خوادم على الإنترنت، مو على معدات نملكها. فتكرار الخدمة في أماكن جديدة يصير رخيص كل ما توسعنا.', 'Why does the cost fall? Because Wesal runs in the cloud, meaning on servers online, not on equipment we own. So repeating the service in new places gets cheaper as we grow.']);

  Q('b6', { com: 1 },
    ['من المنافسون وش ميزتكم؟', 'Who are your competitors and what is your advantage?'],
    ['البدائل اليوم: البوابات الحكومية، ومواقع الطيران والمطارات، والبحث العام، وسؤال الناس. وميزتنا: منصة واحدة مخصصة لذوي الإعاقة بدل جهات متفرقة. سهولة الوصول مبنية فيها من الأساس. وهي مبنية للسياق السعودي والعربي. وإجاباتها مرتبطة بمصدرها، وتقدر تنفذها.', 'The alternatives today are government portals, airline and airport sites, general search, and asking other people. Our advantage: one platform for people with disabilities, instead of separate bodies. Accessibility is built in from the start. It is built for the Saudi and Arab context. Its answers are tied to their source, and you can act on them.']);

  Q('b7', { hard: 1 },
    ['ليش يدفع شخص من ذوي الإعاقة والمعلومات الحكومية مجانية؟', 'Why would a person with a disability pay when government information is free?'],
    ['صحيح، قراءة المعلومة الحكومية مجانية. بس ثمنها وقت وجهد واعتماد على الناس. منصة وصال تقدم لك إرشاد في مكان واحد، ميسّر ومناسب لحالتك. وما لازم الفرد يدفع دايم: الجمعيات والمدارس والجهات والشركات تدعم اشتراكات جماعية. وأقل باقة 29 ريال في الشهر.', 'True, reading government information is free. But it costs you time, effort and dependence on other people. The Wesal platform gives you guidance in one place that is accessible and fits your situation. Individuals do not always have to pay: associations, schools, entities and companies fund group subscriptions. The lowest plan is SAR 29 a month.'],
    ['النسخة التجريبية مجانية الحين. تقدر تجرّب فوراً بدون حساب، بعدد أسئلة محدود. وبعد التسجيل تحصل على رصيد أكبر، يتجدد تلقائياً كل 6 ساعات.', 'The trial version is free right now. You can try it at once without an account, with a limited number of questions. After you register, you get a bigger credit that renews by itself every 6 hours.']);

  Q('b10', { hard: 1, k: 'شراكات عملاء traction partners customers عقود' },
    ['هل عندكم شراكات أو عملاء الحين؟', 'Do you have partnerships or customers now?'],
    ['للحين عندنا مستخدمين، مو عملاء. أكثر من 200 مستخدم جرّبوا النسخة التجريبية. أما الشراكات، فهدفنا في السنة الأولى شراكة مع 3 جمعيات. وخطتنا نبدأ بإطلاق تجريبي في 3 مراكز تأهيل ومدارس لذوي الاحتياجات الخاصة في الرياض.', 'So far we have users, not customers. More than 200 users have tried the trial version. For partnerships, our goal in year one is 3 associations. We plan to start with a trial launch. It will be in 3 rehabilitation centers and special needs schools in Riyadh.']);

  Q('b3', {},
    ['وش مصادر الإيرادات المتوقعة؟', 'What are the expected revenue sources?'],
    ['نتوقع الدخل في أول 6 أشهر من السنة الأولى من أربعة مصادر: اشتراكات المدارس والجمعيات، واشتراكات الأفراد، والرعايات والشراكات، وخدمات مخصصة للجهات. وعلى المدى الأطول يكبر الدخل من اشتراكات الجهات الحكومية والمؤسسات.', 'In the first 6 months of year one we expect income from four sources: school and association subscriptions, individual subscriptions, sponsorships and partnerships, and custom services for entities. In the longer term, income grows from subscriptions by government entities and institutions.'],
    ['وعلى المدى الأطول:\n• اشتراكات سنوية للجهات الحكومية والمؤسسات.\n• اشتراكات جماعية تدعمها الجمعيات.\n• اشتراكات أفراد بأسعار مناسبة.\n• حلول لمراكز الرعاية.', 'In the longer term:\n• Annual subscriptions for government entities and institutions.\n• Group subscriptions supported by associations.\n• Affordable individual subscriptions.\n• Solutions for care centers.']);

  Q('b5', {},
    ['وش الميزانية المطلوبة وكيف توزّع؟', 'What budget is needed and how is it split?'],
    ['نحتاج 36 ألف ريال في المجموع. أكبر البنود: الذكاء الاصطناعي 10,000، والاستضافة على السحابة 8,000. بعدهم التسويق والإطلاق 5,000، واحتياطي للطوارئ 5,000. وأخيراً تصميم واجهة الموقع 3,000، واختبار سهولة الاستخدام 3,000، وأدوات الموقع 2,000.', 'We need SAR 36,000 in total. The biggest items are AI technologies at 10,000 and cloud hosting at 8,000. Next are marketing and launch at 5,000 and an emergency reserve at 5,000. The rest are interface design at 3,000, usability testing at 3,000 and website tools at 2,000.']);

  Q('b8', { k: 'go to market خطة الدخول pilot تجريبي' },
    ['وش خطتكم للوصول للمستخدمين؟', 'What is your plan to reach users?'],
    ['نبدأ من الرياض ونتوسع على مراحل. المرحلة الأولى: إطلاق في 3 مراكز تأهيل ومدارس لذوي الاحتياجات الخاصة في الرياض. بعدها نتوسع في المنطقة الوسطى والغربية والشرقية. ثم المملكة كلها، ثم دول الخليج. وهدف السنة الأولى: 1,000 مستخدم مسجل وشراكة مع 3 جمعيات.', 'We start in Riyadh and grow in phases. Phase one: launch in 3 rehabilitation centers and special needs schools in Riyadh. Then we expand across the Central, Western and Eastern regions. After that, the whole Kingdom, and then the Gulf. The goal for year one is 1,000 registered users and partnerships with 3 associations.'],
    ['• في المرحلة الأولى يكون معنا 200 مستخدم تجريبي. نتأكد معهم من دقة الإجابات، ونضبط الأداء.\n• وهذي المرحلة بدعم من تمويل الحاضنات والمسرّعات.', '• In phase one we have 200 pilot users. With them we check how accurate the answers are, and we tune performance.\n• This phase is backed by funding from incubators and accelerators.']);

  Q('b9', {},
    ['كيف تموّلون المرحلة الأولى؟', 'How will you fund the first phase?'],
    ['المرحلة الأولى نموّلها من الحاضنات والمسرّعات (برامج تدعم المشاريع الناشئة)، ومن المنح وجوائز الابتكار، ومن رعايات الشركات ضمن مسؤوليتها الاجتماعية. ومنها تمويل تجريبي مبكر من قطاعي الاتصالات والبنوك. وبعدها يصير دخلنا من الاشتراكات.', 'We fund the first phase from incubators and accelerators (programs that support startups), from grants and innovation awards, and from company sponsorships as part of their social responsibility. That includes early pilot funding from the telecom and banking sectors. After that, our income comes from subscriptions.'],
    ['الاشتراكات اللي نتوقعها بعدها:\n• سنوية للجهات الحكومية والمؤسسات.\n• جماعية عن طريق الجمعيات.\n• أفراد بأسعار مناسبة.\n• حلول لمراكز الرعاية.', 'The subscriptions we expect after that:\n• Annual ones for government entities and institutions.\n• Group ones through associations.\n• Affordable individual ones.\n• Solutions for care centers.']);

  /* ---------------------------------------------------------------- 9 الأثر والنتائج والتحقق */

  Q('i1', { hot: 9, com: 1, k: 'نتائج results beta 200 users 92% 95% TRL' },
    ['وش نتائج النسخة التجريبية؟', 'What results did the beta achieve?'],
    ['النتيجة: 92% من المستخدمين خلّصوا مهامهم. ووصلوا للي يحتاجونه أسرع بـ95% من البحث بأنفسهم. اختبرنا نسخة تجريبية شغالة فعلاً مع أكثر من 200 مستخدم من ذوي الإعاقة، بمهام حقيقية. وكل إجابة نقدر نرجعها لمصدرها.', 'The result: 92% of the users finished their tasks. They found what they needed 95% faster than searching on their own. We tested a working beta with more than 200 users with disabilities, using real tasks. And we can trace every answer back to its source.'],
    ['قسنا أربعة أشياء:\n• هل المنصة مناسبة للأشخاص ذوي الإعاقة وما فيها عوائق؟\n• هل الجواب يناسب السؤال؟\n• هل نقدر نرجع الجواب لمصدره؟\n• هل استخدامها سهل؟', 'We measured four things:\n• Can people with disabilities use the platform without barriers?\n• Does the answer fit the question?\n• Can we trace the answer back to its source?\n• Is it easy to use?']);

  Q('i3', { com: 1, k: 'أهداف السنة الأولى targets KPI year one مؤشرات' },
    ['وش أهدافكم للسنة الأولى؟', 'What are your year-one targets?'],
    ['أهدافنا للسنة الأولى: 1,000 مستخدم مسجل، وشراكة مع 3 جمعيات. ونبقي نسبة نجاح المهام عند 90% أو أعلى طوال 12 شهر. ونبي 100% من الإجابات معها مصدرها. ونبي رضا المستخدمين 4.5 من 5 أو أعلى. ونستهدف تغطية الإعاقة البصرية والسمعية والحركية والإدراكية.', 'Our targets for the first year: 1,000 registered users and partnerships with 3 associations. We keep task success at 90% or higher for 12 months. We want 100% of answers to come with a source. We want user satisfaction at 4.5 out of 5 or higher. And we aim to cover visual, hearing, mobility and cognitive disabilities.'],
    ['• اللي وصلنا له في الاختبار: 92% مع أكثر من 200 مستخدم.\n• اللي نستهدفه: نحافظ على 90% أو أعلى مع 1,000 مستخدم نشط.', '• What we reached in testing: 92% with more than 200 users.\n• What we aim for: keeping 90% or higher with 1,000 active users.']);

  Q('i7', { com: 1, hard: 1 },
    ['من هم الـ200 مستخدم وكيف اخترتوهم؟', 'Who were the 200 users and how did you choose them?'],
    ['هم مستخدمون من ذوي الإعاقة، جرّبوا نسخة شغالة بمهام حقيقية. قسنا إذا المنصة مناسبة لهم وما فيها عوائق، وإذا الجواب يناسب سؤالهم. وقسنا إذا نقدر نرجع الجواب لمصدره، وإذا استخدامها سهل. ونعيد نفس طريقة الاختبار في القياس الجاي، عشان نقارن الأثر.', 'They are users with disabilities who tried a working version on real tasks. We checked whether the platform suits them and has no barriers, and whether the answer fits their question. We also checked whether we can trace each answer to its source, and whether it is easy to use. We repeat the same test method in the next measurement, so we can compare the impact.']);

  Q('i2', {},
    ['كيف قستوا هذي النتائج؟', 'How did you measure these results?'],
    ['قسنا بثلاث طرق: اختبار نعطي فيه المستخدم مهام ينفذها، واستبيان داخل المنصة بعد كل جلسة، وأرقام استخدام المنصة: عدد الجلسات، ونسبة الإجابات اللي معها مصدر، والوقت اللي نوفّره مقارنة بالبحث بأنفسهم. ونعيد نفس الطريقة في القياس الجاي، ونراجعها كل ثلاثة شهور مع الجمعيات الشريكة.', 'We measured in three ways: a test where users carry out tasks, a survey inside the platform after each session, and usage numbers: the number of sessions, the share of answers that come with a source, and the time we save compared with searching on their own. We repeat the same method in the next round and review it every three months with partner associations.']);

  Q('i4', { k: 'أثر impact scale اجتماعي اقتصادي' },
    ['وش الأثر المتوقع على نطاق أوسع؟', 'What impact do you expect at scale?'],
    ['نتوقع أثر على حوالي 1.35 مليون شخص من ذوي الإعاقة: تصير الخدمات الرقمية مصمّمة لهم من البداية، بدل ما تكون عائق. يعني استقلالية أكثر وجودة حياة أعلى، واعتماد أقل على مقدمي الرعاية في المعلومات الأساسية، ووصول أسهل للتعليم والتوظيف. وللمملكة، مثال وطني لذكاء اصطناعي عربي يشمل الجميع.', 'We expect an impact on about 1.35 million people with disabilities: digital services become designed for them from the start instead of being a barrier. That means more independence and a better quality of life, less dependence on caregivers for basic information, and easier access to education and jobs. For the Kingdom, it is a national example of an inclusive Arabic AI.']);

  Q('i5', { k: 'رؤية 2030 vision 2030 alignment' },
    ['كيف تتصل وصال برؤية 2030؟', 'How does Wesal connect to Vision 2030?'],
    ['منصة وصال تدعم التزام الرؤية بالمشاركة الكاملة للأشخاص ذوي الإعاقة. وتمشي مع معايير سهولة الوصول الرقمي، واسمها WCAG 2.2. وتمشي أيضاً مع دليل الشمولية الرقمية في المملكة. وفي 2030 نخطط نقيس الأثر الاجتماعي والاقتصادي. ونخطط نخلي مؤشراتنا متوافقة مع الرؤية.', 'The Wesal platform supports the commitment of the Vision to the full participation of people with disabilities. It is in line with WCAG 2.2, the standards for digital accessibility. It is also in line with the digital inclusion guide of the Kingdom. In 2030 we plan to measure the social and economic impact. We also plan to bring our indicators in line with the Vision.']);

  Q('i6', {},
    ['وش بتقيسون بعد الإطلاق؟', 'What will you measure after launch?'],
    ['بعد الإطلاق نقيس أربعة أشياء: نسبة اللي يخلّصون مهامهم، ونسبة الإجابات اللي معها مصدر، والوقت اللي نوفّره عليهم، ورضاهم. ونراجعها كل ثلاثة شهور مع الجمعيات الشريكة. وللجهات اللي تقدم الخدمات نقيس العوائق اللي تواجه المستخدم، ونعطيها مؤشرات ترفع جاهزية خدماتها.', 'After launch we measure four things: the share of users who finish their tasks, the share of answers that come with a source, the time we save them, and their satisfaction. We review them every three months with partner associations. For the entities that provide services, we measure the barriers users face and give them indicators that raise the readiness of their services.']);

  /* ---------------------------------------------------------------- 10 الاستدامة والتوسع والفريق */

  Q('f1', { com: 1, k: 'خطة زمنية roadmap timeline 2026 2030' },
    ['وش خطتكم من 2026 إلى 2030؟', 'What is your plan from 2026 to 2030?'],
    ['خطتنا لخمس سنوات. 2026: نبني النسخة الأولية ونجرّبها مع عينة من ذوي الإعاقة. 2027: إطلاق رسمي، وربط مباشر مع أول جهتين أو ثلاث. 2028: ربط مع الجهات الحكومية والخاصة، ونتوسع في الصحة والتعليم والنقل والعمل. 2029: فئات جديدة وأدوات تسهّل الوصول الرقمي. 2030: هدفنا نصير منصة وطنية معتمدة.', 'Our plan covers five years. 2026: we build the first version and test it with a sample of people with disabilities. 2027: official launch, with direct links to the first two or three entities. 2028: links with government and private entities, expanding into health, education, transport and work. 2029: new groups and tools that make digital access easier. 2030: our goal is to become an approved national platform.'],
    ['• 2026: نبدأ أولى الشراكات مع الجمعيات أيضاً.\n• 2027: نبدأ بالخدمات الأهم.\n• 2029: نستخدم الذكاء الاصطناعي لتوقع الاحتياجات.\n• 2030: منصة للتمكين الرقمي وقياس الأثر.', '• 2026: we also start the first partnerships with associations.\n• 2027: we start with the most important services.\n• 2029: we use AI to anticipate needs.\n• 2030: a platform for digital empowerment and impact measurement.']);

  Q('f2', { com: 1, k: 'فريق team members' },
    ['من فريق وصال وش دور كل واحد؟', 'Who is on the Wesal team and what does each person do?'],
    ['فريقنا خمسة أعضاء، ولكل عضو تخصص ودور. لمياء الشهراني، طب وجراحة: قيادة الفريق والإشراف الطبي. هند يحيى آل مفرح، صيدلة: تطوير المحتوى الطبي ومراجعته. رنيم سعيد النجيمي، أخصائي نفسي: تصميم تجربة الاستخدام والهوية. أحمد سامي، مهندس ذكاء اصطناعي: نموذج الذكاء الاصطناعي والمنصة. رغد محمد العسيري، هندسة طبية حيوية: التقنيات المساعدة وسهولة الوصول.', 'Our team has five members, and each member has a field and a role. Lamia Alshahrani, medicine and surgery: leads the team and the medical supervision. Hind Yahya Al Mufarrih, pharmacy: develops and reviews the medical content. Raneem Saeed Alnujaymi, psychologist: designs the user experience and the identity. Ahmad Sami, AI engineer: the AI model and the platform. Raghad Mohammed Alasiri, biomedical engineering: assistive technology and accessibility.']);

  Q('f6', { com: 1, hard: 1 },
    ['وش أكبر المخاطر اللي تواجهكم؟', 'What are the biggest risks you face?'],
    ['خمسة مخاطر، ولكل واحد علاج. معلومة قديمة أو غير دقيقة: نظهر المصدر ونراجع ونستقبل بلاغات الخطأ. الاعتماد على مزوّدي النماذج: نبدّل بينهم. تكلفة التشغيل لما نتوسع: نحط سقوف للاستخدام. حساسية البيانات: نحدد من يطلع عليها ونجمع أقل بيانات. وتركيزنا على فئات محدودة اليوم: نوسّع تدريجياً بعد الاختبار.', 'Five risks, each with a remedy. Outdated or inaccurate information: we show the source, review, and take error reports. Dependence on model providers: we can switch between them. Running costs as we grow: we set usage limits. Data sensitivity: we control who can see it and collect the minimum. Serving limited groups today: we expand step by step after testing.']);

  Q('f7', { com: 1, hard: 1, k: 'ضعف نقاط الضعف weaknesses limitations limits' },
    ['وش نقاط ضعف وصال اليوم؟', 'What are Wesal’s weaknesses today?'],
    ['وصال اليوم نسخة تجريبية، مو منتج نهائي. وهذي حدودها. تخدم الإعاقة الحركية والبصرية بس لين الحين. ومعلوماتها محدودة بالمصادر الرسمية اللي اخترناها. والمحادثات المحفوظة تبقى في متصفح واحد، وما تنتقل بين الأجهزة. وما تقدم تشخيص، ولا تحل محل القنوات الرسمية. نقولها بوضوح، لأن الثقة في مجالنا مبنية على الصدق.', 'Wesal today is a beta, not a final product. Here are its limits. It serves mobility and visual disabilities only so far. Its information is limited to the official sources we chose. Saved chats stay in one browser and do not move between devices. It gives no diagnosis, and it does not replace official channels. We say this clearly because trust in our field is built on honesty.']);

  Q('f3', {},
    ['ليش هذا الفريق مناسب لهذي المشكلة؟', 'Why is this team the right one for this problem?'],
    ['لأن المشكلة تجمع الطب والصيدلة وعلم النفس والذكاء الاصطناعي والتقنيات المساعدة، وفي فريقنا متخصص في كل واحد منها. ويجمعنا هدف واحد: نستخدم التقنية عشان نصنع أثر يدوم لذوي الإعاقة.', 'Because the problem brings together medicine, pharmacy, psychology, artificial intelligence and assistive technology, and our team has a specialist in each one. One goal unites us: using technology to create lasting impact for people with disabilities.']);

  Q('f4', { k: 'خليج توسع expansion GCC' },
    ['كيف تتوسعون للخليج وما بعده؟', 'How will you expand to the Gulf and beyond?'],
    ['نتوسع بالترتيب: السعودية، ثم الخليج، ثم العالم. نظامنا يشتغل عبر الإنترنت، فنقدر نخصص المحتوى والخدمات لكل دولة بسهولة. وكل ما زاد عدد المستخدمين تنزل تكلفة المستخدم من 15 إلى 3 ريال. والتحدي الرئيسي: نحسّن تكلفة التشغيل وتكلفة الخدمات التقنية اللي نعتمد عليها.', 'We expand in order: Saudi Arabia, then the Gulf, then the world. Our system runs over the internet, so we can adapt the content and services for each country easily. As users grow, the cost per user falls from SAR 15 to SAR 3. The main challenge: improving our running costs and the cost of the technical services we depend on.']);

  Q('f5', { k: 'استدامة sustainability' },
    ['كيف تستمر وصال وتستدام؟', 'How does Wesal sustain itself?'],
    ['تستمر وصال بثلاثة أشياء: المعرفة، والأثر، والنمو. المعرفة: نحدّث مصادرنا لما تتغير الأنظمة والخدمات، ونحوّل ملاحظات المستخدمين لمعلومات. الأثر: نقيس العوائق ونعطي الجهات مؤشرات. النمو: شراكات مع الجهات اللي تقدم الخدمات، وحلول تقيس سهولة وصول خدماتها، ونكبر مع كبر الخدمات والمستخدمين.', 'Wesal keeps going through three things: knowledge, impact and growth. Knowledge: we update our sources when regulations and services change, and turn user feedback into information. Impact: we measure barriers and give entities indicators. Growth: partnerships with service providers, solutions that measure how accessible their services are, and growing as services and users grow.']);

  Q('f8', {},
    ['وش تحتاجون من اللجنة والجهات الداعمة؟', 'What do you need from the committee and supporting bodies?'],
    ['نبي منكم أربعة أشياء، وهي اقتراحات من خطتنا. أولاً: تعريفنا بجمعيات ومراكز تأهيل نجرب معها المرحلة الأولى في الرياض. ثانياً: تمويل أو احتضان يغطي الميزانية اللي قدّرناها. ثالثاً: فتح قنوات للربط المباشر مع الجهات الخدمية عشان نربط خدماتها. رابعاً: شراكة لقياس الأثر ومراجعة سهولة الوصول.', 'We would like four things from you, and they are suggestions from our plan. First, introductions to associations and rehabilitation centers where we can run the first phase in Riyadh. Second, funding or incubation that covers our estimated budget. Third, open channels for direct links with the organizations that provide services, so we can connect their services. Fourth, a partnership to measure impact and review accessibility.']);

  window.JUDGES = {
    contact: contact,
    project: project,
    clusters: clusters,
    facts: facts,
    items: items
  };
})();
