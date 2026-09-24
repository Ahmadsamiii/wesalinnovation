<?php
/* اختبار جدوى البث (Server-Sent Events) على الخادم — وجهان منفصلان:

   ?sse=1  الاتجاه الوارد: هل الخادم (LiteSpeed) يمرّر بثّنا الخاص للمتصفح بلا تجميع؟
           بيانات مُصطنعة (عدّاد + sleep) لا تعتمد على أي طرف خارجي.
   ?sse=2  الاتجاه الصادر: هل PHP-cURL يستقبل بثّ Gemini الحقيقي تدريجياً (عبر
           CURLOPT_WRITEFUNCTION) بدل انتظار اكتمال الرد كاملاً قبل إرجاعه؟
           يحتاج GEMINI_KEY في config.php، ويكلّف نداءً حقيقياً واحداً صغيراً.

   افتح كلاً منهما بالترتيب: أولاً ?sse=1 — إن فشل (الدفعات وصلت دفعة واحدة)
   فالمشكلة في الاتجاه الوارد وحدها، ويُعتمد حينها البديل (القراءة التدريجية)
   بلا حاجة لتجربة ?sse=2 أصلاً. إن نجح ?sse=1 لكن فشل ?sse=2، فالمشكلة تحديداً
   في استقبال PHP لبثّ Gemini (نادر، لكن وارد حسب إعداد cURL/OpenSSL على الخادم).

   من المتصفح: https://wesalinnovation.sa/api/stream-test.php
   من SSH:     curl -N "https://wesalinnovation.sa/api/stream-test.php?sse=1"
               curl -N "https://wesalinnovation.sa/api/stream-test.php?sse=2"
   الملف آمن ولا يلمس قاعدة البيانات، ويُحذف بعد انتهاء الاختبار. */

function streamHeaders(): void {
    @ini_set('zlib.output_compression', '0');
    @ini_set('output_buffering', '0');
    @ini_set('implicit_flush', '1');
    while (ob_get_level() > 0) ob_end_flush();
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('X-Accel-Buffering: no');       // nginx / LiteSpeed
    header('Content-Encoding: none');      // يمنع ضغط الاستجابة الذي يجمّعها
    set_time_limit(30);
    echo ': ' . str_repeat(' ', 4096) . "\n\n";   // حشو يفتح المجرى في بعض الخوادم
    flush();
}

if (($_GET['sse'] ?? '') === '1') {
    streamHeaders();
    $t0 = microtime(true);
    for ($i = 1; $i <= 5; $i++) {
        echo 'data: ' . json_encode(['n' => $i, 't' => round(microtime(true) - $t0, 2)]) . "\n\n";
        flush();
        if ($i < 5) sleep(1);
    }
    echo "event: done\ndata: {}\n\n";
    flush();
    exit;
}

