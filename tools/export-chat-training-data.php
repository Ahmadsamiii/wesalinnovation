<?php
/* ==========================================================================
 *  وصال — تصدير محادثات chat_logs كبيانات تدريب مموَّهة الهوية
 *
 *  الاستخدام:
 *      php tools/export-chat-training-data.php [مسار_الخرج.jsonl]
 *      (افتراضي الخرج: tools/output/chat-training-data.jsonl)
 *
 *  يسحب فقط question/answer — لا user_id ولا أي عمود يربط الصف بحساب.
 *  يموّه أنماطاً شائعة داخل النص نفسه (جوال، هوية/إقامة، بريد) لأن مستخدمي
 *  المحادثة قد يكتبون بياناتهم داخل السؤال حتى لو لم يُطلب منهم ذلك.
 *
 *  تنبيه: هذا تمويه أنماط شكلية (regex) لا ضمان خصوصية كامل — لا يكتشف
 *  تفاصيل تُعرّف الشخص بشكل غير مباشر (مثل وصف حالة نادرة + مدينة + جهة
 *  عمل معاً). راجع عيّنة من الخرج يدوياً قبل استخدامه في تدريب فعلي،
 *  خصوصاً أن المستخدمين هنا من ذوي الإعاقة — فئة تستحق حذراً أكبر.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}

$cfg = __DIR__ . '/../api/config.php';
if (!file_exists($cfg)) {
    exit("لم أجد api/config.php — انسخ api/config.example.php إليه واملأ بياناته أولاً.\n");
}
require_once __DIR__ . '/../api/db.php';

$outPath = $argv[1] ?? (__DIR__ . '/output/chat-training-data.jsonl');
@mkdir(dirname($outPath), 0775, true);

/** تمويه الأنماط الشائعة التي قد يكتبها مستخدم داخل نص حر */
function redact(string $text): string
{
    // جوال سعودي: 05xxxxxxxx أو +9665xxxxxxxx أو 9665xxxxxxxx
    $text = preg_replace('/(\+?9665|05)\d{8}\b/', '[رقم_جوال]', $text);
    // هوية وطنية/إقامة: عشرة أرقام تبدأ بـ 1 أو 2
    $text = preg_replace('/\b[12]\d{9}\b/', '[رقم_هوية]', $text);
    // بريد إلكتروني
    $text = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '[بريد_إلكتروني]', $text);
    return $text;
}

$stmt = db()->query("
    SELECT question, answer FROM chat_logs
    WHERE answer IS NOT NULL AND answer <> '' AND mode <> 'demo'
    ORDER BY id
");

$out = fopen($outPath, 'w');
if ($out === false) {
    exit("تعذّر إنشاء ملف الخرج: $outPath\n");
}

$kept = 0;
$redactedRows = 0;
while ($row = $stmt->fetch()) {
    $q = redact((string) $row['question']);
    $a = redact((string) $row['answer']);
    if ($q !== $row['question'] || $a !== $row['answer']) {
        $redactedRows++;
    }
    fwrite($out, json_encode(['instruction' => $q, 'response' => $a], JSON_UNESCAPED_UNICODE) . "\n");
    $kept++;
}
fclose($out);

echo "تم تصدير {$kept} صفاً إلى: {$outPath}\n";
echo "عدد الصفوف التي حوت نمطاً مموَّهاً: {$redactedRows}\n";
echo "راجع عيّنة من الملف يدوياً قبل استخدامه في التدريب.\n";
