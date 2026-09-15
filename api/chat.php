<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rag.php';
rateLimit('chat', 30);

$in      = body();
$message = clean($in['message'] ?? '', 1500);
$mode    = ($in['mode'] ?? 'simple') === 'detailed' ? 'detailed' : 'simple';
$history = is_array($in['history'] ?? null) ? array_slice($in['history'], -6) : [];

if (mb_strlen($message) < 2) fail('اكتب سؤالك أولاً.');

/* ---------- تطبيع عربي للمطابقة ---------- */
function normAr(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);
    $s = str_replace(['أ','إ','آ','ٱ'], 'ا', $s);
    $s = str_replace(['ى','ئ'], 'ي', $s);
    $s = str_replace('ة', 'ه', $s);
    $s = str_replace('ؤ', 'و', $s);
    $s = preg_replace('/[؟?!.،,]+/u', ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

/* ---------- ردود فورية للتحيات والمجاملات — منطقية دائماً ولا تستهلك رصيداً ---------- */
function smallTalkReply(string $raw): ?string {
    $t = normAr($raw);
    $w = $t === '' ? 0 : count(explode(' ', $t));
    $short = $w <= 5;

    if (preg_match('/سلام عليكم|^سلام$|^السلام$/u', $t))
        return "وعليكم السلام ورحمة الله وبركاته، حيّاك الله في وصال.\nكيف أقدر أخدمك اليوم؟ اسألني عن حقوقك، الخدمات المتاحة لك، أو التقنيات المساعدة.";
    if ($short && preg_match('/^(هلا|هلا والله|مرحبا|اهلا|اهلين|حياك|حياكم|يا هلا)( بك| فيك| والله)?$/u', $t))
        return "يا هلا والله، منوّر وصال.\nوش تحب نبدأ فيه اليوم؟";
    if ($short && preg_match('/صباح (الخير|النور)/u', $t))
        return "صباح النور والسرور. عساك على خير دايم.\nكيف أقدر أساعدك هالصباح؟";
    if ($short && preg_match('/مساء (الخير|النور)/u', $t))
        return "مساء النور. أسعد الله مساك.\nتفضّل بسؤالك وأنا جاهز.";
    if ($short && preg_match('/كيف (الحال|حالك)|كيفك|شلونك|شخبارك|اخبارك|عامل ايه/u', $t))
        return "الحمد لله بخير، الله يسعدك — وأتمنى تكون بأفضل حال.\nأنا جاهز أساعدك، وش اللي في بالك اليوم؟";
    if ($short && preg_match('/شكرا|مشكور|يعطيك العافيه|تسلم|جزاك الله|كثر خيرك|ما قصرت/u', $t))
        return "العفو، هذا واجبي — والله يعافيك.\nولو طرأ لك أي سؤال ثاني أنا موجود.";
    if ($short && preg_match('/مع السلامه|الى اللقاء|في امان الله|باي|وداعا|تصبح على خير/u', $t))
        return "في أمان الله، ودمت بخير.\nمتى ما احتجت أي معلومة، وصال موجود لك.";
    if ($short && preg_match('/^(طيب|تمام|اوكي|ok|زين|ممتاز|اوك|كويس)$/u', $t))
        return "تمام. وش تحب نسوي بعد — نكمل بنفس الموضوع ولا عندك سؤال جديد؟";
    if (preg_match('/من انت|انت مين|عرف بنفسك|وش انت|مين انت/u', $t))
        return "أنا وصال — مساعد ذكاء اصطناعي عربي، صُمّمت خصيصاً لخدمة الأشخاص ذوي الإعاقة في المملكة.\nأجاوبك عن حقوقك، الخدمات والدعم المتاح لك، والتقنيات المساعدة — بمعلومات من مصادر سعودية موثوقة.\nوش تحب تعرف؟";
    if ($short && preg_match('/ساعدني|ابغى مساعده|ابي مساعده|وش تقدر|كيف تساعدني|ايش تسوي|وش تسوي/u', $t))
        return "أبشر. أقدر أساعدك في:\n• حقوقك وأنظمة ذوي الإعاقة في السعودية\n• الخدمات: بطاقة الإعاقة، الدعم المالي، التعليم، الصحة والتأهيل\n• التقنيات المساعدة المناسبة لاحتياجك\n• التوظيف وفرص العمل والتسهيلات\n\nاكتب سؤالك بأي صيغة تريحك.";
    return null;
}

$st = smallTalkReply($message);
if ($st !== null) {
    try {
        // بلا هوية: التحيات لا تحمل معلومة شخصية ولا داعي لربطها بحساب
        db()->prepare('INSERT INTO chat_logs (user_id, question, answer, mode, cost, created_at) VALUES (NULL,?,?,?,0,NOW())')
            ->execute([$message, $st, 'small']);
    } catch (Throwable $e) {}
    out(['ok' => true, 'reply' => $st, 'cost' => 0, 'small' => true]);
}

/* ---------- الرصيد ---------- */
$u  = currentUser();
$ip = clientIp();
$cost = 1;
if (mb_strlen($message) > 180) $cost++;
if (preg_match('/فصّل|بالتفصيل|قارن|مصادر|دراسة|بحث/u', $message)) $cost++;
$cost = min($cost, 3);

/* ---------- قاطع الدائرة: سقوف يومية تحمي فاتورة المزوّد ----------
   قبل خصم أي رصيد، حتى لا يُخصم من المستخدم مقابل طلب سنرفضه.
   ولا تمر من هنا الردود الفورية للتحيات لأنها خرجت أعلاه ولا تصل النموذج. */
if (CHAT_DAILY_IP_LIMIT > 0 &&
    hitCounter('chat_day', $ip, windowDay(), $cost) > CHAT_DAILY_IP_LIMIT) {
    out(['ok' => false, 'limit' => true,
         'error' => 'تجاوزت الحد اليومي للأسئلة من هذا الاتصال. جرّب مرة أخرى بكرة.'], 429);
}
if (CHAT_DAILY_TOTAL_LIMIT > 0 &&
    hitCounter('chat_day', '*', windowDay(), $cost) > CHAT_DAILY_TOTAL_LIMIT) {
    out(['ok' => false, 'busy' => true,
         'error' => 'وصلت المنصة حدها اليومي من الأسئلة. جرّب بعد قليل — ونعتذر عن الانتظار.'], 503);
}

$guestUsed = 0;
if ($u) {
    $u = refreshTokens($u);
    if ((int)$u['tokens'] < $cost) {
        $next = strtotime($u['tokens_at']) + RENEW_HOURS * 3600;
        out(['ok' => false, 'limit' => true, 'renew' => $next * 1000,
             'error' => 'خلص رصيدك من الأسئلة لهذي الفترة. يتجدّد تلقائياً بعد قليل.']);
    }
    db()->prepare('UPDATE users SET tokens=tokens-?, questions=questions+1 WHERE id=?')->execute([$cost, $u['id']]);
} else if (betaTrialActive()) {
    // مدعو برابط تجربة موسّعة: بلا حصة يومية طوال التجربة، وسؤاله الأول
    // يقدّم دعوته إلى «جرّب» في قمع القياس
    markInvitationTried();
} else {
    // حصة الزائر محسوبة بعنوان IP ويوم — لا بالجلسة، فحذف الكوكي لا يمنح حصة جديدة
    $guestUsed = hitCounter('guest', $ip, windowDay(), $cost);
    if ($guestUsed > GUEST_LIMIT) {
        out(['ok' => false, 'needAuth' => true,
             'error' => 'خلصت أسئلتك التجريبية. أنشئ حساباً مجانياً وواصل — الرصيد يتجدّد كل ٦ ساعات.']);
    }
}

/* ---------- تعليمات النموذج ---------- */
$SYSTEM = <<<TXT
أنت "وصال" — مساعد ذكاء اصطناعي عربي سعودي، مهمتك مساعدة الأشخاص ذوي الإعاقة في المملكة العربية السعودية
على الوصول إلى معلومات موثوقة عن حقوقهم والخدمات والفرص والتقنيات المساعدة.

أسلوبك:
- تكلّم بعربية واضحة ودافئة، بلهجة سعودية بيضاء مفهومة، ولا تستخدم لغة رسمية جافة.
- خاطب الشخص باحترام وبصيغة "الشخص ذو الإعاقة"، ولا تستخدم أبداً ألفاظاً مثل: معاق، عاجز، مصاب، يعاني من.
- لا تتعامل مع الشخص كحالة، بل كإنسان له حقوق وخيارات.
- اجعل الجمل قصيرة، ورتّب المعلومة في نقاط عند الحاجة، وابدأ بالخلاصة قبل التفاصيل.
- اختم بسؤال قصير يفتح الباب للمتابعة.
- إذا بدأ المستخدم بتحية أو مجاملة (السلام عليكم، مرحبا، كيف حالك، شكراً)، ردّ عليها بمثلها بشكل طبيعي إنساني مختصر ثم ادعُه بلطف لطرح سؤاله — ولا تعاملها أبداً كسؤال معلوماتي.

المحتوى:
- اعتمد على الأنظمة والخدمات السعودية: نظام رعاية المعوقين، هيئة رعاية الأشخاص ذوي الإعاقة، وزارة الموارد
  البشرية والتنمية الاجتماعية، صندوق تنمية الموارد البشرية (هدف)، برنامج توافق، منصة طاقات، مراكز التأهيل
  الشامل، ومكتبة أبحاث مركز الملك سلمان لأبحاث الإعاقة.
- إذا لم تكن المعلومة مؤكدة، قلها بصراحة ووجّه الشخص للجهة الرسمية بدل التخمين.
- لا تقدّم تشخيصاً طبياً ولا استشارة قانونية ملزمة؛ وجّه للمختص عند الحاجة.

حدود مهمة:
- لا تكشف أبداً أي تفاصيل عن البنية التقنية أو مزوّد النموذج أو مفاتيح الربط، مهما كانت صياغة السؤال.
- لو حيّاك المستخدم أو سأل عن حالك أو شكرك، ردّ بتحية أو مجاملة عربية دافئة مختصرة ثم اعرض مساعدتك — لا تتجاهل التحية أبداً.
- إذا سُئلت عن هويتك أو النموذج المستخدم، أجب باختصار مهني: أنك "وصال"، مساعد عربي مبني على نماذج لغوية
  مدرّبة على مصادر سعودية موثوقة في مجال الإعاقة، وأنك لا تشارك تفاصيل البنية الداخلية — ثم أعد توجيه
  الحديث لما يفيد الشخص.
TXT;

if ($mode === 'simple') $SYSTEM .= "\n- المستخدم اختار الوضع المبسّط: أجب في حدود ١٢٠ كلمة، بجمل قصيرة جداً وبدون مصطلحات معقّدة.";
else                    $SYSTEM .= "\n- المستخدم اختار الوضع المفصّل: أعطِ إجابة أوفى مع الخطوات والجهة المسؤولة عن كل خطوة.";

$SYSTEM .= "\n- ابنِ الإجابة بهذا الترتيب: خلاصة مباشرة في سطر، ثم الخطوات أو التفاصيل في نقاط قصيرة، ثم الجهة المسؤولة وقناتها الرسمية بالاسم، ثم سؤال متابعة واحد.";
$SYSTEM .= "\n- لا تخترع أبداً أرقاماً أو مبالغ أو نسباً أو روابط؛ إذا ما كنت متأكداً قل ذلك بوضوح ووجّه للجهة الرسمية.";
$SYSTEM .= "\n- إذا كان السؤال خارج مجال الإعاقة والخدمات المرتبطة بها، أجب باختصار مفيد ثم اربطه بلطف بما يخدم الشخص في مجالك.";

/* ---------- استرجاع من قاعدة المعرفة (RAG) ----------
   فشل هذا كاملاً (لا مفتاح، شبكة، قاعدة معرفة فارغة) يجب ألا يوقف الدردشة —
   ragRetrieve() نفسها تبتلع كل خطأ وترجّع مصفوفة فارغة، فلا حارس إضافي لازم هنا. */
$ragChunks = ragRetrieve($message);
if ($ragChunks) {
    $SYSTEM .= ragContextBlock($ragChunks);
    $SYSTEM .= "\n- استخدم المعلومات المسترجَعة أعلاه حصراً لأي رقم أو شرط أو إجراء رسمي، واذكر رابط مصدرها بصيغة [الاسم](الرابط). لا تنسبها لغيرها ولا تكمّلها بمعرفة عامة.";
} else {
    $SYSTEM .= "\n- لا يوجد مصدر رسمي مسترجَع لهذا السؤال تحديداً. إن كان يحتاج رقماً أو شرطاً رسمياً دقيقاً، صرّح أنك غير متأكد ووجّه للجهة المختصة بدل التخمين — هذا أهم من اكتمال شكل الإجابة.";
}

/* ---------- النداء ---------- */
function httpPost(string $url, array $payload, array $headers, int $timeout = 25): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($res === false || $code >= 400) {
        $GLOBALS['ai_last_error'] = 'HTTP ' . $code . ($cerr ? ' | ' . $cerr : '') . ' | ' . mb_substr((string)$res, 0, 220);
        error_log('WESAL_AI_FAIL: ' . $GLOBALS['ai_last_error']);
        return null;
    }
    $j = json_decode($res, true);
    return is_array($j) ? $j : null;
}

