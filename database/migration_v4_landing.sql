-- Sudarshan Yuvak Mandal - Landing Page Tables Migration v4.0
-- Public-facing content: settings, events, karyakartas, memories, routes
-- Run AFTER schema.sql (v5.0). Safe to run multiple times.

USE `sudarshan_yuvak_mandal`;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. MANDAL SETTINGS (key-value global config store)
CREATE TABLE IF NOT EXISTS `mandal_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. UTSAV EVENTS (one row per Ganesh Utsav year)
CREATE TABLE IF NOT EXISTS `utsav_events` (
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

-- 3. KARYAKARTAS (committee members, linked to utsav year)
CREATE TABLE IF NOT EXISTS `karyakartas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utsav_year` YEAR NOT NULL,
  `full_name` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo_path` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_phone` TINYINT(1) NOT NULL DEFAULT 1,
  `show_whatsapp` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kk_year_visible` (`utsav_year`, `is_visible`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. EVENT MEMORIES (photo/video gallery per year)
CREATE TABLE IF NOT EXISTS `event_memories` (
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

-- 5. AAGMAN & VISARJAN ROUTES (per year)
CREATE TABLE IF NOT EXISTS `event_routes` (
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

SET FOREIGN_KEY_CHECKS = 1;
