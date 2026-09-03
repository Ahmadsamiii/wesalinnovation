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

            $_SESSION['uid'] = (int) $u['id'];
            session_regenerate_id(true);
            db()->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$u['id']]);
            out(['ok' => true, 'user' => publicUser(refreshTokens($u))]);
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
            $types = ['تعديل الاسم','تعديل رقم الجوال','تعديل البريد الإلكتروني','تعديل تاريخ الميلاد',
                      'مشكلة في الرصيد أو الأسئلة','مشكلة تقنية في المنصة','بلاغ عن معلومة غير دقيقة','حذف الحساب','أخرى'];
            $type    = clean($in['type'] ?? '', 60);
            $details = clean($in['details'] ?? '', 2000);
            if (!in_array($type, $types, true)) fail('اختر نوع الطلب من القائمة.');
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
            if (preg_match('#^data:image/(jpeg|png|webp);base64,#', $img, $m)) {
                $img = substr($img, strpos($img, ',') + 1);
            }
            $bin = base64_decode($img, true);
            if ($bin === false || strlen($bin) < 100)      fail('الصورة غير صالحة.');
            if (strlen($bin) > 2.5 * 1024 * 1024)          fail('حجم الصورة كبير — الحد الأقصى 2.5 ميجابايت.');

            $info = @getimagesizefromstring($bin);
            $mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!$info || !isset($mimes[$info['mime']]))   fail('نقبل صور JPG أو PNG أو WebP فقط.');

            $dir = dirname(__DIR__) . '/uploads/avatars';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);

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
            if (@file_put_contents($dir . '/' . $fname, $bin) === false)
                fail('تعذّر حفظ الصورة — حاول مرة أخرى.', 500);

            // حذف الصورة القديمة إن وُجدت
            if (!empty($u['avatar']) && strpos($u['avatar'], 'uploads/avatars/') === 0) {
                @unlink(dirname(__DIR__) . '/' . basename_safe($u['avatar']));
            }

            $path = 'uploads/avatars/' . $fname;
            db()->prepare('UPDATE users SET avatar=? WHERE id=?')->execute([$path, $u['id']]);
            out(['ok' => true, 'avatar' => $path]);
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
