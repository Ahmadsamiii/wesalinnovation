# وصال

قبل كتابة أي نص يظهر للمستخدم أو تعديله (الصفحات، والتنبيهات، ورسائل الأخطاء،
والبريد، وردود المساعد الجاهزة) اقرأ قسم «كتابة النصوص الظاهرة للمستخدم» في
README.md والتزم به، ثم شغّل:

```bash
php tools/check-copy.php
php tools/check-landing.php
```
## خريطة المشروع

آخر مراجعة: 27 نوفمبر 2026، بقراءة كل ملفات المستودع. ما تحت «علل متحقق منها» فُحص على الشيفرة نفسها، وما تحت «ملاحظات لم تُتحقق» جاء من القراءة فقط فتُراجَع قبل أي إصلاح.

### الفكرة والأنظمة
منصة ذكاء اصطناعي عربية تجيب الأشخاص ذوي الإعاقة في السعودية عن الحقوق والخدمات والتقنيات المساعدة. ثلاثة أنظمة في مستودع واحد:
1. **المنصة العامة**: PHP 8 وMySQL بلا composer ولا npm. الواجهة `index.html` (نحو 5900 سطر).
2. **صفحة الشركة** `homepage/`: `index.html` و`assets/home.css` و`assets/home.js`.
3. **مساحة العمل** `workspace/`: Laravel 13.31 وPHP 8.4 (لا 12)، Blade وAlpine وTailwind 3.4، لإدارة المشاريع الداخلية. فيها 47 ملف اختبار (39 Feature و7 Auth و1 Unit)، و25 نموذجاً و10 سياسات و20 Enum و33 ترحيلاً.

