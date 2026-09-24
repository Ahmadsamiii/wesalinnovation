<?php
/* منطق محادثة وصال المشترك بين المسار غير المتدفق (chat.php) والمتدفق
   (chat-stream.php) — يبقيهما متطابقين في السلوك: نفس الحدود، نفس الشحن،
   نفس التعليمات، نفس تعتيم اسم المزوّد، نفس التسجيل. أي تغيير هنا ينعكس
   على المسارين معاً تلقائياً. */

require_once __DIR__ . '/rag.php';

/* ---------- معاملات التوليد المشتركة ----------
   مصدر واحد لكل الأرقام حتى لا يفترق المسار المخزَّن عن المتدفق في الحرارة
   أو حد الكلمات أو المهل الزمنية دون قصد. */
const AI_TEMPERATURE          = 0.4;
const AI_MAX_OUTPUT_TOKENS    = 900;
const AI_TOP_P                = 0.9;
const AI_HIST_CHAR_LIMIT      = 800;
const AI_CONNECT_TIMEOUT      = 8;
const AI_TOTAL_TIMEOUT        = 25;
const AI_FIRST_CONTENT_BUDGET = 12;   // ثوانٍ: أقصى انتظار لأول جزء نص قبل الانتقال لمزوّد آخر (البث فقط)

/* هامش أمان بالأحرف لطبقة تعتيم اسم المزوّد أثناء البث (chat-stream.php):
   أطول عبارة في chatScrubReply() أدناه هي "أوبن إيه آي" (بمسافاتها) أو
   "chatgpt"/"anthropic" تقريباً؛ هذا الرقم هامش سخي فوقها عمداً — تكلفته
   جزء من ثانية تأخير إضافي في تحرير آخر كلمات كل جزء، لا خطر إفلات. إن أُضيف
   مصطلح أطول لـchatScrubReply لاحقاً، ارفع هذا الرقم معه. */
const AI_SCRUB_SAFETY_CHARS = 24;

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

/* ---------- التكلفة ---------- */
function chatCost(string $message): int {
    $cost = 1;
    if (mb_strlen($message) > 180) $cost++;
    if (preg_match('/فصّل|بالتفصيل|قارن|مصادر|دراسة|بحث/u', $message)) $cost++;
    return min($cost, 3);
}

/* ---------- قاطع الدائرة: سقوف يومية تحمي فاتورة المزوّد ----------
   قبل خصم أي رصيد، حتى لا يُخصم من المستخدم مقابل طلب سنرفضه.
   ولا تمر من هنا الردود الفورية للتحيات لأنها تُعالَج قبل الوصول لهذه الدالة. */
function chatCircuitBreaker(string $ip, int $cost): void {
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
}

/* ---------- الرصيد ----------
   مصدر الحقيقة الوحيد لخصم رصيد الأسئلة، حتى يتطابق سلوك الشحن بين
   المسار المخزَّن والمتدفق تلقائياً. يستدعي out() ويُنهي الطلب عند الرفض،
   تماماً كما كان الحال داخل chat.php مباشرة. */
function chatCheckBalance(?array $u, string $ip, int $cost): array {
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
        // مدعو برابط تجربة موسّعة: بلا حصة يومية طوال التجربة
        markInvitationTried();
    } else {
        // حصة الزائر محسوبة بعنوان IP ويوم — لا بالجلسة
        $guestUsed = hitCounter('guest', $ip, windowDay(), $cost);
        if ($guestUsed > GUEST_LIMIT) {
            out(['ok' => false, 'needAuth' => true,
                 'error' => 'خلصت أسئلتك التجريبية. أنشئ حساباً مجانياً وواصل — الرصيد يتجدّد كل ٦ ساعات.']);
        }
    }
    $left = $u ? max(0, (int)$u['tokens'] - $cost) : max(0, GUEST_LIMIT - $guestUsed);
    return ['u' => $u, 'guestUsed' => $guestUsed, 'left' => $left];
}

/* ---------- استخراج نص الرد من Gemini، بتجاهل أجزاء "التفكير" ----------
   نماذج Gemini 3.x المفكِّرة قد تُرجع أجزاء تفكير داخلي (thought:true) قبل
   جزء النص الفعلي؛ أخذ parts[0] كما هو كان يخاطر بالتقاط جزء تفكير فارغ
   من النص. أخطر من هذا: عند تفعيل thinkingConfig، تُخصَم tokens التفكير من
   نفس ميزانية maxOutputTokens — لو استهلك التفكير الميزانية كاملة، يرجع رد
   فارغ تماماً (finishReason=MAX_TOKENS بلا أي جزء نص)، وهو خلل موثَّق في
   نماذج Gemini المفكِّرة عند عدم حجز ميزانية كافية للنص. لهذا نُعطِّل
   التفكير صراحة (thinkingBudget=0) في askGeminiOnce أدناه بدل ضبط مستوى له. */
