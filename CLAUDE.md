# وصال

قبل كتابة أي نص يظهر للمستخدم أو تعديله (الصفحات، والتنبيهات، ورسائل الأخطاء،
والبريد، وردود المساعد الجاهزة) اقرأ قسم «كتابة النصوص الظاهرة للمستخدم» في
README.md والتزم به، ثم شغّل:

```bash
php tools/check-copy.php
php tools/check-landing.php
```

## خريطة المشروع

### الفكرة
منصة ذكاء اصطناعي عربية تجيب الأشخاص ذوي الإعاقة في السعودية عن الحقوق والخدمات والتقنيات المساعدة.
ثلاثة أنظمة في مستودع واحد: (1) المنصة العامة PHP+MySQL بلا بناء، (2) صفحة الشركة `homepage/`، (3) `workspace/` تطبيق Laravel 12/PHP 8.4 داخلي لإدارة المشاريع.

### المنصة العامة (جذر المستودع)
- `index.html` (~5900 سطر، تطبيق صفحة واحدة، CSS/JS مضمّنان). الصفحات (`pages` في `go()`): home, chat, settings, dashboard, resources, about, contact, login, register, privacy, terms, faq, forgot, reset.
- لوحة التحكم: تبويبات overview, chats, users, messages, tickets, content (محرر صفحة الهبوط), experience (استبيانات), audit, profile؛ تُظهَر حسب `data-perm`.
- صفحات مستقلة: login.html (/login)، survey.html (استبانة بدعوة أو رابط عام)، ticket.html (متابعة تذكرة برمز)، hello.html (/hello بوث NFC بهاكاثون ذوي الإعاقة)، hello-wall.html (/hello/wall شاشة الجناح)، judges.html (/judges أسئلة التحكيم، داخلية noindex)، corporate.html (تحويل 301 إلى /homepage/).
- `api/`: db.php (اتصال+جلسة+ensureSchema+أدوار+حدود)، session-lib.php (جلسة موحدة auth_sessions + كوكي wesal_auth)، auth.php، chat.php وchat-stream.php (SSE) وchat-shared.php (برومبت `chatSystemPrompt()`، الحدود، المصادر) وchat-stream-providers.php، rag.php (kb_chunks)، voice.php/voice-lib.php (Groq ثم Azure ثم المتصفح)، admin.php، content.php + landing-schema.php (محتوى الهبوط live/draft)، tickets.php + cron-tickets.php (SLA وتذكيرات وإغلاق تلقائي)، surveys.php، invite-redeem.php، demo.php، contact.php، notifications.php، hello-visit.php، diag.php، stream-test.php.
- المزوّدون: Gemini أساسي، وقابل للتبديل OpenAI/Claude/Kimi/ALLaM (خادم ذاتي، نافذة 4096) عبر `AI_PROVIDER`، مع بديل محلي في الواجهة.
- قاعدة البيانات (`schema.sql` + `ensureSchema()` معاً): users, chat_logs, kb_chunks, messages, support_tickets, invites, invitations, password_resets, site_content, landing_content, audit_log, rate_limits, surveys + survey_questions/options/invitations/submissions/answers/answer_options, survey_responses, auth_sessions, account_links, hello_visits.
- الأدوار الاثنا عشر: user, reviewer, mod, admin(=sysadmin) للمحادثة، و executive, pm, finance, medical, team_member, client, hr, crm للمساحة. أول حساب في قاعدة فارغة = مدير نظام. مصفوفة الدعوة يفرضها الخادم.
- حدود: لكل دقيقة/IP، يومي لكل IP، يومي للمنصة (قاطع دائرة). `TRUST_PROXY` فقط خلف وكيل.
- الخروج التلقائي: 30د/24س للمستفيد، 15د/12س للأدوار، والخادم هو الحكم.

### التوجيه والنشر
- `.htaccess`: ترويسات أمان وCSP (unsafe-inline) وروابط /login /hello /hello/wall /judges /invite/{token}. التبديل يوم 13 أكتوبر بوجود ملف `.switch`: الجذر = صفحة الشركة، المحادثة على chat.wesalinnovation.sa.
- النشر: دفع إلى main ← GitHub Actions (deploy.yml) ← SSH إلى Hostinger ← `deploy.sh` (قائمة سماح SYNC_DIRS/SYNC_FILES، يحمي config.php وuploads وjudges-notes.js، يستدعي `workspace/deploy.sh` متى وُجد .env). أي صفحة عامة جديدة تُضاف إلى sitemap.xml وإلى SYNC_FILES.
- `workspace.yml`: اختبارات Laravel على SQLite ثم MariaDB.

### صفحة الشركة `homepage/`
index.html + assets/home.css/home.js. الأقسام: hero, products, services (ثلاثة مجالات), about, method (خمس خطوات), partners (هاكاثونات), proof, contact, footer.

### مساحة العمل `workspace/` (Laravel)
- مشاريع (مسودة→بانتظار الاعتماد→معتمد→قيد التنفيذ→منجز) وأعضاء ومراحل ومهام (كانبان) ومرفقات خاصة، عقود، أوامر شراء (عتبة اعتماد تنفيذي 50,000)، فواتير (INV-السنة-0001، بالهللات)، شهادات إنجاز/مشاركة، بطاقة رقمية، إفادات وظيفية، طلبات توظيف، تقارير لكل دور، محتوى صحي بمراجعة طبية (/kb/{id}) وتنبيهات حساسية، صحة النظام وتكامل البريد والذكاء، /verify للتحقق العام.
- تسعة أدوار في `config/roles.php` (مصدر واحد للتبويبات). 25 موديلاً، 10 سياسات، 20 Enum، 33 ترحيلاً، 39 ملف اختبار. أوامر: workspace:create-sysadmin, platform:link-accounts, content:export-kb.
- دخول موحد مطفأ افتراضياً: `UNIFIED_SESSION` (المنصة) و`UNIFIED_AUTH` (المساحة).

### الأدوات `tools/`
check-copy.php (لا شرطات في النصوص)، check-landing.php (سجل الهبوط = index.html)، check-roles/invites/auth-session/session/sources/voice/allam/stream/beta (PHP)، check-*-ui.js وbuild-judges-pdf.js (Playwright)، check-routes.sh، rag/ingest-kb.php، seed-demo.php، source-logo.php.

### قواعد الكتابة (CLAUDE.md + README)
قبل أي نص ظاهر: اقرأ «كتابة النصوص الظاهرة للمستخدم». لا — ولا –، فصحى ميسّرة (المساعد وحده بلهجة سعودية بيضاء)، جمل قصيرة، لا ادعاء بلا دليل، «الأشخاص ذوو الإعاقة». ثم شغّل `php tools/check-copy.php` و`php tools/check-landing.php`.

### فخاخ معروفة
- تعديل البنية: في schema.sql **و** ensureSchema().
- ثلاث قيم متزامنة بين config.php وindex.html: GUEST_LIMIT, USER_TOKENS, RENEW_HOURS/RENEW_MS.
- إضافة حقل لصفحة الهبوط: landingSchema() + data-lp في index.html ثم check-landing.
- عند تعديل assets/landing-editor.* ارفع LPE_VER. عند تعديل أسئلة التحكيم أعد توليد ملفي PDF. لا تضع حقل `v` في judges-data.js.
- judges-notes.js خارج Git عمداً (المستودع عام).
- أي تغيير في /hello: العدّاد يحتاج `TRUNCATE hello_visits` قبل الحدث.
