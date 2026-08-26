-- Sudarshan Yuvak Mandal - Safe Migration to v2.0
-- Converts existing tables to support Mandal RBAC, Approval Flow, Hashed OTPs & Audit Logs

USE `sudarshan_yuvak_mandal`;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. ALTER USERS TABLE
ALTER TABLE `users` 
  ADD COLUMN IF NOT EXISTS `role` ENUM('admin', 'member') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member' AFTER `is_verified`,
  ADD COLUMN IF NOT EXISTS `membership_status` ENUM('pending', 'approved', 'rejected', 'suspended', 'inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' AFTER `role`,
  ADD COLUMN IF NOT EXISTS `approved_at` DATETIME DEFAULT NULL AFTER `membership_status`,
  ADD COLUMN IF NOT EXISTS `approved_by` INT UNSIGNED DEFAULT NULL AFTER `approved_at`,
  ADD COLUMN IF NOT EXISTS `rejection_reason` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `approved_by`;

-- Add FK for approved_by if not exists
ALTER TABLE `users` ADD CONSTRAINT `fk_user_approved_by` FOREIGN KEY IF NOT EXISTS (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Add index on role & status
ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_role_status` (`role`, `membership_status`);

-- 2. ALTER OTP_TOKENS TABLE
ALTER TABLE `otp_tokens` CHANGE COLUMN `otp_code` `otp_hash` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL;

-- 3. CREATE AUDIT LOGS TABLE IF NOT EXISTS
CREATE TABLE IF NOT EXISTS `audit_logs` (
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

SET FOREIGN_KEY_CHECKS = 1;
