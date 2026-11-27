<?php
/* ==========================================================================
 *  وصال: بوابة قاعدة البيانات
 *
 *  نقطة HTTPS واحدة تنفّذ جملة SQL على قاعدة الإنتاج بالاتصال نفسه الذي يستعمله
 *  الموقع (db() في db.php)، لمن يحمل مفتاحاً سرياً. وُضعت لتمكين أداة خارجية
 *  (مساعد برمجي مثلاً) من فحص البيانات وتعديلها بلا فتح MySQL للشبكة.
 *
 *  معطّلة افتراضياً: بلا DB_GATEWAY_KEY في config.php (٣٢ حرفاً فأكثر) ترد 404.
 *  الكتابة معطّلة حتى لو فُعّلت البوابة، ولا تُفتح إلا بـ DB_GATEWAY_WRITE = true.
 *  الضبط الكامل في config.example.php.
 *
 *  الطلب: POST بجسم JSON، والمفتاح في ترويسة X-Gateway-Key (لا في الرابط، فالروابط
 *  تُسجَّل في سجلات الخادم):
 *
 *    {"mode":"info"}                               حالة الاتصال والإعدادات
 *    {"mode":"read","sql":"SELECT ... WHERE id=?","params":[7],"limit":200}
 *    {"mode":"write","sql":"UPDATE ... WHERE id=?","params":[7],"dry_run":true}
 *    {"mode":"write","sql":"DELETE FROM t","destructive":true}
 *
 *  الضمانات:
 *    - جملة واحدة في الطلب، والمعاملات (params) تُمرَّر مربوطة لا مدمجة في النص.
 *    - القراءة داخل معاملة READ ONLY يرفضها MySQL نفسه لو تسللت كتابة.
 *    - أعمدة كلمات المرور والرموز لا تخرج قيمها في نتائج القراءة.
 *    - الكتابة DML تُجرَّب أولاً بـ dry_run (تُنفَّذ ثم تُلغى) فيرى المنفّذ عدد
 *      الصفوف المتأثرة قبل التثبيت. التعديلات البنيوية (CREATE/ALTER/DROP) يثبّتها
 *      MySQL فور تنفيذها ولا تقبل dry_run.
 *    - ما يحذف جدولاً أو عموداً، أو يعدّل ويحذف بلا WHERE، يُرفض ما لم يُرسَل
 *      "destructive": true صراحةً.
 *    - كل كتابة مثبّتة تُسجَّل في الجدول db_gateway_log.
 *    - المحاولات الخاطئة للمفتاح محدودة بالدقيقة لكل عنوان IP.
 * ========================================================================== */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db-gateway-lib.php';
session_write_close();   // لا حاجة للجلسة هنا، ولا نريد أن تحجز ملفها

foreach ([
    'DB_GATEWAY_KEY'      => '',
    'DB_GATEWAY_WRITE'    => false,
    'DB_GATEWAY_MAX_ROWS' => 1000,
    /* أي عمود يطابق هذا النمط تُستبدل قيمته في نتائج القراءة */
    'DB_GATEWAY_REDACT'   => '/pass(word)?|secret|token|api_?key|hash$/i',
] as $k => $v) { if (!defined($k)) define($k, $v); }

header('Cache-Control: no-store');

