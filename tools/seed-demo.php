<?php
/* ==========================================================================
 *  وصال — إنشاء/حذف الحسابات التجريبية من الطرفية
 *
 *  الاستخدام:
 *      php tools/seed-demo.php          إنشاء الحسابات وطباعة بياناتها
 *      php tools/seed-demo.php purge    حذفها كلها مع بيانات الأمثلة
 *      php tools/seed-demo.php status   عرض عددها الحالي
 *
 *  للاستضافات التي لا تملك وصولاً للطرفية: نفس العمل متاح من لوحة مدير
 *  النظام ← تبويب المستخدمين ← قسم الحسابات التجريبية.
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
require_once __DIR__ . '/../api/demo.php';

$cmd = $argv[1] ?? 'seed';

try {
    if ($cmd === 'status') {
        printf("الحسابات التجريبية الموجودة: %d من %d\n", demoCount(), count(demoAccounts()));
        exit(0);
    }

    if ($cmd === 'purge') {
        $n = purgeDemoAccounts(null);
        printf("حُذف %d حساباً تجريبياً ومعه بيانات الأمثلة.\n", $n);
        exit(0);
    }

    if ($cmd !== 'seed') {
        exit("أمر غير معروف: $cmd — استخدم seed أو purge أو status.\n");
    }

    $accounts = seedDemoAccounts(null);

    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  الحسابات التجريبية — كلمات المرور تُطبع هذه المرة فقط\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    foreach ($accounts as $a) {
        printf("  %-16s %s\n", 'الدور:',        $a['role_name']);
        printf("  %-16s %s\n", 'الاسم:',        $a['name']);
        printf("  %-16s %s\n", 'البريد:',       $a['email']);
        printf("  %-16s %s\n", 'كلمة المرور:',  $a['password']);
        echo "  ---------------------------------------------------------------\n";
    }
    echo "\n  لو ضاعت كلمة مرور، أعد تشغيل السكربت وتتولّد كلمات جديدة.\n";
    echo "  وللحذف نهائياً:  php tools/seed-demo.php purge\n\n";
    exit(0);

} catch (Throwable $e) {
    fwrite(STDERR, "فشل التنفيذ: " . $e->getMessage() . "\n");
    exit(1);
}
