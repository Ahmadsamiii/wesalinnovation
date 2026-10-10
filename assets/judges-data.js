/* ==========================================================================
 *  وصال: محتوى صفحة «أسئلة التحكيم» (/judges)
 *
 *  كل نص بلغتين: [عربي، إنجليزي]. المراجع:
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

  var sources = {
    hk: ['عرض الهاكاثون النهائي', 'Hackathon final submission'],
    ar: ['العرض العربي', 'Arabic pitch deck'],
    repo: ['منصة وصال: الشيفرة والسياسات', 'Wesal platform: code and policies'],
    gastat: ['الهيئة العامة للإحصاء 2023', 'GASTAT 2023']
  };

  var team = {
    lamia: { name: ['لمياء الشهراني', 'Lamia Alshahrani'], role: ['طب وجراحة، الإشراف الطبي وقيادة الفريق', 'Medicine, medical supervision and team lead'] },
    hind: { name: ['هند يحيى آل مفرح', 'Hind Yahya Al Mufarrih'], role: ['صيدلة، تطوير المحتوى الطبي ومراجعته', 'Pharmacy, medical content development and review'] },
    raneem: { name: ['رنيم سعيد النجيمي', 'Raneem Saeed Alnujaymi'], role: ['أخصائي نفسي، تصميم التجربة والهوية', 'Psychologist, experience and identity design'] },
    ahmad: { name: ['أحمد سامي', 'Ahmad Sami'], role: ['مهندس ذكاء اصطناعي، النموذج والمنصة', 'AI engineer, model and platform'] },
    raghad: { name: ['رغد محمد العسيري', 'Raghad Mohammed Alasiri'], role: ['هندسة طبية حيوية، التقنيات المساعدة والإتاحة', 'Biomedical engineering, assistive technology and accessibility'] }
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
    { v: ['5.9%', '5.9%'], l: ['نسبة السكان من ذوي الإعاقة', 'Share of the population with a disability'], s: 'gastat' },
    { v: ['1.35 مليون', '1.35 million'], l: ['عدد الأشخاص ذوي الإعاقة في المملكة', 'People with disabilities in the Kingdom'], s: 'gastat' },
    { v: ['52.6% و21.8%', '52.6% and 21.8%'], l: ['الحركية والبصرية بين من لديهم إعاقة واحدة', 'Mobility and visual, among single disabilities'], s: 'gastat' },
    { v: ['TRL 6', 'TRL 6'], l: ['نسخة تجريبية عاملة في بيئة تشغيل حقيقية', 'Functional beta in a real operating environment'], s: 'hk' },
    { v: ['+200', '200+'], l: ['مستخدم من ذوي الإعاقة في الاختبار', 'Users with disabilities in testing'], s: 'hk' },
    { v: ['92%', '92%'], l: ['أتموا مهامهم بنجاح في الاختبار', 'Completed their tasks in testing'], s: 'hk' },
    { v: ['95%', '95%'], l: ['أسرع من البحث اليدوي بحسب الاختبار', 'Faster than manual search in testing'], s: 'hk' },
    { v: ['+40 و+120', '40+ and 120+'], l: ['مصدر رسمي وخدمة في قاعدة المعرفة', 'Official sources and services in the knowledge base'], s: 'hk' },
    { v: ['29 ريالاً', 'SAR 29'], l: ['بداية الباقات الشهرية', 'Monthly plans start from'], s: 'hk' },
    { v: ['15 إلى 3 ريالات', 'SAR 15 to SAR 3'], l: ['تكلفة المستخدم اليوم وعند الحجم', 'Cost per user today and at volume'], s: 'hk' },
    { v: ['36 ألف ريال', 'SAR 36,000'], l: ['الميزانية المقدّرة للإطلاق', 'Estimated launch budget'], s: 'ar' },
    { v: ['12 إلى 18 شهراً', '12 to 18 months'], l: ['للتعادل بعد الإطلاق', 'To break-even after launch'], s: 'hk' },
    { v: ['1,000', '1,000'], l: ['مستخدم مسجل مستهدف في السنة الأولى', 'Registered users targeted in year one'], s: 'hk' },
    { v: ['5', '5'], l: ['أعضاء في الفريق من خمسة تخصصات', 'Team members from five disciplines'], s: 'hk' }
  ];

  var items = [];

  function Q(id, who, o, q, s, d) {
    o = o || {};
    items.push({
      id: id,
      c: prefix[id.replace(/[0-9]+$/, '')],
      who: who,
      hot: o.hot || 0,
      com: o.com ? 1 : 0,
      hard: o.hard ? 1 : 0,
      src: (o.src || '').split(',').filter(Boolean),
      v: null,
      k: o.k || '',
      q: q,
      s: s,
      d: d || null
    });
  }

  /* ---------------------------------------------------------------- 1 المشكلة والحاجة */

  Q('p1', 'lamia', { hot: 2, src: 'hk' },
    ['ما المشكلة التي تحلها وصال؟', 'What problem does Wesal solve?'],
    ['الأشخاص ذوو الإعاقة يعرفون في الغالب حقوقهم واحتياجاتهم جيداً. الصعوبة في العمل بهذه المعرفة: عشرات المنصات الحكومية والخاصة المنفصلة، أغلبها لم يُصمَّم لاحتياجاتهم، لتأكيد إجراء أو التحقق من الأهلية أو الوصول إلى القناة الصحيحة. وصال تستبدل هذا البحث المتفرق بمدخل واحد موثوق وميسّر.', 'People with disabilities usually know their own rights and needs well. The difficulty is acting on that knowledge: dozens of separate government and private platforms, most not built for their needs, just to confirm a procedure, check eligibility or reach the right channel. Wesal replaces that scattered search with one trusted, accessible entry point.'],
    ['مثال: مستخدم كرسي متحرك يعرف الدعم الذي يستحقه، لكنه يبحث كل مرة عن نقل ميسّر، ويتحقق من إتاحة المرافق، ويؤكد إجراء اليوم، ويقارن كل تفصيلة بمصدر مختلف وغالباً غير ميسّر.\nالفجوة التي نسدها ليست تعريف الناس بخدمات لا يعرفونها. هي تقليل الوقت والجهد والاعتماد على الآخرين.', 'Example: a wheelchair user already knows the support they are entitled to, yet must search separately for accessible transport, verify facility accessibility and confirm today’s procedure, cross-checking each detail against a different and often inaccessible source.\nThe gap Wesal closes is not telling people about services they did not know existed. It is cutting the time, effort and dependence on others.']);

  Q('p8', 'lamia', { com: 1, hard: 1, src: 'hk,ar', k: 'لوائح سياسات هيئة الحكومة الرقمية كود المنصات الموحد الشمولية الرقمية DGA Platforms Code regulations policies' },
    ['فيه لوائح وكود منصات من هيئة الحكومة الرقمية تلزم المنصات تكون ميسّرة لذوي الإعاقة. وش المشكلة اللي تحلونها إذن؟', 'The Digital Government Authority has regulations and the Unified Platforms Code that require platforms to be accessible. So what problem are you solving?'],
    ['صح، وهذا بحد ذاته يثبت إن الموضوع أولوية وطنية، وإحنا ماشين معهم مو ضدهم. بس هذي اللوائح تضبط كل منصة حكومية لوحدها. ما تحل إن الشخص يتعامل مع جهات كثيرة، ولا إنه يعرف وش الإجراء المناسب لحالته. وصال تغطي هذي الفجوة اللي بين المنصات.', 'True, and that proves this is a national priority. We are moving with them, not against them. But those rules set up each government platform on its own. They do not solve the fact that a person deals with many entities, or that they need to know which procedure fits their case. Wesal covers that gap between platforms.'],
    ['ثلاث فجوات تبقى حتى لو كل منصة التزمت:\n• النطاق: الكود والتعليمات للمنصات الحكومية. والشخص يحتاج معها جهات ثانية: شركات الطيران، والبنوك، والاتصالات، وغيرها من القطاع الخاص.\n• الرحلة: المنصة الميسّرة تخليك تدخل وتقرأ وتستخدم قارئ الشاشة. بس ما تقول لك وش الإجراء المناسب لحالتك (مثلاً نوع كرسيك)، ولا من وين تبدأ، ولا تجمع لك الخطوات من أكثر من جهة.\n• الوقت: التطبيق تدريجي. الجهات تحصل على شهادة الامتثال للكود واحدة واحدة، والشخص يحتاج الإجابة اليوم.\nالدليل من تجربتنا: مع وجود المواقع الرسمية، المستخدمين وصلوا لمعلومتهم أسرع بـ95% مع وصال، في اختبار مع أكثر من 200 مستخدم.\nوإحنا مكمّلين مو بديل: ننقل المستخدم للصفحة الرسمية ونورّيه المصدر، وملتزمين بـWCAG 2.2، ونقدر نعطي الجهات مؤشرات عن العوائق اللي يواجهها المستخدمون عشان ترفع جاهزية خدماتها.', 'Three gaps remain even if every platform complies:\n• Scope: the Code and the guidelines are for government platforms, and people also deal with airlines, banks, telecoms and other private bodies.\n• Journey: an accessible platform lets you enter, read and use a screen reader, but it does not tell you which procedure fits your case (your chair type, for example), where to start, or gather the steps from more than one entity.\n• Time: roll-out is gradual, entities earn their compliance certificate one by one, and the person needs the answer today.\nEvidence from our test: even with the official sites available, users reached their information 95% faster with Wesal, in a test with more than 200 users.\nWe complement, not replace: we send the user to the official page and show the source, we follow WCAG 2.2, and we can give entities indicators on the barriers users face so they can raise the readiness of their services.']);

  Q('p2', 'lamia', { src: 'hk,gastat', k: 'نسبة حجم عدد إحصاء GASTAT census' },
    ['ما حجم المشكلة وكم عدد من تخدمهم؟', 'How big is the problem and how many people does it affect?'],
    ['تقول الهيئة العامة للإحصاء إن 5.9% من سكان المملكة لديهم إعاقة، أي نحو 1.35 مليون شخص. وبين من لديهم إعاقة واحدة، تمثل الصعوبة الحركية 52.6% والبصرية 21.8%، وهما أكبر فئتين بفارق كبير.', 'The General Authority for Statistics reports that 5.9% of the Kingdom’s population lives with a disability, about 1.35 million people. Among people with a single disability, mobility difficulties account for 52.6% and visual difficulties for 21.8%, the two largest groups by a wide margin.'],
    ['المصدر: الهيئة العامة للإحصاء، منشور إحصاءات الإعاقة 2023، بناءً على تعداد 2022.', 'Source: General Authority for Statistics, Disability Statistics Publication 2023, based on Census 2022.']);

  Q('p3', 'lamia', { hard: 1, src: 'hk', k: 'بوابات حكومية أبشر government portals gap' },
    ['المعلومات موجودة أصلاً في المنصات الحكومية، فما الفجوة الفعلية؟', 'The information already exists on government platforms, so what is the real gap?'],
    ['الفجوة في الوصول لا في الوجود. المعلومة موزعة بين مواقع الطيران والمطارات والمنصات الرسمية، وتحتاج إلى مطابقة يدوية، وتختلف بحسب نوع الإعاقة، وقد لا تعمل مع قارئات الشاشة. نحن لا ندّعي أننا نصنع المعلومة. نجمعها ونبسّطها ونوثّقها بمصدرها.', 'The gap is in reaching information, not in its existence. It is spread across airline sites, airport sites and official platforms, needs manual cross-checking, differs by disability type, and may not work with screen readers. We do not claim to create the information. We gather it, simplify it and cite its source.'],
    ['البدائل اليوم: مواقع شركات الطيران والمطارات، والمنصات الحكومية، وسؤال الآخرين.\nوجه القصور: يبحث المستخدم ويقارن ويتحقق بنفسه، فيطول الوقت وتزداد حاجته إلى مساعدة.', 'Today’s alternatives: airline and airport websites, official government platforms, and asking other people.\nWhere they fall short: the user searches, compares and verifies alone, which takes time and increases reliance on others.']);

  Q('p4', 'lamia', { src: 'hk,ar', k: 'خالد persona قصة مستخدم' },
    ['من هو خالد وما قصته؟', 'Who is Khaled and what is his story?'],
    ['خالد شخصية تمثيلية: شاب سعودي عمره 24 سنة يستخدم كرسياً متحركاً، حصل على فرصة لحضور مؤتمر خارج مدينته. اكتشف أن الرحلة أكبر من حجز تذكرة: هل يشحن كرسيه مسبقاً؟ ما نوع البطارية؟ كيف تتم إجراءات الصعود والوصول؟ ومن المسؤول إن لم يصل الكرسي؟ في وصال كتب حالته بجملة واحدة، فحصل خلال ثوانٍ على إجابة مخصصة مستندة إلى أنظمة الهيئة العامة للطيران المدني.', 'Khaled is a representative persona: a 24-year-old Saudi man who uses a wheelchair and got the chance to attend a conference outside his city. He found the trip was more than booking a ticket: must he ship his chair in advance? What battery type is allowed? How do boarding and arrival work? Who is responsible if the chair does not arrive? In Wesal he described his case in one sentence and within seconds received a personalized answer based on General Authority of Civil Aviation regulations.'],
    ['ما يمثله خالد: الحاجة إلى معرفة ما يجب فعله وما الخدمات المتاحة دون سؤال أحد أو تصفح مواقع كثيرة. وعبارته في العرض: «أريد أن أعرف بالضبط ما عليّ فعله وما الخدمات المتاحة لي».', 'What Khaled stands for: needing to know exactly what to do and which services are available without asking someone or browsing many sites. His line in the deck: “I want to know exactly what I need to do and what services are available to me.”']);

  Q('p5', 'lamia', { src: 'hk,ar', k: 'دراسة احتياج استبيان validation needs study survey' },
    ['كيف تحققتم أن الحاجة موجودة فعلاً؟', 'How did you validate that the need is real?'],
    ['أجرينا دراسة احتياج على مستفيدين وأصحاب علاقة للتحقق من حجم المشكلة وملاءمة الحل. ثم اختبرنا نسخة تجريبية عاملة مع أكثر من 200 مستخدم من ذوي الإعاقة بمهام فعلية.', 'We ran a needs study with beneficiaries and stakeholders to check the size of the problem and the fit of the solution. Then we tested a working beta with more than 200 users with disabilities on real tasks.'],
    ['رابط نموذج الدراسة موجود في العرض العربي برمز QR.', 'The study form is linked from the Arabic deck through a QR code.']);

  Q('p7', 'lamia', { src: 'ar' },
    ['ما أثر المشكلة على الشخص في حياته اليومية؟', 'What does the problem do to a person in daily life?'],
    ['أربعة آثار: هدر الوقت، فقد يستغرق تأكيد معلومة واحدة ساعات. وفقدان الاستقلالية، لأن الشخص يعتمد على مساعدة الآخرين واجتهاداتهم. وضعف الموثوقية بسبب تعليمات قديمة أو غير دقيقة. وتشتت المصادر بين مواقع المطار وشركات الطيران والجهات الرسمية.', 'Four effects: wasted time, since confirming one fact can take hours. Lost independence, because the person depends on other people’s help and guesses. Weak reliability, because instructions are outdated or inaccurate. And scattered sources across airport sites, airlines and official bodies.'],
    ['لا يوجد اليوم مرجع موحد يجمع هذه الإجراءات ليضمن تجربة مستقلة وآمنة. والمشكلة ليست غياب المعلومة، بل رحلة البحث عنها التي تتحول إلى عائق.', 'There is no single reference today that gathers these procedures to guarantee an independent and safe experience. The problem is not missing information. It is the search for it, which becomes a barrier.']);

  /* ---------------------------------------------------------------- 2 الفئة المستهدفة والمستخدمون */

  Q('u1', 'raneem', { src: 'hk,ar' },
    ['من المستخدم الأساسي لوصال؟', 'Who is Wesal’s primary user?'],
    ['الأشخاص ذوو الإعاقة في المملكة. والفئة الأساسية في عرضنا: الإعاقة الحركية والبصرية والسمعية والإدراكية. ومثالنا التمثيلي خالد، 24 سنة، يستخدم كرسياً متحركاً ويدير شؤونه بنفسه، ويحتاج مساعدة حين لا تكون الإجراءات واضحة.', 'People with disabilities in the Kingdom. The primary groups in our deck are mobility, visual, hearing and cognitive disabilities. Our representative example is Khaled, 24, who uses a wheelchair and manages his own affairs but needs help when procedures are unclear.'],
    ['النسخة الحالية تركّز على الحركية والبصرية، وتُضاف بقية الفئات تدريجياً. انظر سؤال «ماذا عن الصم والإعاقة الإدراكية».', 'The current version focuses on mobility and visual disabilities, and the other groups are added gradually. See the question on deaf users and cognitive disabilities.']);

  Q('u2', 'raneem', { src: 'ar' },
    ['من المستخدمون الثانويون؟', 'Who are the secondary users?'],
    ['الجمعيات والمنظمات، والجهات الحكومية، والمختصون، والمدارس والجامعات، ومقدمو الرعاية، وأولياء الأمور. احتياجهم يتقاطع مع المستخدم الأساسي: إجابة سريعة وسهلة وموثقة.', 'Associations and organizations, government entities, specialists, schools and universities, caregivers and parents. Their need overlaps with the primary user: a fast, easy and documented answer.'],
    ['الجهات تستفيد أيضاً من مؤشرات العوائق التي يواجهها المستخدمون، لترفع جاهزية خدماتها الرقمية للإتاحة.', 'Entities also benefit from the indicators of barriers users face, to raise the accessibility readiness of their digital services.']);

  Q('u3', 'raghad', { src: 'hk,repo', k: 'حركية بصرية ابدأ ليش لماذا الفئتين mobility visual scope' },
    ['لماذا تبدؤون بالإعاقة الحركية والبصرية؟', 'Why start with mobility and visual disabilities?'],
    ['لأنهما أكبر فئتين بين من لديهم إعاقة واحدة (52.6% و21.8% بحسب هيئة الإحصاء)، ولأن قدرات المنصة الحالية، من نص وصوت وواجهة ميسّرة، تخدمهما فعلاً. قررنا إتقان خدمة فئتين أولاً بدل أن نعد الجميع بخدمة ناقصة.', 'Because they are the two largest groups among single disabilities (52.6% and 21.8% per GASTAT), and because the platform’s current capabilities, text, voice and an accessible interface, serve them well. We chose to master two groups first rather than promise everyone an incomplete service.']);

  Q('u4', 'raghad', { hard: 1, src: 'hk,ar,repo', k: 'صم سمعية لغة الإشارة إدراكية deaf sign language cognitive' },
    ['ماذا عن الصم وضعاف السمع والإعاقة الإدراكية؟', 'What about deaf and hard of hearing users and cognitive disabilities?'],
    ['ننسب الدعم الحالي للفئتين اللتين اختبرناه معهما فقط. الإعاقة السمعية تحتاج إلى دعم لغة الإشارة وهو غير متوفر حالياً، ونعمل على إضافته مع مختصين. والدعم الإدراكي يُضاف بعد اختباره مع المستخدمين والجمعيات الشريكة. نضيف كل فئة حين نتحقق أنها مخدومة بشكل صحيح.', 'We attribute current support only to the two groups we tested with. Hearing disabilities need sign language support, which is not available yet, and we are working on it with specialists. Cognitive support is added after testing with users and partner associations. We add each group once we verify it is properly served.'],
    ['الخطة الزمنية في العرض العربي تضع توسيع الفئات وتطوير أدوات النفاذية الرقمية في 2029. والوضع «المبسّط» في المحادثة يفيد بعض المستخدمين، لكننا لا نعلن دعماً لم نختبره.', 'The Arabic deck timeline places category expansion and digital accessibility tools in 2029. The “simple” mode in the chat helps some users, but we do not announce support we have not tested.']);

  Q('u5', 'raneem', { src: 'ar' },
    ['هل تخدم وصال الأسر ومقدمي الرعاية؟', 'Does Wesal serve families and caregivers?'],
    ['نعم، فأسئلتهم هي نفسها: ما الحقوق، أين أقدّم، ما المستندات المطلوبة، ومن أتواصل معه. والإجابات موثقة بالمصادر نفسها. ونخطط لأدوات متقدمة للمختصين ومقدمي الرعاية ضمن باقة أعلى.', 'Yes, their questions are the same: what the rights are, where to apply, which documents are needed and whom to contact. The answers carry the same documented sources. We plan advanced tools for specialists and caregivers in a higher plan.']);

  Q('u6', 'raneem', { src: 'ar,repo' },
    ['لماذا يجب أن تختلف الإجابة من شخص لآخر؟', 'Why should the answer differ from one person to another?'],
    ['لأن ما يناسب مستخدم كرسي يدوي قد لا يناسب مستخدم كرسي كهربائي، وما يناسب رحلة قد لا يناسب أخرى. في النسخة الحالية يختار المستخدم أسلوب الإجابة، مبسّطاً أو مفصلاً، ويستطيع إضافة بيانات ملفه الاختيارية لتخصيص الإجابة. وملف الإتاحة الشخصي الكامل من الميزات المخطط لها.', 'Because what suits a manual wheelchair user may not suit an electric wheelchair user, and what suits one trip may not suit another. In the current version users choose the answer style, simple or detailed, and can add optional profile details to personalize answers. A full personal accessibility profile is a planned feature.']);

  /* ---------------------------------------------------------------- 3 الحل والمنتج */

  Q('s1', 'lamia', { hot: 1, src: 'hk', k: 'pitch تعريف جملة واحدة one line' },
    ['ما وصال في جملة واحدة؟', 'What is Wesal in one sentence?'],
    ['وصال تساعد الأشخاص ذوي الإعاقة على الوصول إلى الخدمات والإجراءات الرقمية المعقدة عبر مساعد سعودي متخصص بالذكاء الاصطناعي.', 'Wesal helps people with disabilities access complex digital services and procedures through a specialized Saudi AI assistant.'],
    ['بتفصيل أكثر: منصة سعودية تجعل المعلومات والخدمات الرقمية أسهل وصولاً وفهماً وتنفيذاً، فتحوّل المعلومات المعقدة والمتفرقة إلى إرشاد مخصص وميسّر وموثوق، يجد به المستخدم ما يحتاجه ويخطو خطوته التالية باستقلالية.', 'In more detail: a Saudi platform that makes digital information and services more accessible, understandable and actionable. It turns complex, scattered information into personalized, accessible and trustworthy guidance, so users find what they need and take the next step independently.']);

  Q('s2', 'ahmad', { src: 'hk', k: 'خطوات المراحل flow steps pipeline' },
    ['كيف تعمل وصال خطوة بخطوة؟', 'How does Wesal work, step by step?'],
    ['خمس خطوات. يكتب المستخدم سؤاله أو يقوله. تفهم وصال النية والسياق والاحتياج. تسترجع المعلومات ذات الصلة من قاعدة معرفة سعودية منتقاة. تبسّط الإجابة وترتبها بحسب احتياج المستخدم وتفضيلات الإتاحة. ثم يستلم إجابة ميسّرة نصاً أو صوتاً مع خطوات تالية واضحة.', 'Five steps. The user types or speaks a question. Wesal understands intent, context and need. It retrieves relevant information from a curated Saudi knowledge base. It simplifies and structures the answer for the user’s needs and accessibility preferences. The user then receives an accessible answer as text or voice, with clear next steps.']);

  Q('s3', 'raneem', { src: 'ar', k: 'ميزات features' },
    ['ما ميزات وصال؟', 'What are Wesal’s features?'],
    ['العرض العربي يعرض ست ميزات: معرفة سعودية متخصصة، ومرافق إنجاز ذكي خطوة بخطوة، وتحليل العوائق الرقمية، وملف إتاحة شخصي، ومطابقة ذكية للخدمة، وذاكرة لرحلة المستخدم تتيح استكمال الإجراءات دون إعادة الخطوات.', 'The Arabic deck presents six features: specialized Saudi knowledge, a smart step-by-step completion companion, digital barrier analysis, a personal accessibility profile, smart service matching, and a user journey memory that lets people resume procedures without repeating steps.']);

  Q('s4', 'ahmad', { src: 'repo', k: 'إجابة شكل الرد answer format مبسط مفصل' },
    ['كيف تبدو الإجابة التي يراها المستخدم؟', 'What does the answer the user sees look like?'],
    ['تبدأ بخلاصة مباشرة في سطر، ثم الخطوات في نقاط قصيرة، ثم الجهة المسؤولة وقناتها الرسمية بالاسم، ثم سؤال متابعة واحد. وتحتها ثلاثة أسئلة متابعة مقترحة، وبطاقات المصدر الرسمي إن اعتمدت الإجابة على صفحات رسمية.', 'It starts with a one-line summary, then the steps in short points, then the responsible entity and its official channel by name, then one follow-up question. Below it come three suggested follow-ups, and official source cards when the answer relies on official pages.'],
    ['للمستخدم وضعان: مبسّط (نحو 120 كلمة بجمل قصيرة جداً) ومفصّل (الخطوات والجهة المسؤولة عن كل خطوة).', 'Users have two modes: simple (about 120 words in very short sentences) and detailed (the steps and the entity responsible for each).']);

  Q('s6', 'ahmad', { src: 'ar,repo', k: 'لوحة التحكم dashboard مساحة عمل workspace' },
    ['ماذا في المنصة غير المحادثة؟', 'What is in the platform besides the chat?'],
    ['حساب شخصي وملف اختياري، ومحادثات محفوظة في متصفح المستخدم، وطلبات دعم فني، وتنزيل البيانات وحذف الحساب. وللفريق لوحة تحكم: المستخدمون والأدوار والرسائل والتذاكر والدعوات والاستبيانات وتحرير محتوى صفحة الهبوط.', 'A personal account with an optional profile, chats saved in the user’s browser, support tickets, data download and account deletion. For the team there is a dashboard: users, roles, messages, tickets, invitations, surveys and landing page content editing.'],
    ['إلى جانبها مساحة عمل داخلية لإدارة المشاريع تشارك المنصة الحساب والدور والجلسة، وفي العرض العربي: المحادثة الذكية والموارد ولوحة التحكم.', 'Alongside it is an internal project workspace that shares the platform’s account, role and session. The Arabic deck shows the smart chat, resources and the dashboard.']);

  Q('s7', 'lamia', { hot: 10, hard: 1, src: 'hk,ar,repo', k: 'جاهز مخطط roadmap MVP beta live planned' },
    ['ما الجاهز اليوم وما المخطط؟', 'What is ready today and what is planned?'],
    ['الجاهز: نسخة تجريبية عاملة على wesalinnovation.sa، اختُبرت مع أكثر من 200 مستخدم (TRL 6). فيها محادثة بإجابات متدفقة ومصادر رسمية، وصوت سعودي، وإعدادات وصول سريعة، وأدوار ولوحة تحكم واستبيانات. المخطط: تكامل مع جهات خدمية عبر API، وفئات إعاقة إضافية، وملف إتاحة شخصي كامل، وتحليل المستندات، وتوسع إقليمي.', 'Ready: a working beta at wesalinnovation.sa, tested with more than 200 users (TRL 6). It has a chat with streaming answers and official sources, Saudi voice, quick accessibility settings, roles, a dashboard and surveys. Planned: API integration with service entities, additional disability groups, a full personal accessibility profile, document analysis and regional expansion.'],
    ['قول واضح أفضل من وعد واسع: ما لم يُختبر مع المستخدمين نسميه مخططاً.', 'Being clear beats a broad promise: anything not yet tested with users we call planned.']);

  Q('s8', 'lamia', { src: 'hk', k: 'مقارنة منافسين government portals general search comparison' },
    ['بماذا تختلف وصال عن البوابات الحكومية والبحث العام؟', 'How does Wesal differ from government portals and general search?'],
    ['النهج: وصال إرشاد شخصي بخطوات قابلة للتنفيذ، والبوابات معلومات موزعة على خدمات منفصلة، والبحث العام نتائج متعددة تحتاج بحثاً يدوياً. الإتاحة: مبنية في تجربة وصال من الأساس، وتتفاوت بين البوابات، وليس البحث العام موجهاً للإعاقة. السياق: سعودي وعربي، مقابل موزع أو عام.', 'Approach: Wesal gives personalized guidance with actionable steps, portals give information spread across separate services, and general search gives many results that need manual searching. Accessibility: built into Wesal’s experience from the start, varying across portals, and not designed for disability in general search. Context: Saudi and Arabic, versus distributed or general.'],
    ['ثلاثة ادعاءات: رحلة واحدة بدل معلومات متفرقة، وإتاحة بالتصميم لا بعد الإنجاز، ومعرفة سعودية موثوقة قابلة للتنفيذ.', 'Three claims: one journey instead of scattered information, accessibility by design rather than as an afterthought, and trusted Saudi knowledge that people can act on.']);

  /* ---------------------------------------------------------------- 4 التقنية والذكاء الاصطناعي */

  Q('ai1', 'ahmad', { hot: 4, com: 1, hard: 1, src: 'repo,hk', k: 'وش النموذج Gemini OpenAI Claude wrapper غلاف تدريب ضبط fine-tune LoRA نموذجنا model train' },
    ['وش النموذج اللي تستخدمونه، وهل دربتوا نموذجكم الخاص؟', 'Which model do you use, and did you train your own?'],
    ['نبني نموذج وصال على مرحلتين. الأولى شغّالة الحين: نموذج لغوي قوي مربوط بقاعدتنا المعرفية السعودية الرسمية ومقيّد بقواعدنا، ما يخترع ولا يشخّص، ونقدر نبدّله بإعداد واحد. والثانية قيد العمل: نضبط نموذج مفتوح على بيانات مجال الإعاقة في السعودية ليصير نموذج وصال الخاص، ونقلل اعتمادنا على مزوّد خارجي.', 'We are building the Wesal model in two stages. The first works today: a strong language model tied to our official Saudi knowledge base and held by our rules, so it does not make things up or diagnose, and we can swap it with one setting. The second is in progress: tuning an open model on Saudi disability domain data so it becomes Wesal’s own model and we depend less on an outside provider.'],
    ['حالياً الأساسي Gemini من قوقل عن طريق API، وعندنا نماذج احتياطية، ونقدر نبدّله لـOpenAI أو Claude بإعداد واحد. وما نبدأ من الصفر لأن القيمة في بياناتنا ومعرفتنا الرسمية وسلامتنا، وهي اللي نبني عليها نموذجنا. فتبديل النموذج الأساسي ما يلغي اللي بنيناه، يستفيد منه.', 'Right now the main one is Google’s Gemini through an API, we keep backup models, and one setting switches it to OpenAI or Claude. We do not start from scratch because the value is in our data, our official knowledge and our safety, which is what we build our own model on. Swapping the base model does not cancel what we built, it benefits from it.']);

  Q('ai2', 'ahmad', { hot: 3, com: 1, hard: 1, src: 'hk,repo', k: 'ChatGPT شات جي بي تي كلود Claude Gemini عام general AI chatbot الفرق وش تفرقون' },
    ['وش تفرقون عن ChatGPT أو كلود أو غيره؟', 'How are you different from ChatGPT, Claude or the others?'],
    ['ChatGPT وClaude وغيرها نماذج قوية، وأنا أستخدمها كل يوم في مهامي الشخصية. لكنها لكل الناس: ما تضمن لك دقة إجراءات السعودية، ولا تلتزم بمصدر رسمي، وممكن تجاوب وهي مو متأكدة. وصال تجاوبك من مصادر سعودية رسمية، وتورّيك المصدر، وتقول «ما أدري» بدل ما تخترع. وهي مخصصة لمجتمع ذوي الإعاقة في المملكة، شي مننا وفينا، يفهمنا ويفهم وش نبي بالضبط.', 'ChatGPT, Claude and others are strong models, and I use them every day for my personal tasks. But they are built for everyone: they do not guarantee the accuracy of Saudi procedures, they do not commit to an official source, and they may answer when they are not sure. Wesal answers from official Saudi sources, shows you the source, and says “I don’t know” instead of making things up. And it is made for the disability community in Saudi Arabia, one of our own, one that understands us and exactly what we need.'],
    ['الفروق باختصار:\n• المعلومة: من مصادر رسمية سعودية، مو من الإنترنت العام، ونورّيك المصدر تحت الإجابة.\n• الأمانة: لو ما لقينا مصدر نقول «ما أدري» ونوجّهك للجهة الرسمية.\n• الحدود: ما نشخّص ولا نعطي رأي طبي أو قانوني.\n• الإتاحة: صوت سعودي، وقارئ شاشة، وتكبير الخط، ووضع مبسّط بجمل قصيرة.\n• المراجعة: فريقنا الطبي يراجع المحتوى الصحي قبل النشر.\n• التجربة: جرّبناها مع أكثر من 200 مستخدم من ذوي الإعاقة، و92% أنجزوا مهامهم.\nوبصراحة: نستخدم نماذج مثلهم من جوّا، وما نقول إن ذكاءنا أقوى منهم. نقول إن المصدر والأمان والإتاحة عندنا أنسب لهذا المجال.', 'The differences in short:\n• Information: from official Saudi sources, not the open internet, and we show the source under the answer.\n• Honesty: if we find no source we say “I don’t know” and point you to the official entity.\n• Limits: we do not diagnose or give medical or legal opinions.\n• Accessibility: Saudi voice, screen reader support, bigger text and a simple mode with short sentences.\n• Review: our medical team reviews health content before publishing.\n• Testing: more than 200 users with disabilities tried it, and 92% completed their tasks.\nTo be frank: we use models like theirs under the hood, and we do not claim our AI is smarter. We say our sources, safety and accessibility fit this field better.']);

  Q('ai3', 'ahmad', { hot: 5, com: 1, hard: 1, src: 'hk,repo', k: 'هلوسة hallucination اختلاق five layers خمس طبقات دقة' },
    ['كيف تضمنون إن الإجابة صحيحة وما تهلوس؟', 'How do you make sure an answer is correct and not made up?'],
    ['ما نخلّي النموذج يجاوب من راسه. يرجع أول للمصادر الرسمية عندنا ويجاوب منها. وإذا ما لقى مصدر يقول إنه مو متأكد ويوجّهك للجهة الرسمية. وتحت الإجابة نعرض المصدر عشان تتأكد بنفسك. وعندنا خمس طبقات تفلتر: الرجوع للمصادر، وقواعد تمنع اختلاق الأرقام والروابط، والسيرفر يحدد المصدر المعروض مو النموذج، وفريقنا الطبي يراجع المحتوى الصحي، وأي مستخدم يبلّغ عن خطأ بضغطة.', 'We do not let the model answer from its own head. It first goes back to our official sources and answers from them. If it finds no source, it says it is not sure and points you to the official entity. And we show the source under the answer so you can check it yourself. We have five filtering layers: going back to the sources, rules that forbid inventing numbers or links, the server (not the model) deciding which source is shown, our medical team reviewing health content, and any user reporting an error with one tap.']);

  Q('te1', 'ahmad', { com: 1, src: 'repo', k: 'architecture stack PHP MySQL SSE بنية تقنية معمارية' },
    ['وش البنية التقنية للنظام؟', 'What is the technical architecture?'],
    ['واجهة بسيطة تشتغل من المتصفح بدون تثبيت. خلفها سيرفر PHP وقاعدة بيانات MySQL، ويكلّم النموذج عن طريق API والرد يطلع كلمة كلمة. وقاعدة المعرفة، يعني المصادر الرسمية، محفوظة عندنا في قاعدة البيانات.', 'A simple interface that runs in the browser with nothing to install. Behind it a PHP server and a MySQL database, which talks to the model through an API and streams the reply word by word. Our knowledge base, the official sources, is stored in our database.']);

  Q('te3', 'ahmad', { com: 1, hard: 1, src: 'repo', k: 'بيانات تخزين استضافة داخل المملكة data location hosting residency PDPL' },
    ['وين تنحفظ البيانات؟ وهل تطلع برا السعودية؟', 'Where is the data stored? Does it leave Saudi Arabia?'],
    ['بيانات الحسابات والمصادر على استضافتنا. وقائمة المحادثات المحفوظة تبقى في متصفح المستخدم نفسه. وعشان النموذج يجاوب نرسل له نص السؤال وآخر رسائل المحادثة بدون اسم المستخدم ولا إيميله، فالنص يتعالج عند مزوّد النموذج، وكذا الصوت عند مزوّده.', 'Account data and the sources are on our hosting. The saved chat list stays in the user’s own browser. For the model to answer, we send it the question text and the last few messages without the user’s name or email, so that text is processed by the model provider, and voice is processed by its provider.']);

  Q('ai4', 'ahmad', { com: 1, src: 'repo', k: 'RAG retrieval embedding vector استرجاع متجهات قاعدة المعرفة cosine' },
    ['وش هو RAG وكيف يشتغل عندكم؟', 'What is RAG and how does it work for you?'],
    ['نخلّي النموذج يفتح المصادر قبل ما يجاوب، مثل الطالب اللي يجاوب والكتاب مفتوح قدامه. نحوّل سؤالك لأرقام تمثّل معناه، ونقارنه بمقاطع من المصادر الرسمية عندنا، ونجيب أقرب أربعة ونعطيها للنموذج يجاوب منها. وإذا ما لقينا مقطع قريب كفاية، ما نعرض مصدر.', 'RAG means we make the model open the sources before it answers, like a student answering with the book open. We turn your question into numbers that represent its meaning, compare it with pieces of our official sources, pick the closest four, and hand them to the model to answer from. If no piece is close enough, we show no source.']);

  Q('ai6', 'ahmad', { com: 1, src: 'hk', k: 'دقة accuracy قياس تقييم evaluation' },
    ['كم دقة الإجابات وكيف تقيسونها؟', 'How accurate are the answers and how do you measure it?'],
    ['ما نعطيك رقم دقة واحد لأننا ما قسناها بهالطريقة. اللي قسناه: جرّبناها مع أكثر من 200 مستخدم بمهام حقيقية؛ 92% خلّصوا مهامهم، وكانوا أسرع بـ95% من البحث بأنفسهم، وكل إجابة نقدر نرجعها لمصدرها.', 'We do not give one accuracy number because we did not measure it that way. What we measured: more than 200 users tried it on real tasks. 92% finished their tasks, they were 95% faster than searching by themselves, and every answer can be traced back to its source.']);

  Q('ai5', 'ahmad', { src: 'repo', k: 'تعلم تدريب محادثات المستخدمين خصوصية learn train user data conversations' },
    ['هل تتعلم وصال من محادثات المستخدمين؟ وهل تدرّبون عليها؟', 'Does Wesal learn from users’ conversations? Do you train on them?'],
    ['لا، ما نعيد تدريب النموذج على كلام المستخدمين. نحفظ نص الأسئلة والأجوبة بدون اسم ولا حساب عشان نحسّن الإجابات، والمستخدم المسجّل ما نحفظ كلامه إلا لو وافق. وما نبيع البيانات.', 'No, we do not retrain the model on users’ words. We save the text of questions and answers with no name or account, to improve answers, and for signed-in users we save it only if they agree. We do not sell data.']);

  Q('te4', 'ahmad', { com: 1, src: 'hk,repo', k: 'حماية أمن اختراق security HTTPS تشفير كلمات المرور' },
    ['كيف تحمون النظام من الاختراق؟', 'How do you protect the system from attacks?'],
    ['الاتصال مشفّر (HTTPS). كلمة المرور ما نحفظها نص، نحفظ لها بصمة مشفّرة. والصلاحيات يفرضها السيرفر حسب الدور. والجلسة تنتهي لو ما استخدمها الشخص: 30 دقيقة للمستخدم و15 لفريقنا. وفيه حدود على عدد الطلبات، وفحص للصور المرفوعة، وسجل لكل عملية إدارية.', 'The connection is encrypted (HTTPS). We do not store passwords as text, only an encrypted fingerprint. The server enforces permissions by role. Sessions end when unused: 30 minutes for users and 15 for our team. There are request limits, checks on uploaded images, and a log of every admin action.']);

  Q('te5', 'ahmad', { src: 'repo', k: 'تعطل fallback failover rate limit حصة quota المزوّد' },
    ['وش يصير لو وقف المزوّد أو طاحت الخدمة؟', 'What happens if the provider goes down?'],
    ['عندنا ثلاث طبقات. أول شي نماذج احتياطية عند نفس المزوّد، وبعدين مزوّدين ثانيين لو فعّلناهم، ولو انقطع البث المتصفح يطلب الرد كامل مرة وحدة. وآخر شي قاعدة معرفة بسيطة داخل الصفحة نفسها تجاوب منها.', 'We have three layers. First, backup models with the same provider. Then other providers if we enable them, and if streaming breaks the browser asks for the full reply in one go. Last, a simple knowledge base inside the page itself that answers from it.']);

  Q('te6', 'ahmad', { src: 'hk,ar,repo', k: 'توسع ضغط مستخدمين scale load cloud سحابية' },
    ['هل النظام يتحمّل مستخدمين كثير؟', 'Can the system handle many users?'],
    ['اليوم المنصة على استضافة ويب وتكفي مرحلة التجربة (جرّبها أكثر من 200 مستخدم). لما نكبر ننقلها لخدمة سحابية تتوسع حسب الضغط، وهذا ضمن ميزانية البنية السحابية (8,000 ريال). وعندنا حدود لكل شخص عشان ما أحد يستهلك الموارد كلها.', 'Today the platform runs on web hosting and is enough for the pilot stage (more than 200 users tried it). As we grow we will move it to a cloud service that scales with load, which is covered by the cloud infrastructure budget (SAR 8,000). We also set limits per person so nobody uses up all the resources.']);

  Q('te7', 'ahmad', { com: 1, src: 'hk,repo', k: 'تكلفة التشغيل cost حدود سقف limits' },
    ['كم تكلفة التشغيل؟', 'How much does it cost to run?'],
    ['التكلفة المقدّرة للمستخدم الواحد حوالي 15 ريال الحين، وتنزل لـ3 ريال لما نكبر. وحاميين الفاتورة بحدود لكل شخص وسقف يومي، ونحفظ الأصوات اللي تتكرر عشان ما ندفع عليها مرتين.', 'The estimated cost per user is about SAR 15 now and drops to SAR 3 as we grow. We protect the bill with per-person limits and a daily cap, and we cache voices that repeat so we do not pay twice for them.']);

  Q('te2', 'ahmad', { src: 'repo,hk', k: 'طورنا بنينا جاهز built ready-made' },
    ['وش اللي طوّرتوه أنتم، ووش اللي جاهز؟', 'What did you build yourselves and what is ready-made?'],
    ['طوّرنا المنصة كلها: الواجهة والسيرفر ولوحة التحكم والحسابات والأدوار، وطريقة ربط الإجابة بالمصادر وعرضها. واللي استخدمناه جاهز: النموذج اللغوي، وأصوات القراءة، والاستضافة.', 'We built the whole platform: the interface, the server, the dashboard, accounts and roles, and the way answers are tied to sources and displayed. What we used ready-made: the language model, the reading voices and the hosting.']);

  Q('ai7', 'ahmad', { src: 'repo', k: 'نموذج سعودي عربي ALLaM Saudi Arabic model' },
    ['ليش ما استخدمتوا نموذج سعودي أو عربي؟', 'Why not use a Saudi or Arabic model?'],
    ['نبغى الأفضل للمستخدم من اللي متاح بواجهة برمجية ثابتة وسريعة. ووصال مبنية إن النموذج يتبدّل بإعداد واحد، فلو في نموذج سعودي مناسب ومتاح نجرّبه ونقارنه ونستخدمه لو طلع أحسن.', 'We want what is best for the user among what is available through a stable, fast API. Wesal is built so the model can be swapped with one setting, so if a suitable Saudi model is available we will test it, compare it and use it if it proves better.']);

  Q('te8', 'ahmad', { src: 'repo', k: 'صوت tts stt voice Whisper Azure Groq املاء dictation' },
    ['كيف يشتغل الصوت؟', 'How does voice work?'],
    ['تسأل بصوتك أو تكتب. والإجابة تنقرأ لك بصوت سعودي، وإذا ما اشتغل يقرأ المتصفح بصوت جهازك. وإذا متصفحك ما يدعم الإملاء، نحوّل تسجيلك لنص ونمسح التسجيل، ما نحتفظ فيه.', 'You ask by voice or by typing. The answer is read to you in a Saudi voice, and if that does not work the browser reads it with your device voice. If your browser does not support dictation, we turn your recording into text and delete the recording, we do not keep it.']);

  Q('ai8', 'ahmad', { hard: 1, src: 'repo', k: 'تلاعب prompt injection jailbreak حقن' },
    ['هل أحد يقدر يتلاعب بالمساعد؟', 'Can someone manipulate the assistant?'],
    ['تعليمات المساعد تمنعه يكشف تفاصيله التقنية ويرجّع الحديث للموضوع. والأهم إن المصادر اللي تنعرض يحددها السيرفر مو النموذج، فما أحد يقدر يخلّيه يختلق جهة رسمية. وعندنا حدود على عدد الأسئلة تمنع الإساءة.', 'The assistant’s instructions stop it from revealing its technical details and bring the chat back to the topic. More important, the server, not the model, decides which sources are shown, so nobody can make it invent an official entity. We also limit the number of questions to prevent abuse.']);

  Q('ai9', 'ahmad', { src: 'repo,ar', k: 'لهجة سعودية عربي dialect Saudi Arabic' },
    ['كيف يتعامل مع العربي واللهجات؟', 'How does it handle Arabic and dialects?'],
    ['يجاوب بعربي واضح ولهجة سعودية بيضاء وجمل قصيرة، ويبدأ بالخلاصة. والموقع نفسه بفصحى مبسّطة، والصوت بأصوات سعودية، وفيه إنجليزي كمان.', 'It answers in clear Arabic with an easy Saudi dialect and short sentences, and starts with the summary. The website itself is in simple standard Arabic, the voices are Saudi, and English is supported too.'],
    ['والنموذج يفهم الفصحى واللهجات، بس ما اختبرنا كل لهجة.', 'The model understands standard Arabic and dialects, but we have not tested every dialect.']);

  Q('te9', 'ahmad', { src: 'hk,ar,repo', k: 'ربط API تكامل جهات الكود مفتوح GitHub open source integration' },
    ['هل تقدرون تربطون مع جهات ثانية؟ وهل الكود مفتوح؟', 'Can other entities integrate with you? Is the code open?'],
    ['الكود على GitHub. وحالياً ما عندنا API عام للجهات، والربط مخطط في 2027 مع أول جهتين أو ثلاث جهات خدمية.', 'The code is on GitHub. For now we have no public API for entities, and integration is planned for 2027 with the first two or three service entities.']);

  Q('te10', 'ahmad', { src: 'repo', k: 'مراقبة monitoring لوحة التحكم logs أعطال' },
    ['كيف تراقبون النظام وتعرفون إذا فيه مشكلة؟', 'How do you monitor the system and know when something is wrong?'],
    ['عندنا لوحة تحكم تورّي صحة الردود وسرعتها، وكم مرة انقطع البث ولأي سبب. وسجل لكل عملية إدارية، وتذاكر لمراجع المحتوى لما يبلّغ مستخدم عن خطأ.', 'We have a dashboard that shows the health and speed of replies, and how often streaming broke and why. There is a log of every admin action, and tickets for the content reviewer when a user reports an error.']);

  Q('te11', 'ahmad', { src: 'hk,ar', k: 'تطبيق جوال app mobile APK ios android' },
    ['هل لوصال تطبيق جوال؟', 'Does Wesal have a mobile app?'],
    ['لا. وصال اليوم موقع يشتغل على الجوال والكمبيوتر من المتصفح بدون تثبيت، وما أعلنّا عن تطبيق.', 'No. Today Wesal is a website that works on phone and desktop from the browser with nothing to install, and we have not announced an app.']);

  /* ---------------------------------------------------------------- 5 الموثوقية والمصادر */

  Q('r1', 'hind', { src: 'hk,repo', k: 'مصادر جهات هيئة رعاية وزارة sources entities' },
    ['من أين تأتي معلومات وصال؟', 'Where does Wesal’s information come from?'],
    ['من قاعدة معرفة منتقاة من مصادر سعودية رسمية. عرضنا يذكر أكثر من 40 مصدراً رسمياً وأكثر من 120 خدمة. ومن أمثلة الجهات: وزارة الموارد البشرية والتنمية الاجتماعية، وهيئة رعاية الأشخاص ذوي الإعاقة، وصندوق تنمية الموارد البشرية «هدف»، ووزارة الصحة، ووزارة التعليم، وبنك التنمية الاجتماعية، ومركز الملك سلمان لأبحاث الإعاقة.', 'From a knowledge base curated from official Saudi sources. Our deck cites more than 40 official sources and more than 120 services. Example entities: the Ministry of Human Resources and Social Development, the Disability Care Authority, the Human Resources Development Fund (Hadaf), the Ministry of Health, the Ministry of Education, the Social Development Bank and the King Salman Center for Disability Research.']);

  Q('r2', 'ahmad', { src: 'repo,hk', k: 'مصدر الإجابة source card citation توثيق' },
    ['كيف يرى المستخدم مصدر الإجابة؟', 'How does the user see the source of an answer?'],
    ['تحت كل إجابة مبنية على صفحات رسمية بطاقة لكل جهة، ثلاث على الأكثر: شعارها واسمها ونطاقها، وتفتح الصفحة نفسها التي أُخذت منها المعلومة. والإجابة التي لم تُبنَ على مصدر رسمي لا يظهر تحتها شيء.', 'Under every answer based on official pages there is a card per entity, three at most: its logo, name and domain, opening the very page the information came from. An answer not based on an official source shows nothing underneath.'],
    ['النموذج يكتب سراً أرقام المصادر التي استخدمها، والخادم يقبل منها ما جلبه هو فقط. فلا تظهر جهة لم نأخذ منها شيئاً مهما كتب النموذج.', 'The model privately writes the numbers of the sources it used, and the server accepts only those it retrieved itself. An entity we took nothing from never appears, whatever the model writes.']);

  Q('r3', 'hind', { src: 'hk,repo' },
    ['كيف تبقون المعلومات محدّثة؟', 'How do you keep information up to date?'],
    ['نعيد فحص المحتوى دورياً مقابل المصادر الرسمية المحدَّثة، ونحدّث قاعدة المعرفة بإعادة تلقيم الصفحات التي تغيّرت، فتُستبدل مقاطعها القديمة. وملاحظات المستخدمين وبلاغات الخطأ تتحول إلى تحسينات.', 'We periodically re-check content against updated official sources and refresh the knowledge base by re-ingesting changed pages, which replaces their old chunks. User feedback and error reports turn into improvements.']);

  Q('r4', 'lamia', { hard: 1, src: 'hk,ar' },
    ['من يراجع المحتوى الصحي ومن يتحمل مسؤولية دقته؟', 'Who reviews the health content and who is accountable for its accuracy?'],
    ['بحسب عرضنا، يُكتب المحتوى الصحي والمتعلق بالإعاقة أو يُراجع بواسطة طبيب مرخّص وصيدلي إكلينيكي قبل نشره، ويُعاد فحصه دورياً. ومن الفريق: لمياء الشهراني للإشراف الطبي، وهند آل مفرح لتطوير المحتوى الطبي ومراجعته. ووصال لا تقدم تشخيصاً ولا قرار علاج.', 'According to our deck, health and disability related content is written or reviewed by a licensed physician and a clinical pharmacist before publication, and re-checked periodically. On the team, Lamia Alshahrani supervises the medical side and Hind Al Mufarrih develops and reviews medical content. Wesal gives no diagnosis or treatment decision.']);

  Q('r5', 'ahmad', { hot: 6, src: 'hk,repo', k: 'لا يوجد مصدر no source لا أعرف uncertain' },
    ['ماذا يحدث إن لم يوجد مصدر موثوق للإجابة؟', 'What happens when no reliable source exists for an answer?'],
    ['وصال تقول ذلك بوضوح وتوجّه المستخدم إلى الجهة الرسمية بدل التخمين. ولا تظهر تحت الإجابة بطاقات مصدر إن لم تُبنَ على صفحة رسمية، فيعرف المستخدم أن ما قرأه إرشاد عام.', 'Wesal says so plainly and points the user to the official entity instead of guessing. No source cards appear under an answer not built on an official page, so the user knows what they read is general guidance.'],
    ['تعليمات النموذج: «إذا لم تكن المعلومة مؤكدة قلها بصراحة ووجّه الشخص للجهة الرسمية»، و«لا تخترع أرقاماً أو مبالغ أو نسباً أو روابط».', 'The model instructions say: if the information is not certain, say so frankly and direct the person to the official entity, and never invent numbers, amounts, percentages or links.']);

  Q('r6', 'hind', { src: 'hk,repo', k: 'بلاغ خطأ report error feedback مراجع reviewer' },
    ['كيف يبلّغ المستخدم عن خطأ وماذا يحدث بعد ذلك؟', 'How does a user report an error and what happens next?'],
    ['تحت كل إجابة زر «أبلغ عن خطأ». البلاغ يضم نص الإجابة ومصادرها وروابطها، فيعرف المراجع أي صفحة يراجع. ثم تُتابَع كتذكرة عند مراجع المحتوى حتى تُغلق، ويُصحَّح المحتوى. ويُستفاد من ملاحظات المستخدم بعد كل تفاعل لتحسين الإجابات.', 'Under every answer there is a “report an error” button. The report includes the answer text, its sources and links, so the reviewer knows which page to check. It is then tracked as a ticket with the content reviewer until closed, and the content is corrected. Feedback after each interaction is used to improve answers.']);

  /* ---------------------------------------------------------------- 6 الأمان والخصوصية والأخلاقيات */

  Q('v1', 'ahmad', { src: 'repo', k: 'بيانات احتفاظ حذف retention data collected سياسة الخصوصية' },
    ['ما البيانات التي تجمعونها وكم تحتفظون بها؟', 'What data do you collect and how long do you keep it?'],
    ['بيانات الحساب: الاسم والبريد والجوال وتاريخ الميلاد. وبيانات اختيارية يضيفها المستخدم: المدينة ونوع الإعاقة والاهتمامات. ونص المحادثات دون ربطه بالحساب لتحسين الدقة، ويمكن إيقاف ذلك. وعنوان IP لحماية المنصة. نحتفظ بالبيانات ما دام الحساب قائماً، وبعد حذفه نزيل البيانات الشخصية خلال 30 يوماً على الأكثر.', 'Account data: name, email, phone and date of birth. Optional data the user adds: city, disability type and interests. Chat text, not linked to the account, to improve accuracy, which can be switched off. And the IP address to protect the platform. We keep data while the account exists, and after deletion we remove personal data within 30 days at most.'],
    ['قائمة المحادثات المحفوظة تبقى في متصفح المستخدم، ولا تظهر لمن يدخل بحساب آخر على الجهاز نفسه. وللمستخدم تنزيل نسخة من بياناته ومسح محادثاته وحذف حسابه بنفسه.', 'The saved chat list stays in the user’s browser and is not visible to someone signing in with another account on the same device. Users can download a copy of their data, clear their chats and delete their account themselves.']);

  Q('v2', 'ahmad', { src: 'hk,repo', k: 'حماية security تشفير كلمات المرور RBAC session' },
    ['كيف تحمون بيانات الإعاقة والبيانات الحساسة؟', 'How do you protect disability and other sensitive data?'],
    ['الاتصال مشفّر بـHTTPS. كلمة المرور تُخزَّن بصمةً مشفّرة لا تُسترجع منها. والصلاحيات بحسب الدور، يفرضها الخادم. والجلسة تنتهي تلقائياً بعد 30 دقيقة دون نشاط للمستخدم و15 دقيقة لحسابات الفريق. ولا تُعرض بيانات ملف المستخدم، ومنها نوع الإعاقة، لأي مستخدم آخر.', 'The connection is encrypted with HTTPS. Passwords are stored as a cryptographic hash that cannot be reversed. Permissions follow the role and are enforced by the server. Sessions end automatically after 30 minutes of inactivity for users and 15 minutes for team accounts. A user’s profile data, including disability type, is never shown to any other user.'],
    ['• استعلامات قاعدة البيانات معدّة مسبقاً.\n• الجلسات HttpOnly وSameSite، وSecure على HTTPS.\n• سجل عمليات يوثّق الدخول وتغيير الصلاحيات والتصدير.\n• عند كل خروج يُمسح ما يخص الحساب من المتصفح.\n• الصور المرفوعة تُفحص وتُمنع فيها السكربتات.', '• Database queries are prepared statements.\n• Sessions are HttpOnly and SameSite, and Secure over HTTPS.\n• An audit log records sign-ins, permission changes and exports.\n• On every sign-out the account’s data is cleared from the browser.\n• Uploaded images are validated and scripts are blocked.']);

  Q('v3', 'lamia', { hot: 7, src: 'hk,repo', k: 'تشخيص طبي استشارة diagnosis medical legal advice' },
    ['هل تقدم وصال تشخيصاً أو استشارة طبية أو قانونية؟', 'Does Wesal give diagnosis or medical or legal advice?'],
    ['لا. وصال تساعد المستخدم على فهم حقوقه ومستحقاته وكيف يصل إلى الخدمات. وتوجّهه إلى مختص مرخّص في القرارات الطبية والقانونية. وشروط الاستخدام تنص على أن الإجابات لا تغني عن الاستشارة المتخصصة.', 'No. Wesal helps users understand their rights and entitlements and how to reach services. It directs them to a licensed professional for medical and legal decisions. The terms of use state that answers are not a substitute for specialist advice.']);

  Q('v4', 'hind', { src: 'hk' },
    ['كيف تتعاملون مع الحالات الحساسة أو عالية الخطورة؟', 'How do you handle sensitive or high-risk cases?'],
    ['وصال لا تحل محل قنوات الدعم الرسمية. في الحالات الحساسة توجّه المستخدم إلى الجهة الرسمية المعنية أو مختص مؤهل بدل أن تتولى هي الأمر، وتذكّره بمراجعة مختص مرخّص قبل أي قرار طبي.', 'Wesal does not replace official support channels. In sensitive cases it directs the user to the relevant official entity or a qualified specialist rather than handling the matter itself, and reminds them to consult a licensed professional before any medical decision.']);

  Q('v5', 'lamia', { hard: 1, src: 'repo' },
    ['هل تلتزمون بنظام حماية البيانات الشخصية وضوابط سدايا؟', 'Do you comply with the Personal Data Protection Law and SDAIA controls?'],
    ['صممنا المنصة على مبادئ مثل الحد الأدنى من البيانات، وإتاحة حق التنزيل والتعديل والحذف للمستخدم، وتحديد مدة الاحتفاظ، وعدم بيع البيانات أو مشاركتها مع معلنين، وسياسة خصوصية معلنة. أما التقييم الرسمي للامتثال فخطوة نخطط لها قبل التوسع.', 'We designed the platform on principles such as data minimization, giving users the right to download, edit and delete, a defined retention period, no sale or sharing of data with advertisers, and a published privacy policy. A formal compliance assessment is a step we plan before scaling.']);

  Q('v6', 'ahmad', { src: 'repo', k: 'بيع بيانات إعلانات مزود النموذج data sale ads provider' },
    ['هل تبيعون البيانات؟ وماذا يُرسل لمزوّد الذكاء الاصطناعي؟', 'Do you sell data? And what is sent to the AI provider?'],
    ['لا نبيع البيانات ولا نشاركها مع معلنين، ولا إعلانات في وصال. لتوليد الإجابة نرسل إلى مزود تقني نص السؤال وآخر رسائل المحادثة، ولا نرسل معها الاسم أو البريد أو بيانات الملف الشخصي. وسياسة الخصوصية تنص على ذلك.', 'We do not sell data or share it with advertisers, and there are no ads in Wesal. To generate an answer we send a technical provider the question text and the last chat messages, without the name, email or profile data. The privacy policy states this.']);

  Q('v7', 'lamia', { hard: 1, src: 'repo' },
    ['من المسؤول إن كانت الإجابة خاطئة؟', 'Who is responsible if an answer is wrong?'],
    ['الإجابات إرشادية والمرجع النهائي هو الجهة الرسمية، وهذا مكتوب في شروط الاستخدام. نبذل جهدنا لضمان الدقة، ولا نتحمل مسؤولية قرارات تُتخذ اعتماداً على إجابات المساعد وحده. وعملياً نخفف الخطأ بالمصادر الظاهرة وبلاغات الخطأ ومراجعة المحتوى.', 'Answers are guidance and the final reference is the official entity, as the terms of use state. We do our best to ensure accuracy, and we are not responsible for decisions taken relying on the assistant’s answers alone. In practice we reduce error through visible sources, error reports and content review.']);

  Q('v8', 'raneem', { src: 'repo,hk', k: 'تحيز bias لغة محترمة respectful language' },
    ['كيف تتجنبون التحيز واللغة غير المحترمة؟', 'How do you avoid bias and disrespectful language?'],
    ['تعليمات المساعد تلزمه بمخاطبة الشخص باحترام وبصيغة «الشخص ذو الإعاقة»، وتمنع ألفاظاً مثل «معاق» و«عاجز»، وتطلب أن يُعامل الشخص إنساناً له حقوق وخيارات لا حالة. والتصميم يقوده تخصصا علم النفس والهندسة الطبية الحيوية، وتُختبر الميزات مع مستخدمين وجمعيات شريكة قبل التوسع.', 'The assistant’s instructions require addressing the person respectfully as “a person with a disability”, forbid terms like “handicapped” and “helpless”, and ask that the person be treated as a human with rights and choices, not a case. Design is led by psychology and biomedical engineering expertise, and features are tested with users and partner associations before wider rollout.']);

  /* ---------------------------------------------------------------- 7 الإتاحة وتجربة المستخدم */

  Q('a1', 'raghad', { com: 1, src: 'hk,repo', k: 'WCAG معايير الإتاحة الوصول standards accessibility كود المنصات Platforms Code' },
    ['وش معايير الإتاحة اللي تمشون عليها؟', 'Which accessibility standards do you follow?'],
    ['نمشي على معايير الإتاحة الدولية WCAG 2.2، وهي المعايير اللي تعتمد عليها الجهات الحكومية في منصاتها. وكل تعديل على الواجهة يمرّ عندنا على قائمة فحص: يشتغل بلوحة المفاتيح وحدها، ومع قارئ الشاشة، ومع تكبير حتى 200%، ومع التباين العالي والوضع الداكن وتقليل الحركة. وفيه صوت سعودي ووضع مبسّط بجمل قصيرة.', 'We follow the international accessibility standards WCAG 2.2, the standards government entities rely on for their platforms. Every interface change goes through a checklist here: it works with the keyboard alone, with a screen reader, with zoom up to 200%, and with high contrast, dark mode and reduced motion. It also has a Saudi voice and a simple mode with short sentences.'],
    ['وكل ميزة تنختبر مع مستخدمين من ذوي الإعاقة وجمعيات شريكة قبل ما نوسّع.', 'And every feature is tested with users with disabilities and partner associations before we widen it.']);

  Q('a2', 'raghad', { src: 'repo', k: 'إعدادات الوصول حجم الخط تباين dark mode font size contrast' },
    ['ما إعدادات الوصول السريعة المتاحة؟', 'What quick accessibility settings are available?'],
    ['زر واحد في الشريط العلوي يفتح لوحة «إعدادات سريعة»: حجم الخط، والتباين العالي، والوضع الداكن، وتقليل الحركة، مع رابط صفحة الإعدادات الكاملة. وتتبع الواجهة إعدادات الجهاز أولاً، واختيار المستخدم يتقدم عليها ويُحفظ.', 'One button in the top bar opens a “quick settings” panel: font size, high contrast, dark mode and reduced motion, with a link to the full settings page. The interface follows device settings first, and the user’s choice takes precedence and is saved.'],
    ['لا زر عائم يغطي المحتوى. واللوحة تلي زرها مباشرة في ترتيب القراءة، ويغلقها Escape مع عودة التركيز إلى الزر.', 'There is no floating button covering content. The panel follows its button directly in reading order, and Escape closes it and returns focus to the button.']);

  Q('a3', 'raghad', { src: 'repo' },
    ['كيف تعمل وصال مع قارئات الشاشة ولوحة المفاتيح؟', 'How does Wesal work with screen readers and the keyboard?'],
    ['كل الواجهة قابلة للتنقل بلوحة المفاتيح من أول عنصر إلى آخره. والعناصر المنسدلة تعلن حالتها لقارئ الشاشة، والنص المتحرك في العنوان يبقى كاملاً في الصفحة فيقرؤه القارئ كاملاً. وقبل الخروج التلقائي بدقيقتين تظهر رسالة «هل ما زلت هنا؟» ويمدّدها أي حركة، حسب معيار WCAG 2.2.1.', 'The whole interface can be navigated by keyboard from the first element to the last. Collapsible elements announce their state to a screen reader, and the animated heading text stays complete in the page so a reader gets it whole. Two minutes before automatic sign-out a “are you still there?” message appears and any activity extends it, per WCAG 2.2.1.']);

  Q('a4', 'raneem', { src: 'hk' },
    ['كيف شارك الأشخاص ذوو الإعاقة في التصميم؟', 'How did people with disabilities take part in the design?'],
    ['صُمّمت التجربة والهوية بقيادة تخصص علم النفس، بمراعاة اختلاف الإعاقات وراحة المستخدم، وبمشاركة تخصص الهندسة الطبية الحيوية المعني بالتقنيات المساعدة. وتُختبر كل ميزة مع مستخدمين من ذوي الإعاقة وجمعيات شريكة قبل التوسع، فتتحقق الإتاحة بشهادة من تخدمهم المنصة. وشملت تجربة النسخة التجريبية أكثر من 200 مستخدم.', 'The experience and identity were designed under psychology expertise, considering different disabilities and user comfort, with biomedical engineering expertise focused on assistive technology. Every feature is tested with users with disabilities and partner associations before wider rollout, so accessibility is validated by the people the platform serves. The beta test included more than 200 users.']);

  Q('a5', 'raghad', { src: 'repo,ar', k: 'كتابة قراءة صعوبة voice simple mode مبسط' },
    ['ماذا لمن يصعب عليه الكتابة أو القراءة؟', 'What about people who find typing or reading difficult?'],
    ['يسأل بصوته ويسمع الإجابة بصوت سعودي، ويختار الوضع المبسّط بجمل قصيرة جداً، ويكبّر الخط ويرفع التباين. وEsc يوقف الرد أو قراءته. وهذا يخدم الإعاقة الحركية والبصرية خصوصاً.', 'They can ask by voice and listen to the answer in a Saudi voice, choose the simple mode with very short sentences, enlarge the text and raise the contrast. Escape stops a reply or its read-aloud. This serves mobility and visual disabilities in particular.']);

  /* ---------------------------------------------------------------- 8 الأعمال ونموذج الإيرادات */

  Q('b1', 'lamia', { src: 'hk', k: 'من يدفع payers B2B B2G B2C customers عملاء' },
    ['من يدفع مقابل وصال؟', 'Who pays for Wesal?'],
    ['ست شرائح: الأشخاص ذوو الإعاقة وأسرهم (B2C). ومراكز التأهيل ومدارس ذوي الاحتياجات الخاصة. والجهات الحكومية والتعليمية (B2G). والشركات ضمن المسؤولية الاجتماعية (B2B وCSR). والجمعيات والمؤسسات الداعمة بالاشتراكات الجماعية المدعومة (B2B2C). ومطورو المنصات عبر تكامل API.', 'Six segments: people with disabilities and their families (B2C). Rehabilitation centers and special needs schools. Government and educational institutions (B2G). Companies through corporate social responsibility (B2B and CSR). Associations and foundations through sponsored bulk subscriptions (B2B2C). And platform developers through API integration.']);

  Q('b2', 'lamia', { src: 'ar,hk' },
    ['ما الباقات والأسعار؟', 'What are the plans and prices?'],
    ['أساسية 29 ريالاً شهرياً، واحترافية 79، ومتقدمة 179، وللمؤسسات تبدأ من 479 ريالاً شهرياً. الأساسية: البحث الذكي المبسط، ودعم قارئات الشاشة، وتحويل النص إلى صوت، وحفظ آخر محادثة. الاحترافية تضيف مساعداً صوتياً تفاعلياً، وحفظاً غير محدود للمحادثات، وتوصيات بحسب نوع الإعاقة.', 'Basic is SAR 29 a month, Pro SAR 79, Advanced SAR 179, and institutions start from SAR 479 a month. Basic: simplified smart search, screen reader support, text to speech and saving the last chat. Pro adds an interactive voice assistant, unlimited chat saving and recommendations by disability type.'],
    ['المتقدمة تضيف تحليل المستندات وتلخيصها، ودعماً ذا أولوية، وأدوات للمختصين ومقدمي الرعاية. والمؤسسات: حسابات متعددة، ولوحة تحكم إدارية، وتخصيص قاعدة المعرفة، ومدير حساب مخصص.', 'Advanced adds document analysis and summarizing, priority support and tools for specialists and caregivers. Institutions get multiple accounts, an administrative dashboard, a customized knowledge base and a dedicated account manager.']);

  Q('b3', 'lamia', { src: 'ar,hk' },
    ['ما مصادر الإيرادات المتوقعة؟', 'What are the expected revenue sources?'],
    ['أربعة مصادر خلال أول 6 أشهر من السنة الأولى: اشتراكات المدارس والجمعيات، واشتراكات الأفراد، والرعايات والشراكات، والخدمات المخصصة للجهات. وعلى المدى الأطول: تراخيص سنوية للجهات الحكومية والمؤسسات (SaaS)، واشتراكات جماعية مدعومة من الجمعيات، واشتراكات أفراد ميسّرة، وحلول لمراكز الرعاية.', 'Four sources in the first 6 months of year one: school and association subscriptions, individual subscriptions, sponsorships and partnerships, and custom services for entities. Longer term: annual SaaS licenses for government and institutions, sponsored bulk subscriptions through associations, affordable individual subscriptions, and solutions for care centers.']);

  Q('b4', 'lamia', { hot: 8, src: 'hk', k: 'تعادل break-even unit economics تكلفة المستخدم margin' },
    ['ما تكلفة المستخدم ومتى تصلون إلى التعادل؟', 'What is the cost per user and when do you break even?'],
    ['تكلفة المستخدم نحو 15 ريالاً اليوم، وتنخفض إلى 3 ريالات عند الحجم. نقدّر التعادل خلال 12 إلى 18 شهراً بعد الإطلاق، بشرط وصول 1,000 مستخدم نشط أو التعاقد مع 3 جهات حكومية أو مؤسسية. والعامل الحاسم رخص المؤسسات ذات الهامش العالي التي تغطي تكلفة السحابة وواجهات الذكاء الاصطناعي.', 'The cost per user is about SAR 15 today and falls to SAR 3 at volume. We estimate break-even 12 to 18 months after launch, once we reach 1,000 active users or 3 government or enterprise contracts. The key driver is high-margin enterprise licenses that offset cloud and AI API costs.'],
    ['سبب انخفاض التكلفة: بنية سحابية بلا بنية فيزيائية، فتصير إعادة التكرار رخيصة مع التوسع.', 'Why the cost drops: a cloud architecture with no physical infrastructure, which makes replication cheap as we scale.']);

  Q('b5', 'lamia', { src: 'ar' },
    ['ما الميزانية المطلوبة وكيف توزّع؟', 'What budget is needed and how is it split?'],
    ['الإجمالي 36 ألف ريال: البنية السحابية 8,000، وتقنيات الذكاء الاصطناعي 10,000، وتصميم الواجهات 3,000، واختبارات الاستخدام 3,000، والتسويق والإطلاق 5,000، واحتياط الطوارئ 5,000، وأدوات الموقع 2,000.', 'The total is SAR 36,000: cloud infrastructure 8,000, AI technologies 10,000, interface design 3,000, usability testing 3,000, marketing and launch 5,000, emergency reserve 5,000 and website tools 2,000.']);

  Q('b6', 'lamia', { src: 'hk' },
    ['من المنافسون وما ميزتكم؟', 'Who are your competitors and what is your advantage?'],
    ['البدائل اليوم: البوابات الحكومية، ومواقع الطيران والمطارات، والبحث العام، والاستعانة بالآخرين. ميزتنا: منصة واحدة مخصصة بدل جهات موزعة، وإتاحة مبنية في التجربة من الأساس، وسياق سعودي عربي، وإجابات موثقة بمصدرها قابلة للتنفيذ.', 'Today’s alternatives: government portals, airline and airport sites, general search and asking other people. Our advantage: one dedicated platform instead of distributed entities, accessibility built into the experience from the start, a Saudi Arabic context, and answers documented by source that people can act on.']);

  Q('b7', 'lamia', { hard: 1, src: 'hk,repo' },
    ['لماذا يدفع شخص من ذوي الإعاقة والمعلومات الحكومية مجانية؟', 'Why would a person with a disability pay when government information is free?'],
    ['قراءة المعلومة مجانية، لكن ثمنها وقت وجهد واعتماد على الآخرين. وصال تبيع إرشاداً موحداً وميسّراً وشخصياً. ولا يلزم أن يدفع الفرد دائماً: الجمعيات والمدارس والجهات والشركات تدعم اشتراكات جماعية. وأقل باقة 29 ريالاً شهرياً.', 'Reading the information is free, but it costs time, effort and dependence on others. Wesal sells unified, accessible, personalized guidance. And individuals do not always have to pay: associations, schools, entities and companies fund bulk subscriptions. The lowest plan is SAR 29 a month.'],
    ['حالياً النسخة التجريبية مجانية: تجربة فورية دون حساب بعدد محدود من الأسئلة، ورصيد أكبر بعد التسجيل يتجدد تلقائياً كل 6 ساعات.', 'The beta is currently free: an immediate trial without an account with a limited number of questions, and a larger credit after registration that renews automatically every 6 hours.']);

  Q('b8', 'lamia', { src: 'hk', k: 'go to market خطة الدخول pilot تجريبي' },
    ['ما خطة الوصول إلى المستخدمين؟', 'What is your plan to reach users?'],
    ['المرحلة الأولى: الإطلاق في 3 مراكز تأهيل ومدارس لذوي الاحتياجات الخاصة في الرياض، بدعم من تمويل الحاضنات والمسرّعات، مع 200 مستخدم تجريبي لاختبار الدقة وضبط الأداء. ثم التوسع الإقليمي في المنطقة الوسطى والغربية والشرقية. ثم تغطية المملكة كلها ثم دول الخليج. وهدف السنة الأولى 1,000 مستخدم مسجل وشراكة مع 3 جمعيات.', 'Phase one: launch in 3 major rehabilitation centers and special needs schools in Riyadh, backed by incubator and accelerator funding, with 200 pilot users to validate accuracy and tune performance. Then regional rollout across the Central, Western and Eastern provinces. Then the whole Kingdom and the Gulf. The year-one target is 1,000 registered users and partnerships with 3 associations.']);

  Q('b9', 'lamia', { src: 'hk' },
    ['كيف تموّلون المرحلة الأولى؟', 'How will you fund the first phase?'],
    ['من الحاضنات والمسرّعات، والمنح وجوائز الابتكار، ورعايات المسؤولية الاجتماعية، ومنها تمويل تجريبي مبكر من قطاعي الاتصالات والبنوك. ثم تتحول الإيرادات إلى تراخيص سنوية للجهات الحكومية والمؤسسات، واشتراكات جماعية عبر الجمعيات، واشتراكات أفراد ميسّرة، وحلول لمراكز الرعاية.', 'From incubators and accelerators, grants and innovation awards, and corporate social responsibility sponsorships, including early pilot funding from telecom and banking. Revenue then shifts to annual licenses for government and institutions, bulk subscriptions through associations, affordable individual subscriptions and solutions for care centers.']);

  /* ---------------------------------------------------------------- 9 الأثر والنتائج والتحقق */

  Q('i1', 'lamia', { hot: 9, src: 'hk', k: 'نتائج results beta 200 users 92% 95% TRL' },
    ['ما نتائج النسخة التجريبية؟', 'What results did the beta achieve?'],
    ['اختُبرت نسخة عاملة بمهام فعلية مع أكثر من 200 مستخدم من ذوي الإعاقة. أتم 92% منهم مهامهم بنجاح. ووصلوا إلى ما يحتاجونه أسرع بنسبة 95% من البحث اليدوي. وكل إجابة قابلة للتتبع إلى مصدرها. ومستوى النضج TRL 6: نسخة وظيفية في بيئة تشغيل حقيقية.', 'A working version was tested on real tasks with more than 200 users with disabilities. 92% of them completed their tasks successfully. They reached what they needed 95% faster than with manual search. Every answer can be traced to its source. Maturity is TRL 6: a functional version in a real operating environment.'],
    ['ما قيس: إتاحة المنصة، وصلة الإجابة، وتتبع المصدر، وسهولة الاستخدام.', 'What was measured: platform accessibility, answer relevance, source traceability and ease of use.']);

  Q('i2', 'ahmad', { src: 'hk' },
    ['كيف قسمتم هذه النتائج؟', 'How did you measure these results?'],
    ['باختبار قائم على المهام، وبعد كل جلسة استبيان داخل المنصة، وتحليلات الاستخدام: الجلسات ونسبة الإجابات الموثقة والوقت الموفَّر مقابل البحث اليدوي. وسنكرر البروتوكول نفسه عند القياس اللاحق ونراجعه كل ربع سنة مع الجمعيات الشريكة.', 'Through task-based testing, a post-session survey inside the platform, and usage analytics: sessions, share of cited answers and time saved versus manual search. We will repeat the same protocol in later measurement and review it every quarter with partner associations.']);

  Q('i3', 'lamia', { src: 'hk', k: 'أهداف السنة الأولى targets KPI year one مؤشرات' },
    ['ما أهدافكم للسنة الأولى؟', 'What are your year-one targets?'],
    ['1,000 مستخدم مسجل، وشراكة مع 3 جمعيات، واستمرار نجاح المهام عند 90% أو أعلى على مدى 12 شهراً، مع إجابات موثقة بالمصدر بنسبة 100% ورضا المستخدمين 4.5 من 5 أو أعلى. والتغطية المستهدفة تشمل الإعاقة البصرية والسمعية والحركية والإدراكية.', '1,000 registered users, partnerships with 3 associations, task success sustained at 90% or higher over 12 months, 100% of answers cited by source and user satisfaction at 4.5 out of 5 or higher. The target coverage includes visual, hearing, mobility and cognitive disabilities.'],
    ['من 92% في الاختبار (+200 مستخدم) إلى 90% أو أعلى مستداماً مع 1,000 مستخدم نشط.', 'From 92% in testing (200+ users) to a sustained 90% or higher with 1,000 active users.']);

  Q('i4', 'lamia', { src: 'hk', k: 'أثر impact scale اجتماعي اقتصادي' },
    ['ما الأثر المتوقع على نطاق أوسع؟', 'What impact do you expect at scale?'],
    ['لنحو 1.35 مليون شخص من ذوي الإعاقة تصير الخدمات الرقمية متاحة بالتصميم لا عائقاً. إنسانياً: استقلالية وجودة حياة أعلى. اجتماعياً: اعتماد أقل على مقدمي الرعاية في المعلومات الأساسية. تعليمياً واقتصادياً: وصول أسهل للتعليم والتوظيف والفرص. تقنياً: نموذج وطني للذكاء الاصطناعي العربي الشامل والمسؤول.', 'For about 1.35 million people with disabilities, digital services become accessible by design rather than a barrier. Human: more independence and quality of life. Social: less reliance on caregivers for basic information. Educational and economic: easier access to education, employment and opportunities. Technological: a national model for inclusive, responsible Arabic AI.']);

  Q('i5', 'lamia', { src: 'hk,ar', k: 'رؤية 2030 vision 2030 alignment' },
    ['كيف تتصل وصال برؤية 2030؟', 'How does Wesal connect to Vision 2030?'],
    ['تدعم التزام الرؤية بالمشاركة الكاملة للأشخاص ذوي الإعاقة، وتتماشى مع معايير الوصول الرقمي WCAG 2.2 ودليل الشمولية الرقمية في المملكة. وفي 2030 نخطط لقياس الأثر الاجتماعي والاقتصادي ومواءمة المؤشرات مع الرؤية.', 'It supports the Vision’s commitment to the full participation of people with disabilities, and aligns with the WCAG 2.2 digital accessibility standards and the Kingdom’s digital inclusion guide. In 2030 we plan to measure the social and economic impact and align indicators with the Vision.']);

  Q('i6', 'ahmad', { src: 'hk,ar' },
    ['ماذا ستقيسون بعد الإطلاق؟', 'What will you measure after launch?'],
    ['نسبة إتمام المهام، ونسبة الإجابات الموثقة، والوقت الموفَّر مقابل البحث اليدوي، ورضا المستخدمين، مع مراجعة ربع سنوية مع الجمعيات الشريكة. وللجهات نقيس العوائق التي يواجهها المستخدم عند الوصول للخدمة، ونرصد الخدمات الأكثر حاجة للتحسين، ونزودها بمؤشرات لرفع جاهزية خدماتها.', 'Task completion rate, share of cited answers, time saved versus manual search and user satisfaction, with a quarterly review with partner associations. For entities, we measure the barriers users face when reaching a service, track the services most in need of improvement, and give them indicators to raise their service readiness.']);

  Q('i7', 'lamia', { hard: 1, src: 'hk' },
    ['من هم الـ200 مستخدم وكيف اخترتموهم؟', 'Who were the 200 users and how did you choose them?'],
    ['اختبرنا نسخة عاملة بمهام فعلية مع مستخدمين من ذوي الإعاقة، ونقيس الإتاحة وصلة الإجابة وتتبع المصدر وسهولة الاستخدام. ونكرر بروتوكول الاختبار نفسه في القياس اللاحق ليكون الأثر قابلاً للمقارنة.', 'We tested a working version with real tasks with users with disabilities, measuring accessibility, answer relevance, source traceability and ease of use. We repeat the same test protocol in later measurement so the impact can be compared.']);

  /* ---------------------------------------------------------------- 10 الاستدامة والتوسع والفريق */

  Q('f1', 'lamia', { src: 'ar', k: 'خطة زمنية roadmap timeline 2026 2030' },
    ['ما خطتكم من 2026 إلى 2030؟', 'What is your plan from 2026 to 2030?'],
    ['2026: التأسيس والنموذج الأولي، واختبار عملي مع عينة من ذوي الإعاقة، وأولى الشراكات مع الجمعيات. 2027: الإطلاق الرسمي وقاعدة الخدمات الأكثر حرجاً، وتكامل API مع أول جهتين أو ثلاث. 2028: ربط مباشر بالجهات الحكومية والخاصة وتوسيع القطاعات: الصحة والتعليم والنقل والعمل. 2029: فئات إضافية وأدوات نفاذية رقمية وذكاء اصطناعي للتنبؤ بالاحتياجات. 2030: منصة وطنية معتمدة للتمكين الرقمي وقياس الأثر.', '2026: foundation and prototype, practical testing with a sample of people with disabilities, and first partnerships with associations. 2027: official launch and a base of the most critical services, with API integration with the first two or three entities. 2028: direct links to government and private entities and expansion into health, education, transport and work. 2029: additional groups, digital accessibility tools and AI to anticipate needs. 2030: an approved national platform for digital empowerment and impact measurement.']);

  Q('f2', 'lamia', { src: 'hk,ar', k: 'فريق team members' },
    ['من فريق وصال وما دور كل عضو؟', 'Who is on the Wesal team and what does each person do?'],
    ['خمسة أعضاء. لمياء الشهراني: طب وجراحة، تقود الفريق والإشراف الطبي. هند يحيى آل مفرح: صيدلة، تطوير المحتوى الطبي ومراجعته. رنيم سعيد النجيمي: أخصائي نفسي، تصميم التجربة والهوية. أحمد سامي: مهندس ذكاء اصطناعي، النموذج والمنصة. رغد محمد العسيري: هندسة طبية حيوية، التقنيات المساعدة والإتاحة.', 'Five members. Lamia Alshahrani: medicine, leads the team and medical supervision. Hind Yahya Al Mufarrih: pharmacy, medical content development and review. Raneem Saeed Alnujaymi: psychologist, experience and identity design. Ahmad Sami: AI engineer, model and platform. Raghad Mohammed Alasiri: biomedical engineering, assistive technology and accessibility.']);

  Q('f3', 'lamia', { src: 'ar,hk' },
    ['لماذا هذا الفريق مناسب لهذه المشكلة؟', 'Why is this team the right one for this problem?'],
    ['لأن المشكلة تجمع الطب والصيدلة وعلم النفس والذكاء الاصطناعي والتقنيات المساعدة، وفي الفريق متخصص في كل منها. جمعنا شغف واحد: تمكين التقنية والابتكار لصنع أثر مستدام للأشخاص ذوي الإعاقة.', 'Because the problem spans medicine, pharmacy, psychology, artificial intelligence and assistive technology, and the team has a specialist in each. One passion brought us together: using technology and innovation to create lasting impact for people with disabilities.']);

  Q('f4', 'lamia', { src: 'hk,ar', k: 'خليج توسع expansion GCC' },
    ['كيف تتوسعون إلى الخليج وما بعده؟', 'How will you expand to the Gulf and beyond?'],
    ['التوسع بالترتيب: السعودية ثم الخليج ثم العالم. والبنية مرنة تسمح بتخصيص المحتوى والخدمات لكل دولة بسهولة، وهي سحابية بلا بنية فيزيائية، فتنخفض تكلفة المستخدم من 15 إلى 3 ريالات عند الحجم. والقيد الرئيسي: تحسين تكلفة البنية واستدعاءات API.', 'Expansion in order: Saudi Arabia, then the Gulf, then the world. The architecture is flexible enough to customize content and services for each country, and it is cloud-based with no physical infrastructure, so the cost per user falls from SAR 15 to SAR 3 at volume. The key constraint is optimizing infrastructure and API costs.']);

  Q('f5', 'lamia', { src: 'ar', k: 'استدامة sustainability' },
    ['كيف تستدام وصال؟', 'How does Wesal sustain itself?'],
    ['ثلاث استدامات. استدامة المعرفة: تحديث القاعدة مع تغير الأنظمة والخدمات، وتوثيق المعلومات من مصادر رسمية، وتحويل ملاحظات المستخدمين إلى معرفة. استدامة الأثر: قياس العوائق وتزويد الجهات بمؤشرات. استدامة النمو: شراكات مع الجهات المقدمة للخدمات، وحلول لقياس إتاحتها، ونمو مع توسع الخدمات والمستخدمين.', 'Three sustainabilities. Knowledge: continuously updating the base as regulations and services change, documenting information from official sources and turning user feedback into knowledge. Impact: measuring barriers and giving entities indicators. Growth: partnerships with service providers, solutions to measure their accessibility, and growth with the expansion of services and users.']);

  Q('f6', 'lamia', { hard: 1, src: 'hk,repo' },
    ['ما أكبر المخاطر التي تواجهكم؟', 'What are the biggest risks you face?'],
    ['خمسة: دقة المعلومات وقِدمها، ونعالجها بالمصادر الظاهرة والمراجعة الدورية وبلاغات الخطأ. والاعتماد على مزودي النماذج، ونعالجه بالتبديل والبدائل التلقائية. وتكلفة الواجهات البرمجية عند التوسع، ونعالجها بالسقوف والحفظ المؤقت وتراخيص المؤسسات. وحساسية البيانات، ونعالجها بالصلاحيات والحد الأدنى من البيانات. وضيق الفئات المخدومة اليوم، ونعالجه بالتوسع التدريجي بعد الاختبار.', 'Five. Accuracy and staleness of information, which we address with visible sources, periodic review and error reports. Dependence on model providers, addressed by swapping and automatic fallbacks. API cost when scaling, addressed by caps, caching and enterprise licenses. Data sensitivity, addressed by permissions and data minimization. And the narrow set of groups served today, addressed by gradual expansion after testing.']);

  Q('f7', 'lamia', { hard: 1, src: 'repo,hk', k: 'ضعف نقاط الضعف weaknesses limitations limits' },
    ['ما نقاط ضعف وصال اليوم؟', 'What are Wesal’s weaknesses today?'],
    ['نسخة تجريبية لا منتج نهائي. تخدم الإعاقة الحركية والبصرية فقط حتى الآن. وقاعدة معرفتها محدودة بما انتقيناه من مصادر رسمية. والمحادثات المحفوظة تبقى في متصفح واحد ولا تنتقل بين الأجهزة. ولا تقدم تشخيصاً ولا تحل محل القنوات الرسمية. نقولها بوضوح لأن الثقة في مجالنا تُبنى على الصدق.', 'It is a beta, not a final product. It serves mobility and visual disabilities only so far. Its knowledge base is limited to what we curated from official sources. Saved chats stay in one browser and do not move across devices. It gives no diagnosis and does not replace official channels. We say this plainly because trust in our field is built on honesty.']);

  Q('f8', 'lamia', { src: 'hk' },
    ['ماذا تحتاجون من اللجنة والجهات الداعمة؟', 'What do you need from the committee and supporting bodies?'],
    ['اقتراحات من خطتنا: تعريف بجمعيات ومراكز تأهيل نجرب معها المرحلة الأولى في الرياض. وتمويل أو احتضان يغطي الميزانية المقدّرة. وفتح قنوات تكامل مع الجهات الخدمية لربط الخدمات عبر API. وشراكة لقياس الأثر وتدقيق الإتاحة.', 'Suggestions from our plan: introductions to associations and rehabilitation centers to run the Riyadh first phase with. Funding or incubation covering the estimated budget. Integration channels with service entities to connect services through API. And a partnership to measure impact and audit accessibility.']);

  window.JUDGES = {
    contact: contact,
    project: project,
    sources: sources,
    team: team,
    clusters: clusters,
    facts: facts,
    items: items
  };
})();
