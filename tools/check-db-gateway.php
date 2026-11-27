<?php
/* ==========================================================================
 *  وصال: فحص دوال بوابة قاعدة البيانات
 *
 *  الاستخدام:
 *      php tools/check-db-gateway.php
 *
 *  يفحص تصنيف الجمل في api/db-gateway-lib.php: ما يُقبل قراءةً أو كتابة، وما
 *  يُرفض، وما يُطلب له destructive. لا يحتاج قاعدة بيانات ولا إعدادات.
 *  خرج 0 نجاح، 1 فشل.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}

require_once __DIR__ . '/../api/db-gateway-lib.php';

$pass = 0;
$fail = 0;

/** $expect: 'read' أو 'write' أو 'destructive' أو 'reject' */
function t(string $sql, string $expect): void
{
    global $pass, $fail;
    $c = gwClassify($sql);
    $got = !$c['ok'] ? 'reject' : ($c['kind'] === 'write' && $c['destructive'] ? 'destructive' : $c['kind']);
    if ($got === $expect) { $pass++; return; }
    $fail++;
    printf("  \033[31m✗\033[0m %s\n      المتوقع: %s، الفعلي: %s%s\n", $sql, $expect, $got, $c['ok'] ? '' : ' (' . $c['error'] . ')');
}

/* ----- قراءة ----- */
t('SELECT * FROM users WHERE id = ?', 'read');
t('select 1;', 'read');
t('  SHOW TABLES', 'read');
t('DESCRIBE users', 'read');
t('EXPLAIN SELECT * FROM users', 'read');
t('(SELECT 1) UNION (SELECT 2)', 'read');
t('WITH x AS (SELECT 1) SELECT * FROM x', 'read');
t("SELECT * FROM messages WHERE body = 'drop database; delete from users'", 'read');
t("SELECT 'a;b' AS x", 'read');
t('SELECT `delete` FROM t', 'read');
t("SELECT 5--3", 'read');

/* ----- كتابة عادية ----- */
t('INSERT INTO t (a) VALUES (?)', 'write');
t('UPDATE users SET name = ? WHERE id = ?', 'write');
t('DELETE FROM users WHERE id = ?', 'write');
t('UPDATE t SET a = (SELECT MAX(b) FROM u WHERE u.x = 1) WHERE id = 3', 'write');
t('CREATE TABLE x (id INT)', 'write');
t('ALTER TABLE users ADD COLUMN note VARCHAR(10) NULL', 'write');
t("INSERT INTO log (m) VALUES ('update x set a=1')", 'write');

/* ----- تحتاج destructive ----- */
t('DELETE FROM users', 'destructive');
t('UPDATE users SET role = ?', 'destructive');
t('UPDATE t SET a = (SELECT b FROM u WHERE u.x = 1)', 'destructive');
t('DROP TABLE users', 'destructive');
t('TRUNCATE TABLE users', 'destructive');
t('ALTER TABLE users DROP COLUMN bio', 'destructive');
t("DELETE FROM users -- WHERE id = 1\n", 'destructive');

/* ----- مرفوضة ----- */
t('', 'reject');
t(';', 'reject');
t('SELECT 1; DROP TABLE users', 'reject');
t('SELECT 1; SELECT 2', 'reject');
t('GRANT ALL ON *.* TO x', 'reject');
t('SET GLOBAL max_connections = 1', 'reject');
t('KILL 1', 'reject');
t('CALL do_things()', 'reject');
t('DROP DATABASE wesal', 'reject');
t('DROP SCHEMA wesal', 'reject');
t('CREATE USER x IDENTIFIED BY y', 'reject');
t("SELECT * FROM users INTO OUTFILE '/tmp/x'", 'reject');
t("SELECT LOAD_FILE('/etc/passwd')", 'reject');
t('SELECT SLEEP(100)', 'reject');
t('SELECT /*! 1; DROP TABLE users */ 1', 'reject');
t('SELECT /*+ MAX_EXECUTION_TIME(1) */ 1', 'reject');
t("SELECT 'unterminated", 'reject');
t('SELECT /* unterminated', 'reject');
t('SELECT 1 /* ok */ ; /* ok */', 'read');
t("LOAD DATA INFILE 'x' INTO TABLE t", 'reject');

/* ----- علاقة التعليق بالفحص ----- */
t("SELECT 1 # ; DROP TABLE users\n", 'read');
t("DELETE FROM t WHERE id = 1 # WHERE\n", 'write');

/* ----- أعمدة محجوبة ----- */
foreach ([['password', true], ['pass_hash', true], ['token_hash', true], ['api_key', true], ['name', false], ['email', false]] as [$col, $want]) {
    $got = gwIsRedactedColumn($col, '/pass(word)?|secret|token|api_?key|hash$/i');
    if ($got === $want) { $pass++; continue; }
    $fail++;
    printf("  \033[31m✗\033[0m العمود %s: المتوقع %s\n", $col, $want ? 'محجوب' : 'ظاهر');
}

printf("\n%d نجح، %d فشل\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