function chatExtractGeminiText(array $j): ?string {
    $parts = $j['candidates'][0]['content']['parts'] ?? [];
    $text = '';
    foreach ($parts as $part) {
        if (!empty($part['thought'])) continue;
        if (isset($part['text'])) $text .= $part['text'];
    }
    if ($text !== '') return $text;
    $reason = $j['candidates'][0]['finishReason'] ?? ($j['promptFeedback']['blockReason'] ?? null);
    if ($reason) $GLOBALS['ai_last_error'] = 'Gemini: بلا نص، finishReason=' . $reason;
    return null;
}

/* ---------- تعليمات النموذج ---------- */
function chatSystemPrompt(string $mode): string {
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
    $SYSTEM .= "\n- بعد إجابتك الكاملة، أضف سطراً فارغاً ثم العلامة ===ASK3=== وحدها في سطر، ثم بالضبط ثلاثة أسئلة متابعة قصيرة جداً (أقل من عشر كلمات لكل سؤال) قد يطرحها المستخدم بعد إجابتك، سؤالاً واحداً في كل سطر بلا ترقيم ولا شرطات. لا تكتب أي شيء بعد الأسئلة الثلاثة.";

    return $SYSTEM;
}

/* ---------- استرجاع من قاعدة المعرفة (RAG) ----------
   فشل هذا كاملاً (لا مفتاح، شبكة، قاعدة معرفة فارغة) يجب ألا يوقف الدردشة —
   ragRetrieve() نفسها تبتلع كل خطأ وترجّع مصفوفة فارغة، فلا حارس إضافي لازم هنا. */
function chatAugmentWithRag(string $system, string $message): array {
    $ragChunks = ragRetrieve($message);
    if ($ragChunks) {
        $system .= ragContextBlock($ragChunks);
        $system .= "\n- استخدم المعلومات المسترجَعة أعلاه حصراً لأي رقم أو شرط أو إجراء رسمي، واذكر رابط مصدرها بصيغة [الاسم](الرابط). لا تنسبها لغيرها ولا تكمّلها بمعرفة عامة.";
    } else {
        $system .= "\n- لا يوجد مصدر رسمي مسترجَع لهذا السؤال تحديداً. إن كان يحتاج رقماً أو شرطاً رسمياً دقيقاً، صرّح أنك غير متأكد ووجّه للجهة المختصة بدل التخمين — هذا أهم من اكتمال شكل الإجابة.";
    }
    return [$system, $ragChunks];
}

/* ---------- النداء ---------- */
function httpPost(string $url, array $payload, array $headers, int $timeout = AI_TOTAL_TIMEOUT): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_CONNECTTIMEOUT => AI_CONNECT_TIMEOUT,
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

function askGemini(string $sys, string $msg, array $hist, bool $noThinking = true): ?string {
    $models = [GEMINI_MODEL];
    if (defined('GEMINI_FALLBACKS'))
        foreach (explode(',', GEMINI_FALLBACKS) as $m) { $m = trim($m); if ($m !== '') $models[] = $m; }
    foreach ($models as $model) {
        $nt = $noThinking;
        $t = askGeminiOnce($model, $sys, $msg, $hist, $nt);
        if ($t !== null) { $GLOBALS['ai_last_model'] = $model; return $t; }
        $err = $GLOBALS['ai_last_error'] ?? '';
        if (strpos($err, 'HTTP 429') !== false) {                   // حصة: انتظر ثم أعد نفس المحاولة مرة واحدة
            usleep(1300000);
            $t = askGeminiOnce($model, $sys, $msg, $hist, $nt);
            if ($t !== null) { $GLOBALS['ai_last_model'] = $model; return $t; }
            $err = $GLOBALS['ai_last_error'] ?? '';
        }
        if ($nt && strpos($err, 'HTTP 400') !== false) {   // تراجع آمن: قد يكون حقل thinkingConfig غير مدعوم لهذا النموذج
            $t = askGeminiOnce($model, $sys, $msg, $hist, false);   // بلا thinkingConfig إطلاقاً
            if ($t !== null) { $GLOBALS['ai_last_model'] = $model; return $t; }
        }
        // أي خطأ آخر → جرّب النموذج التالي فوراً
    }
    return null;
}
function askGeminiOnce(string $model, string $sys, string $msg, array $hist, bool $noThinking = true): ?string {
    if (!GEMINI_KEY) return null;
    $contents = [];
    foreach ($hist as $h) {
        $contents[] = ['role' => (($h['role'] ?? '') === 'user' ? 'user' : 'model'),
                       'parts' => [['text' => mb_substr((string)($h['text'] ?? ''), 0, AI_HIST_CHAR_LIMIT)]]];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $msg]]];
    $generationConfig = ['temperature' => AI_TEMPERATURE, 'maxOutputTokens' => AI_MAX_OUTPUT_TOKENS, 'topP' => AI_TOP_P];
    if ($noThinking) $generationConfig['thinkingConfig'] = ['thinkingBudget' => 0];
    $j = httpPost(
        'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . GEMINI_KEY,
        ['system_instruction' => ['parts' => [['text' => $sys]]],
         'contents' => $contents,
         'generationConfig' => $generationConfig],
        []
    );
    return is_array($j) ? chatExtractGeminiText($j) : null;
}

