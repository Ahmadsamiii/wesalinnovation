<?php
/* اختبار جدوى البث (Server-Sent Events) على الخادم.
   افتح في المتصفح:  https://wesalinnovation.sa/api/stream-test.php
   أو من SSH:         curl -N "https://wesalinnovation.sa/api/stream-test.php?sse=1"
   إن ظهرت الأرقام واحداً تلو الآخر (كل ثانية) فالخادم يمرّر البث. إن ظهرت دفعة واحدة
   بعد خمس ثوانٍ فالخادم يجمّع المخرجات، وعندها نعتمد الخطة البديلة (القراءة التدريجية).
   الملف آمن ولا يلمس قاعدة البيانات، ويُحذف بعد انتهاء الاختبار. */

if (($_GET['sse'] ?? '') === '1') {
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
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>اختبار البث — وصال</title>
<style>body{font-family:system-ui,Tahoma,sans-serif;max-width:560px;margin:2rem auto;padding:0 1rem;line-height:1.8}
#log div{padding:.3rem .6rem;border-bottom:1px solid #ddd}#verdict{font-weight:700;font-size:1.2rem;margin-top:1rem}</style></head>
<body>
<h2>اختبار البث على الخادم</h2>
<p>ننتظر خمس دفعات، واحدة كل ثانية…</p>
<div id="log"></div>
<div id="verdict"></div>
<script>
const log=document.getElementById('log'),v=document.getElementById('verdict');
const arrivals=[];const t0=performance.now();
const es=new EventSource('stream-test.php?sse=1');
es.onmessage=e=>{const now=(performance.now()-t0)/1000;arrivals.push(now);
  const d=document.createElement('div');d.textContent='وصلت الدفعة '+JSON.parse(e.data).n+' بعد '+now.toFixed(2)+' ث';log.appendChild(d);};
es.addEventListener('done',()=>{es.close();
  const spread=arrivals.length>1?arrivals[arrivals.length-1]-arrivals[0]:0;
  v.textContent=spread>2.5?'✅ البث يعمل: الدفعات وصلت متباعدة ('+spread.toFixed(1)+' ث بين الأولى والأخيرة).':'❌ الخادم يجمّع المخرجات: كل الدفعات وصلت معاً ('+spread.toFixed(2)+' ث). نعتمد الخطة البديلة.';});
es.onerror=()=>{if(!arrivals.length){v.textContent='⚠️ تعذّر فتح المجرى. أرسل لي لقطة لهذه الصفحة.';es.close();}};
</script>
</body></html>
