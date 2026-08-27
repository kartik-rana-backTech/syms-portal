-- ================================================================
-- Sudarshan Yuvak Mandal (SYMS) - Production Master Database Schema
-- Host-Agnostic Single Import File (Compatible with all Free/Shared Hosts & phpMyAdmin)
-- ================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. USERS TABLE
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `password_hash` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `google_id` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `github_id` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `avatar_url` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `auth_provider` ENUM('local', 'google', 'github') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `role` ENUM('admin', 'member') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `membership_status` ENUM('pending', 'approved', 'rejected', 'suspended', 'inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_at` DATETIME DEFAULT NULL,
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `rejection_reason` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failed_logins` INT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_email` (`email`),
  UNIQUE KEY `idx_users_phone` (`phone`),
  UNIQUE KEY `idx_users_google_id` (`google_id`),
  UNIQUE KEY `idx_users_github_id` (`github_id`),
  KEY `idx_role_status` (`role`, `membership_status`),
  CONSTRAINT `fk_user_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. OTP TOKENS TABLE
DROP TABLE IF EXISTS `otp_tokens`;
CREATE TABLE `otp_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `email` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp_hash` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` ENUM('signup', 'login', 'reset') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'signup',
  `attempts_left` TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `expires_at` DATETIME NOT NULL,
  `resend_count` INT UNSIGNED NOT NULL DEFAULT 1,
  `last_resend_at` DATETIME NOT NULL,
  `is_used` TINYINT(1) NOT NULL DEFAULT 0,
  `payload_json` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_otp_email_purpose` (`email`, `purpose`),
  KEY `idx_otp_expires` (`expires_at`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. RATE LIMITS TABLE
DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifier` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
  `locked_until` DATETIME DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_rate_limit` (`ip_address`, `identifier`, `action`),
  KEY `idx_rate_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. USER SESSIONS TABLE
DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `session_id` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` VARCHAR(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_session_id` (`session_id`),
  KEY `idx_session_user` (`user_id`),
  CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. REMEMBER TOKENS TABLE
DROP TABLE IF EXISTS `remember_tokens`;
CREATE TABLE `remember_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `selector` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_rem_selector` (`selector`),
  KEY `idx_rem_user` (`user_id`),
  CONSTRAINT `fk_rem_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. MANDAL REQUESTS TABLE (Financial & Administrative Requests)
DROP TABLE IF EXISTS `mandal_requests`;
CREATE TABLE `mandal_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `request_type` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `event_date` DATE NOT NULL,
  `is_hidden` TINYINT(1) NOT NULL DEFAULT 0,
  `proof_file` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rejection_reason` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_by` INT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_requests` (`user_id`, `status`),
  KEY `idx_visibility` (`status`, `is_hidden`),
  KEY `idx_req_type_cat` (`request_type`, `category`),
  CONSTRAINT `fk_req_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_req_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. IN-APP NOTIFICATIONS TABLE
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`, `is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. AUDIT LOGS TABLE
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `actor_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` VARCHAR(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details_json` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. SYSTEM ERROR LOGS TABLE
DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE `system_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `level` ENUM('error', 'warning', 'info') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'error',
  `message` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
  `context_json` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_syslog_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. MANDAL SETTINGS (Branding & Contact Information)
DROP TABLE IF EXISTS `mandal_settings`;
CREATE TABLE `mandal_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. UTSAV EVENTS (Multi-Year Festival Engine)
DROP TABLE IF EXISTS `utsav_events`;
CREATE TABLE `utsav_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year` YEAR NOT NULL,
  `theme` VARCHAR(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ganesh_arrival_date` DATE DEFAULT NULL,
  `ganesh_visarjan_date` DATE DEFAULT NULL,
  `murtikar_name` VARCHAR(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `murtikar_info` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `murtikar_photo` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_utsav_year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. KARYAKARTAS (Mandal Committee Members)
DROP TABLE IF EXISTS `karyakartas`;
CREATE TABLE `karyakartas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utsav_year` YEAR NOT NULL DEFAULT '2026',
  `full_name` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo_path` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` VARCHAR(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_email` TINYINT(1) NOT NULL DEFAULT 1,
  `show_whatsapp` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kk_visibility` (`is_visible`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. EVENT MEMORIES (Photo & Video Gallery)
DROP TABLE IF EXISTS `event_memories`;
CREATE TABLE `event_memories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utsav_year` YEAR NOT NULL,
  `title` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_type` ENUM('photo','video') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'photo',
  `file_path` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_url` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mem_year_visible` (`utsav_year`, `is_visible`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. EVENT ROUTES (Aagman & Visarjan Procession Paths)
DROP TABLE IF EXISTS `event_routes`;
CREATE TABLE `event_routes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utsav_year` YEAR NOT NULL,
  `route_type` ENUM('aagman','visarjan') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `map_embed_url` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_pdf_path` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_route_year_type` (`utsav_year`, `route_type`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Active Festival Year Bootstrap
INSERT INTO `utsav_events` (`year`, `theme`, `ganesh_arrival_date`, `ganesh_visarjan_date`, `is_active`)
VALUES (2026, 'Sudarshan Ganesh Utsav 2026', '2026-09-14', '2026-09-24', 1)
ON DUPLICATE KEY UPDATE `is_active` = 1;

SET FOREIGN_KEY_CHECKS = 1;
