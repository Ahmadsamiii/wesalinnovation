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
  campaign_name         VARCHAR(80)  NULL,            -- لاستبيان الرابط العام بلا دعوة؛ المرتبط بدعوة يُقرأ من invitations
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
  KEY ix_campaign (campaign_name),
  CONSTRAINT fk_survey_invitation FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
--  محرّك الاستبيانات — يحل محل الاستبانة الواحدة الثابتة أعلاه (invitations
--  وsurvey_responses). عدد الاستبيانات وأسئلتها غير محدود ويُدار بالكامل من
--  لوحة التحكم، بلا أي تعديل على الكود أو المخطط لإضافة استبيان أو سؤال.
--
--  invitations وsurvey_responses تبقيان في المخطط مؤقتاً لسلامة الترحيل
--  (بياناتهما تُنسخ إلى الجداول الجديدة عبر migrateSurveys() في api/db.php)
--  وتُحذفان في خطوة تالية منفصلة بعد التأكد من نجاح الترحيل.
-- --------------------------------------------------------------------------

--  تعريف الاستبيان: عنوانه وحالته ورابطه العام
CREATE TABLE IF NOT EXISTS surveys (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  title               VARCHAR(160) NOT NULL,
  description         VARCHAR(500) NULL,
  thank_you_message   VARCHAR(500) NULL,
  status              ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  public_token        CHAR(40)     NULL,                -- رمز الرابط العام؛ NULL يعني ما فُعِّل بعد
  public_link_enabled TINYINT(1)   NOT NULL DEFAULT 0,
  grants_trial        TINYINT(1)   NOT NULL DEFAULT 0,   -- يمنح تجربة الذكاء الاصطناعي الموسّعة عبر رابط الدعوة (كالاستبيان الافتراضي)
  created_by          INT          NULL,
  created_at          DATETIME     NOT NULL,
  updated_at          DATETIME     NOT NULL,
  published_at        DATETIME     NULL,
  UNIQUE KEY uq_survey_public_token (public_token),
  KEY ix_status (status),
  CONSTRAINT fk_survey_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  أسئلة الاستبيان — بترتيبها ونوعها، يحررها المشرف بالكامل من لوحة التحكم
CREATE TABLE IF NOT EXISTS survey_questions (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  survey_id        INT          NOT NULL,
  position         INT          NOT NULL DEFAULT 0,
  type             ENUM('single_choice','multi_choice','scale','short_text','long_text') NOT NULL,
  question_text    VARCHAR(500) NOT NULL,
  help_text        VARCHAR(300) NULL,
  placeholder      VARCHAR(200) NULL,                    -- نص إرشادي رمادي داخل حقل النص (لأنواع النص فقط)
  is_required      TINYINT(1)   NOT NULL DEFAULT 0,
  scale_min        TINYINT      NULL,                   -- لنوع scale فقط (مثلاً ١ أو ٠)
  scale_max        TINYINT      NULL,                   -- (مثلاً ٥ أو ١٠)
  scale_min_label  VARCHAR(40)  NULL,
  scale_max_label  VARCHAR(40)  NULL,
  max_length       SMALLINT     NULL,                   -- لأنواع النص فقط
  created_at       DATETIME     NOT NULL,
  KEY ix_survey (survey_id, position),
  CONSTRAINT fk_question_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  خيارات أسئلة الاختيار الفردي/المتعدد، مع سؤال متابعة نصي اختياري لكل خيار
CREATE TABLE IF NOT EXISTS survey_question_options (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  question_id    INT          NOT NULL,
  position       INT          NOT NULL DEFAULT 0,
  option_text    VARCHAR(200) NOT NULL,
  option_value   VARCHAR(60)  NOT NULL,
  has_followup   TINYINT(1)   NOT NULL DEFAULT 0,        -- يظهر حقل نصي إضافي عند اختيار هذا الخيار
  followup_label VARCHAR(200) NULL,
  followup_max   SMALLINT     NULL DEFAULT 500,
  KEY ix_question (question_id, position),
  CONSTRAINT fk_option_question FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  دعوات الاستبيان — نسخة معمَّمة من invitations، بربط صريح باستبيان محدد.
--  الحالة تقدّمية ولا تتراجع: sent ← opened ← completed. trial_started_at
--  مستقل تماماً عن الحالة: يُسجَّل فقط لو الاستبيان grants_trial=1.
CREATE TABLE IF NOT EXISTS survey_invitations (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  survey_id         INT          NOT NULL,
  email             VARCHAR(120) NOT NULL,
  token             CHAR(40)     NOT NULL,
  campaign_name     VARCHAR(80)  NOT NULL DEFAULT '',
  status            ENUM('sent','opened','completed') NOT NULL DEFAULT 'sent',
  sent_at           DATETIME     NOT NULL,
  opened_at         DATETIME     NULL,
  trial_started_at  DATETIME     NULL,
  completed_at      DATETIME     NULL,
  created_by        INT          NULL,
  UNIQUE KEY uq_invitation_token (token),
  KEY ix_survey (survey_id),
  KEY ix_email (email),
  KEY ix_campaign (campaign_name),
  KEY ix_status (status),
  CONSTRAINT fk_invitation_survey  FOREIGN KEY (survey_id)  REFERENCES surveys(id) ON DELETE CASCADE,
  CONSTRAINT fk_invitation_creator2 FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  تسليم واحد للاستبيان — من دعوة شخصية أو من الرابط العام مباشرة
CREATE TABLE IF NOT EXISTS survey_submissions (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  survey_id      INT          NOT NULL,
  invitation_id  INT          NULL,                     -- NULL لردود الرابط العام المجهولة
  campaign_name  VARCHAR(80)  NULL,                      -- وسم تحليلي اختياري لردود الرابط العام
  submitted_at   DATETIME     NOT NULL,
  updated_at     DATETIME     NOT NULL,
  UNIQUE KEY uq_submission_invitation (invitation_id),   -- رد واحد لكل دعوة؛ إعادة الإرسال تُحدّث لا تكرّر
  KEY ix_survey (survey_id),
  KEY ix_campaign (campaign_name),
  CONSTRAINT fk_submission_survey     FOREIGN KEY (survey_id)     REFERENCES surveys(id)            ON DELETE CASCADE,
  CONSTRAINT fk_submission_invitation FOREIGN KEY (invitation_id) REFERENCES survey_invitations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  إجابة سؤال واحد ضمن تسليم — option_id للاختيار الفردي فقط، والاختيار
--  المتعدد يُسجَّل في survey_answer_options بدلاً منه
CREATE TABLE IF NOT EXISTS survey_answers (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  submission_id  INT          NOT NULL,
  question_id    INT          NOT NULL,
  answer_text    VARCHAR(1000) NULL,                    -- نص حر (أنواع النص) أو نص سؤال المتابعة
  option_id      INT          NULL,                     -- الخيار المُختار (اختيار فردي)
  number_value   TINYINT      NULL,                     -- القيمة الرقمية (مقياس)
  UNIQUE KEY uq_answer (submission_id, question_id),
  KEY ix_question (question_id),
  KEY ix_option (option_id),
  CONSTRAINT fk_answer_submission FOREIGN KEY (submission_id) REFERENCES survey_submissions(id) ON DELETE CASCADE,
  CONSTRAINT fk_answer_question   FOREIGN KEY (question_id)   REFERENCES survey_questions(id)    ON DELETE CASCADE,
  CONSTRAINT fk_answer_option     FOREIGN KEY (option_id)      REFERENCES survey_question_options(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  الخيارات المُختارة لسؤال اختيار متعدد — صف لكل خيار مُحدَّد
CREATE TABLE IF NOT EXISTS survey_answer_options (
  answer_id  INT NOT NULL,
  option_id  INT NOT NULL,
  PRIMARY KEY (answer_id, option_id),
  CONSTRAINT fk_answer_opt_answer FOREIGN KEY (answer_id) REFERENCES survey_answers(id)         ON DELETE CASCADE,
  CONSTRAINT fk_answer_opt_option FOREIGN KEY (option_id) REFERENCES survey_question_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
