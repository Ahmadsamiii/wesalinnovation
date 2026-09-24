<?php
/* ==========================================================================
 *  وصال — الحسابات التجريبية
 *
 *  الفرق الجوهري عن الطريقة القديمة المحذوفة:
 *  كانت الحسابات تُزرع في localStorage عند كل تحميل صفحة، بكلمة مرور مكتوبة
 *  في الكود، ويُقبل الدخول بها محلياً عند تعذّر الوصول للخادم — أي باب خلفي
 *  لواجهة المشرف عند كل زائر.
 *
 *  هنا: حسابات حقيقية في قاعدة البيانات، تُنشأ فقط بطلب صريح من مدير النظام،
 *  كلمات مرورها تُولَّد عشوائياً وتُعرض مرة واحدة ولا تُخزَّن إلا كهاش،
 *  ومعلَّمة بـ is_demo حتى تُحذف كلها بأمان بلا لمس أي حساب حقيقي.
 * ========================================================================== */

/** كلمة مرور عشوائية قابلة للنقل شفهياً — بلا أحرف متشابهة (O/0، l/1) */
function demoPassword(): string {
    $lower = 'abcdefghijkmnopqrstuvwxyz';   // بلا l
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';    // بلا I
    $digit = '23456789';                    // بلا 0 و1
    $p = '';
    for ($i = 0; $i < 6; $i++) $p .= $lower[random_int(0, strlen($lower) - 1)];
    $p .= '-';
    for ($i = 0; $i < 4; $i++) $p .= $digit[random_int(0, strlen($digit) - 1)];
    $p .= $upper[random_int(0, strlen($upper) - 1)];
    return $p;                              // مثال: karthm-8342K
}

/** تعريف الحسابات التجريبية الأربعة — دور واحد لكل حساب */
function demoAccounts(): array {
    return [
        ['email' => 'demo.admin@wesalinnovation.sa',    'role' => 'admin',
         'name' => 'نورة الحربي',   'name_en' => 'Noura Alharbi',
         'phone' => '0590000001', 'dob' => '1990-04-12', 'pref' => 'detailed',
         'city' => 'الرياض', 'bio' => 'حساب تجريبي لعرض صلاحيات مدير النظام.'],

        ['email' => 'demo.mod@wesalinnovation.sa',      'role' => 'mod',
         'name' => 'فهد القحطاني',  'name_en' => 'Fahad Alqahtani',
         'phone' => '0590000002', 'dob' => '1993-09-01', 'pref' => 'simple',
         'city' => 'جدة', 'bio' => 'حساب تجريبي لعرض صلاحيات المشرف.'],

        ['email' => 'demo.reviewer@wesalinnovation.sa', 'role' => 'reviewer',
         'name' => 'لمياء الشهراني','name_en' => 'Lamia Alshahrani',
         'phone' => '0590000003', 'dob' => '1988-12-20', 'pref' => 'detailed',
         'city' => 'أبها', 'bio' => 'حساب تجريبي لعرض صلاحيات مراجع المحتوى.'],

        ['email' => 'demo.user@wesalinnovation.sa',     'role' => 'user',
         'name' => 'خالد العتيبي',  'name_en' => 'Khaled Alotaibi',
         'phone' => '0590000004', 'dob' => '2001-02-08', 'pref' => 'voice',
         'city' => 'الدمام', 'disability' => 'إعاقة بصرية',
         'interests' => 'التقنيات المساعدة، التوظيف',
         'bio' => 'حساب تجريبي لعرض تجربة المستفيد.'],
    ];
}

/**
 * ينشئ الحسابات التجريبية أو يجدّد كلمات مرورها إن كانت موجودة،
 * ويزرع بيانات أمثلة حتى لا تكون لوحات المشرف والمراجع فارغة.
 * يعيد قائمة البيانات لعرضها مرة واحدة على مدير النظام.
 */