function askOpenAI(string $sys, string $msg, array $hist): ?string {
    if (!OPENAI_KEY) return null;
    $msgs = [['role' => 'system', 'content' => $sys]];
    foreach ($hist as $h) {
        $msgs[] = ['role' => (($h['role'] ?? '') === 'user' ? 'user' : 'assistant'),
                   'content' => mb_substr((string)($h['text'] ?? ''), 0, AI_HIST_CHAR_LIMIT)];
    }
    $msgs[] = ['role' => 'user', 'content' => $msg];
    $j = httpPost('https://api.openai.com/v1/chat/completions',
        ['model' => OPENAI_MODEL, 'messages' => $msgs, 'temperature' => 0.5, 'max_tokens' => AI_MAX_OUTPUT_TOKENS],
        ['Authorization: Bearer ' . OPENAI_KEY]);
    $t = $j['choices'][0]['message']['content'] ?? null;
    if ($t !== null) $GLOBALS['ai_last_model'] = OPENAI_MODEL;
    return $t;
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
                   'content' => mb_substr((string)($h['text'] ?? ''), 0, AI_HIST_CHAR_LIMIT)];
    }
    $msgs[] = ['role' => 'user', 'content' => $msg];
    $j = httpPost('https://api.anthropic.com/v1/messages',
        ['model' => CLAUDE_MODEL, 'max_tokens' => AI_MAX_OUTPUT_TOKENS, 'system' => $sys,
         'messages' => $msgs, 'thinking' => ['type' => 'disabled']],
        ['x-api-key: ' . CLAUDE_KEY, 'anthropic-version: 2023-06-01']);
    if (!$j || ($j['stop_reason'] ?? '') === 'refusal') return null;
    foreach ($j['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') { $GLOBALS['ai_last_model'] = CLAUDE_MODEL; return $block['text']; }
    }
    return null;
}

/* Kimi (Moonshot AI) — واجهة متوافقة مع OpenAI حرفياً، فنفس شكل askOpenAI()
   تماماً مع عنوان ومفتاح مختلفين فقط. */
function askKimi(string $sys, string $msg, array $hist): ?string {
    if (!KIMI_KEY) return null;
    $msgs = [['role' => 'system', 'content' => $sys]];
    foreach ($hist as $h) {
        $msgs[] = ['role' => (($h['role'] ?? '') === 'user' ? 'user' : 'assistant'),
                   'content' => mb_substr((string)($h['text'] ?? ''), 0, AI_HIST_CHAR_LIMIT)];
    }
    $msgs[] = ['role' => 'user', 'content' => $msg];
    $j = httpPost(rtrim(KIMI_BASE_URL, '/') . '/chat/completions',
        ['model' => KIMI_MODEL, 'messages' => $msgs, 'temperature' => 0.5, 'max_tokens' => AI_MAX_OUTPUT_TOKENS],
        ['Authorization: Bearer ' . KIMI_KEY]);
    $t = $j['choices'][0]['message']['content'] ?? null;
    if ($t !== null) $GLOBALS['ai_last_model'] = KIMI_MODEL;
    return $t;
}

/* المزوّد النشط يُضبط من config.php بلا تعديل كود. gemini وopenai يبقيان
   بديلين لبعضهما كما كانا دائماً؛ claude وkimi مزوّدان اختياريان جديدان،
   وبديلهما التلقائي gemini لأنه لا يحتاج اشتراكاً مدفوعاً (قرار المشروع).
   جدول بحث بدل شرط if/elseif — يستخدمه المساران المخزَّن والمتدفق معاً،
   وتوسيعه لاحقاً بمزوّد جديد (Groq مثلاً) تعديل سطر واحد هنا فقط. */
