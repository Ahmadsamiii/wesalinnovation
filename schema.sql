-- ==========================================================================
--  وصال — مخطط قاعدة البيانات الكامل
--  الاستخدام:  mysql -u USER -p DBNAME < schema.sql
--
--  هذا الملف هو المرجع الوحيد لبنية قاعدة البيانات.
--  أي تعديل على البنية يُضاف هنا وفي ensureSchema() في api/db.php معاً،
--  حتى تبقى التركيبات الجديدة والقديمة متطابقة.
-- ==========================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- --------------------------------------------------------------------------
--  المستخدمون
--
--  الأدوار:
--    admin     مدير النظام  — كل الصلاحيات: المحتوى والمستخدمون والأدوار
--                             وإعادة تعيين كلمات المرور والدعوات والسجل
--    mod       مشرف         — المستخدمون (عرض) والرسائل والتذاكر والدعوات
--    reviewer  مراجع محتوى  — بلاغات دقة المعلومات وتذاكر المحتوى
--    user      مستفيد       — المحادثة والملف الشخصي والتذاكر
--
--  الحساب الأول الذي يُسجَّل في منصة فارغة يصبح مدير النظام تلقائياً
--  (انظر auth.php → case 'register').
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,                 -- الاسم بالعربية: الأول + الأخير
  name_en     VARCHAR(80)  NULL,                     -- الاسم بالإنجليزية
  dob         DATE         NULL,                     -- تاريخ الميلاد
  email       VARCHAR(120) NOT NULL,
  phone       VARCHAR(20)  NOT NULL,                 -- بصيغة 05XXXXXXXX
  pref        ENUM('simple','detailed','voice','visual') NOT NULL DEFAULT 'simple',
  pass_hash   VARCHAR(255) NOT NULL,                 -- password_hash() — لا تخزّن كلمة المرور أبداً
  role        ENUM('user','reviewer','mod','admin') NOT NULL DEFAULT 'user',
  status      ENUM('active','suspended') NOT NULL DEFAULT 'active',
  must_change_pw TINYINT(1) NOT NULL DEFAULT 0,      -- بعد إعادة تعيين من مدير النظام
  improve     TINYINT(1)   NOT NULL DEFAULT 0,       -- موافقة استخدام المحادثات للتحسين
  is_demo     TINYINT(1)   NOT NULL DEFAULT 0,       -- حساب تجريبي أنشأه مدير النظام للعرض
  tokens      INT          NOT NULL DEFAULT 30,      -- الرصيد المتبقي من الأسئلة
  tokens_at   DATETIME     NOT NULL,                 -- بداية دورة التجديد الحالية
  questions   INT          NOT NULL DEFAULT 0,       -- إجمالي الأسئلة المطروحة
  created_at  DATETIME     NOT NULL,
  last_login  DATETIME     NULL,
  -- حقول الملف الشخصي
  avatar      VARCHAR(160) NULL,                     -- مسار نسبي: uploads/avatars/...
  city        VARCHAR(60)  NULL,
  age_range   VARCHAR(20)  NULL,
  disability  VARCHAR(60)  NULL,
  interests   VARCHAR(300) NULL,
  bio         VARCHAR(500) NULL,
  UNIQUE KEY uq_email (email),
  UNIQUE KEY uq_phone (phone),
  KEY ix_created (created_at),
  KEY ix_role (role),
  KEY ix_demo (is_demo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
--  سجل المحادثات
--  user_id فارغ للزوار. ON DELETE SET NULL يفصل السجل عن صاحبه عند حذف الحساب
--  بدل أن يمنع الحذف.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_logs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          NULL,
  question    TEXT         NOT NULL,
  answer      MEDIUMTEXT   NULL,
  mode        VARCHAR(20)  NOT NULL DEFAULT 'simple', -- simple | detailed | small
  cost        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL,
  KEY ix_user (user_id),
  KEY ix_created (created_at),
  CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
--  رسائل نموذج «تواصل معنا»
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  email       VARCHAR(120) NOT NULL,
  subject     VARCHAR(120) NOT NULL,
  message     TEXT         NOT NULL,
  ip          VARCHAR(45)  NULL,
  is_read     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL,
  KEY ix_created (created_at),
  KEY ix_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
--  طلبات الدعم
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS support_tickets (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          NOT NULL,
  type        VARCHAR(60)  NOT NULL,
  details     TEXT         NOT NULL,
  status      ENUM('open','done') NOT NULL DEFAULT 'open',
  created_at  DATETIME     NOT NULL,
  KEY ix_user (user_id),
  KEY ix_created (created_at),
  CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
--  الدعوات — دعوة واحدة لكل بريد، وإعادة الدعوة تحدّث الصف نفسه برمز جديد
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invites (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  email       VARCHAR(120) NOT NULL,
  role_target ENUM('user','reviewer','mod') NOT NULL DEFAULT 'user',
  token       VARCHAR(64)  NOT NULL,
  invited_by  INT          NOT NULL,
  status      ENUM('sent','accepted','revoked') NOT NULL DEFAULT 'sent',
  created_at  DATETIME     NOT NULL,
  accepted_at DATETIME     NULL,
  UNIQUE KEY uq_email (email),
  KEY ix_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
--  إعادة تعيين كلمة المرور
--  يُخزَّن هاش الرمز لا الرمز نفسه، حتى لا يمنح تسرّب قاعدة البيانات
--  القدرة على إعادة تعيين كلمات المرور.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          NOT NULL,
  token_hash  CHAR(64)     NOT NULL,                 -- sha256 للرمز المرسل
  issued_by   INT          NULL,                     -- مدير النظام إن كانت إعادة تعيين إدارية
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  created_at  DATETIME     NOT NULL,
  KEY ix_token (token_hash),
  KEY ix_user (user_id),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
--  محتوى الصفحات القابل للتحرير
--  كل مفتاح يقابل عنصراً في index.html يحمل data-cms="المفتاح".
--  غياب الصف يعني أن النص الأصلي المكتوب في الصفحة هو المعروض.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_content (
  ckey        VARCHAR(80)  NOT NULL PRIMARY KEY,
  cval        TEXT         NOT NULL,
  updated_by  INT          NULL,
  updated_at  DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
--  سجل العمليات الإدارية
--  كان يُخزَّن في متصفح المشرف فقط — أي أنه لم يكن سجلاً. الآن على الخادم.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  actor_id    INT          NULL,
  actor_name  VARCHAR(80)  NULL,                     -- محفوظ نصاً ليبقى بعد حذف الفاعل
  action      VARCHAR(40)  NOT NULL,
  target      VARCHAR(160) NULL,
  detail      VARCHAR(400) NULL,
  ip          VARCHAR(45)  NULL,
  created_at  DATETIME     NOT NULL,
  KEY ix_created (created_at),
  KEY ix_actor (actor_id),
  KEY ix_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
--  حدود الاستخدام
--  المفتاح (نوع العدّاد، المُعرِّف، بداية النافذة). المُعرِّف عنوان IP عادةً،
--  و '*' يعني عدّاداً عاماً للمنصة كلها.
--  السبب في وجوده: العدّادات المخزّنة في الجلسة يتجاوزها حذف الكوكي.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
  bucket       VARCHAR(40)  NOT NULL,
  ident        VARCHAR(45)  NOT NULL,
  window_start DATETIME     NOT NULL,
  hits         INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket, ident, window_start),
  KEY ix_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_general_ci;

-- --------------------------------------------------------------------------
--  دعوات النسخة التجريبية (قياس الأداء)
--  نظام مستقل عن جدول invites (دعوات الأدوار): هذه دعوات جماعية بحملات
--  تمنح المدعو تجربة موسّعة بلا حساب، وتتتبع رحلته حتى إكمال الاستبانة.
--  الرابط: SITE_URL/invite/{token} — والحالة تتقدم ولا تتراجع:
--    sent → clicked → tried → completed_survey
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invitations (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(120) NOT NULL,
  token         CHAR(40)     NOT NULL,                -- bin2hex(random_bytes(20))
  campaign_name VARCHAR(80)  NOT NULL DEFAULT '',     -- لتجميع النتائج حسب الحملة
  status        ENUM('sent','clicked','tried','completed_survey') NOT NULL DEFAULT 'sent',
  sent_at       DATETIME     NOT NULL,
  clicked_at    DATETIME     NULL,                    -- أول فتح للرابط
  tried_at      DATETIME     NULL,                    -- أول سؤال فعلي للمساعد
  created_by    INT          NULL,
  UNIQUE KEY uq_token (token),
  KEY ix_email (email),
  KEY ix_campaign (campaign_name),
  KEY ix_status (status),
  KEY ix_sent (sent_at),
  CONSTRAINT fk_invitation_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
--  إجابات استبانة قياس التجربة — عشرة أسئلة، إجابة واحدة لكل دعوة
--  invitation_id فارغ للإجابات المجهولة (استبانة بلا رابط دعوة)،
--  و ON DELETE SET NULL يبقي الإجابة للإحصاءات بعد حذف الدعوة.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS survey_responses (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  invitation_id         INT          NULL,
  accessibility_need    VARCHAR(60)  NULL,            -- نوع الاحتياج أو الإعاقة
  ease_of_use           TINYINT      NULL,            -- سهولة الاستخدام 1-5
  access_difficulty     TINYINT(1)   NULL,            -- صعوبة مع لوحة المفاتيح/قارئ الشاشة؟
  access_details        VARCHAR(500) NULL,            -- تفاصيل الصعوبة إن وجدت
  trust_in_sources      TINYINT      NULL,            -- الثقة بالمصادر 1-5
  helped_access_service TINYINT      NULL,            -- ساعدك تصل لخدمة؟ 1-5
  pmf_reaction          ENUM('very_disappointed','somewhat_disappointed','not_disappointed') NULL,
  nps_score             TINYINT      NULL,            -- 0-10
  return_intent         ENUM('yes','maybe','no') NULL,
  missing_service       VARCHAR(500) NULL,            -- الخدمة الناقصة الأهم
  other_feedback        VARCHAR(1000) NULL,
  created_at            DATETIME     NOT NULL,
  KEY ix_invitation (invitation_id),
  KEY ix_created (created_at),
  CONSTRAINT fk_survey_invitation FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