if (($_GET['sse'] ?? '') === '2') {
    streamHeaders();
    $t0 = microtime(true);
    $cfgFile = __DIR__ . '/config.php';
    if (!is_file($cfgFile)) {
        echo 'data: ' . json_encode(['err' => 'config.php غير موجود — لا يمكن قراءة GEMINI_KEY']) . "\n\n";
        flush();
        echo "event: done\ndata: {}\n\n"; flush(); exit;
    }
    require_once $cfgFile;
    if (!defined('GEMINI_KEY') || !GEMINI_KEY) {
        echo 'data: ' . json_encode(['err' => 'GEMINI_KEY فارغ في config.php']) . "\n\n";
        flush();
        echo "event: done\ndata: {}\n\n"; flush(); exit;
    }
    $model = defined('GEMINI_MODEL') && GEMINI_MODEL ? GEMINI_MODEL : 'gemini-3.5-flash-lite';
    $n = 0;
    $buf = '';
    $write = function ($ch, string $data) use (&$n, &$buf, $t0): int {
        $buf .= $data;
        while (($p = strpos($buf, "\n\n")) !== false) {
            $frame = substr($buf, 0, $p);
            $buf = substr($buf, $p + 2);
            if (strpos($frame, 'data:') === 0) {
                $json = trim(substr($frame, 5));
                $j = json_decode($json, true);
                $text = $j['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text !== null && $text !== '') {
                    $n++;
                    echo 'data: ' . json_encode(['n' => $n, 't' => round(microtime(true) - $t0, 2), 'len' => mb_strlen($text)]) . "\n\n";
                    flush();
                }
            }
        }
        return strlen($data);
    };
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':streamGenerateContent?alt=sse&key=' . GEMINI_KEY);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'contents' => [['role' => 'user', 'parts' => [['text' => 'عدّ من واحد إلى عشرة، رقماً في كل سطر، بلا أي كلام إضافي.']]]],
            'generationConfig' => ['maxOutputTokens' => 200],
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_WRITEFUNCTION => $write,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_RETURNTRANSFER => false,
    ]);
    curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'data: ' . json_encode(['err' => 'cURL: ' . curl_error($ch)]) . "\n\n";
        flush();
    }
    curl_close($ch);
    if ($n === 0) {
        echo 'data: ' . json_encode(['err' => 'ما وصل أي جزء نصي — راجع صلاحية GEMINI_KEY واسم النموذج']) . "\n\n";
        flush();
    }
    echo "event: done\ndata: {}\n\n";
    flush();
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>اختبار البث — وصال</title>
<style>body{font-family:system-ui,Tahoma,sans-serif;max-width:560px;margin:2rem auto;padding:0 1rem;line-height:1.8}
#log1 div,#log2 div{padding:.3rem .6rem;border-bottom:1px solid #ddd}
.verdict{font-weight:700;font-size:1.1rem;margin:.5rem 0 1.5rem}
button{font:inherit;padding:.5rem 1rem;border-radius:.5rem;border:1px solid #ccc;cursor:pointer;margin-inline-end:.5rem}
h3{margin-bottom:.3rem}</style></head>
<body>
<h2>اختبار البث على الخادم</h2>

<h3>١) الاتجاه الوارد — خادمنا إلى المتصفح</h3>
<p>خمس دفعات مُصطنعة، واحدة كل ثانية.</p>
<button onclick="run(1)">شغّل الاختبار الأول</button>
<div id="log1"></div>
<div id="verdict1" class="verdict"></div>

<h3>٢) الاتجاه الصادر — Gemini إلى خادمنا</h3>
<p>نداء حقيقي صغير لـGemini، يحتاج مفتاحاً صالحاً في config.php. شغّله بعد نجاح الاختبار الأول فقط.</p>
<button onclick="run(2)">شغّل الاختبار الثاني</button>
<div id="log2"></div>
<div id="verdict2" class="verdict"></div>

<script>
function run(which){
  const log=document.getElementById('log'+which), v=document.getElementById('verdict'+which);
  log.innerHTML=''; v.textContent='جارٍ الاختبار…';
  const arrivals=[]; const t0=performance.now(); let sawErr=false;
  const es=new EventSource('stream-test.php?sse='+which);
  es.onmessage=e=>{
    const d=JSON.parse(e.data);
    if(d.err){sawErr=true;const el=document.createElement('div');el.textContent='خطأ: '+d.err;log.appendChild(el);return;}
    const now=(performance.now()-t0)/1000; arrivals.push(now);
    const el=document.createElement('div');el.textContent='وصلت الدفعة '+d.n+' بعد '+now.toFixed(2)+' ث'+(d.len?(' — '+d.len+' حرفاً'):'');
    log.appendChild(el);
  };
  es.addEventListener('done',()=>{
    es.close();
    if(sawErr){v.textContent='⚠️ تعذّر إكمال الاختبار — راجع رسالة الخطأ أعلاه.';return;}
    if(arrivals.length<2){v.textContent='⚠️ وصلت دفعة واحدة أو لا شيء — لا يكفي للحكم.';return;}
    const spread=arrivals[arrivals.length-1]-arrivals[0];
    v.textContent=spread>1.0
      ?'✅ يعمل تدريجياً: الدفعات وصلت متباعدة ('+spread.toFixed(1)+' ث بين الأولى والأخيرة).'
      :'❌ يبدو أنه يُجمَّع: كل الدفعات وصلت معاً تقريباً ('+spread.toFixed(2)+' ث).';
  });
  es.onerror=()=>{if(!arrivals.length){v.textContent='⚠️ تعذّر فتح المجرى. أرسل لقطة لهذه الصفحة.';es.close();}};
}
</script>
</body></html>
