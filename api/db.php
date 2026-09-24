<?php

/* حارس عام، مُسجَّل قبل أي تحميل. الواجهة تعتبر أي رد ليس JSON انقطاعَ شبكة
   وتقول للمستخدم "ما قدرنا نوصل للخادم" — فتلوم اتصاله على عطل في الخادم
   ولا يبقى في السجل أثر يُشخَّص منه السبب. الترتيب هنا مقصود: التسجيل يسبق
   require config.php تحديداً ليلتقط خطأً نحوياً في ذلك الملف بعد تحرير يدوي
   على الخادم، وهي الحالة الوحيدة التي تُسقط كل نقاط النهاية دفعةً واحدة. */
function apiFail(string $detail): void
{
    static $sent = false;
    if ($sent) { return; }
    $sent = true;

    @error_log('[wesal-api] ' . $detail);
    discardStrayOutput();
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $debug = defined('APP_DEBUG') && APP_DEBUG;
    echo json_encode([
        'ok'    => false,
        'error' => $debug ? $detail : 'صار خلل مؤقت في الخادم. حاول بعد قليل، وإذا تكرر راسل الدعم الفني.',
    ], JSON_UNESCAPED_UNICODE);
}

/* أي بايت يُطبع خارج وسوم PHP — مسافة أو سطر أو حرف شارد بعد تحرير يدوي —
   يسبق الرد فيكسر تحليله عند العميل، بينما تبقى الحالة 200 والجسم سليماً
   بعده؛ فيبدو العطل انقطاعَ شبكة ولا يظهر له أثر في أي سجل. يُلتقط هنا
   ويُسجَّل ثم يُطرح قبل إرسال أي رد. */
function discardStrayOutput(): void
{
    if (ob_get_level() === 0) { return; }
    $stray = ob_get_contents();
    if ($stray !== false && $stray !== '') {
        @error_log('[wesal-api] بايتات دخيلة قبل الرد: ' . substr((string) json_encode($stray), 0, 200));
    }
    ob_clean();
}

set_exception_handler(static function (Throwable $e): void {
    apiFail(get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
});

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e !== null && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        apiFail('Fatal: ' . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line']);
    }
});

ob_start();
require_once __DIR__ . '/config.php';

/* قيم افتراضية للثوابت — حتى يظل ملف config.php القائم على الخادم يعمل بلا
   تعديل بعد أي ترقية تضيف إعداداً جديداً. الشرح الكامل في config.example.php. */
foreach ([
    'APP_DEBUG'              => false,
    'GUEST_LIMIT'            => 5,
    'USER_TOKENS'            => 30,
    'RENEW_HOURS'            => 6,
    'CHAT_DAILY_IP_LIMIT'    => 120,
    'CHAT_DAILY_TOTAL_LIMIT' => 0,
    'TRUST_PROXY'            => false,
    'GEMINI_FALLBACKS'       => '',
    'OPENAI_KEY'             => '',
    'OPENAI_MODEL'           => 'gpt-4o-mini',
    'CLAUDE_KEY'             => '',
    'CLAUDE_MODEL'           => 'claude-sonnet-5',
    'KIMI_KEY'               => '',
    'KIMI_MODEL'             => 'kimi-k2-turbo-preview',
    'KIMI_BASE_URL'          => 'https://api.moonshot.ai/v1',
    'INVITE_DAILY_LIMIT'     => 100,
    'BETA_TRIAL_HOURS'       => 48,
    'SMTP_HOST'              => 'smtp.hostinger.com',
    'SMTP_PORT'              => 465,
    'SMTP_ENCRYPTION'        => 'ssl',
    'SMTP_USER'              => '',
    'SMTP_PASS'              => '',
] as $k => $v) { if (!defined($k)) define($k, $v); }

if (!APP_DEBUG) { ini_set('display_errors', '0'); error_reporting(0); }

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
    }
    return $pdo;
}

