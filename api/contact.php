<?php
require_once __DIR__ . '/db.php';
rateLimit('contact', 6);

$in      = body();
$name    = clean($in['name'] ?? '', 80);
$email   = mb_strtolower(clean($in['email'] ?? '', 120));
$subject = clean($in['subject'] ?? '', 120);
$message = clean($in['message'] ?? '', 4000);

if (mb_strlen($name) < 3)                       fail('اكتب اسمك كاملاً.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('اكتب بريداً إلكترونياً صحيحاً حتى نقدر نرد عليك.');
if ($subject === '')                            fail('اختر موضوع الرسالة.');
if (mb_strlen($message) < 10)                   fail('اكتب رسالتك بتفصيل أكثر.');

try {
    db()->prepare('INSERT INTO messages (name,email,subject,message,ip,created_at) VALUES (?,?,?,?,?,NOW())')
        ->execute([$name, $email, $subject, $message, $_SERVER['REMOTE_ADDR'] ?? '']);
} catch (Throwable $e) {
    fail(APP_DEBUG ? $e->getMessage() : 'تعذّر حفظ رسالتك. حاول مرة أخرى.', 500);
}

// إشعار بالبريد (اختياري — يعمل إذا كان mail() مفعّلاً في الاستضافة)
@mail(CONTACT_TO,
    '=?UTF-8?B?' . base64_encode('رسالة جديدة من ' . SITE_NAME . ': ' . $subject) . '?=',
    "الاسم: $name\nالبريد: $email\nالموضوع: $subject\n\n$message\n",
    "From: " . MAIL_FROM . "\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8\r\n");

out(['ok' => true, 'message' => 'وصلتنا رسالتك. نرد عليك خلال يوم عمل واحد.']);
