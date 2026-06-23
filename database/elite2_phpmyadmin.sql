-- ============================================================
--  Elite 2.0 — phpMyAdmin Import File
--  Instructions:
--    1. Open phpMyAdmin
--    2. Click "Import" tab (no database selected, OR select 'elite2' first)
--    3. Choose this file → click "Go"
--
--  Default Admin Login after import:
--    Email:    admin@elite2.com
--    Password: Admin@1234!
--    ⚠ CHANGE THIS PASSWORD immediately after first login.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Create & select database ──────────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS `elite2`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `elite2`;

-- ═══════════════════════════════════════════════════════════════════════════════
--  TABLES
-- ═══════════════════════════════════════════════════════════════════════════════

-- ── users ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(191)    NOT NULL,
    `password`   VARCHAR(255)    NOT NULL,
    `role`       ENUM('admin','head_coach','coach','student') NOT NULL DEFAULT 'student',
    `first_name` VARCHAR(80)     NOT NULL,
    `last_name`  VARCHAR(80)     NOT NULL,
    `phone`      VARCHAR(30)     NULL DEFAULT NULL,
    `avatar`     VARCHAR(255)    NULL DEFAULT NULL,
    `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── refresh_tokens ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `refresh_tokens` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(128) NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `revoked`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rt_hash` (`token_hash`),
    KEY `idx_rt_user` (`user_id`),
    CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── password_resets ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(128) NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `used`       TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pr_user` (`user_id`),
    CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── disciplines ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `disciplines` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(80)  NOT NULL,
    `description` TEXT         NULL DEFAULT NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`  SMALLINT     NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_disc_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── belts ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `belts` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `discipline_id`       INT UNSIGNED NOT NULL,
    `name`                VARCHAR(80)  NOT NULL,
    `color_hex`           VARCHAR(7)   NOT NULL DEFAULT '#FFFFFF',
    `sort_order`          SMALLINT     NOT NULL DEFAULT 0,
    `min_attendance_pct`  TINYINT UNSIGNED NOT NULL DEFAULT 80,
    `min_eval_score`      TINYINT UNSIGNED NOT NULL DEFAULT 7,
    `min_classes`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_belt_disc` (`discipline_id`),
    CONSTRAINT `fk_belt_disc` FOREIGN KEY (`discipline_id`) REFERENCES `disciplines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── belt_skills ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `belt_skills` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `belt_id`     INT UNSIGNED NOT NULL,
    `name`        VARCHAR(120) NOT NULL,
    `description` TEXT         NULL DEFAULT NULL,
    `sort_order`  SMALLINT     NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_bs_belt` (`belt_id`),
    CONSTRAINT `fk_bs_belt` FOREIGN KEY (`belt_id`) REFERENCES `belts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── student_profiles ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `student_profiles` (
    `user_id`         INT UNSIGNED NOT NULL,
    `discipline_id`   INT UNSIGNED NULL DEFAULT NULL,
    `current_belt_id` INT UNSIGNED NULL DEFAULT NULL,
    `dob`             DATE         NULL DEFAULT NULL,
    `gender`          ENUM('M','F','Other') NULL DEFAULT NULL,
    `emergency_name`  VARCHAR(120) NULL DEFAULT NULL,
    `emergency_phone` VARCHAR(30)  NULL DEFAULT NULL,
    `notes`           TEXT         NULL DEFAULT NULL,
    `enrolled_at`     DATE         NULL DEFAULT NULL,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_sp_user` FOREIGN KEY (`user_id`)         REFERENCES `users` (`id`)       ON DELETE CASCADE,
    CONSTRAINT `fk_sp_disc` FOREIGN KEY (`discipline_id`)   REFERENCES `disciplines` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sp_belt` FOREIGN KEY (`current_belt_id`) REFERENCES `belts` (`id`)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── coach_students ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `coach_students` (
    `coach_id`    INT UNSIGNED NOT NULL,
    `student_id`  INT UNSIGNED NOT NULL,
    `assigned_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`coach_id`, `student_id`),
    CONSTRAINT `fk_cs_coach`   FOREIGN KEY (`coach_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cs_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── classes ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `classes` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `discipline_id` INT UNSIGNED NOT NULL,
    `coach_id`      INT UNSIGNED NOT NULL,
    `title`         VARCHAR(120) NOT NULL,
    `day_of_week`   TINYINT(1)   NOT NULL COMMENT '0=Sun,1=Mon,2=Tue,3=Wed,4=Thu,5=Fri,6=Sat',
    `start_time`    TIME         NOT NULL,
    `end_time`      TIME         NOT NULL,
    `location`      VARCHAR(120) NULL DEFAULT NULL,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cls_coach` (`coach_id`),
    KEY `idx_cls_disc`  (`discipline_id`),
    CONSTRAINT `fk_cls_disc`  FOREIGN KEY (`discipline_id`) REFERENCES `disciplines` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cls_coach` FOREIGN KEY (`coach_id`)      REFERENCES `users` (`id`)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── attendance_sessions ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `attendance_sessions` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id`     INT UNSIGNED NOT NULL,
    `coach_id`     INT UNSIGNED NOT NULL,
    `session_date` DATE         NOT NULL,
    `opened_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `closed_at`    DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_as_class` (`class_id`),
    KEY `idx_as_date`  (`session_date`),
    CONSTRAINT `fk_as_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_as_coach` FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── attendance ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `attendance` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `status`     ENUM('present','absent','late') NOT NULL,
    `note`       VARCHAR(255) NULL DEFAULT NULL,
    `marked_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_att_session_student` (`session_id`, `student_id`),
    KEY `idx_att_student` (`student_id`),
    CONSTRAINT `fk_att_session` FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_att_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)               ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── evaluations ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `evaluations` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `student_id`  INT UNSIGNED     NOT NULL,
    `coach_id`    INT UNSIGNED     NOT NULL,
    `technique`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `fitness`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `discipline`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `focus`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `attitude`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `balance`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `reflex`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `speed`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `flexibility` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `overall`     DECIMAL(4,2)     NOT NULL DEFAULT 0.00,
    `notes`       TEXT             NULL DEFAULT NULL,
    `eval_date`   DATE             NOT NULL,
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ev_student` (`student_id`),
    KEY `idx_ev_date`    (`eval_date`),
    CONSTRAINT `fk_ev_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ev_coach`   FOREIGN KEY (`coach_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── student_skills ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `student_skills` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `skill_id`   INT UNSIGNED NOT NULL,
    `level`      ENUM('not_started','developing','competent','advanced') NOT NULL DEFAULT 'not_started',
    `updated_by` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ss_student_skill` (`student_id`, `skill_id`),
    CONSTRAINT `fk_ss_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)       ON DELETE CASCADE,
    CONSTRAINT `fk_ss_skill`   FOREIGN KEY (`skill_id`)   REFERENCES `belt_skills` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ss_by`      FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── coach_notes ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `coach_notes` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `coach_id`   INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `body`       TEXT         NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cn_student` (`student_id`),
    CONSTRAINT `fk_cn_coach`   FOREIGN KEY (`coach_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cn_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── promotions ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `promotions` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`   INT UNSIGNED NOT NULL,
    `from_belt_id` INT UNSIGNED NULL DEFAULT NULL,
    `to_belt_id`   INT UNSIGNED NOT NULL,
    `approved_by`  INT UNSIGNED NOT NULL,
    `status`       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `notes`        TEXT         NULL DEFAULT NULL,
    `promoted_at`  DATETIME     NULL DEFAULT NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_promo_student` (`student_id`),
    CONSTRAINT `fk_promo_student` FOREIGN KEY (`student_id`)  REFERENCES `users` (`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_promo_from`    FOREIGN KEY (`from_belt_id`) REFERENCES `belts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_promo_to`      FOREIGN KEY (`to_belt_id`)   REFERENCES `belts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_promo_by`      FOREIGN KEY (`approved_by`)  REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── points ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `points` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `amount`     SMALLINT     NOT NULL,
    `reason`     ENUM('attendance','perfect_month','seminar','promotion','streak','custom') NOT NULL,
    `note`       VARCHAR(255) NULL DEFAULT NULL,
    `awarded_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pts_student` (`student_id`),
    CONSTRAINT `fk_pts_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── achievements ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `achievements` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key_name`    VARCHAR(60)  NOT NULL,
    `label`       VARCHAR(80)  NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `icon`        VARCHAR(10)  NOT NULL DEFAULT '🏅',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ach_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── student_achievements ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `student_achievements` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`     INT UNSIGNED NOT NULL,
    `achievement_id` INT UNSIGNED NOT NULL,
    `earned_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sa` (`student_id`, `achievement_id`),
    CONSTRAINT `fk_sa_student` FOREIGN KEY (`student_id`)     REFERENCES `users` (`id`)        ON DELETE CASCADE,
    CONSTRAINT `fk_sa_ach`     FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── notifications ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `type`       VARCHAR(60)  NOT NULL,
    `title`      VARCHAR(150) NOT NULL,
    `body`       TEXT         NOT NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user` (`user_id`, `is_read`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── settings ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
    `key_name`   VARCHAR(80) NOT NULL,
    `value`      TEXT        NULL DEFAULT NULL,
    `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── seminars ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `seminars` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(150) NOT NULL,
    `description` TEXT         NULL DEFAULT NULL,
    `date`        DATE         NOT NULL,
    `location`    VARCHAR(150) NULL DEFAULT NULL,
    `points`      SMALLINT     NOT NULL DEFAULT 50,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_sem_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── seminar_attendance ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `seminar_attendance` (
    `seminar_id` INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `marked_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`seminar_id`, `student_id`),
    CONSTRAINT `fk_sema_sem`     FOREIGN KEY (`seminar_id`) REFERENCES `seminars` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sema_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════════════════
--  SEED DATA
-- ═══════════════════════════════════════════════════════════════════════════════

-- ── Default admin user ────────────────────────────────────────────────────────
-- Password: Admin@1234!  (bcrypt cost 12 — change after first login!)
INSERT IGNORE INTO `users`
    (`email`, `password`, `role`, `first_name`, `last_name`, `is_active`)
VALUES (
    'admin@elite2.com',
    '$2b$12$UiR4GQ5j4EzW2dyq0C27bu6PX6EuV0V7ScU17buSjhE7o6uSvjICK',
    'admin',
    'System',
    'Admin',
    1
);

-- ── Achievements (badges) ─────────────────────────────────────────────────────
INSERT IGNORE INTO `achievements` (`key_name`, `label`, `description`, `icon`) VALUES
('streak_30',          '30-Day Streak',       'Attended 30 consecutive classes',     '🔥'),
('perfect_attendance', 'Perfect Attendance',  'Perfect attendance for a full month', '⭐'),
('first_belt',         'First Belt Earned',   'Earned their first belt promotion',   '🥋'),
('sessions_100',       '100 Sessions',        'Completed 100 training sessions',     '💯'),
('first_eval',         'First Evaluation',    'Completed first coach evaluation',    '📋'),
('seminar_5',          'Seminar Enthusiast',  'Attended 5 seminars',                 '🎓');

-- ── Disciplines ───────────────────────────────────────────────────────────────
INSERT IGNORE INTO `disciplines` (`name`, `sort_order`) VALUES
('Kickboxing', 1),
('BJJ',        2),
('Karate',     3),
('MMA',        4);

-- ── Sample belts for Kickboxing (discipline_id = 1) ──────────────────────────
INSERT IGNORE INTO `belts`
    (`discipline_id`, `name`, `color_hex`, `sort_order`, `min_attendance_pct`, `min_eval_score`, `min_classes`)
VALUES
(1, 'White Belt',  '#FFFFFF', 1, 70, 5, 0),
(1, 'Yellow Belt', '#FFD700', 2, 75, 6, 10),
(1, 'Orange Belt', '#FF8C00', 3, 75, 6, 20),
(1, 'Green Belt',  '#228B22', 4, 80, 7, 30),
(1, 'Blue Belt',   '#1E90FF', 5, 80, 7, 50),
(1, 'Red Belt',    '#DC143C', 6, 85, 8, 75),
(1, 'Black Belt',  '#1a1a1a', 7, 90, 9, 100);

-- ── Sample belts for BJJ (discipline_id = 2) ─────────────────────────────────
INSERT IGNORE INTO `belts`
    (`discipline_id`, `name`, `color_hex`, `sort_order`, `min_attendance_pct`, `min_eval_score`, `min_classes`)
VALUES
(2, 'White Belt',  '#FFFFFF', 1, 70, 5, 0),
(2, 'Blue Belt',   '#1E90FF', 2, 80, 7, 50),
(2, 'Purple Belt', '#8B008B', 3, 80, 7, 100),
(2, 'Brown Belt',  '#8B4513', 4, 85, 8, 150),
(2, 'Black Belt',  '#1a1a1a', 5, 90, 9, 200);

-- ── Default settings ──────────────────────────────────────────────────────────
INSERT IGNORE INTO `settings` (`key_name`, `value`) VALUES
('academy_name',           'Elite 2.0 Academy'),
('academy_logo',           ''),
('attendance_late_credit', '0.5'),
('points_attendance',      '10'),
('points_perfect_month',   '100'),
('points_seminar',         '50'),
('points_promotion',       '200'),
('email_from',             'noreply@elite2.com'),
('smtp_host',              ''),
('smtp_user',              ''),
('whatsapp_api_key',       '');

-- ═══════════════════════════════════════════════════════════════════════════════
--  DONE
--  Tables created: 18
--  Default login:  admin@elite2.com / Admin@1234!
--  ⚠ Change the admin password immediately after first login.
-- ═══════════════════════════════════════════════════════════════════════════════
