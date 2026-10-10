<?php
/* ==========================================================================
 *  عدّاد زوار جناح وصال (صفحة /hello وشاشة الجناح /hello/wall)
 *
 *  POST: كل مسح لبطاقة NFC يفتح الصفحة يزيد العدّاد مرة واحدة، حتى لو لم يُكمل الزائر
 *        التجربة. الصفحة تحفظ الرقم في جهاز الزائر نفسه، فالجهاز الواحد لا يُعدّ مرتين.
 *  GET:  يعيد آخر رقم زائر لشاشة الجناح، ولا يغيّر شيئاً. الرقم يظهر على الشاشة نفسها.
 *
 *  لا يُخزَّن هنا اسم ولا عنوان IP ولا أي بيان عن الزائر: صف واحد فيه وقت الزيارة.
 *  رقم الزائر هو معرّف الصف، فهو فريد ولو وصل زائران في اللحظة نفسها.
 *
 *  لا يضمّن db.php عمداً: تضمينه يبدأ جلسة ويرسل كوكي لكل زائر، والعدّاد لا يحتاج
 *  أياً منهما. يكتفي بثوابت الاتصال من config.php.
 * ========================================================================== */

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function helloOut(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function helloDb(): PDO
{
    require_once __DIR__ . '/config.php';
    return new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';

/* قراءة آخر رقم لشاشة الجناح. الجدول ينشأ مع أول مسح، وقبله العدد صفر. */
if ($method === 'GET') {
    try {
        $n = 0;
        try {
            $n = (int) helloDb()->query('SELECT COALESCE(MAX(id), 0) FROM hello_visits')->fetchColumn();
        } catch (PDOException $e) {
            if ((string) $e->getCode() !== '42S02') {
                throw $e;
            }
        }
        helloOut(['ok' => true, 'n' => $n]);
    } catch (Throwable $e) {
        @error_log('[hello-visit] ' . get_class($e) . ': ' . $e->getMessage());
        helloOut(['ok' => false], 500);
    }
}

/* الترويسة المخصصة لا يرسلها متصفح من موقع آخر دون موافقة صريحة من الخادم، فتُغني عن
   رمز حماية: صفحة غريبة لا تستطيع أن تزيد العدّاد من جهاز زائر. */
if ($method !== 'POST' || ($_SERVER['HTTP_X_HELLO'] ?? '') !== '1') {
    helloOut(['ok' => false], 405);
}

/* زواحف المعاينة وأدوات الفحص الآلي ليست زواراً */
if (preg_match('/bot|crawl|spider|slurp|headless|preview/i', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
    helloOut(['ok' => true, 'n' => 0]);
}

try {
    $pdo = helloDb();

    $insert = static function () use ($pdo): void {
        $pdo->exec('INSERT INTO hello_visits (created_at) VALUES (NOW())');
    };
    try {
        $insert();
    } catch (PDOException $e) {
        if ((string) $e->getCode() !== '42S02') {
            throw $e;
        }
        /* الجدول غير موجود بعد (أول مسح على هذه القاعدة): ننشئه ثم نعيد الإدراج.
           تعريفه نفسه في schema.sql. */
        $pdo->exec('CREATE TABLE IF NOT EXISTS hello_visits (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $insert();
    }

    helloOut(['ok' => true, 'n' => (int) $pdo->lastInsertId()]);
} catch (Throwable $e) {
    @error_log('[hello-visit] ' . get_class($e) . ': ' . $e->getMessage());
    helloOut(['ok' => false], 500);
}
