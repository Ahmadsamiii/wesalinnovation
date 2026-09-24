<?php
require_once __DIR__ . '/db.php';
ensureSchema();

$in = body();
$action = $in['action'] ?? '';

/** توحيد رقم الجوال السعودي إلى صيغة 05XXXXXXXX */
function normPhone(string $p): string {
    $p = preg_replace('/[\s\-()]/', '', $p);
    if (preg_match('/^\+9665\d{8}$/', $p)) return '0' . substr($p, 4);
    if (preg_match('/^9665\d{8}$/',  $p)) return '0' . substr($p, 3);
    return $p;
}
function validPhone(string $p): bool { return preg_match('/^05\d{8}$/', $p) === 1; }
function validName(string $n): bool  { return preg_match('/^[\p{L}\s\'\-]{2,40}$/u', $n) === 1; }
function validNameAr(string $n): bool { return preg_match('/^[\x{0621}-\x{064A}\s]{2,40}$/u', $n) === 1; }
function validNameEn(string $n): bool { return preg_match('/^[A-Za-z\s\'\-]{2,40}$/', $n) === 1; }
function validDob(string $d): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    $ts = strtotime($d);
    if ($ts === false || $ts > time()) return false;
    return $ts >= strtotime('-100 years');
}

try {
    switch ($action) {

        case 'register': {
            rateLimit('reg', 8);
            $first   = clean($in['first'] ?? '', 40);
            $last    = clean($in['last']  ?? '', 40);
            $firstEn = clean($in['first_en'] ?? '', 40);
            $lastEn  = clean($in['last_en']  ?? '', 40);
            $email = mb_strtolower(clean($in['email'] ?? '', 120));
            $phone = normPhone(clean($in['phone'] ?? '', 20));
            $dob   = clean($in['dob'] ?? '', 10);
            $pref  = in_array($in['pref'] ?? '', ['simple','detailed','voice','visual'], true) ? $in['pref'] : 'simple';
            $pass  = (string)($in['password'] ?? '');

            if (!validNameAr($first))                      fail('اكتب اسمك الأول بالحروف العربية فقط.');
            if (!validNameAr($last))                       fail('اكتب اسمك الأخير بالحروف العربية فقط.');
            if (!validNameEn($firstEn))                    fail('اكتب اسمك الأول بالحروف الإنجليزية فقط (First name).');
            if (!validNameEn($lastEn))                     fail('اكتب اسمك الأخير بالحروف الإنجليزية فقط (Last name).');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('البريد الإلكتروني غير صحيح — تأكد من كتابته.');
            if (!validPhone($phone))                        fail('رقم الجوال إجباري — الصيغة: 05XXXXXXXX أو +9665XXXXXXXX.');
            if (!validDob($dob))                            fail('تاريخ الميلاد غير صحيح.');
            if (mb_strlen($pass) < 8)                       fail('كلمة المرور لازم تكون 8 أحرف فأكثر.');
            if (!preg_match('/\p{L}/u', $pass) || !preg_match('/\d/', $pass))
                                                            fail('كلمة المرور لازم تحتوي حرفاً ورقماً على الأقل.');

            $s = db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
            $s->execute([$email]);
            if ($s->fetch()) fail('يوجد حساب مرتبط بنفس البريد الإلكتروني — سجّل دخولك أو استخدم بريداً آخر.');

            $s = db()->prepare('SELECT id FROM users WHERE phone=? LIMIT 1');
            $s->execute([$phone]);
            if ($s->fetch()) fail('يوجد حساب مرتبط بنفس رقم الجوال — سجّل دخولك أو استخدم رقماً آخر.');

            $inviteRole = null; $inviteId = null;
            $tok = preg_replace('/[^a-f0-9]/', '', (string)($in['invite'] ?? ''));
            if (strlen($tok) >= 32) {
                $s = db()->prepare("SELECT id,email,role_target FROM invites WHERE token=? AND status='sent' LIMIT 1");
                $s->execute([$tok]);
                if ($inv = $s->fetch()) {
                    if (mb_strtolower($inv['email']) !== $email)
                        fail('هذه الدعوة مرسلة لبريد إلكتروني آخر — سجّل بنفس البريد الذي وصلته الدعوة.');
                    $inviteRole = $inv['role_target'];
                    $inviteId   = (int)$inv['id'];
                } else fail('رابط الدعوة غير صالح أو استُخدم من قبل.');
            }

            $name   = trim($first . ' ' . $last);
            $nameEn = trim($firstEn . ' ' . $lastEn);
            $isFirst = (int) db()->query('SELECT COUNT(*) c FROM users')->fetch()['c'] === 0;

            $ins = db()->prepare('INSERT INTO users (name,name_en,dob,email,phone,pref,pass_hash,role,tokens,tokens_at,created_at)
                                  VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())');
            $ins->execute([$name, $nameEn, $dob, $email, $phone, $pref,
                password_hash($pass, PASSWORD_DEFAULT),
                $isFirst ? 'admin' : ($inviteRole ?: 'user'), USER_TOKENS]);

            $_SESSION['uid'] = (int) db()->lastInsertId();
            if ($inviteId) db()->prepare("UPDATE invites SET status='accepted', accepted_at=NOW() WHERE id=?")->execute([$inviteId]);
            session_regenerate_id(true);
            out(['ok' => true, 'user' => publicUser(currentUser())]);
        }

        case 'login': {
            rateLimit('login', 12);
            $email = mb_strtolower(clean($in['email'] ?? '', 120));
            $pass  = (string)($in['password'] ?? '');

            $s = db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
            $s->execute([$email]);
            $u = $s->fetch();
            if (!$u || !password_verify($pass, $u['pass_hash']))
                fail('البريد أو كلمة المرور غير صحيحة.', 401);
            if (($u['status'] ?? 'active') === 'suspended')
                fail('حسابك موقوف حالياً. راسلنا من صفحة «تواصل معنا» ونراجع الموضوع معك.', 403);

            $_SESSION['uid'] = (int) $u['id'];
            session_regenerate_id(true);
            db()->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$u['id']]);
            out(['ok' => true, 'user' => publicUser(refreshTokens($u))]);
        }

        /* ---------- إعادة تعيين كلمة المرور ---------- */

        case 'forgot': {
            rateLimit('forgot', 5);
            $email = mb_strtolower(clean($in['email'] ?? '', 120));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('اكتب بريداً إلكترونياً صحيحاً.');
            $s = db()->prepare('SELECT id,name,status FROM users WHERE email=? LIMIT 1');
            $s->execute([$email]);
            $u = $s->fetch();
            // الرد نفسه سواء وُجد الحساب أو لا، حتى لا يُستخدم النموذج لمعرفة من هو مسجّل
            if ($u && $u['status'] !== 'suspended') {
                $token = issueResetToken((int)$u['id']);
                $link  = SITE_URL . '/?reset=' . $token;
                sendMail($email, 'إعادة تعيين كلمة المرور في وصال',
                         resetEmailHtml($u['name'], $link, false, 2));
                audit(null, 'forgot', $email, 'طلب المستخدم إعادة تعيين');
            }
            out(['ok' => true, 'message' => 'إذا كان البريد مسجّلاً عندنا فبيوصلك رابط إعادة التعيين خلال دقائق. راجع مجلد الرسائل غير المرغوبة لو ما وصل.']);
        }

        case 'reset_info': {
            $tok = preg_replace('/[^a-f0-9]/', '', (string)($in['token'] ?? ''));
            if (strlen($tok) < 32) fail('رابط إعادة التعيين غير صالح.');
            $s = db()->prepare('SELECT u.email, u.name FROM password_resets r JOIN users u ON u.id=r.user_id
                                WHERE r.token_hash=? AND r.used_at IS NULL AND r.expires_at > NOW() LIMIT 1');
            $s->execute([hash('sha256', $tok)]);
            $r = $s->fetch();
            if (!$r) fail('انتهت صلاحية الرابط أو استُخدم من قبل. اطلب رابطاً جديداً.');
            $p = explode('@', $r['email']);
            $mask = mb_substr($p[0], 0, 2) . str_repeat('•', max(2, mb_strlen($p[0]) - 2)) . '@' . ($p[1] ?? '');
            out(['ok' => true, 'name' => $r['name'], 'email_masked' => $mask]);
        }

        case 'reset': {
            rateLimit('reset', 10);
            $tok  = preg_replace('/[^a-f0-9]/', '', (string)($in['token'] ?? ''));
            $pass = (string)($in['password'] ?? '');
            if (strlen($tok) < 32)            fail('رابط إعادة التعيين غير صالح.');
            if ($e = passwordError($pass))    fail($e);

            $s = db()->prepare('SELECT user_id FROM password_resets
                                WHERE token_hash=? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
            $s->execute([hash('sha256', $tok)]);
            $r = $s->fetch();
            if (!$r) fail('انتهت صلاحية الرابط أو استُخدم من قبل. اطلب رابطاً جديداً.');

            db()->beginTransaction();
            try {
                db()->prepare('UPDATE users SET pass_hash=?, must_change_pw=0 WHERE id=?')
                    ->execute([password_hash($pass, PASSWORD_DEFAULT), $r['user_id']]);
                db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')
                    ->execute([$r['user_id']]);
                db()->commit();
            } catch (Throwable $e) {
                db()->rollBack();
                fail(APP_DEBUG ? $e->getMessage() : 'تعذّر تعيين كلمة المرور. حاول مرة أخرى.', 500);
            }
            audit(null, 'reset_done', 'user#' . $r['user_id'], 'عبر رابط إعادة التعيين');
            $_SESSION = [];   // أنهِ أي جلسة قائمة — يدخل بكلمة المرور الجديدة
            out(['ok' => true, 'message' => 'تم تعيين كلمة المرور. سجّل دخولك الآن.']);
        }

        case 'change_password': {
            $u = currentUser();
            if (!$u) fail('سجّل دخولك أولاً.', 401);
            rateLimit('chpw', 10);
            $cur = (string)($in['current'] ?? '');
            $new = (string)($in['password'] ?? '');
            $s = db()->prepare('SELECT pass_hash FROM users WHERE id=? LIMIT 1');
            $s->execute([$u['id']]);
            $row = $s->fetch();
            if (!$row || !password_verify($cur, $row['pass_hash'])) fail('كلمة المرور الحالية غير صحيحة.');
            if ($e = passwordError($new))  fail($e);
            if ($cur === $new)             fail('اختر كلمة مرور مختلفة عن الحالية.');
            db()->prepare('UPDATE users SET pass_hash=?, must_change_pw=0 WHERE id=?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            audit($u, 'change_pw', $u['email'], '');
            out(['ok' => true, 'user' => publicUser(currentUser())]);
        }

        /* ---------- الخصوصية ---------- */

        case 'set_improve': {
            $u = currentUser();
            if (!$u) fail('سجّل دخولك أولاً.', 401);
            $v = !empty($in['value']) ? 1 : 0;
            db()->prepare('UPDATE users SET improve=? WHERE id=?')->execute([$v, $u['id']]);
            out(['ok' => true, 'improve' => (bool)$v]);
        }

        case 'delete_account': {
            $u = currentUser();
            if (!$u) fail('سجّل دخولك أولاً.', 401);
            $pass = (string)($in['password'] ?? '');
            $s = db()->prepare('SELECT pass_hash FROM users WHERE id=? LIMIT 1');
            $s->execute([$u['id']]);
            $row = $s->fetch();
            if (!$row || !password_verify($pass, $row['pass_hash']))
                fail('اكتب كلمة مرورك الحالية لتأكيد الحذف.', 401);
            if ($u['role'] === 'admin') {
                $n = (int) db()->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch()['c'];
                if ($n <= 1) fail('ما نقدر نحذف آخر حساب مدير نظام في المنصة. عيّن مديراً آخر أولاً.');
            }
            if (!empty($u['avatar']) && strpos($u['avatar'], 'uploads/avatars/') === 0)
                @unlink(dirname(__DIR__) . '/uploads/avatars/' . basename($u['avatar']));

            db()->beginTransaction();
            try {
                // سجل المحادثات يُفصل عن الهوية بدل حذفه، فتبقى إحصاءات المنصة سليمة
                db()->prepare('UPDATE chat_logs SET user_id=NULL WHERE user_id=?')->execute([$u['id']]);
                db()->prepare('DELETE FROM support_tickets WHERE user_id=?')->execute([$u['id']]);
                db()->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$u['id']]);
                db()->prepare('DELETE FROM users WHERE id=?')->execute([$u['id']]);
                db()->commit();
            } catch (Throwable $e) {
                db()->rollBack();
                fail(APP_DEBUG ? $e->getMessage() : 'تعذّر حذف الحساب. حاول مرة أخرى.', 500);
            }
            audit(null, 'delete_account', $u['email'], 'حذف ذاتي');
            $_SESSION = [];
            session_destroy();
            out(['ok' => true, 'message' => 'حُذف حسابك وبياناتك الشخصية نهائياً من أنظمتنا.']);
        }

        case 'me': {
            $u = currentUser();
            if (!$u) out(['ok' => false, 'guest' => true]);
            out(['ok' => true, 'user' => publicUser(refreshTokens($u))]);
        }

        case 'update': {
            // البيانات الأساسية مقفلة — التخصيص فقط قابل للتعديل، والباقي عبر طلب دعم
            $u = currentUser();
            if (!$u) fail('سجّل دخولك أولاً.', 401);

            $pref  = in_array($in['pref'] ?? '', ['simple','detailed','voice','visual'], true) ? $in['pref'] : $u['pref'];
            $city      = clean($in['city']      ?? '', 60);
            $disab     = clean($in['disability']?? '', 60);
            $interests = clean($in['interests'] ?? '', 300);
            $bio       = clean($in['bio']       ?? '', 500);

            db()->prepare('UPDATE users SET pref=?, city=?, disability=?, interests=?, bio=? WHERE id=?')
                ->execute([$pref, $city, $disab, $interests, $bio, $u['id']]);
            out(['ok' => true, 'user' => publicUser(currentUser())]);
        }

        case 'ticket': {
            $u = currentUser();
            if (!$u) fail('سجّل دخولك أولاً.', 401);
            rateLimit('ticket', 6);
            $type    = clean($in['type'] ?? '', 60);
            $details = clean($in['details'] ?? '', 2000);
            if (!in_array($type, TICKET_TYPES, true)) fail('اختر نوع الطلب من القائمة.');
            if (mb_strlen($details) < 10)       fail('اكتب تفاصيل الطلب (10 أحرف على الأقل).');
            db()->prepare('INSERT INTO support_tickets (user_id,type,details,created_at) VALUES (?,?,?,NOW())')
                ->execute([$u['id'], $type, $details]);
            out(['ok' => true]);
        }

        case 'my_tickets': {
            $u = currentUser();
            if (!$u) fail('سجّل دخولك أولاً.', 401);
            $s = db()->prepare('SELECT id,type,details,status,created_at FROM support_tickets WHERE user_id=? ORDER BY created_at DESC LIMIT 20');
            $s->execute([$u['id']]);
            $ts = array_map(fn($t) => ['id'=>(int)$t['id'],'type'=>$t['type'],'details'=>$t['details'],
                'status'=>$t['status'],'t'=>strtotime($t['created_at'])*1000], $s->fetchAll());
            out(['ok' => true, 'tickets' => $ts]);
        }

        case 'avatar': {
            $u = currentUser();
            if (!$u) fail('سجّل دخولك أولاً.', 401);

            $img = (string)($in['image'] ?? '');
            // الواجهة تصغّر الصورة إلى 512 بكسل قبل الإرسال، فالنص الطويل جداً طلب غير طبيعي
            if (strlen($img) > 3500000)                    fail('حجم الصورة كبير — الحد الأقصى 2.5 ميجابايت.');
            if (preg_match('#^data:image/(jpeg|png|webp);base64,#', $img, $m)) {
                $img = substr($img, strpos($img, ',') + 1);
            }
            $bin = base64_decode($img, true);
            if ($bin === false || strlen($bin) < 100)      fail('الصورة غير صالحة.');
            if (strlen($bin) > 2.5 * 1024 * 1024)          fail('حجم الصورة كبير — الحد الأقصى 2.5 ميجابايت.');

            $info = @getimagesizefromstring($bin);
            $mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!$info || !isset($mimes[$info['mime']]))   fail('نقبل صور JPG أو PNG أو WebP فقط.');

            // فشل المجلد كان يظهر للمستخدم كفشل عام بلا أثر في السجل — الآن السبب يُسجَّل
            $dir = dirname(__DIR__) . '/uploads/avatars';
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                error_log('WESAL_AVATAR_FAIL: cannot create ' . $dir);
                fail('تعذّر تجهيز مجلد الصور على الخادم — بلّغنا بطلب دعم فني.', 500);
            }
            if (!is_writable($dir)) {
                error_log('WESAL_AVATAR_FAIL: not writable ' . $dir);
                fail('مجلد الصور على الخادم غير قابل للكتابة — بلّغنا بطلب دعم فني.', 500);
            }

            // خط دفاع ثانٍ داخل مجلد المرفوعات نفسه، إضافة إلى قاعدة الجذر في .htaccess
            $guard = dirname(__DIR__) . '/uploads/.htaccess';
            if (!file_exists($guard)) {
                @file_put_contents($guard,
                    "<IfModule mod_mime.c>\n"
                  . "  RemoveHandler .php .phtml .phar .php3 .php4 .php5 .php7 .php8\n"
                  . "  RemoveType    .php .phtml .phar\n"
                  . "</IfModule>\n"
                  . "<FilesMatch \"\\.(php|phtml|phar|php[0-9]|pl|py|cgi|sh)$\">\n"
                  . "  Require all denied\n"
                  . "</FilesMatch>\n");
            }
            $fname = 'u' . (int)$u['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $mimes[$info['mime']];
            if (@file_put_contents($dir . '/' . $fname, $bin) === false) {
                error_log('WESAL_AVATAR_FAIL: write failed ' . $dir . '/' . $fname);
                fail('تعذّر حفظ الصورة — حاول مرة أخرى.', 500);
            }

            // حذف الصورة القديمة إن وُجدت
            if (!empty($u['avatar']) && strpos($u['avatar'], 'uploads/avatars/') === 0) {
                @unlink(dirname(__DIR__) . '/' . basename_safe($u['avatar']));
            }

            $path = 'uploads/avatars/' . $fname;
            db()->prepare('UPDATE users SET avatar=? WHERE id=?')->execute([$path, $u['id']]);
            out(['ok' => true, 'avatar' => $path]);
        }

        case 'avatar_remove': {
            $u = currentUser();
            if (!$u) fail('سجّل دخولك أولاً.', 401);
            if (!empty($u['avatar']) && strpos($u['avatar'], 'uploads/avatars/') === 0) {
                @unlink(dirname(__DIR__) . '/' . basename_safe($u['avatar']));
            }
            db()->prepare('UPDATE users SET avatar=NULL WHERE id=?')->execute([$u['id']]);
            out(['ok' => true]);
        }

        case 'logout': {
            $_SESSION = [];
            session_destroy();
            out(['ok' => true]);
        }

        case 'invite_info': {
            $tok = preg_replace('/[^a-f0-9]/', '', (string)($in['invite'] ?? ''));
            if (strlen($tok) < 32) fail('رابط الدعوة غير صالح.');
            $s = db()->prepare("SELECT email, role_target FROM invites WHERE token=? AND status='sent' LIMIT 1");
            $s->execute([$tok]);
            $inv = $s->fetch();
            if (!$inv) fail('رابط الدعوة غير صالح أو استُخدم من قبل.');
            out(['ok' => true, 'email' => $inv['email'], 'role_target' => $inv['role_target']]);
        }

        default: fail('طلب غير معروف.');
    }
} catch (Throwable $e) {
    fail(APP_DEBUG ? $e->getMessage() : 'صار خطأ غير متوقع. حاول مرة أخرى.', 500);
}

function basename_safe(string $p): string {
    return 'uploads/avatars/' . basename($p);
}