function chatProviderOrder(): array {
    switch (AI_PROVIDER) {
        case 'openai': return ['openai', 'gemini'];
        case 'claude': return ['claude', 'gemini'];
        case 'kimi':   return ['kimi', 'gemini'];
        case 'gemini': return ['gemini', 'openai'];
        default:       return ['gemini'];
    }
}

function chatAskProvider(string $provider, string $sys, string $msg, array $hist): ?string {
    switch ($provider) {
        case 'gemini': return askGemini($sys, $msg, $hist);
        case 'openai': return askOpenAI($sys, $msg, $hist);
        case 'claude': return askClaude($sys, $msg, $hist);
        case 'kimi':   return askKimi($sys, $msg, $hist);
        default:       return null;
    }
}

/* ---------- حارس: لا يُذكر المزوّد إطلاقاً ----------
   \b يعتبر الحرف العربي أيضاً "حرف كلمة" تحت المعدِّل /u في PCRE، فلا يُكوّن
   حداً بين حرف عربي ملتصق (كواو العطف "و" مثلاً، وهو الشكل الإملائي الصحيح
   بلا مسافة) وكلمة لاتينية تالية — فمصطلح مثل "وClaude" أو "وGPT-4" كان
   يُفلت من التعتيم تماماً. المصطلحات اللاتينية هنا تستخدم حدوداً صريحة
   (عدم ملاصقة حرف/رقم لاتيني) بدل \b لهذا السبب؛ المصطلحات العربية تبقى
   على \b العادية فهي صحيحة وكافية لحدودها. */
function chatScrubReply(string $reply): string {
    $reply = preg_replace(
        '/(?<![A-Za-z0-9])(google|gemini|openai|chatgpt|gpt-?[0-9o]*|anthropic|claude|api key)(?![A-Za-z0-9])/iu',
        'وصال', $reply);
    return preg_replace(
        '/\b(مفتاح api|جوجل|قوقل|جيميناي|أوبن ?إيه ?آي)\b/iu',
        'وصال', $reply);
}

/* ---------- فصل ذيل الأسئلة المقترحة عن نص الإجابة ---------- */
function chatExtractSuggestions(string $text): array {
    $marker = '===ASK3===';
    $pos = strpos($text, $marker);
    if ($pos === false) return ['reply' => trim($text), 'suggestions' => []];
    $reply = trim(substr($text, 0, $pos));
    $tail  = trim(substr($text, $pos + strlen($marker)));
    $lines = array_values(array_filter(array_map('trim', explode("\n", $tail)), fn($l) => $l !== ''));
    $suggestions = array_map('chatScrubReply', array_slice($lines, 0, 3));
    return ['reply' => $reply, 'suggestions' => $suggestions];
}

/* ---------- تسجيل ----------
   يحترم موافقة المستخدم في إعدادات الخصوصية:
     بموافقة   → يُحفظ نص السؤال والجواب، وبلا هوية (user_id فارغ دائماً)
     بلا موافقة → يبقى الصف عدّاداً للإحصاءات بلا أي محتوى
   الزائر بلا حساب مجهول أصلاً فلا هوية تُحفظ له، والتحيات الفورية لا تحمل
   معلومة شخصية أصلاً فتُحفظ دائماً بلا حاجة لموافقة. */
function chatLogInteraction(?array $u, string $message, ?string $reply, string $mode, int $cost, array $extra = []): void {
    ensureSchema();
    $consent = $mode === 'small' || !$u || ((int)($u['improve'] ?? 0) === 1);
    try {
        db()->prepare('INSERT INTO chat_logs
            (user_id, question, answer, mode, cost, model, provider, stream, ttfb_ms, total_ms, aborted, created_at)
            VALUES (NULL,?,?,?,?,?,?,?,?,?,?,NOW())')
            ->execute([
                $consent ? $message : '',
                $consent ? mb_substr((string)$reply, 0, 4000) : null,
                $mode,
                $cost,
                $extra['model'] ?? null,
                $extra['provider'] ?? null,
                !empty($extra['stream']) ? 1 : 0,
                $extra['ttfb_ms'] ?? null,
                $extra['total_ms'] ?? null,
                !empty($extra['aborted']) ? 1 : 0,
            ]);
    } catch (Throwable $e) { /* التسجيل لا يوقف الرد */ }
}