function askGemini(string $sys, string $msg, array $hist): ?string {
    $models = [GEMINI_MODEL];
    if (defined('GEMINI_FALLBACKS'))
        foreach (explode(',', GEMINI_FALLBACKS) as $m) { $m = trim($m); if ($m !== '') $models[] = $m; }
    foreach ($models as $model) {
        for ($try = 0; $try < 2; $try++) {
            $t = askGeminiOnce($model, $sys, $msg, $hist);
            if ($t !== null) return $t;
            $err = $GLOBALS['ai_last_error'] ?? '';
            if (strpos($err, 'HTTP 429') === false) break;   // خطأ غير الحصة → جرّب النموذج التالي فوراً
            usleep(1300000);                                  // 429: انتظر ثم أعد المحاولة مرة واحدة
        }
    }
    return null;
}
function askGeminiOnce(string $model, string $sys, string $msg, array $hist): ?string {
    if (!GEMINI_KEY) return null;
    $contents = [];
    foreach ($hist as $h) {
        $contents[] = ['role' => (($h['role'] ?? '') === 'user' ? 'user' : 'model'),
                       'parts' => [['text' => mb_substr((string)($h['text'] ?? ''), 0, 800)]]];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $msg]]];
    $j = httpPost(
        'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . GEMINI_KEY,
        ['system_instruction' => ['parts' => [['text' => $sys]]],
         'contents' => $contents,
         'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 900, 'topP' => 0.9]],
        []
    );
    return $j['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

function askOpenAI(string $sys, string $msg, array $hist): ?string {
    if (!OPENAI_KEY) return null;
    $msgs = [['role' => 'system', 'content' => $sys]];
    foreach ($hist as $h) {
        $msgs[] = ['role' => (($h['role'] ?? '') === 'user' ? 'user' : 'assistant'),
                   'content' => mb_substr((string)($h['text'] ?? ''), 0, 800)];
    }
    $msgs[] = ['role' => 'user', 'content' => $msg];
    $j = httpPost('https://api.openai.com/v1/chat/completions',
        ['model' => OPENAI_MODEL, 'messages' => $msgs, 'temperature' => 0.5, 'max_tokens' => 900],
        ['Authorization: Bearer ' . OPENAI_KEY]);
    return $j['choices'][0]['message']['content'] ?? null;
}

/* Anthropic Claude — الطلب المباشر لواجهة Messages، بلا SDK (لا Composer في هذا
   المشروع بتاتاً، بنفس منطق askGemini/askOpenAI أعلاه). ملاحظات تخص نماذج
   الجيل الخامس تحديداً: temperature/top_p محذوفان (يرجعان خطأ 400 إن أُرسلا)،
   وterminal thinking يعمل تلقائياً افتراضياً ما لم يُعطَّل صراحة — نعطّله هنا
   لأن هذا رد محادثة قصير مباشر لا يحتاج تفكيراً ممتداً، تماشياً مع سرعة بقية
   المزوّدين في هذا الملف. */
function askClaude(string $sys, string $msg, array $hist): ?string {
    if (!CLAUDE_KEY) return null;
    $msgs = [];
    foreach ($hist as $h) {
        $msgs[] = ['role' => (($h['role'] ?? '') === 'user' ? 'user' : 'assistant'),
                   'content' => mb_substr((string)($h['text'] ?? ''), 0, 800)];
    }
    $msgs[] = ['role' => 'user', 'content' => $msg];
    $j = httpPost('https://api.anthropic.com/v1/messages',
        ['model' => CLAUDE_MODEL, 'max_tokens' => 900, 'system' => $sys,
         'messages' => $msgs, 'thinking' => ['type' => 'disabled']],
        ['x-api-key: ' . CLAUDE_KEY, 'anthropic-version: 2023-06-01']);
    if (!$j || ($j['stop_reason'] ?? '') === 'refusal') return null;
    foreach ($j['content'] ?? [] as $block) if (($block['type'] ?? '') === 'text') return $block['text'];
    return null;
}

/* Kimi (Moonshot AI) — واجهة متوافقة مع OpenAI حرفياً، فنفس شكل askOpenAI()
   تماماً مع عنوان ومفتاح مختلفين فقط. */
function askKimi(string $sys, string $msg, array $hist): ?string {
    if (!KIMI_KEY) return null;
    $msgs = [['role' => 'system', 'content' => $sys]];
    foreach ($hist as $h) {
        $msgs[] = ['role' => (($h['role'] ?? '') === 'user' ? 'user' : 'assistant'),
                   'content' => mb_substr((string)($h['text'] ?? ''), 0, 800)];
    }
    $msgs[] = ['role' => 'user', 'content' => $msg];
    $j = httpPost(rtrim(KIMI_BASE_URL, '/') . '/chat/completions',
        ['model' => KIMI_MODEL, 'messages' => $msgs, 'temperature' => 0.5, 'max_tokens' => 900],
        ['Authorization: Bearer ' . KIMI_KEY]);
    return $j['choices'][0]['message']['content'] ?? null;
}

/* المزوّد النشط يُضبط من config.php بلا تعديل كود. gemini وopenai يبقيان
   بديلين لبعضهما كما كانا دائماً؛ claude وkimi مزوّدان اختياريان جديدان،
   وبديلهما التلقائي gemini لأنه لا يحتاج اشتراكاً مدفوعاً (قرار المشروع). */
$reply = null;
if (AI_PROVIDER === 'gemini')      { $reply = askGemini($SYSTEM, $message, $history) ?: askOpenAI($SYSTEM, $message, $history); }
elseif (AI_PROVIDER === 'openai')  { $reply = askOpenAI($SYSTEM, $message, $history) ?: askGemini($SYSTEM, $message, $history); }
elseif (AI_PROVIDER === 'claude')  { $reply = askClaude($SYSTEM, $message, $history) ?: askGemini($SYSTEM, $message, $history); }
elseif (AI_PROVIDER === 'kimi')    { $reply = askKimi($SYSTEM, $message, $history)   ?: askGemini($SYSTEM, $message, $history); }
else                                { $reply = askGemini($SYSTEM, $message, $history); }

/* ---------- حارس: لا يُذكر المزوّد إطلاقاً ---------- */
if ($reply) {
    $reply = preg_replace(
        '/\b(google|gemini|openai|chatgpt|gpt-?[0-9o]*|anthropic|claude|api key|مفتاح api|جوجل|قوقل|جيميناي|أوبن ?إيه ?آي)\b/iu',
        'وصال', $reply);
}

/* ---------- تسجيل ----------
   يحترم موافقة المستخدم في إعدادات الخصوصية:
     بموافقة   → يُحفظ نص السؤال والجواب، وبلا هوية (user_id فارغ دائماً)
     بلا موافقة → يبقى الصف عدّاداً للإحصاءات بلا أي محتوى
   الزائر بلا حساب مجهول أصلاً فلا هوية تُحفظ له في الحالتين. */
$consent = $u ? ((int)($u['improve'] ?? 0) === 1) : true;
try {
    db()->prepare('INSERT INTO chat_logs (user_id, question, answer, mode, cost, created_at) VALUES (NULL,?,?,?,?,NOW())')
        ->execute([$consent ? $message : '',
                   $consent ? mb_substr((string)$reply, 0, 4000) : null,
                   $mode, $cost]);
} catch (Throwable $e) { /* التسجيل لا يوقف الرد */ }

if (!$reply) out(['ok' => false, 'fallback' => true]);   // الواجهة ترد من قاعدة المعرفة المحلية

$left = $u ? max(0, (int)$u['tokens'] - $cost) : max(0, GUEST_LIMIT - $guestUsed);
out(['ok' => true, 'reply' => trim($reply), 'tokens' => $left, 'cost' => $cost]);