/** هل يوجد عمود بهذا الاسم في هذا الجدول؟ */
function colExists(string $table, string $col): bool {
    $s = db()->prepare("SELECT COUNT(*) c FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $s->execute([$table, $col]);
    return (int)($s->fetch()['c'] ?? 0) > 0;
}

/** نوع عمود ENUM كما هو مخزّن، لمعرفة القيم المسموحة فيه حالياً */
function colType(string $table, string $col): string {
    $s = db()->prepare("SELECT COLUMN_TYPE t FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $s->execute([$table, $col]);
    return (string)($s->fetch()['t'] ?? '');
}

/* ---------- تذاكر الدعم: الحالات والأولويات ---------- */
const TICKET_STATUSES = ['new', 'in_progress', 'waiting', 'escalated', 'resolved', 'closed', 'reopened'];
const TICKET_PRIORITIES = ['critical', 'high', 'normal', 'low'];

/**
 * ترقية جدول التذاكر من الشكل البدائي (ستة أعمدة وحالتان) إلى نظام تذاكر
 * كامل. مقسّمة إلى خطوات كل واحدة محروسة بفحص وجودها، لأن ensureSchema()
 * تُنفَّذ مع كل طلب فلا يجوز أن تعيد أي خطوة تنفيذ نفسها.
 */
function migrateTickets(): void {
    /* ١) الأعمدة الجديدة. ref وtrack_token تُضافان بلا UNIQUE أولاً حتى
       نملأهما للصفوف القائمة، ثم يُضاف القيد في الخطوة ٣. */
    if (!colExists('support_tickets', 'ref')) {
        db()->exec("ALTER TABLE support_tickets
            ADD COLUMN ref VARCHAR(20) NULL AFTER id,
            ADD COLUMN track_token CHAR(32) NULL AFTER ref,
            ADD COLUMN guest_name VARCHAR(80) NULL AFTER user_id,
            ADD COLUMN guest_email VARCHAR(120) NULL AFTER guest_name,
            ADD COLUMN subject VARCHAR(140) NULL AFTER type,
            ADD COLUMN priority ENUM('critical','high','normal','low') NOT NULL DEFAULT 'normal' AFTER details,
            ADD COLUMN assignee_id INT NULL AFTER priority,
            ADD COLUMN source ENUM('corporate','chat','internal') NOT NULL DEFAULT 'chat' AFTER assignee_id,
            ADD COLUMN due_first_response DATETIME NULL,
            ADD COLUMN due_resolution DATETIME NULL,
            ADD COLUMN first_response_at DATETIME NULL,
            ADD COLUMN resolved_at DATETIME NULL,
            ADD COLUMN closed_at DATETIME NULL,
            ADD COLUMN updated_at DATETIME NULL,
            ADD COLUMN resolution TEXT NULL,
            ADD COLUMN csat TINYINT NULL,
            ADD INDEX ix_status (status),
            ADD INDEX ix_assignee (assignee_id),
            ADD INDEX ix_due (due_resolution)");
    }

    /* ٢) user_id يصبح اختيارياً ليرفع الضيف تذكرة. المفتاح الأجنبي في
       schema.sql يقبل NULL بلا فحص، فلا حاجة لإسقاطه. */
    if (stripos(colType('support_tickets', 'user_id'), 'int') !== false
        && !colNullable('support_tickets', 'user_id')) {
        db()->exec("ALTER TABLE support_tickets MODIFY user_id INT NULL");
    }

    /* ٣) توسيع الحالات من اثنتين إلى سبع. تتم على ثلاث مراحل لأن UPDATE
       على قيمة غير موجودة في ENUM يفشل: نضيف الجديدة مع إبقاء القديمة،
       ثم نحوّل الصفوف، ثم نحذف القديمة. */
    $statusType = colType('support_tickets', 'status');
    if (strpos($statusType, "'in_progress'") === false) {
        $all = "'open','done','" . implode("','", TICKET_STATUSES) . "'";
        db()->exec("ALTER TABLE support_tickets MODIFY status ENUM($all) NOT NULL DEFAULT 'new'");
        db()->exec("UPDATE support_tickets SET status='new' WHERE status='open'");
        db()->exec("UPDATE support_tickets SET status='closed', closed_at=created_at WHERE status='done'");
        $new = "'" . implode("','", TICKET_STATUSES) . "'";
        db()->exec("ALTER TABLE support_tickets MODIFY status ENUM($new) NOT NULL DEFAULT 'new'");
    }

    /* ٤) ملء الرقم المرجعي ورمز التتبّع للتذاكر القائمة، ثم فرض التفرّد.
       بلا هذه الخطوة يفشل قيد UNIQUE على صفوف NULL متكررة في بعض الإعدادات. */
    $pending = db()->query('SELECT id, created_at FROM support_tickets WHERE ref IS NULL')->fetchAll();
    if ($pending) {
        $up = db()->prepare('UPDATE support_tickets SET ref=?, track_token=?, updated_at=created_at WHERE id=?');
        foreach ($pending as $row) {
            $year = (int)date('Y', strtotime($row['created_at'])) ?: (int)date('Y');
            $up->execute([ticketRef($year, (int)$row['id']), bin2hex(random_bytes(16)), (int)$row['id']]);
        }
    }
    if (strpos(indexList('support_tickets'), 'uq_ticket_ref') === false) {
        db()->exec("ALTER TABLE support_tickets
                    ADD UNIQUE KEY uq_ticket_ref (ref),
                    ADD UNIQUE KEY uq_ticket_token (track_token)");
    }

    /* ٥) سجل التذكرة: كل رد وملاحظة وإحالة وتغيير حالة، بترتيب زمني.
       visibility هو العمود الذي يحسم ما يراه صاحب التذكرة وما يبقى داخلياً. */
    db()->exec("CREATE TABLE IF NOT EXISTS ticket_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        author_id INT NULL,
        author_name VARCHAR(80) NULL,
        kind ENUM('reply','note','referral','status','priority','assign','system') NOT NULL DEFAULT 'reply',
        visibility ENUM('public','internal') NOT NULL DEFAULT 'internal',
        body TEXT NULL,
        meta TEXT NULL,
        created_at DATETIME NOT NULL,
        INDEX ix_ticket (ticket_id, created_at), INDEX ix_vis (visibility)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    /* ٦) المتابعون: المُحيل يبقى مشتركاً في التذكرة بعد خروجها من يده. */
    db()->exec("CREATE TABLE IF NOT EXISTS ticket_watchers (
        ticket_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (ticket_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    /* ٧) مستوى الدعم مستقل عن دور المنصة: مراجع محتوى يمكن أن يكون معالج
       تذاكر دون منحه صلاحيات إدارية، والعكس. */
    if (!colExists('users', 'support_level')) {
        db()->exec("ALTER TABLE users
                    ADD COLUMN support_level ENUM('none','agent','lead','exec') NOT NULL DEFAULT 'none'");
    }
    /* مدير النظام يصبح قائد الفريق التقني تلقائياً حتى لا يبقى الطابور بلا
       مالك. خارج الشرط أعلاه ويُعاد تنفيذها كل مرة (رخيصة، بلا أثر إن لم
       تكن هناك صفوف مطابقة) لأنها إن نُفِّذت مرة واحدة فقط لحظة إضافة
       العمود، فإن مدير نظام يُسجَّل بعد تلك اللحظة على قاعدة فارغة (كأول
       حساب في المنصة) يفوتها تماماً — كما حدث فعلاً أثناء الاختبار. الشرط
       support_level='none' يمنعها من الكتابة فوق ترقية يدوية لاحقة (مثل
       'exec'). */
    db()->exec("UPDATE users SET support_level='lead' WHERE role='admin' AND support_level='none'");

    /* ٨) صيانة تذاكر الدعم الدورية (api/cron-tickets.php): عمودان لتتبّع ما
       أُرسل فعلاً من تنبيهات، حتى لا يكرّر كل تشغيل تالٍ نفس التنبيه ولا
       يُعيد إغلاق ما أُغلق أصلاً — بنفس فكرة كل الهجرات أعلاه. */
    if (!colExists('support_tickets', 'sla_warned_at')) {
        db()->exec("ALTER TABLE support_tickets
            ADD COLUMN sla_warned_at DATETIME NULL,
            ADD COLUMN waiting_reminder_count TINYINT NOT NULL DEFAULT 0");
    }

    migrateMessagesToTickets();
    ensureNotifications();
}

/* ---------- محرّك الاستبيانات ----------
   يحل محل الاستبانة الواحدة الثابتة (invitations وsurvey_responses).
   الجداول القديمة تبقى بلا حذف؛ بياناتها تُنسخ مرة واحدة فقط عند إنشاء
   الجداول الجديدة، لأن كل دعوة أو رد جديد بعد تلك اللحظة يُكتب في الجداول
   الجديدة مباشرة ولا يمر بالقديمة أبداً — فلا حاجة لترحيل متكرر كالتذاكر. */
function migrateSurveys(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS surveys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(160) NOT NULL,
        description VARCHAR(500) NULL,
        thank_you_message VARCHAR(500) NULL,
        status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
        public_token CHAR(40) NULL,
        public_link_enabled TINYINT(1) NOT NULL DEFAULT 0,
        grants_trial TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        published_at DATETIME NULL,
        UNIQUE KEY uq_survey_public_token (public_token),
        KEY ix_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS survey_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        position INT NOT NULL DEFAULT 0,
        type ENUM('single_choice','multi_choice','scale','short_text','long_text') NOT NULL,
        question_text VARCHAR(500) NOT NULL,
        help_text VARCHAR(300) NULL,
        placeholder VARCHAR(200) NULL,
        is_required TINYINT(1) NOT NULL DEFAULT 0,
        scale_min TINYINT NULL,
        scale_max TINYINT NULL,
        scale_min_label VARCHAR(40) NULL,
        scale_max_label VARCHAR(40) NULL,
        max_length SMALLINT NULL,
        created_at DATETIME NOT NULL,
        KEY ix_survey (survey_id, position)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS survey_question_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id INT NOT NULL,
        position INT NOT NULL DEFAULT 0,
        option_text VARCHAR(200) NOT NULL,
        option_value VARCHAR(60) NOT NULL,
        has_followup TINYINT(1) NOT NULL DEFAULT 0,
        followup_label VARCHAR(200) NULL,
        followup_max SMALLINT NULL DEFAULT 500,
        KEY ix_question (question_id, position)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS survey_invitations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        email VARCHAR(120) NOT NULL,
        token CHAR(40) NOT NULL,
        campaign_name VARCHAR(80) NOT NULL DEFAULT '',
        status ENUM('sent','opened','completed') NOT NULL DEFAULT 'sent',
        sent_at DATETIME NOT NULL,
        opened_at DATETIME NULL,
        trial_started_at DATETIME NULL,
        completed_at DATETIME NULL,
        created_by INT NULL,
        UNIQUE KEY uq_invitation_token (token),
        KEY ix_survey (survey_id), KEY ix_email (email), KEY ix_campaign (campaign_name), KEY ix_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS survey_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        invitation_id INT NULL,
        campaign_name VARCHAR(80) NULL,
        submitted_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_submission_invitation (invitation_id),
        KEY ix_survey (survey_id), KEY ix_campaign (campaign_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS survey_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_id INT NOT NULL,
        question_id INT NOT NULL,
        answer_text VARCHAR(1000) NULL,
        option_id INT NULL,
        number_value TINYINT NULL,
        UNIQUE KEY uq_answer (submission_id, question_id),
        KEY ix_question (question_id), KEY ix_option (option_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS survey_answer_options (
        answer_id INT NOT NULL,
        option_id INT NOT NULL,
        PRIMARY KEY (answer_id, option_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ((int)(db()->query('SELECT COUNT(*) c FROM surveys')->fetch()['c'] ?? 0) === 0) {
        seedPlatformSurveyAndMigrateLegacyData();
    }
}

/**
 * يبذر استبيان «تجربة المنصة» الافتراضي بأسئلته العشرة (نفس نص وترتيب
 * STEPS القديمة في survey.html حرفياً)، ثم ينسخ إليه كل دعوة ورد سابقين من
 * invitations وsurvey_responses — فلا يفقد أي بيانات جمعتها النسخة القديمة.
 * يُستدعى مرة واحدة فقط (بشرط جدول surveys فارغاً)، وأي عطل فيه لا يوقف
 * الطلب لأن ensureSchema() كلها داخل try/catch عند الاستدعاء.
 */
function seedPlatformSurveyAndMigrateLegacyData(): void {
    $now = date('Y-m-d H:i:s');

    db()->prepare("INSERT INTO surveys
        (title, description, thank_you_message, status, public_token, public_link_enabled,
         grants_trial, created_at, updated_at, published_at)
        VALUES (?,?,?,'published',?,1,1,?,?,?)")
        ->execute(['تجربة المنصة',
            'استبانة قصيرة عن تجربتك مع وصال — عشرة أسئلة، أقل من دقيقة.',
            'وصلتنا إجاباتك — شكراً لك. رأيك يدخل مباشرة في تطوير وصال.',
            bin2hex(random_bytes(20)), $now, $now, $now]);
    $surveyId = (int)db()->lastInsertId();

    $qIns = db()->prepare("INSERT INTO survey_questions
        (survey_id, position, type, question_text, help_text, placeholder, is_required,
         scale_min, scale_max, scale_min_label, scale_max_label, max_length, created_at)
        VALUES (?,?,?,?,?,?,0,?,?,?,?,?,?)");
    $oIns = db()->prepare("INSERT INTO survey_question_options
        (question_id, position, option_text, option_value, has_followup, followup_label, followup_max)
        VALUES (?,?,?,?,?,?,?)");

    /** يضيف سؤال اختيار (فردي) بخياراته، ويعيد [question_id, [قيمة => option_id]] */
    $addChoice = function (int $pos, string $q, string $hint, array $opts, ?array $follow = null) use ($qIns, $oIns, $surveyId, $now): array {
        $qIns->execute([$surveyId, $pos, 'single_choice', $q, $hint ?: null, null, null, null, null, null, $now]);
        $qid = (int)db()->lastInsertId();
        $byValue = [];
        foreach ($opts as $i => [$text, $value]) {
            $hasFollow = $follow && $follow['when'] === $value;
            $oIns->execute([$qid, $i, $text, $value, $hasFollow ? 1 : 0,
                $hasFollow ? $follow['label'] : null, $hasFollow ? $follow['max'] : null]);
            $byValue[$value] = (int)db()->lastInsertId();
        }
        return [$qid, $byValue];
    };
    /** يضيف سؤال مقياس، ويعيد question_id */
    $addScale = function (int $pos, string $q, string $hint, int $min, int $max, string $lowLbl, string $highLbl) use ($qIns, $surveyId, $now): int {
        $qIns->execute([$surveyId, $pos, 'scale', $q, $hint ?: null, null, $min, $max, $lowLbl, $highLbl, null, $now]);
        return (int)db()->lastInsertId();
    };
    /** يضيف سؤال نص طويل، ويعيد question_id */
    $addLongText = function (int $pos, string $q, string $hint, string $placeholder, int $max) use ($qIns, $surveyId, $now): int {
        $qIns->execute([$surveyId, $pos, 'long_text', $q, $hint ?: null, $placeholder ?: null, null, null, null, null, $max, $now]);
        return (int)db()->lastInsertId();
    };

    [$qNeed, $optNeed] = $addChoice(0, 'وش أقرب وصف لك؟', 'يساعدنا نعرف لمن نصمّم — وتقدر تتخطى السؤال.', [
        ['أعيش بإعاقة بصرية', 'بصرية'], ['أعيش بإعاقة سمعية', 'سمعية'],
        ['أعيش بإعاقة حركية', 'حركية'], ['أعيش بإعاقة ذهنية أو صعوبات تعلّم', 'ذهنية أو صعوبات تعلم'],
        ['ما عندي إعاقة — مهتم أو مرافق', 'بلا إعاقة'], ['أفضّل ما أحدد', 'أفضّل عدم التحديد'],
    ]);
    $qEase = $addScale(1, 'قد إيش كان استخدام وصال سهلاً عليك؟', 'من ١ (صعب جداً) إلى ٥ (سهل جداً).', 1, 5, 'صعب جداً', 'سهل جداً');
    [$qDiff, $optDiff] = $addChoice(2, 'واجهتك أي صعوبة وأنت تستخدم الموقع؟', 'في القراءة أو التنقل أو فهم الإجابات — أي شيء.', [
        ['نعم، واجهتني صعوبة', '1'], ['لا، كل شيء كان واضحاً', '0'],
    ], ['when' => '1', 'label' => 'احكِ لنا وش صار — حتى لو بسطر واحد', 'max' => 500]);
    $qTrust = $addScale(3, 'قد إيش تثق بإجابات وصال والمصادر اللي يذكرها؟', 'من ١ (ما أثق) إلى ٥ (أثق تماماً).', 1, 5, 'ما أثق', 'أثق تماماً');
    $qHelped = $addScale(4, 'قد إيش ساعدك وصال توصل لمعلومة أو خدمة تحتاجها؟', 'من ١ (ما ساعدني) إلى ٥ (ساعدني كثير).', 1, 5, 'ما ساعدني', 'ساعدني كثير');
    [$qPmf, $optPmf] = $addChoice(5, 'لو اختفى وصال بكرة، وش راح يكون شعورك؟', 'إجابتك هنا أهم مؤشر نقيس به قيمة وصال.', [
        ['بنزعج جداً', 'very_disappointed'], ['بنزعج شوي', 'somewhat_disappointed'], ['عادي، ما بنزعج', 'not_disappointed'],
    ]);
    $qNps = $addScale(6, 'كم تنصح شخصاً مثلك يجرّب وصال؟', 'من صفر (ما أنصح) إلى عشرة (أنصح بقوة).', 0, 10, 'ما أنصح', 'أنصح بقوة');
    [$qReturn, $optReturn] = $addChoice(7, 'بترجع تستخدم وصال مرة ثانية؟', '', [
        ['نعم', 'yes'], ['يمكن', 'maybe'], ['لا', 'no'],
    ]);
    $qMissing = $addLongText(8, 'دوّرت على خدمة أو معلومة وما لقيتها؟', 'اكتبها لنا — هذا اللي يحدّد وش نضيف بعدين.', 'مثلاً: معلومات عن التوظيف، أجهزة مساعدة، دعم مالي…', 500);
    $qFeedback = $addLongText(9, 'أي شيء ثاني ودّك توصله لنا؟', 'اقتراح أو ملاحظة أو حتى كلمة — كلها توصل للفريق.', 'اكتب هنا…', 1000);

    /* ---------- ترحيل الدعوات القديمة ---------- */
    $invMap = [];   // معرّف الدعوة القديم ← معرّف survey_invitations الجديد
    $siIns = db()->prepare("INSERT INTO survey_invitations
        (survey_id, email, token, campaign_name, status, sent_at, opened_at, trial_started_at, created_by)
        VALUES (?,?,?,?,?,?,?,?,?)");
    foreach (db()->query('SELECT * FROM invitations')->fetchAll() as $old) {
        $status = $old['status'] === 'completed_survey' ? 'completed'
                : ($old['status'] === 'sent' ? 'sent' : 'opened');   // clicked وtried كلاهما «فُتحت» في القمع الجديد
        $siIns->execute([$surveyId, $old['email'], $old['token'], $old['campaign_name'], $status,
            $old['sent_at'], $old['clicked_at'], $old['tried_at'], $old['created_by']]);
        $invMap[(int)$old['id']] = (int)db()->lastInsertId();
    }

    /* ---------- ترحيل الردود القديمة ---------- */
    $ssIns = db()->prepare("INSERT INTO survey_submissions
        (survey_id, invitation_id, campaign_name, submitted_at, updated_at) VALUES (?,?,?,?,?)");
    $saIns = db()->prepare("INSERT INTO survey_answers
        (submission_id, question_id, answer_text, option_id, number_value) VALUES (?,?,?,?,?)");
    $completeInv = db()->prepare("UPDATE survey_invitations SET completed_at=? WHERE id=? AND completed_at IS NULL");

    foreach (db()->query('SELECT * FROM survey_responses')->fetchAll() as $old) {
        $newInvId = $old['invitation_id'] !== null ? ($invMap[(int)$old['invitation_id']] ?? null) : null;
        $ssIns->execute([$surveyId, $newInvId, $old['campaign_name'], $old['created_at'], $old['created_at']]);
        $subId = (int)db()->lastInsertId();

        $ans = function (int $qid, ?string $text, ?int $optId, ?int $num) use ($saIns, $subId): void {
            $saIns->execute([$subId, $qid, $text, $optId, $num]);
        };
        if ($old['accessibility_need'] !== null && isset($optNeed[$old['accessibility_need']]))
            $ans($qNeed, null, $optNeed[$old['accessibility_need']], null);
        if ($old['ease_of_use'] !== null) $ans($qEase, null, null, (int)$old['ease_of_use']);
        if ($old['access_difficulty'] !== null) {
            $val = (string)(int)$old['access_difficulty'];
            $ans($qDiff, $old['access_details'], $optDiff[$val] ?? null, null);
        }
        if ($old['trust_in_sources'] !== null) $ans($qTrust, null, null, (int)$old['trust_in_sources']);
        if ($old['helped_access_service'] !== null) $ans($qHelped, null, null, (int)$old['helped_access_service']);
        if ($old['pmf_reaction'] !== null && isset($optPmf[$old['pmf_reaction']]))
            $ans($qPmf, null, $optPmf[$old['pmf_reaction']], null);
        if ($old['nps_score'] !== null) $ans($qNps, null, null, (int)$old['nps_score']);
        if ($old['return_intent'] !== null && isset($optReturn[$old['return_intent']]))
            $ans($qReturn, null, $optReturn[$old['return_intent']], null);
        if ($old['missing_service'] !== null) $ans($qMissing, $old['missing_service'], null, null);
        if ($old['other_feedback'] !== null) $ans($qFeedback, $old['other_feedback'], null, null);

        if ($newInvId !== null) $completeInv->execute([$old['created_at'], $newInvId]);
    }
}

/* ---------- الإشعارات ----------
   بنية عامة لأي إجراء يخصّ أي مستخدم — التذاكر أول من يستخدمها، وأي ميزة
   لاحقة (رسائل، دعوات، مراجعات) تستدعي notify() نفسها بلا جدول جديد. */
function ensureNotifications(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(40) NOT NULL,
        title VARCHAR(160) NOT NULL,
        body VARCHAR(500) NULL,
        link VARCHAR(200) NULL,
        read_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        INDEX ix_user (user_id, read_at, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * ينشئ إشعاراً لمستخدم مسجَّل واحد. لا تُستدعى لضيف (لا user_id له) —
 * الضيف يُخطَر بالبريد وحده عبر sendMail(). فشل الإشعار لا يوقف العملية
 * التي استدعته أبداً — أهم عملية (حفظ التذكرة، الرد، ...) قد تمّت فعلاً.
 */
function notify(int $userId, string $type, string $title, string $body = '', ?string $link = null): void
{
    try {
        db()->prepare('INSERT INTO notifications (user_id,type,title,body,link,created_at) VALUES (?,?,?,?,?,NOW())')
            ->execute([$userId, $type, mb_substr($title, 0, 160), mb_substr($body, 0, 500), $link]);
    } catch (Throwable $e) { error_log('WESAL_NOTIFY_FAIL: ' . $e->getMessage()); }
}

/** رقم مرجعي مقروء يُذكر في المراسلات — ليس مفتاحاً سرّياً */
function ticketRef(int $year, int $id): string {
    return sprintf('WSL-%d-%05d', $year, $id);
}

function colNullable(string $table, string $col): bool {
    $s = db()->prepare("SELECT IS_NULLABLE n FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $s->execute([$table, $col]);
    return strtoupper((string)($s->fetch()['n'] ?? '')) === 'YES';
}

function indexList(string $table): string {
    $s = db()->prepare("SELECT GROUP_CONCAT(DISTINCT INDEX_NAME) i FROM information_schema.STATISTICS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $s->execute([$table]);
    return (string)($s->fetch()['i'] ?? '');
}

/**
 * ترحيل رسائل «تواصل معنا» القديمة إلى تذاكر مغلقة، فيصبح تاريخ التواصل
 * كله في مكان واحد بدل صندوقين لا يعرف أحدهما الآخر. يُنفَّذ مرة واحدة،
 * ويُعلَّم كل صف مُرحَّل حتى لا يتكرر.
 */
function migrateMessagesToTickets(): void {
    if (!colExists('messages', 'migrated_ticket_id')) {
        db()->exec("ALTER TABLE messages ADD COLUMN migrated_ticket_id INT NULL");
    }
    $rows = db()->query('SELECT id,name,email,subject,message,created_at
                         FROM messages WHERE migrated_ticket_id IS NULL
                         ORDER BY id LIMIT 200')->fetchAll();
    if (!$rows) return;

    $ins = db()->prepare("INSERT INTO support_tickets
        (ref,track_token,user_id,guest_name,guest_email,type,subject,details,priority,
         status,source,created_at,updated_at,closed_at)
        VALUES (?,?,NULL,?,?,?,?,?,'normal','closed','corporate',?,?,?)");
    $mark = db()->prepare('UPDATE messages SET migrated_ticket_id=? WHERE id=?');
    $entry = db()->prepare("INSERT INTO ticket_entries
        (ticket_id,author_id,author_name,kind,visibility,body,created_at)
        VALUES (?,NULL,?, 'reply','public',?,?)");

    foreach ($rows as $m) {
        $when = $m['created_at'];
        /* رقم مؤقت فريد: المعرّف الحقيقي غير معروف قبل الإدراج، وقيد التفرّد
           على ref يرفض قيمة ثابتة مكرّرة لو رُحّلت أكثر من رسالة. */
        $ins->execute([
            'TMP-' . bin2hex(random_bytes(6)), bin2hex(random_bytes(16)), $m['name'], $m['email'],
            'أخرى', mb_substr((string)$m['subject'], 0, 140), (string)$m['message'],
            $when, $when, $when,
        ]);
        $tid  = (int)db()->lastInsertId();
        $year = (int)date('Y', strtotime($when)) ?: (int)date('Y');
        db()->prepare('UPDATE support_tickets SET ref=? WHERE id=?')->execute([ticketRef($year, $tid), $tid]);
        $entry->execute([$tid, $m['name'], (string)$m['message'], $when]);
        $mark->execute([$tid, (int)$m['id']]);
    }
}

/**
 * ترقية تلقائية — تُبقي قاعدة بيانات قائمة متوافقة مع schema.sql.
 * لا تُستخدم مفاتيح أجنبية هنا (بعكس schema.sql) لأن جدولاً قديماً بترميز
 * أو محرّك مختلف قد يرفضها، فيفشل الإنشاء بصمت ونفقد الجدول كله.
 */
function ensureSchema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        if (!colExists('users', 'avatar')) {
            db()->exec("ALTER TABLE users
                        ADD COLUMN avatar     VARCHAR(160) NULL,
                        ADD COLUMN city       VARCHAR(60)  NULL,
                        ADD COLUMN age_range  VARCHAR(20)  NULL,
                        ADD COLUMN disability VARCHAR(60)  NULL,
                        ADD COLUMN interests  VARCHAR(300) NULL,
                        ADD COLUMN bio        VARCHAR(500) NULL");
        }
        if (!colExists('users', 'name_en')) {
            db()->exec("ALTER TABLE users ADD COLUMN name_en VARCHAR(80) NULL, ADD COLUMN dob DATE NULL");
        }
        if (!colExists('users', 'is_demo')) {
            db()->exec("ALTER TABLE users ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0,
                        ADD INDEX ix_demo (is_demo)");
        }
        if (!colExists('users', 'status')) {
            db()->exec("ALTER TABLE users
                        ADD COLUMN status         ENUM('active','suspended') NOT NULL DEFAULT 'active',
                        ADD COLUMN must_change_pw TINYINT(1) NOT NULL DEFAULT 0,
                        ADD COLUMN improve        TINYINT(1) NOT NULL DEFAULT 0");
        }
        // دور مراجع المحتوى — كان معرّفاً في الواجهة فقط ولا يمكن إسناده فعلياً
        $rt = colType('users', 'role');
        if ($rt !== '' && strpos($rt, 'reviewer') === false) {
            db()->exec("ALTER TABLE users MODIFY COLUMN role
                        ENUM('user','reviewer','mod','admin') NOT NULL DEFAULT 'user'");
        }

        /* كان هذا الجدول في schema.sql فقط وليس في الترقية التلقائية، فأي نشر
           على قاعدة بيانات جديدة (نطاق فرعي أو نسخة اختبار) يجعل نموذج
           «تواصل معنا» يفشل صامتاً بلا أي أثر ظاهر. */
        db()->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL,
            email VARCHAR(120) NOT NULL,
            subject VARCHAR(120) NOT NULL,
            message TEXT NOT NULL,
            ip VARCHAR(45) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX (created_at), INDEX (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->exec("CREATE TABLE IF NOT EXISTS support_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(60) NOT NULL,
            details TEXT NOT NULL,
            status ENUM('open','done') NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL,
            INDEX (user_id), INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        migrateTickets();

        db()->exec("CREATE TABLE IF NOT EXISTS invites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(120) NOT NULL,
            role_target ENUM('user','reviewer','mod') NOT NULL DEFAULT 'user',
            token VARCHAR(64) NOT NULL,
            invited_by INT NOT NULL,
            status ENUM('sent','accepted','revoked') NOT NULL DEFAULT 'sent',
            created_at DATETIME NOT NULL,
            accepted_at DATETIME NULL,
            UNIQUE KEY uq_email (email), INDEX (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $it = colType('invites', 'role_target');
        if ($it !== '' && strpos($it, 'reviewer') === false) {
            db()->exec("ALTER TABLE invites MODIFY COLUMN role_target
                        ENUM('user','reviewer','mod') NOT NULL DEFAULT 'user'");
        }
        $ist = colType('invites', 'status');
        if ($ist !== '' && strpos($ist, 'revoked') === false) {
            db()->exec("ALTER TABLE invites MODIFY COLUMN status
                        ENUM('sent','accepted','revoked') NOT NULL DEFAULT 'sent'");
        }

        db()->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            issued_by INT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX (token_hash), INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->exec("CREATE TABLE IF NOT EXISTS site_content (
            ckey VARCHAR(80) NOT NULL PRIMARY KEY,
            cval TEXT NOT NULL,
            updated_by INT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_id INT NULL,
            actor_name VARCHAR(80) NULL,
            action VARCHAR(40) NOT NULL,
            target VARCHAR(160) NULL,
            detail VARCHAR(400) NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL,
            INDEX (created_at), INDEX (actor_id), INDEX (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->exec("CREATE TABLE IF NOT EXISTS invitations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(120) NOT NULL,
            token CHAR(40) NOT NULL,
            campaign_name VARCHAR(80) NOT NULL DEFAULT '',
            status ENUM('sent','clicked','tried','completed_survey') NOT NULL DEFAULT 'sent',
            sent_at DATETIME NOT NULL,
            clicked_at DATETIME NULL,
            tried_at DATETIME NULL,
            created_by INT NULL,
            UNIQUE KEY uq_token (token),
            INDEX (email), INDEX (campaign_name), INDEX (status), INDEX (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->exec("CREATE TABLE IF NOT EXISTS survey_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invitation_id INT NULL,
            campaign_name VARCHAR(80) NULL,
            accessibility_need VARCHAR(60) NULL,
            ease_of_use TINYINT NULL,
            access_difficulty TINYINT(1) NULL,
            access_details VARCHAR(500) NULL,
            trust_in_sources TINYINT NULL,
            helped_access_service TINYINT NULL,
            pmf_reaction ENUM('very_disappointed','somewhat_disappointed','not_disappointed') NULL,
            nps_score TINYINT NULL,
            return_intent ENUM('yes','maybe','no') NULL,
            missing_service VARCHAR(500) NULL,
            other_feedback VARCHAR(1000) NULL,
            created_at DATETIME NOT NULL,
            INDEX (invitation_id), INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!colExists('survey_responses', 'campaign_name')) {
            db()->exec("ALTER TABLE survey_responses
                ADD COLUMN campaign_name VARCHAR(80) NULL AFTER invitation_id,
                ADD INDEX ix_campaign (campaign_name)");
        }

        /* مقاطع قاعدة المعرفة لنظام RAG — مصادر رسمية مُقطَّعة مع متجه تضمينها.
           embedding مخزَّن كنص JSON لا عمود VECTOR: المطابقة تُحسب في PHP وقت
           السؤال (مسح خطي)، وهذا كافٍ تماماً لحجم مئات لا ملايين المقاطع —
           لا داعي لخدمة قاعدة بيانات متجهية منفصلة بهذا الحجم. */
        db()->exec("CREATE TABLE IF NOT EXISTS kb_chunks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source_url VARCHAR(500) NOT NULL,
            source_title VARCHAR(300) NULL,
            chunk_index INT NOT NULL DEFAULT 0,
            chunk_text TEXT NOT NULL,
            embedding LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX (source_url)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        /* قياس زمن الاستجابة (مسار الرد الفوري المتدفق): اسم النموذج/المزوّد
           الفعليَين سجلٌّ تشغيلي داخلي يقرأه الإداري فقط، ولا يخالف قاعدة
           "لا يُذكر المزوّد" التي تحكم النص المعروض للمستخدم المحادث حصراً. */
        if (!colExists('chat_logs', 'model')) {
            db()->exec("ALTER TABLE chat_logs
                ADD COLUMN model     VARCHAR(40)      NULL,
                ADD COLUMN provider  VARCHAR(20)      NULL,
                ADD COLUMN stream    TINYINT(1)       NOT NULL DEFAULT 0,
                ADD COLUMN ttfb_ms   INT UNSIGNED     NULL,
                ADD COLUMN total_ms  INT UNSIGNED     NULL,
                ADD COLUMN aborted   TINYINT(1)       NOT NULL DEFAULT 0,
                ADD INDEX ix_provider (provider, created_at)");
        }

        migrateSurveys();
        ensureRateTable();
    } catch (Throwable $e) {
        /* الترقية لا توقف الطلب، لكن صمتها التام كان يخفي هجرة نصف مكتملة
           فيظهر العطل لاحقاً في مكان بعيد عن سببه. */
        error_log('WESAL_SCHEMA_FAIL: ' . $e->getMessage());
    }
}

function body(): array {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

function out(array $data, int $code = 200): void {
    discardStrayOutput();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $msg, int $code = 400): void { out(['ok' => false, 'error' => $msg], $code); }

function clean($v, int $max = 2000): string {
    return mb_substr(trim(strip_tags((string)$v)), 0, $max, 'UTF-8');
}

function currentUser(): ?array {
    if (empty($_SESSION['uid'])) return null;
    ensureSchema();
    $s = db()->prepare('SELECT id,name,name_en,dob,email,phone,pref,role,status,must_change_pw,improve,
                               is_demo,tokens,tokens_at,created_at,questions,
                               avatar,city,age_range,disability,interests,bio,support_level
                        FROM users WHERE id=? LIMIT 1');
    $s->execute([$_SESSION['uid']]);
    $u = $s->fetch();
    if (!$u) return null;
    // حساب أوقفه مدير النظام: تُنهى جلسته فوراً في أول طلب بعد الإيقاف
    if (($u['status'] ?? 'active') === 'suspended') { $_SESSION = []; return null; }
    return $u;
}

/* ---------- الأدوار ---------- */
const ROLES = ['user', 'reviewer', 'mod', 'admin'];
function isAdmin(?array $u): bool { return $u && $u['role'] === 'admin'; }

/* ---------- أنواع تذاكر الدعم ----------
   المصدر الوحيد للأنواع. كانت مكرّرة نصّاً في auth.php وفي قائمة HTML،
   فأي تعديل في أحدهما يكسر الآخر صامتاً.
   التسعة الأولى: طلبات حساب المستفيد المسجَّل (كما كانت). الثلاثة الأخيرة:
   أُضيفت لتغطية الضيف بلا حساب وصفحة الشركة — صعوبة الوصول تُعامَل كعُطل
   لا كاقتراح تحسين، لأن منصة لذوي الإعاقة لا تحتمل عائق وصول قائماً. */
const TICKET_TYPES = [
    'تعديل الاسم', 'تعديل رقم الجوال', 'تعديل البريد الإلكتروني', 'تعديل تاريخ الميلاد',
    'مشكلة في الرصيد أو الأسئلة', 'مشكلة تقنية في المنصة', 'بلاغ عن معلومة غير دقيقة',
    'حذف الحساب', 'أخرى',
    'صعوبة وصول', 'استفسار عام', 'فرصة عمل أو شراكة',
];
/** النوع الوحيد الذي يراه مراجع المحتوى ويغلقه */
const TICKET_TYPE_ACCURACY = 'بلاغ عن معلومة غير دقيقة';
/** يرتفع بأولوية درجة واحدة تلقائياً — انظر computePriority() في tickets.php */
const TICKET_TYPE_ACCESS = 'صعوبة وصول';

/** تسجيل عملية إدارية في سجل الخادم — السجل الوحيد الذي يُعتد به */
function audit(?array $actor, string $action, string $target = '', string $detail = ''): void {
    try {
        db()->prepare('INSERT INTO audit_log (actor_id,actor_name,action,target,detail,ip,created_at)
                       VALUES (?,?,?,?,?,?,NOW())')
            ->execute([$actor['id'] ?? null, $actor['name'] ?? null, $action,
                       mb_substr($target, 0, 160), mb_substr($detail, 0, 400), clientIp()]);
    } catch (Throwable $e) { /* السجل لا يوقف العملية */ }
}

/* ---------- محتوى الصفحات ---------- */
/** كل المحتوى المحرَّر كخريطة مفتاح ← نص */
function contentMap(): array {
    ensureSchema();
    $out = [];
    try {
        foreach (db()->query('SELECT ckey, cval FROM site_content')->fetchAll() as $r) {
            $out[$r['ckey']] = $r['cval'];
        }
    } catch (Throwable $e) { /* المحتوى الأصلي في الصفحة هو البديل */ }
    return $out;
}

/** أي عضو في الفريق: مدير نظام أو مشرف أو مراجع محتوى */
function requireStaff(): array {
    $u = currentUser();
    if (!$u || !in_array($u['role'], ['admin','mod','reviewer'], true))
        fail('غير مصرّح لك بالوصول لهذه البيانات.', 403);
    return $u;
}

/** اسم الدور بالعربية — مصدر واحد تستخدمه الرسائل والسجل والبريد */
function roleName(string $r): string {
    return ['admin' => 'مدير النظام', 'mod' => 'مشرف',
            'reviewer' => 'مراجع محتوى', 'user' => 'مستفيد'][$r] ?? 'مستفيد';
}

/** إرسال بريد HTML من عنوان المنصة — SMTP مصادَق إن كانت SMTP_PASS مضبوطة،
 *  وإلا mail() المحلي في الاستضافة كما كان دائماً. */
function sendMail(string $to, string $subject, string $html): bool {
    if (SMTP_PASS !== '' && smtpSend($to, $subject, $html)) return true;
    if (SMTP_PASS !== '') error_log('WESAL_MAIL_FAIL: فشل SMTP، رجعنا لـmail() المحلي كبديل مؤقت');
    $fname = '=?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?=';
    $headers = "From: $fname <" . MAIL_FROM . ">\r\nReply-To: " . MAIL_FROM . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers, '-f' . MAIL_FROM);
}

/** اتصال SMTP مصادَق مباشر بالبروتوكول الخام (بلا PHPMailer ولا أي اعتمادية،
 *  بنفس فلسفة هذا المشروع بأكمله). يدعم SSL الفوري (المنفذ 465 عادة)
 *  وSTARTTLS (587)، ويُسجّل سبب أي فشل في سجل الأخطاء للتشخيص. */
function smtpSend(string $to, string $subject, string $html): bool {
    $host = SMTP_HOST . ''; $port = (int)SMTP_PORT; $enc = SMTP_ENCRYPTION;
    $transport = $enc === 'ssl' ? 'ssl://' : 'tcp://';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { error_log("WESAL_MAIL_FAIL: تعذّر الاتصال بـ $host:$port — $errstr"); return false; }
    stream_set_timeout($fp, 12);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (!isset($line[3]) || $line[3] !== '-') break;   // "250 " آخر سطر؛ "250-" متعدد الأسطر يكمل القراءة
        }
        return $data;
    };
    $cmd = function (string $c) use ($fp) { fwrite($fp, $c . "\r\n"); };
    $ok  = fn(string $data, string ...$codes) => in_array(substr($data, 0, 3), $codes, true);
    $fail = function (string $label, string $data) use ($fp) {
        fclose($fp);
        error_log("WESAL_MAIL_FAIL: $label — " . trim(mb_substr($data, 0, 200)));
        return false;
    };

    $ehloHost = (string)(parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost');
    $banner = $read();
    if (!$ok($banner, '220')) return $fail('بادئة الخادم غير متوقعة', $banner);

    $cmd('EHLO ' . $ehloHost);
    $r = $read();
    if (!$ok($r, '250')) return $fail('رفض EHLO', $r);

    if ($enc === 'tls') {
        $cmd('STARTTLS');
        $r = $read();
        if (!$ok($r, '220')) return $fail('رفض STARTTLS', $r);
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
            return $fail('فشل تفعيل التشفير بعد STARTTLS', '');
        $cmd('EHLO ' . $ehloHost);
        $r = $read();
        if (!$ok($r, '250')) return $fail('رفض EHLO بعد STARTTLS', $r);
    }

    $cmd('AUTH LOGIN');
    $r = $read(); if (!$ok($r, '334')) return $fail('رفض AUTH LOGIN', $r);
    $cmd(base64_encode(SMTP_USER));
    $r = $read(); if (!$ok($r, '334')) return $fail('رفض اسم المستخدم', $r);
    $cmd(base64_encode(SMTP_PASS));
    $r = $read();
    if (!$ok($r, '235')) return $fail('فشلت المصادقة — تحقّق من كلمة مرور صندوق البريد', $r);

    $cmd('MAIL FROM:<' . MAIL_FROM . '>');
    $r = $read(); if (!$ok($r, '250')) return $fail('رفض المرسل', $r);
    $cmd('RCPT TO:<' . $to . '>');
    $r = $read(); if (!$ok($r, '250', '251')) return $fail('رفض المستلم', $r);
    $cmd('DATA');
    $r = $read(); if (!$ok($r, '354')) return $fail('رفض بدء المحتوى', $r);

    $fname   = '=?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?=';
    $subjEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = "From: $fname <" . MAIL_FROM . ">\r\nTo: <$to>\r\nSubject: $subjEnc\r\n"
             . "Reply-To: " . MAIL_FROM . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
             . "Date: " . date('r') . "\r\n\r\n";
    $bodyEscaped = preg_replace('/^\./m', '..', $html);   // نقطة في بداية سطر تُضاعَف — قاعدة SMTP لإنهاء DATA
    $cmd($headers . $bodyEscaped . "\r\n.");
    $r = $read();
    fclose($fp);
    if (!$ok($r, '250')) { error_log('WESAL_MAIL_FAIL: رُفضت الرسالة بعد DATA — ' . trim(mb_substr($r, 0, 200))); return false; }
    return true;
}

/** قالب بريد الدعوة */
function inviteEmailHtml(string $inviter, string $roleTarget, string $link): string {
    $isTeam  = $roleTarget !== 'user';
    $roleTxt = $isTeam ? 'للانضمام لفريق وصال بصفة ' . roleName($roleTarget) : 'لتجربة منصة وصال';
    $btnTxt  = $isTeam ? 'قبول الدعوة وإنشاء حسابي' : 'تجربة وصال الآن';
    $i = htmlspecialchars($inviter, ENT_QUOTES, 'UTF-8');
    return '<div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;background:#f4f2fb;padding:32px 16px">'
        . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e4ddf0">'
        . '<div style="background:linear-gradient(135deg,#814fc3,#282692);padding:26px;text-align:center;color:#fff;font-size:22px;font-weight:bold">وصــال</div>'
        . '<div style="padding:28px 26px;color:#3d3558;line-height:1.9;font-size:15px">'
        . 'السلام عليكم،<br><b>' . $i . '</b> يدعوك ' . $roleTxt . ' — أول منصة ذكاء اصطناعي سعودية مصممة لخدمة الأشخاص ذوي الإعاقة.'
        . '<div style="text-align:center;margin:26px 0"><a href="' . $link . '" style="background:linear-gradient(135deg,#814fc3,#5039a8);color:#fff;text-decoration:none;padding:14px 34px;border-radius:99px;font-weight:bold;display:inline-block">' . $btnTxt . '</a></div>'
        . '<div style="font-size:12px;color:#8a7fa3">لو الزر ما اشتغل انسخ الرابط:<br><span dir="ltr" style="word-break:break-all">' . $link . '</span></div>'
        . '</div></div></div>';
}

/* ---------- التجربة الموسّعة — دعوات النسخة التجريبية ----------
   المدعو من رابط /invite/{token} يجرّب المساعد بلا حساب لمدة BETA_TRIAL_HOURS
   ساعة، متجاوزاً حصة الزائر اليومية. الحالة تعيش في الجلسة، والفحص كله
   يمر من betaTrialActive() — نقطة واحدة، لا منطق مكرر في الملفات. */

/** هل لهذه الجلسة تجربة موسّعة سارية من رابط دعوة؟ */
function betaTrialActive(): bool {
    return !empty($_SESSION['beta_trial_until']) && (int)$_SESSION['beta_trial_until'] > time();
}

/** أول سؤال فعلي من المدعو: يُسجَّل وقت بدء تجربته — مرة واحدة لكل جلسة.
 *  مستقل عن status (تقدّم إكمال الاستبيان)؛ يُقرأ فقط للاستبيان الذي
 *  grants_trial=1، لكن التسجيل نفسه غير مشروط بذلك فلا حاجة لفحص إضافي. */
function markInvitationTried(): void {
    if (empty($_SESSION['invitation_id']) || !empty($_SESSION['invitation_tried'])) return;
    $_SESSION['invitation_tried'] = 1;
    try {
        db()->prepare('UPDATE survey_invitations SET trial_started_at=NOW()
                       WHERE id=? AND trial_started_at IS NULL')
            ->execute([(int)$_SESSION['invitation_id']]);
    } catch (Throwable $e) { /* التتبع لا يوقف الرد */ }
}

/** قالب بريد دعوة الاستبيان — عام لأي استبيان، وتُضاف فقرة التجربة الموسّعة
 *  فقط للاستبيان الذي يمنحها (grants_trial=1) بنفس نص الدعوة الأصلي. */
function surveyInviteEmailHtml(string $surveyTitle, string $link, bool $grantsTrial): string {
    $title = htmlspecialchars($surveyTitle, ENT_QUOTES, 'UTF-8');
    $lead = $grantsTrial
        ? 'تمت دعوتك لتجربة <b>وصال</b> — أول منصة ذكاء اصطناعي سعودية مصممة لخدمة الأشخاص ذوي الإعاقة.'
          . '<br>الرابط يفتح لك تجربة موسّعة لمدة <b>' . (int)BETA_TRIAL_HOURS . ' ساعة</b> بلا حاجة لإنشاء حساب: اسأل المساعد عن حقوقك والخدمات والتقنيات المساعدة بأي صيغة تريحك، وبعدها ودّنا رأيك في استبانة «' . $title . '» — أقل من دقيقة.'
        : 'ندعوك تشاركنا رأيك في استبانة «<b>' . $title . '</b>» — بضع دقائق من وقتك تساعدنا نطوّر وصال.';
    $btnTxt = $grantsTrial ? 'ابدأ التجربة الآن' : 'فتح الاستبانة';
    return '<div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;background:#f4f2fb;padding:32px 16px">'
        . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e4ddf0">'
        . '<div style="background:linear-gradient(135deg,#814fc3,#282692);padding:26px;text-align:center;color:#fff;font-size:22px;font-weight:bold">وصــال</div>'
        . '<div style="padding:28px 26px;color:#3d3558;line-height:1.9;font-size:15px">'
        . 'السلام عليكم،<br>' . $lead
        . '<div style="text-align:center;margin:26px 0"><a href="' . $link . '" style="background:linear-gradient(135deg,#814fc3,#5039a8);color:#fff;text-decoration:none;padding:14px 34px;border-radius:99px;font-weight:bold;display:inline-block">' . $btnTxt . '</a></div>'
        . '<div style="font-size:12px;color:#8a7fa3">لو الزر ما اشتغل انسخ الرابط:<br><span dir="ltr" style="word-break:break-all">' . $link . '</span></div>'
        . '</div></div></div>';
}

/** قالب بريد إعادة تعيين كلمة المرور */
function resetEmailHtml(string $name, string $link, bool $byAdmin, int $hours): string {
    $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $lead = $byAdmin
        ? 'أعاد مدير النظام في وصال تعيين كلمة مرورك. اضغط الزر لاختيار كلمة مرور جديدة.'
        : 'وصلنا طلب لإعادة تعيين كلمة مرور حسابك في وصال. اضغط الزر لاختيار كلمة مرور جديدة.';
    return '<div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;background:#f4f2fb;padding:32px 16px">'
        . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e4ddf0">'
        . '<div style="background:linear-gradient(135deg,#814fc3,#282692);padding:26px;text-align:center;color:#fff;font-size:22px;font-weight:bold">وصــال</div>'
        . '<div style="padding:28px 26px;color:#3d3558;line-height:1.9;font-size:15px">'
        . 'أهلاً <b>' . $n . '</b>،<br>' . $lead
        . '<div style="text-align:center;margin:26px 0"><a href="' . $link . '" style="background:linear-gradient(135deg,#814fc3,#5039a8);color:#fff;text-decoration:none;padding:14px 34px;border-radius:99px;font-weight:bold;display:inline-block">تعيين كلمة مرور جديدة</a></div>'
        . '<div style="font-size:13px;color:#8a7fa3">الرابط صالح لمدة ' . $hours . ' ساعة، ويُستخدم مرة واحدة فقط.<br>'
        . 'إذا ما طلبت هذا، تجاهل الرسالة — كلمة مرورك ما تغيّرت.</div>'
        . '<div style="font-size:12px;color:#8a7fa3;margin-top:14px">لو الزر ما اشتغل انسخ الرابط:<br><span dir="ltr" style="word-break:break-all">' . $link . '</span></div>'
        . '</div></div></div>';
}

/** إنشاء رمز إعادة تعيين — يعيد الرمز الخام (يُرسل) ويخزّن هاشه فقط */
function issueResetToken(int $userId, ?int $issuedBy = null, int $hours = 2): string {
    ensureSchema();
    $token = bin2hex(random_bytes(32));
    // إبطال أي رموز سابقة لم تُستخدم بعد
    db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')
        ->execute([$userId]);
    db()->prepare('INSERT INTO password_resets (user_id,token_hash,issued_by,expires_at,created_at)
                   VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL ? HOUR),NOW())')
        ->execute([$userId, hash('sha256', $token), $issuedBy, $hours]);
    return $token;
}

/** قواعد كلمة المرور — مكان واحد يستخدمه التسجيل وإعادة التعيين والتغيير */
function passwordError(string $p): ?string {
    if (mb_strlen($p) < 8) return 'كلمة المرور لازم تكون 8 أحرف فأكثر.';
    if (!preg_match('/\p{L}/u', $p) || !preg_match('/\d/', $p))
        return 'كلمة المرور لازم تحتوي حرفاً ورقماً على الأقل.';
    return null;
}

function requireAdmin(): array {
    $u = currentUser();
    if (!$u || $u['role'] !== 'admin') fail('غير مصرّح لك بالوصول لهذه البيانات.', 403);
    return $u;
}

/** تجديد الرصيد كل RENEW_HOURS ساعات */
function refreshTokens(array $u): array {
    $last = strtotime($u['tokens_at'] ?: 'now');
    $span = RENEW_HOURS * 3600;
    if (time() - $last >= $span) {
        $cycles = (int) floor((time() - $last) / $span);
        $newAt  = date('Y-m-d H:i:s', $last + $cycles * $span);
        db()->prepare('UPDATE users SET tokens=?, tokens_at=? WHERE id=?')
            ->execute([USER_TOKENS, $newAt, $u['id']]);
        $u['tokens'] = USER_TOKENS;
        $u['tokens_at'] = $newAt;
    }
    return $u;
}

/* ==========================================================================
 *  حدود الاستخدام
 *
 *  العدّادات مخزّنة في قاعدة البيانات ومفتاحها عنوان IP — لا الجلسة.
 *  العدّاد المخزّن في الجلسة يتجاوزه حذف الكوكي، فيعود الزائر بحصة كاملة
 *  في كل طلب، وهو ما يحوّل نقطة المحادثة إلى بوابة مفتوحة على مزوّد مدفوع.
 * ========================================================================== */

/** جدول العدّادات — يُنشأ عند أول استخدام فقط، لا مع كل طلب */
function ensureRateTable(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    db()->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        bucket       VARCHAR(40)  NOT NULL,
        ident        VARCHAR(45)  NOT NULL,
        window_start DATETIME     NOT NULL,
        hits         INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (bucket, ident, window_start),
        KEY ix_window (window_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_general_ci");
}

/**
 * عنوان الزائر. ترويسات الوكيل العكسي قابلة للانتحال من أي متصفح، فلا تُقرأ
 * إلا إذا فُعّل TRUST_PROXY صراحةً لموقع خلف Cloudflare أو موازن حِمل.
 */
function clientIp(): string {
    if (TRUST_PROXY) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
            if (empty($_SERVER[$h])) continue;
            $cand = trim(explode(',', (string)$_SERVER[$h])[0]);
            if (filter_var($cand, FILTER_VALIDATE_IP)) return $cand;
        }
    }
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** بداية نافذة الدقيقة الحالية */
function windowMinute(): string { return date('Y-m-d H:i:00'); }
/** بداية نافذة اليوم الحالي */
function windowDay(): string { return date('Y-m-d 00:00:00'); }

/**
 * يزيد عدّاداً ويعيد مجموعه بعد الزيادة.
 * $ident عنوان IP عادةً، و '*' يعني عدّاداً عاماً للمنصة كلها.
 */
function hitCounter(string $bucket, string $ident, string $windowStart, int $by = 1): int {
    try {
        return hitCounterRun($bucket, $ident, $windowStart, $by);
    } catch (PDOException $e) {
        // الجدول غير موجود بعد (تركيب جديد أو ترقية) — أنشئه ثم أعد المحاولة مرة واحدة
        ensureRateTable();
        return hitCounterRun($bucket, $ident, $windowStart, $by);
    }
}

function hitCounterRun(string $bucket, string $ident, string $windowStart, int $by): int {
    $d = $ident !== '' ? $ident : '0.0.0.0';
    db()->prepare('INSERT INTO rate_limits (bucket, ident, window_start, hits) VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE hits = hits + ?')
        ->execute([$bucket, $d, $windowStart, $by, $by]);
    $s = db()->prepare('SELECT hits FROM rate_limits WHERE bucket=? AND ident=? AND window_start=? LIMIT 1');
    $s->execute([$bucket, $d, $windowStart]);
    $row = $s->fetch();
    pruneRateLimits();
    return (int)($row['hits'] ?? $by);
}

/** تنظيف الصفوف المنتهية — احتمالياً حتى لا يُثقل كل طلب */
function pruneRateLimits(): void {
    static $done = false;
    if ($done) return;
    try {
        if (random_int(1, 200) !== 1) return;
        $done = true;
        db()->exec('DELETE FROM rate_limits WHERE window_start < (NOW() - INTERVAL 2 DAY)');
    } catch (Throwable $e) { /* غير حرج */ }
}

/** حد الطلبات في الدقيقة لكل عنوان IP */
function rateLimit(string $bucket, int $max = 20): void {
    if ($max <= 0) return;
    if (hitCounter('m:' . $bucket, clientIp(), windowMinute()) > $max)
        fail('طلبات كثيرة في وقت قصير. انتظر دقيقة وحاول مرة أخرى.', 429);
}

function publicUser(array $u): array {
    return [
        'id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'],
        'phone' => $u['phone'], 'pref' => $u['pref'], 'role' => $u['role'],
        'status' => $u['status'] ?? 'active',
        'must_change_pw' => (int)($u['must_change_pw'] ?? 0) === 1,
        'improve' => (int)($u['improve'] ?? 0) === 1,
        'is_demo' => (int)($u['is_demo'] ?? 0) === 1,
        'tokens' => (int)$u['tokens'], 'last' => strtotime($u['tokens_at']) * 1000,
        'created' => strtotime($u['created_at']) * 1000, 'qs' => (int)$u['questions'],
        'name_en' => $u['name_en'] ?? '', 'dob' => $u['dob'] ?? '',
        'avatar' => $u['avatar'] ?? '', 'city' => $u['city'] ?? '', 'age_range' => $u['age_range'] ?? '',
        'disability' => $u['disability'] ?? '', 'interests' => $u['interests'] ?? '', 'bio' => $u['bio'] ?? '',
        /* مستقل عن role تماماً — الواجهة تستخدمه لإظهار تبويب تذاكر الدعم
           لأي حساب مُنح صلاحية معالجة، بصرف النظر عن دوره في المنصة. */
        'support_level' => $u['support_level'] ?? 'none',
    ];
}