### المنصة العامة
- **الصفحات في `index.html`** (`pages` في `go()`): home, chat, settings, dashboard, resources, about, contact, login, register, privacy, terms, faq, forgot, reset. تبويبات اللوحة: overview, chats, profile (بلا صلاحية)، users.view, messages.view, tickets.view, content.manage, experience.view, audit.view.
- **صفحات مستقلة**: `login.html` (/login) و`survey.html` (?t= دعوة 40 hex، ?s= رابط عام) و`ticket.html` (?token= 32 hex) و`hello.html` (/hello بوث NFC) و`hello-wall.html` (/hello/wall) و`judges.html` (/judges داخلية بلا فهرسة، 83 سؤالاً في 10 أقسام) و`corporate.html` (نسخة قديمة كاملة تُحوَّل 301 إلى /homepage/).
- **api/**: `db.php` يُضمَّن في كل نقطة عدا `hello-visit.php` و`stream-test.php`، ويحوي الاتصال والجلسة وensureSchema والأدوار والحدود والبريد. `session-lib.php` للجلسة الموحدة. `auth.php` و`admin.php` و`tickets.php` و`cron-tickets.php` و`surveys.php` و`content.php` و`landing-schema.php` و`notifications.php` و`contact.php` و`demo.php` و`invite-redeem.php`. المحادثة: `chat.php` و`chat-stream.php` (SSE) و`chat-shared.php` و`chat-stream-providers.php` و`rag.php`. الصوت: `voice.php` و`voice-lib.php`.
- **الجلسة**: 30 دقيقة خمولاً و24 ساعة حداً أقصى للمستفيد، و15 دقيقة و12 ساعة لفريق المنصة ولكل صاحب `org_role`. الخادم هو الحكم بهامش 120 ثانية. `UNIFIED_SESSION` مطفأ افتراضياً.
- **الأدوار الاثنا عشر**: user, reviewer, mod, admin للمحادثة (admin = sysadmin)، وexecutive, pm, finance, medical, team_member, client, hr, crm للمساحة. الدور الفعلي: admin ثم org_role ثم role. أول حساب في قاعدة فارغة يصبح admin. مصفوفة الدعوة يفرضها الخادم (`invitableRoles`).
- **المحادثة**: Gemini أساسي. ترتيب المزوّدين: gemini ثم openai احتياطاً، وopenai ثم gemini، وclaude وkimi ثم gemini، وallam وحده إلا مع `ALLAM_FALLBACK`. الخصم من الرصيد يسبق نداء المزوّد. تكلفة السؤال من 1 إلى 3. أحداث البث: delta وsuggestions وsources وdone وerror. بعد أول delta لا بديل أبداً.
- **قاعدة البيانات**: `schema.sql` لا يكفي وحده. جدولا `users` و`chat_logs` فيه فقط، وجداول التذاكر والإشعارات و`cron_runs` تُبنى في `ensureSchema()` (`migrateTickets` وغيرها). عند أي تعديل على البنية حدّث الاثنين.
- **ثوابت متزامنة مع الواجهة**: `GUEST_LIMIT=5` و`USER_TOKENS=30` و`RENEW_HOURS=6` في `config.php` تقابل `RENEW_MS` في `index.html`. وثوابت بلا قيمة افتراضية في `db.php` (يقع Fatal إن غابت من config قديم): DB_*, SITE_NAME, SITE_URL, AI_PROVIDER, GEMINI_KEY, GEMINI_MODEL, MAIL_FROM, MAIL_FROM_NAME, CONTACT_TO, CRON_SECRET.
- **التذاكر**: حالات new, in_progress, waiting, escalated, resolved, closed, reopened. مستويات none, agent, lead, exec. SLA: critical ساعة و4 ساعات على مدار الساعة، وhigh 4 و8، وnormal 8 و24، وlow 16 و40 بساعات عمل الأحد إلى الخميس 8ص إلى 6م بتوقيت الرياض.

### التوجيه والنشر
- `.htaccess`: ترويسات أمان وCSP بـ unsafe-inline، وروابط /login و/hello و/hello/wall و/judges و/invite/{token}. التبديل بملف `.switch` على الخادم: الجذر صفحة الشركة والمحادثة على chat.
- الدفع إلى `main` ينشر تلقائياً: `deploy.yml` ثم SSH إلى Hostinger ثم `deploy.sh` (قائمة سماح `SYNC_DIRS` و`SYNC_FILES`، يحمي config.php وuploads وjudges-notes.js). **لا يشترط نجاح أي فحص**، ولا يُختبر `api/` ولا `tools/` في CI. صفحة عامة جديدة تُضاف إلى sitemap.xml وإلى `SYNC_FILES`.
- لا تتوفر في جلسات Claude السحابية بيانات الاستضافة ولا SSH. `api/config.php` غير موجود محلياً.

### الأدوات `tools/`
`check-copy.php` يفحص 8 صفحات HTML و`homepage/` وأربعة ملفات JS وكل `api/*.php` (عدا diag وstream-test)، **ولا يفحص `workspace/` ولا `tools/` ولا Blade**. العبارات الجاهزة تنبيهات لا أخطاء. `check-landing.php` يطابق `landing-schema.php` مع `index.html`. فحوص بلا قاعدة بيانات: check-copy, check-landing, check-sources, check-voice, check-stream, check-allam, check-judges.js. فحوص تحتاج قاعدة ومحلية فقط: check-roles, check-session, check-auth-session, check-beta, check-invites، وفحوص `*-ui.js` بـ Playwright (BASE محلي وإلا خروج 2). `exit("نص")` في PHP يرجع 0، فغياب config.php يبدو نجاحاً في عدة فحوص. أدوات غير مذكورة في README: kscdr-scan.php وexport-chat-training-data.php وrag/collect-disability-sources.ipynb وtraining/wesal-finetune.ipynb وjudges-pdf-hash.js.

### مساحة العمل
- تسعة أدوار في `config/roles.php` (مصدر واحد للتبويبات). أوامر: `workspace:create-sysadmin` و`platform:link-accounts` (تقرير جاف ثم `--apply --plan`، وفيه `--undo`) و`content:export-kb`.
- الحساب بالهللات في المعاينة (`app.js`) والتخزين `decimal:2` بالريال. الضريبة 15%. عتبة اعتماد أمر الشراء التنفيذي 50000 (أكبر تماماً). الأرقام: INV عند الإصدار، وPO وCT وCERT وHIR عند الإنشاء، وREF عند الاعتماد.
- لا تضف دوراً إلا في `config/roles.php`. لا تشغّل `DatabaseSeeder` في الإنتاج (تسعة حسابات بكلمة مرور password).

### قواعد الكتابة
قبل أي نص ظاهر اقرأ «كتابة النصوص الظاهرة للمستخدم» في README. لا شرطة طويلة ولا قصيرة، فصحى ميسّرة (المساعد وحده بلهجة سعودية بيضاء)، جملة قصيرة، لا ادعاء بلا دليل. ثم شغّل `check-copy.php` و`check-landing.php`.

### فخاخ معروفة
- ثلاث قيم متزامنة بين config.php وindex.html (انظر أعلاه). إضافة حقل لصفحة الهبوط: `landingSchema()` و`data-lp` ثم `check-landing`. تعديل `assets/landing-editor.*` يستلزم رفع `LPE_VER`.
- بعد أي تعديل على أسئلة التحكيم أو ورق الطباعة أعد توليد ملفي PDF. لا تضع حقل `v` في `judges-data.js`. `judges-notes.js` خارج Git عمداً (المستودع عام).
- تصفير عدّاد /hello بـ `TRUNCATE hello_visits` لا `DELETE`.
- الدخول الموحد: مساحة العمل تكتب في `auth_sessions` بالمنصة، وتعديل الدور أو الاسم محلياً قد يُمحى بمزامنة المنصة.

### علل متحقق منها (تنتظر قراراً)
1. `api/stream-test.php` عام بلا مصادقة، ويستدعي Gemini بمفتاح الإنتاج عند `?sse=2`، ولا يحجبه `.htaccess`، ويُنشر لأن `api` ضمن `SYNC_DIRS`. README نفسه يقول إنه يُحذف بعد الاختبار.
2. أجوبة `assets/judges-data.js` (te3 وv5 وv6 وai1 وb4) تدّعي أن النموذج ALLaM على خوادمنا داخل المملكة وأنه لا يُرسل نص السؤال لمزوّد خارجي. الشيفرة: Gemini أساسي، والصوت Groq أو Azure، و`ragEmbed()` في `api/rag.php:20-25` يرسل نص السؤال إلى Google متى وُجد `GEMINI_KEY` أياً كان المزوّد (وREADME يقر بذلك). والنشر على Hostinger. صحّحوا الأجوبة قبل أي جلسة تحكيم.
3. `api/chat-shared.php:105` خصم الرصيد بلا شرط `tokens>=?` فالتوازي قد يهبط تحت الصفر.
4. `api/contact.php` لا يستدعي `ensureSchema()` ويرسل بـ `@mail()` لا SMTP. وتقييم الزائر (`index.html:2433`) يرسل بريداً فارغاً فيرفضه الخادم حسب التقرير ويضيع التقييم مع «شكراً لتقييمك».
5. `schema.sql` يحوي `support_tickets` بصيغته القديمة (open وdone)، فالتذاكر لا تعمل منه وحده.
6. `index.html`: مؤشر القوة في صفحة إعادة التعيين (س2233 و2237) يحدّث عناصر التسجيل لا `pwBar2` و`pwHint2`.
7. `workspace`: صفحة `/kb/{id}` العامة تعرض `category` و`source_url` من السجل القابل للتحرير لا من النسخة المنشورة، و`update()` يغيّرهما فوراً. وتعديل منشور يعيده مسودة فيسقط من قائمة المراجعة الدورية (`ContentReviewController:27-30` يشترط Approved) فيبقى منشوراً بعد انتهاء 12 شهراً.
8. `workspace/certificates/show.blade.php:32` يطبع `type->label()` لا العنوان المخصص.
9. `workspace`: 55 ملف Blade فيها شرطة طويلة أو قصيرة، بعضها نص ظاهر (مثل `invoices/print.blade.php:7` و`kb/show.blade.php:6`)، ولا يفحصها `check-copy.php`.
10. `workspace/resources/views/components/form/textarea.blade.php` (ومثلها input وselect) تقرأ `old()` بالاسم وحده، و`medical/alerts.blade.php:58-60` يكرر الحقول نفسها لكل تنبيه فقد تتسرب القيم والأخطاء بين البطاقات.
11. `deploy.yml` لا يشترط نجاح أي فحص قبل النشر.

### ملاحظات لم تُتحقق (من القراءة فقط)
- `smallTalkReply` قد يعترض أسئلة حقيقية («من انتهت صلاحية بطاقتي»، «شكراً كيف أقدّم...») ويجيب بجواب جاهز بلا خصم.
- الواجهة: رد HTML بخطأ 5xx على البث يُعد التزاماً فلا يسقط إلى `chat.php`، ورد `ok:false` بحد المعدل يُعرض جواباً محلياً (`index.html:3251-3319`).
- `migrateMessagesToTickets` (`db.php:667`) تحوّل رسائل التواصل إلى تذاكر مغلقة، فتزدوج الصناديق.
- `must_change_pw` يُفرض في الواجهة فقط، والكلمة المؤقتة بلا انتهاء.
- حذف الحساب لا ينظف `ticket_entries` وغيرها، والرسالة تدّعي حذفاً نهائياً.
- `AccountLinker`: التراجع يحدّث `reverted_at` ولا يحذف الصف، والمفتاحان `uq_ws` و`uq_pl` فريدان، فإعادة الربط بعد التراجع قد تفشل (المفتاحان مؤكدان في المخطط، والسلوك لم يُجرَّب).
- `hello-wall.html:190` مؤقت 960ms قد يصفّر خانة بعد تغيرها في الوضع السريع.
- `homepage/assets/home.js:270` يحذف `aria-describedby` عند أول إدخال.
- `tools/check-invites.php` و`check-auth-session.php` يصفّران `rate_limits` ويجرّبان `RENAME TABLE`، فلا يُشغَّلان على قاعدة مشتركة.
