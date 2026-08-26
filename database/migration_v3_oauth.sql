-- Sudarshan Yuvak Mandal - Safe OAuth 2.0 Migration v3.0
-- Adds Google & GitHub OAuth provider columns & indices to users table

USE `sudarshan_yuvak_mandal`;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `users`
  MODIFY COLUMN `password_hash` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
  MODIFY COLUMN `phone` VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL,
  ADD COLUMN IF NOT EXISTS `google_id` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `password_hash`,
  ADD COLUMN IF NOT EXISTS `github_id` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `google_id`,
  ADD COLUMN IF NOT EXISTS `avatar_url` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `github_id`,
  ADD COLUMN IF NOT EXISTS `auth_provider` ENUM('local', 'google', 'github') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local' AFTER `avatar_url`;

-- Add unique indices for Google and GitHub IDs
ALTER TABLE `users` ADD UNIQUE INDEX IF NOT EXISTS `idx_users_google_id` (`google_id`);
ALTER TABLE `users` ADD UNIQUE INDEX IF NOT EXISTS `idx_users_github_id` (`github_id`);

SET FOREIGN_KEY_CHECKS = 1;
