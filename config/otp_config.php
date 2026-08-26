<?php
/**
 * Sudarshan Yuvak Mandal - Real OTP & SMTP Service Configuration
 * Location: Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';

// Service Mode: 'production' (Sends real emails via Gmail/SMTP) or 'development' (Dev Mode with preview)
define('OTP_SERVICE_MODE', (string)Env::get('OTP_SERVICE_MODE', 'production'));

// Real Gmail / Enterprise SMTP Server Settings
define('SMTP_HOST', (string)Env::get('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int)Env::get('SMTP_PORT', 587));
define('SMTP_ENCRYPTION', (string)Env::get('SMTP_ENCRYPTION', 'tls'));
define('SMTP_USERNAME', (string)Env::get('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', (string)Env::get('SMTP_PASSWORD', ''));
define('SMTP_FROM_EMAIL', (string)Env::get('SMTP_FROM_EMAIL', ''));
define('SMTP_FROM_NAME', (string)Env::get('SMTP_FROM_NAME', 'Sudarshan Yuvak Mandal'));

// OTP & Security Policy Constants
define('OTP_EXPIRY_MINUTES', (int)Env::get('OTP_EXPIRY_MINUTES', 5));
define('OTP_RESEND_COOLDOWN_SECONDS', (int)Env::get('OTP_RESEND_COOLDOWN_SECONDS', 60));
define('MAX_OTP_ATTEMPTS', (int)Env::get('MAX_OTP_ATTEMPTS', 3));
define('MAX_OTP_RESENDS_PER_WINDOW', (int)Env::get('MAX_OTP_RESENDS_PER_WINDOW', 3));
define('RESEND_WINDOW_MINUTES', (int)Env::get('RESEND_WINDOW_MINUTES', 15));
define('MAX_FAILED_LOGINS', (int)Env::get('MAX_FAILED_LOGINS', 5));
define('LOGIN_LOCKOUT_MINUTES', (int)Env::get('LOGIN_LOCKOUT_MINUTES', 15));