/** رد البوابة: يتحمّل نصوصاً ثنائية في الأعمدة بدل أن يفشل ترميز الرد كله */
function gwOut(array $data, int $code = 200): void
{
    discardStrayOutput();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

function gwFail(string $msg, int $code = 400, array $extra = []): void
{
    gwOut(['ok' => false, 'error' => $msg] + $extra, $code);
}

/* ---------------- الحارس ---------------- */
if (strlen((string) DB_GATEWAY_KEY) < 32) {
    http_response_code(404);   // البوابة غير مفعّلة: لا تُعلن عن وجودها
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    gwFail('الطلب يكون بالطريقة POST.', 405);
}

$sent = (string) ($_SERVER['HTTP_X_GATEWAY_KEY'] ?? '');
if ($sent === '' || !hash_equals((string) DB_GATEWAY_KEY, $sent)) {
    /* عدّ المحاولات الخاطئة قبل الرد، فلا يمكن تخمين المفتاح بسرعة */
    if (hitCounter('m:gateway-fail', clientIp(), windowMinute()) > 10) {
        http_response_code(429);
    } else {
        http_response_code(403);
    }
    exit;
}

$raw = (string) file_get_contents('php://input');
if (strlen($raw) > 1048576) { gwFail('حجم الطلب أكبر من المسموح.', 413); }
$in = json_decode($raw, true);
if (!is_array($in)) { gwFail('جسم الطلب يجب أن يكون JSON.'); }

$mode = (string) ($in['mode'] ?? '');
$pdo = db();

/* ---------------- معلومات الاتصال ---------------- */
if ($mode === 'info') {
    $row = $pdo->query('SELECT DATABASE() AS db, VERSION() AS version, NOW() AS server_time')->fetch();
    gwOut(['ok' => true, 'mode' => 'info'] + $row + [
        'write_enabled' => DB_GATEWAY_WRITE === true,
        'max_rows'      => (int) DB_GATEWAY_MAX_ROWS,
    ]);
}

if ($mode !== 'read' && $mode !== 'write') {
    gwFail('الوضع يكون info أو read أو write.');
}

/* ---------------- فحص الجملة ومعاملاتها ---------------- */
$sql = (string) ($in['sql'] ?? '');
$params = $in['params'] ?? [];
if (!is_array($params) || count($params) > 500) { gwFail('المعاملات مصفوفة لا تزيد على ٥٠٠ عنصر.'); }
foreach ($params as $p) {
    if (!is_scalar($p) && $p !== null) { gwFail('كل معامل نص أو رقم أو قيمة فارغة.'); }
}
$params = array_values($params);

$cls = gwClassify($sql);
if (!$cls['ok']) { gwFail($cls['error']); }
if ($cls['kind'] !== $mode) {
    gwFail($cls['kind'] === 'read'
        ? 'هذه الجملة قراءة، فأرسلها بالوضع read.'
        : 'هذه الجملة تعدّل البيانات، فأرسلها بالوضع write.');
}

/* ---------------- القراءة ---------------- */
if ($mode === 'read') {
    $max = max(1, min((int) DB_GATEWAY_MAX_ROWS, (int) ($in['limit'] ?? DB_GATEWAY_MAX_ROWS)));

    /* سقف زمن التنفيذ: الأول لـMySQL بالمللي ثانية، والثاني لـMariaDB بالثواني */
    foreach (['SET SESSION MAX_EXECUTION_TIME=10000', 'SET SESSION max_statement_time=10'] as $setting) {
        try { $pdo->exec($setting); } catch (Throwable $e) { /* المتغير غير موجود في هذا الخادم */ }
    }

    $rows = [];
    $truncated = false;
    $redacted = [];
    try {
        $pdo->exec('START TRANSACTION READ ONLY');
        $st = $pdo->prepare($sql);
        $st->execute($params);

        $cols = [];
        for ($i = 0, $n = $st->columnCount(); $i < $n; $i++) {
            $cols[$i] = (string) ($st->getColumnMeta($i)['name'] ?? '');
            if (gwIsRedactedColumn($cols[$i], (string) DB_GATEWAY_REDACT)) { $redacted[] = $cols[$i]; }
        }
        while (($r = $st->fetch()) !== false) {
            if (count($rows) >= $max) { $truncated = true; break; }
            foreach ($redacted as $c) {
                if (array_key_exists($c, $r) && $r[$c] !== null && $r[$c] !== '') { $r[$c] = '[مخفي]'; }
            }
            $rows[] = $r;
        }
        $st->closeCursor();
    } catch (PDOException $e) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $e2) { /* لا شيء مفتوح */ }
        gwFail('رفض الخادم الجملة: ' . $e->getMessage(), 400, ['sqlstate' => $e->getCode()]);
    }
    try { $pdo->exec('ROLLBACK'); } catch (Throwable $e) { /* لا شيء مفتوح */ }

    gwOut(['ok' => true, 'mode' => 'read', 'count' => count($rows), 'truncated' => $truncated,
           'redacted_columns' => array_values(array_unique($redacted)), 'rows' => $rows]);
}