function seedDemoAccounts(?array $actor = null): array {
    ensureSchema();
    $d = db();

    /* لازم يوجد مدير نظام حقيقي قبل الزرع. بدون هذا الشرط: الزرع في منصة
       فارغة يجعل حساب العرض هو المدير الوحيد، وأول تسجيل حقيقي بعده يصير
       «مستفيد» لأنه ليس الحساب الأول — فيبقى مالك المنصة بلا صلاحيات. */
    $realAdmins = (int) $d->query("SELECT COUNT(*) c FROM users
                                   WHERE role='admin' AND status='active' AND is_demo=0")->fetch()['c'];
    if ($realAdmins === 0) {
        throw new RuntimeException(
            'سجّل حسابك أنت أولاً، فأول حساب في منصة فارغة يصبح مدير النظام. '
          . 'بعد ذلك أنشئ الحسابات التجريبية، وإلا أصبح حساب العرض هو المدير الوحيد.');
    }

    $out = [];

    $find = $d->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    $ins  = $d->prepare('INSERT INTO users
        (name,name_en,dob,email,phone,pref,pass_hash,role,status,is_demo,improve,
         tokens,tokens_at,created_at,city,disability,interests,bio)
        VALUES (?,?,?,?,?,?,?,?,\'active\',1,0,?,NOW(),NOW(),?,?,?,?)');
    $upd  = $d->prepare('UPDATE users SET pass_hash=?, role=?, status=\'active\', is_demo=1,
        must_change_pw=0, tokens=?, tokens_at=NOW(), name=?, name_en=?, phone=? WHERE id=?');

    $d->beginTransaction();
    try {
        foreach (demoAccounts() as $a) {
            $pass = demoPassword();
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $find->execute([$a['email']]);
            $row = $find->fetch();

            if ($row) {
                $upd->execute([$hash, $a['role'], USER_TOKENS,
                               $a['name'], $a['name_en'], $a['phone'], $row['id']]);
                $id = (int)$row['id'];
                $fresh = false;
            } else {
                $ins->execute([$a['name'], $a['name_en'], $a['dob'], $a['email'], $a['phone'],
                               $a['pref'], $hash, $a['role'], USER_TOKENS,
                               $a['city'] ?? null, $a['disability'] ?? null,
                               $a['interests'] ?? null, $a['bio'] ?? null]);
                $id = (int)$d->lastInsertId();
                $fresh = true;
            }
            $out[] = ['id' => $id, 'email' => $a['email'], 'password' => $pass,
                      'role' => $a['role'], 'role_name' => roleName($a['role']),
                      'name' => $a['name'], 'fresh' => $fresh];
        }
        seedDemoContent((int)$out[3]['id']);   // المستفيد التجريبي صاحب التذاكر
        $d->commit();
    } catch (Throwable $e) {
        $d->rollBack();
        throw $e;
    }

    audit($actor, 'demo_seed', 'أربعة حسابات', 'تجديد كلمات المرور وبيانات الأمثلة');
    return $out;
}

/** بيانات أمثلة: رسائل تواصل وتذاكر دعم ومحادثات، كلها معلَّمة لتُحذف مع الحسابات */
function seedDemoContent(int $demoUserId): void {
    $d = db();

    // الرسائل معلَّمة ببريد demo.* حتى يجدها الحذف
    $d->prepare("DELETE FROM messages WHERE email LIKE 'demo.%@wesalinnovation.sa'")->execute();
    $m = $d->prepare('INSERT INTO messages (name,email,subject,message,ip,is_read,created_at)
                      VALUES (?,?,?,?,?,?, NOW() - INTERVAL ? HOUR)');
    foreach ([
        ['سارة المطيري', 'demo.sara@wesalinnovation.sa', 'استفسار عن بطاقة إثبات الإعاقة',
         'السلام عليكم، ودي أعرف المستندات المطلوبة لإصدار بطاقة إثبات الإعاقة لابني، وهل التقديم كله إلكتروني؟', 0, 3],
        ['عبدالله الزهراني', 'demo.abdullah@wesalinnovation.sa', 'اقتراح: دعم لغة الإشارة',
         'المنصة ممتازة، لكن أخي من الصم ويحتاج ترجمة بلغة الإشارة. متى تتوقعون إضافتها؟', 0, 19],
        ['منى العسيري', 'demo.mona@wesalinnovation.sa', 'بلاغ عن معلومة غير دقيقة',
         'الإجابة عن تخفيضات الطيران ذكرت نسبة غير محدّثة. أرجو مراجعتها مع الناقل.', 1, 50],
    ] as $r) $m->execute([$r[0], $r[1], $r[2], $r[3], '203.0.113.10', $r[4], $r[5]]);

    // تذاكر الدعم للمستفيد التجريبي
    $d->prepare('DELETE FROM support_tickets WHERE user_id=?')->execute([$demoUserId]);
    $t = $d->prepare('INSERT INTO support_tickets (user_id,type,details,status,created_at)
                      VALUES (?,?,?,?, NOW() - INTERVAL ? HOUR)');
    foreach ([
        ['بلاغ عن معلومة غير دقيقة',
         'الإجابة عن إعانة التأهيل الشامل ذكرت مبلغاً يبدو قديماً. أرجو التحقق من صفحة الخدمة الرسمية.', 'open', 5],
        ['تعديل رقم الجوال',
         'غيّرت رقمي وأبغى أحدّثه في حسابي. الرقم الجديد ينتهي بـ 0412.', 'open', 26],
        ['مشكلة تقنية في المنصة',
         'القراءة الصوتية ما تشتغل عندي على المتصفح، تبدأ وتتوقف بعد جملة.', 'done', 72],
    ] as $r) $t->execute([$demoUserId, $r[0], $r[1], $r[2], $r[3]]);

    // محادثات مجهولة الهوية معلَّمة mode='demo' حتى لا تختلط بأرقام حقيقية
    $d->prepare("DELETE FROM chat_logs WHERE mode='demo'")->execute();
    $c = $d->prepare("INSERT INTO chat_logs (user_id,question,answer,mode,cost,created_at)
                      VALUES (NULL,?,?,'demo',?, NOW() - INTERVAL ? HOUR)");
    foreach ([
        ['كيف أطلع بطاقة إثبات الإعاقة؟', 'بطاقة إثبات الإعاقة تصدرها وزارة الموارد البشرية والتنمية الاجتماعية…', 1, 2],
        ['وش التقنيات المساعدة للإعاقة البصرية؟', 'قارئات الشاشة مثل VoiceOver وTalkBack مدمجة في الجوالات…', 1, 8],
        ['حقوقي في العمل كشخص ذي إعاقة', 'نظام العمل السعودي ونظام حقوق الأشخاص ذوي الإعاقة يكفلان لك…', 2, 27],
        ['هل فيه دعم مالي للأسرة؟', 'الدعم المالي يأتي من أكثر من قناة: الإعانة المالية لذوي الإعاقة…', 1, 45],
    ] as $r) $c->execute([$r[0], $r[1], $r[2], $r[3]]);
}

/** يحذف كل ما زرعته الدالة أعلاه — ولا يلمس أي حساب حقيقي */
function purgeDemoAccounts(?array $actor = null): int {
    ensureSchema();
    $d = db();

    /* نفس حاجز آخر مدير نظام في admin.php: الحذف الجماعي لا يجوز أن يُفرغ
       المنصة من مديريها، وإلا انقفلت اللوحة على الجميع. */
    $realAdmins = (int) $d->query("SELECT COUNT(*) c FROM users
                                   WHERE role='admin' AND status='active' AND is_demo=0")->fetch()['c'];
    $demoAdmins = (int) $d->query("SELECT COUNT(*) c FROM users
                                   WHERE role='admin' AND status='active' AND is_demo=1")->fetch()['c'];
    if ($realAdmins === 0 && $demoAdmins > 0) {
        throw new RuntimeException(
            'حذفها الآن يترك المنصة بلا مدير نظام. رقِّ حسابك أنت لمدير نظام أولاً، ثم احذفها.');
    }

    $ids = $d->query('SELECT id, avatar FROM users WHERE is_demo=1')->fetchAll();
    $d->beginTransaction();
    try {
        foreach ($ids as $u) {
            if (!empty($u['avatar']) && strpos($u['avatar'], 'uploads/avatars/') === 0)
                @unlink(dirname(__DIR__) . '/uploads/avatars/' . basename($u['avatar']));
            $d->prepare('UPDATE chat_logs SET user_id=NULL WHERE user_id=?')->execute([$u['id']]);
            $d->prepare('DELETE FROM support_tickets WHERE user_id=?')->execute([$u['id']]);
            $d->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$u['id']]);
        }
        $d->exec('DELETE FROM users WHERE is_demo=1');
        $d->exec("DELETE FROM messages  WHERE email LIKE 'demo.%@wesalinnovation.sa'");
        $d->exec("DELETE FROM chat_logs WHERE mode='demo'");
        $d->exec("DELETE FROM invites   WHERE email LIKE 'demo.%@wesalinnovation.sa'");
        $d->commit();
    } catch (Throwable $e) {
        $d->rollBack();
        throw $e;
    }
    $n = count($ids);
    audit($actor, 'demo_purge', $n . ' حساباً', 'حذف الحسابات التجريبية وبيانات الأمثلة');
    return $n;
}

/** كم حساباً تجريبياً موجود الآن؟ */
function demoCount(): int {
    ensureSchema();
    try { return (int) db()->query('SELECT COUNT(*) c FROM users WHERE is_demo=1')->fetch()['c']; }
    catch (Throwable $e) { return 0; }
}
