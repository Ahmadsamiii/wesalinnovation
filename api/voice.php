<?php
/* ==========================================================================
 *  وصال: الصوت السعودي (قراءة الإجابات، وتحويل الكلام إلى نص)
 *
 *  POST api/voice.php
 *    JSON {action:'status'}                       ← {ok, tts, stt}
 *    JSON {action:'tts', text, gender, tier?}     ← الصوت نفسه (audio/wav أو audio/mpeg)
 *    multipart: action=stt، audio=<التسجيل>        ← {ok, text}
 *
 *  القراءة بطبقات مجانية كل منها بحصة تتجدد، بالترتيب في VOICE_TTS_ORDER:
 *    groq   canopylabs/orpheus-arabic-saudi: ستة أصوات سعودية، حصة يومية صغيرة،
 *           و200 حرف للطلب (لهذا تقسّم الواجهة الرد إلى جمل).
 *    azure  ar-SA: حامد وزارية، حصة شهرية، و20 طلباً في الدقيقة للفئة المجانية.
 *  وإن لم تبقَ طبقة يرد هذا الملف 503 فتقرأ الواجهة بصوت الجهاز كما كانت.
 *
 *  ثبات الصوت: نفاد حصة طبقة يُسجَّل في ملف حالة فتبدأ الردود التالية من الطبقة
 *  التالية حتى موعد تجدد الحصة، بدل أن يتبدل الصوت بين رد وآخر. والرد الواحد
 *  يرسل طبقة جملته الأولى (tier) مع كل جملة بعدها فيُقرأ كله بصوت واحد.
 *
 *  كل صوت مولَّد يُحفظ بمفتاح بصمة النص والصوت، فالعبارات المتكررة (التحيات،
 *  وعبارات الإعدادات، وإجابات قاعدة المعرفة المحلية) لا تستهلك الحصة مرتين.
 *  الحجم سقفه VOICE_CACHE_MB ويُحذف الأقدم استخداماً أولاً.
 *
 *  التفريغ: Groq Whisper للمتصفحات التي لا تحوّل الكلام إلى نص بنفسها (Firefox
 *  مثلاً) أو حين يفشل ذلك فيها. التسجيل يُرسل للمزوّد ثم يُحذف، ولا يُحفظ هنا.
 *  الدوال والمزوّدون في voice-lib.php، ويفحصها دون شبكة tools/check-voice.php.
 * ========================================================================== */
require_once __DIR__ . '/db.php';
/* لا يحتاج هذا الملف الجلسة بعد أن سجّل db.php النشاط فيها. تحرير قفلها يسمح
   بطلبين متوازيين (جملة تُقرأ وأخرى تُجهَّز بعدها) بدل أن ينتظر أحدهما الآخر. */
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
require_once __DIR__ . '/voice-lib.php';