/* ---------------- الكتابة ---------------- */
if (DB_GATEWAY_WRITE !== true) {
    gwFail('الكتابة معطّلة. فعّلها بـ DB_GATEWAY_WRITE في config.php على الخادم.', 403);
}
if ($cls['destructive'] && ($in['destructive'] ?? false) !== true) {
    gwFail('الجملة مرفوضة احتياطاً: ' . $cls['reason'] . '. إن كنت تقصدها فأعد إرسالها مع destructive=true.', 409,
           ['needs' => 'destructive']);
}
$dry = ($in['dry_run'] ?? false) === true;
if ($dry && $cls['ddl']) {
    gwFail('التعديلات البنيوية يثبّتها MySQL فور تنفيذها، فلا تقبل dry_run.');
}

/** يسجّل الكتابة المثبّتة. فشل التسجيل لا يلغي ما ثُبّت، لكنه يُكتب في سجل الأخطاء. */
function gwLog(PDO $pdo, string $sql, array $params, ?int $affected, bool $ok, string $note = ''): void
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS db_gateway_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            at DATETIME NOT NULL,
            ip VARCHAR(45) NOT NULL,
            statement_text TEXT NOT NULL,
            params_text VARCHAR(400) NULL,
            affected INT NULL,
            ok TINYINT(1) NOT NULL,
            note VARCHAR(300) NULL,
            INDEX (at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->prepare('INSERT INTO db_gateway_log (at,ip,statement_text,params_text,affected,ok,note)
                       VALUES (NOW(),?,?,?,?,?,?)')
            ->execute([clientIp(), mb_substr($sql, 0, 4000), $params ? mb_substr((string) json_encode($params, JSON_UNESCAPED_UNICODE), 0, 400) : null,
                       $affected, $ok ? 1 : 0, $note !== '' ? mb_substr($note, 0, 300) : null]);
    } catch (Throwable $e) {
        error_log('WESAL_GATEWAY_LOG_FAIL: ' . $e->getMessage());
    }
}

try {
    if ($cls['ddl']) {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $affected = $st->rowCount();
        $st->closeCursor();
        gwLog($pdo, $sql, $params, $affected, true, 'بنيوي');
        gwOut(['ok' => true, 'mode' => 'write', 'verb' => $cls['verb'], 'affected' => $affected,
               'committed' => true, 'dry_run' => false]);
    }

    $pdo->beginTransaction();
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $affected = $st->rowCount();
    $insertId = (int) $pdo->lastInsertId();
    $st->closeCursor();

    if ($dry) {
        $pdo->rollBack();
        gwOut(['ok' => true, 'mode' => 'write', 'verb' => $cls['verb'], 'affected' => $affected,
               'committed' => false, 'dry_run' => true]);
    }
    $pdo->commit();
    gwLog($pdo, $sql, $params, $affected, true);
    gwOut(['ok' => true, 'mode' => 'write', 'verb' => $cls['verb'], 'affected' => $affected,
           'insert_id' => $insertId, 'committed' => true, 'dry_run' => false]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    if (!$dry) { gwLog($pdo, $sql, $params, null, false, $e->getMessage()); }
    gwFail('رفض الخادم الجملة ولم يُثبَّت شيء: ' . $e->getMessage(), 400, ['sqlstate' => $e->getCode()]);
}