/* ---------- نقطة النهاية ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fail('طلب غير معروف.', 405);
if (!voiceSameOrigin()) fail('طلب غير مسموح.', 403);

$isJson = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === 0;
$in     = $isJson ? body() : [];
$action = (string)($isJson ? ($in['action'] ?? '') : ($_POST['action'] ?? ''));

if ($action === 'status') {
    out(['ok' => true, 'tts' => voiceTiers() !== [], 'stt' => GROQ_KEY !== '']);
}

if ($action === 'tts') {
    if (!$isJson) fail('طلب غير معروف.');
    rateLimit('voice', 40);
    set_time_limit(60);

    $text = voiceCleanText((string)($in['text'] ?? ''));
    if ($text === '') fail('لا يوجد نص للقراءة.');
    if (mb_strlen($text) > VOICE_MAX_CHARS) fail('النص أطول من المسموح للقراءة دفعة واحدة.');
    $gender = ($in['gender'] ?? '') === 'female' ? 'female' : 'male';
    $want   = (string)($in['tier'] ?? '');
    $order  = voiceOrder($want, voiceTiers(time()));
    if (!$order) fail('الصوت السعودي غير متاح الآن.', 503);

    $ip = clientIp();
    foreach ($order as $tier) {
        $voice = voiceName($tier, $gender);
        $file  = voiceCacheFile($tier, $voice, $text);
        if ($file !== '' && is_file($file) && ($audio = @file_get_contents($file)) !== false && $audio !== '') {
            @touch($file);
            voiceSendAudio($audio, $tier, 'hit');
        }
        // السقف اليومي لكل عنوان يُحسب على ما يولّده المزوّد فعلاً، لا على المحفوظ
        if (VOICE_DAILY_IP_CHARS > 0 && hitCounter('d:voice-chars', $ip, windowDay(), 0) >= VOICE_DAILY_IP_CHARS)
            fail('بلغت حد القراءة بالصوت السعودي لهذا اليوم.', 429);

        $call = $tier === 'groq' ? 'voiceGroqTts' : 'voiceAzureTts';
        $r = $call($text, $voice);
        // حد الدقيقة أثناء رد بدأ بهذه الطبقة: انتظار قصير يحفظ صوت الرد من التبدّل
        if ($r['code'] === 429 && $tier === $want && $r['retry'] > 0 && $r['retry'] <= 6) {
            sleep($r['retry']);
            $r = $call($text, $voice);
        }
        if (voiceLooksLikeAudio($r)) {
            if (VOICE_DAILY_IP_CHARS > 0) hitCounter('d:voice-chars', $ip, windowDay(), mb_strlen($text));
            voiceCachePut($file, $r['body']);
            voiceSendAudio($r['body'], $tier, 'miss');
        }
        $why = 'HTTP ' . $r['code'] . ($r['err'] ? ' ' . $r['err'] : '') . ' ' . mb_substr($r['body'], 0, 200);
        if (($pause = voicePauseFor($r)) > 0) voicePause($tier, $pause, $why);
        else @error_log('[wesal-voice] ' . $tier . ' failed: ' . $why);
    }
    fail('الصوت السعودي غير متاح الآن.', 503);
}

if ($action === 'stt') {
    if (GROQ_KEY === '') fail('تحويل الصوت إلى نص غير متاح الآن.', 503);
    rateLimit('stt', 6);
    if (VOICE_DAILY_IP_STT > 0 && hitCounter('d:stt', clientIp(), windowDay()) > VOICE_DAILY_IP_STT)
        fail('بلغت حد التسجيلات الصوتية لهذا اليوم، ويمكنك كتابة سؤالك.', 429);
    set_time_limit(45);

    $f = $_FILES['audio'] ?? null;
    if (!is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        fail(($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'التسجيل أطول من المسموح. سجّل سؤالك في دقيقة أو أقل.' : 'لم يصل التسجيل. حاول مرة أخرى.');
    }
    if ($f['size'] < 1000) fail('التسجيل قصير جداً. اضغط الميكروفون وتكلّم، ثم اضغطه مرة أخرى.');
    if ($f['size'] > VOICE_STT_MAX_BYTES) fail('التسجيل أطول من المسموح. سجّل سؤالك في دقيقة أو أقل.');
    $kind = voiceSniffAudio($f['tmp_name']);
    if ($kind === null) fail('صيغة التسجيل غير مدعومة.');

    $r = voiceHttp(rtrim(GROQ_BASE_URL, '/') . '/audio/transcriptions', [
        'model' => GROQ_STT_MODEL, 'language' => 'ar', 'response_format' => 'json', 'temperature' => '0',
        'prompt' => VOICE_STT_PROMPT, 'file' => new CURLFile($f['tmp_name'], $kind[0], 'voice.' . $kind[1]),
    ], ['Authorization: Bearer ' . GROQ_KEY], 30);
    @unlink($f['tmp_name']);

    if ($r['code'] !== 200) {
        @error_log('[wesal-voice] stt failed: HTTP ' . $r['code'] . ' ' . $r['err'] . ' ' . mb_substr($r['body'], 0, 200));
        fail($r['code'] === 429 ? 'خدمة تحويل الصوت إلى نص مشغولة الآن. حاول بعد قليل، أو اكتب سؤالك.'
                                : 'تعذّر تحويل صوتك إلى نص. حاول مرة أخرى، أو اكتب سؤالك.', 503);
    }
    $j = json_decode($r['body'], true);
    $text = clean(is_array($j) ? ($j['text'] ?? '') : '', 1500);
    if (voiceIsNoise($text)) fail('لم نسمع كلاماً واضحاً في التسجيل. حاول مرة أخرى.');
    out(['ok' => true, 'text' => $text]);
}

fail('طلب غير معروف.');

