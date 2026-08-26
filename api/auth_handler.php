<?php
/**
 * Sudarshan Yuvak Mandal - Enterprise Authentication API Endpoint
 * Handles Signup, Login, Hashed OTP Generation, Verification, Resend & Security Rate Limits
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/smtp_mailer.php';

ob_start();

// Helper response function with production error suppression
function sendJsonResponse(string $status, string $message, array $data = [], int $httpCode = 200): void {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($httpCode);
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message,
        'timestamp' => time()
    ], $data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Enterprise Real Email Delivery Service (Strict Production Delivery)
function deliverEmailOTP(string $toEmail, string $otpCode, string $purposeName, string $userName = 'Member'): array {
    $smtpResult = SmtpMailer::sendOTPEmail($toEmail, $otpCode, $purposeName, $userName);
    
    return [
        'mail_sent' => $smtpResult['success'],
        'mode' => $smtpResult['mode'] ?? 'real_smtp'
    ];
}

// Rate Limiting Helper
function checkRateLimit(PDO $pdo, string $ip, string $identifier, string $action, int $maxAttempts, int $lockoutMinutes): array {
    $stmt = $pdo->prepare("SELECT attempts, locked_until FROM rate_limits WHERE ip_address = ? AND identifier = ? AND action = ?");
    $stmt->execute([$ip, $identifier, $action]);
    $record = $stmt->fetch();

    if ($record) {
        if ($record['locked_until'] !== null) {
            $lockTime = strtotime($record['locked_until']);
            if (time() < $lockTime) {
                $diffMinutes = ceil(($lockTime - time()) / 60);
                return [
                    'allowed' => false,
                    'message' => "Max limit reached. Access temporarily locked for {$diffMinutes} minute(s)."
                ];
            } else {
                // Lock expired, reset attempts
                $resetStmt = $pdo->prepare("UPDATE rate_limits SET attempts = 0, locked_until = NULL WHERE ip_address = ? AND identifier = ? AND action = ?");
                $resetStmt->execute([$ip, $identifier, $action]);
            }
        }
        if ($record['attempts'] >= $maxAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', strtotime("+{$lockoutMinutes} minutes"));
            $lockStmt = $pdo->prepare("UPDATE rate_limits SET locked_until = ? WHERE ip_address = ? AND identifier = ? AND action = ?");
            $lockStmt->execute([$lockedUntil, $ip, $identifier, $action]);
            return [
                'allowed' => false,
                'message' => "Maximum attempt limit reached. Access locked for {$lockoutMinutes} minutes."
            ];
        }
    }
    return ['allowed' => true];
}

function incrementRateLimit(PDO $pdo, string $ip, string $identifier, string $action): void {
    $stmt = $pdo->prepare("
        INSERT INTO rate_limits (ip_address, identifier, action, attempts, updated_at) 
        VALUES (?, ?, ?, 1, NOW()) 
        ON DUPLICATE KEY UPDATE attempts = attempts + 1, updated_at = NOW()
    ");
    $stmt->execute([$ip, $identifier, $action]);
}

function resetRateLimit(PDO $pdo, string $ip, string $identifier, string $action): void {
    $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND identifier = ? AND action = ?");
    $stmt->execute([$ip, $identifier, $action]);
}

// Process API Request
try {
    $pdo = Database::getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method !== 'POST') {
        sendJsonResponse('error', 'Invalid HTTP Method. POST required.', [], 405);
    }

    $action = Security::sanitizeInput($_POST['action'] ?? '');
    $clientIP = Security::getClientIP();

    // Verify CSRF Token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($csrfToken)) {
        sendJsonResponse('error', 'Security token invalid or expired. Please refresh the page.', [], 403);
    }

    // CAPTCHA Verification Helper
    $verifyCaptcha = function() {
        $userCaptcha = strtoupper(trim($_POST['captcha_input'] ?? ''));
        $sessCaptcha = $_SESSION['captcha_code'] ?? '';
        if (empty($userCaptcha) || empty($sessCaptcha) || !hash_equals($sessCaptcha, $userCaptcha)) {
            unset($_SESSION['captcha_code']);
            sendJsonResponse('error', 'Invalid CAPTCHA code. Please enter the characters shown in the image.', [], 400);
        }
        unset($_SESSION['captcha_code']); // Single-use captcha
    };

    switch ($action) {

        // -------------------------------------------------------------
        // 1. SIGNUP INITIALIZATION (Validates details -> Hashed OTP)
        // -------------------------------------------------------------
        case 'signup_init':
            $verifyCaptcha();

            $fullName = Security::sanitizeInput($_POST['full_name'] ?? '');
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $phone = Security::sanitizeInput($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!$fullName || strlen($fullName) < 3) {
                sendJsonResponse('error', 'Please enter your full name (minimum 3 characters).', [], 400);
            }
            if (!$email) {
                sendJsonResponse('error', 'Please enter a valid email address.', [], 400);
            }
            if (!preg_match('/^[0-9]{10}$/', $phone)) {
                sendJsonResponse('error', 'Please enter a valid 10-digit mobile number.', [], 400);
            }
            if (strlen($password) < 8) {
                sendJsonResponse('error', 'Password must be at least 8 characters long.', [], 400);
            }
            if ($password !== $confirmPassword) {
                sendJsonResponse('error', 'Passwords do not match.', [], 400);
            }

            // Check if user (email or phone) already exists & is verified or locked
            $stmt = $pdo->prepare("SELECT email, phone, is_verified, locked_until FROM users WHERE email = ? OR phone = ?");
            $stmt->execute([$email, $phone]);
            $existingUsers = $stmt->fetchAll();
            foreach ($existingUsers as $exUser) {
                if ($exUser['locked_until'] !== null && strtotime($exUser['locked_until']) > time()) {
                    $diffMins = ceil((strtotime($exUser['locked_until']) - time()) / 60);
                    sendJsonResponse('error', "Security Lockout: Account or mobile number is locked. Please try again after {$diffMins} minute(s).", [], 403);
                }
                if ((int)$exUser['is_verified'] === 1) {
                    if ($exUser['email'] === $email) {
                        sendJsonResponse('error', 'Email address is already registered. Please proceed to Login.', [], 400);
                    }
                    if ($exUser['phone'] === $phone) {
                        sendJsonResponse('error', 'Mobile number (' . htmlspecialchars($phone) . ') is already registered.', [], 400);
                    }
                }
            }

            // Rate Limit check for generating OTP
            $rateCheck = checkRateLimit($pdo, $clientIP, $email, 'signup_otp', MAX_OTP_RESENDS_PER_WINDOW, RESEND_WINDOW_MINUTES);
            if (!$rateCheck['allowed']) {
                sendJsonResponse('error', $rateCheck['message'], [], 429);
            }

            // Invalidate any old unused signup OTPs for this email
            $pdo->prepare("UPDATE otp_tokens SET is_used = 1 WHERE email = ? AND purpose = 'signup' AND is_used = 0")->execute([$email]);

            // Generate 6-digit numeric OTP & HASH IT before DB storage
            $otpCode = (string)random_int(100000, 999999);
            $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', strtotime("+" . OTP_EXPIRY_MINUTES . " minutes"));
            $now = date('Y-m-d H:i:s');

            $payloadJson = json_encode([
                'full_name' => $fullName,
                'phone' => $phone,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT)
            ]);

            // Insert new secure OTP record
            $stmt = $pdo->prepare("
                INSERT INTO otp_tokens (user_id, email, otp_hash, purpose, attempts_left, expires_at, resend_count, last_resend_at, is_used, payload_json)
                VALUES (NULL, ?, ?, 'signup', ?, ?, 1, ?, 0, ?)
                ON DUPLICATE KEY UPDATE 
                    otp_hash = VALUES(otp_hash),
                    attempts_left = VALUES(attempts_left),
                    expires_at = VALUES(expires_at),
                    resend_count = resend_count + 1,
                    last_resend_at = VALUES(last_resend_at),
                    is_used = 0,
                    payload_json = VALUES(payload_json)
            ");
            $stmt->execute([$email, $otpHash, MAX_OTP_ATTEMPTS, $expiresAt, $now, $payloadJson]);

            incrementRateLimit($pdo, $clientIP, $email, 'signup_otp');

            // Dispatch Email
            $delivery = deliverEmailOTP($email, $otpCode, 'Registration', $fullName);

            Security::logAudit($pdo, null, null, 'SIGNUP_INIT', ['email' => $email, 'ip' => $clientIP]);

            sendJsonResponse('success', 'Verification OTP sent to ' . htmlspecialchars($email), [
                'email' => $email,
                'purpose' => 'signup',
                'resend_cooldown' => OTP_RESEND_COOLDOWN_SECONDS,
                'expires_in' => OTP_EXPIRY_MINUTES * 60,
                'demo_otp' => $delivery['demo_otp_preview'] ?? null
            ]);
            break;

        // -------------------------------------------------------------
        // 2. SIGNUP VERIFY (Validates OTP -> Account Pending Approval)
        // -------------------------------------------------------------
        case 'signup_verify':
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $userOTP = trim($_POST['otp_code'] ?? '');

            if (!$email || strlen($userOTP) !== 6) {
                sendJsonResponse('error', 'Please enter the complete 6-digit OTP.', [], 400);
            }

            $stmt = $pdo->prepare("SELECT * FROM otp_tokens WHERE email = ? AND purpose = 'signup' AND is_used = 0 ORDER BY id DESC LIMIT 1");
            $stmt->execute([$email]);
            $token = $stmt->fetch();

            if (!$token) {
                sendJsonResponse('error', 'No active signup request found. Please restart registration.', [], 404);
            }

            // Check expiration
            if (strtotime($token['expires_at']) < time()) {
                sendJsonResponse('error', 'OTP has expired (5-minute limit reached). Please click Resend OTP.', [], 400);
            }

            // Check remaining attempts
            if ($token['attempts_left'] <= 0) {
                sendJsonResponse('error', 'Maximum invalid OTP attempts reached. Please request a new OTP.', [], 429);
            }

            // Validate Hashed OTP
            if (!password_verify($userOTP, $token['otp_hash'])) {
                $newAttempts = $token['attempts_left'] - 1;
                $pdo->prepare("UPDATE otp_tokens SET attempts_left = ? WHERE id = ?")->execute([$newAttempts, $token['id']]);

                if ($newAttempts <= 0) {
                    $lockoutUntil = date('Y-m-d H:i:s', strtotime("+" . LOGIN_LOCKOUT_MINUTES . " minutes"));
                    sendJsonResponse('error', "Security Lockout: " . MAX_OTP_ATTEMPTS . " invalid OTP attempts reached. Locked for " . LOGIN_LOCKOUT_MINUTES . " minutes.", [
                        'status' => 'lockout',
                        'attempts_left' => 0
                    ], 429);
                } else {
                    sendJsonResponse('error', "Invalid OTP code. Attempts remaining: {$newAttempts}", [
                        'attempts_left' => $newAttempts
                    ], 400);
                }
            }

            // Success! Create User with role='member', membership_status='pending'
            $payload = json_decode($token['payload_json'] ?? '{}', true);
            if (empty($payload)) {
                sendJsonResponse('error', 'Registration payload error. Please restart registration.', [], 500);
            }

            $pdo->beginTransaction();
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $checkStmt->execute([$email]);
                $user = $checkStmt->fetch();

                if ($user) {
                    $userId = (int)$user['id'];
                    $updateStmt = $pdo->prepare("
                        UPDATE users 
                        SET full_name = ?, phone = ?, password_hash = ?, is_verified = 1, role = 'member', membership_status = 'pending', failed_logins = 0 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$payload['full_name'], $payload['phone'], $payload['password_hash'], $userId]);
                } else {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO users (full_name, email, phone, password_hash, is_verified, role, membership_status) 
                        VALUES (?, ?, ?, ?, 1, 'member', 'pending')
                    ");
                    $insertStmt->execute([$payload['full_name'], $email, $payload['phone'], $payload['password_hash']]);
                    $userId = (int)$pdo->lastInsertId();
                }

                // Mark OTP used
                $pdo->prepare("UPDATE otp_tokens SET is_used = 1, user_id = ? WHERE id = ?")->execute([$userId, $token['id']]);

                // Reset rate limits
                resetRateLimit($pdo, $clientIP, $email, 'signup_otp');

                $pdo->commit();

                Security::logAudit($pdo, $userId, null, 'MEMBER_REGISTERED_PENDING', ['email' => $email]);

                sendJsonResponse('success', 'Email verified successfully! Your application is now pending approval by the Mandal Admin.', [
                    'redirect' => 'login.php?registered=1'
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Signup Verify Exception: " . $e->getMessage());
                sendJsonResponse('error', 'Registration failed due to a database constraint. Please check your details.', [], 400);
            }
            break;

        // -------------------------------------------------------------
        // 3. LOGIN INITIALIZATION (Validates Credentials & Membership Status)
        // -------------------------------------------------------------
        case 'login_init':
            $verifyCaptcha();

            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $password = $_POST['password'] ?? '';

            if (!$email || empty($password)) {
                sendJsonResponse('error', 'Please enter your email and password.', [], 400);
            }

            // Check failed login rate limit
            $rateCheck = checkRateLimit($pdo, $clientIP, $email, 'failed_login', MAX_FAILED_LOGINS, LOGIN_LOCKOUT_MINUTES);
            if (!$rateCheck['allowed']) {
                sendJsonResponse('error', $rateCheck['message'], [], 429);
            }

            // Find user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                incrementRateLimit($pdo, $clientIP, $email, 'failed_login');
                sendJsonResponse('error', 'Invalid email address or password.', [], 401);
            }

            if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
                $diffMins = ceil((strtotime($user['locked_until']) - time()) / 60);
                sendJsonResponse('error', "Account is locked due to security policy. Please try again after {$diffMins} minute(s).", [], 403);
            }

            // CHECK MEMBERSHIP STATUS & ROLE
            if ((int)$user['is_verified'] !== 1) {
                sendJsonResponse('error', 'Email address is not verified. Please complete signup verification.', [], 403);
            }

            if ($user['role'] === 'member') {
                switch ($user['membership_status']) {
                    case 'pending':
                        sendJsonResponse('error', 'Your registration is pending approval by the Mandal Admin. You will be able to log in once approved.', [], 403);
                        break;
                    case 'rejected':
                        $reason = !empty($user['rejection_reason']) ? " Reason: " . htmlspecialchars($user['rejection_reason']) : "";
                        sendJsonResponse('error', 'Your membership application was rejected by the Mandal Admin.' . $reason, [], 403);
                        break;
                    case 'suspended':
                        sendJsonResponse('error', 'Your account has been suspended by the Mandal Admin. Please contact the Mandal office.', [], 403);
                        break;
                    case 'inactive':
                        sendJsonResponse('error', 'Your account is currently inactive.', [], 403);
                        break;
                    case 'approved':
                        // Approved member -> Proceed to OTP 2FA
                        break;
                }
            }

            // Invalidate any existing unused login OTPs for this user
            $pdo->prepare("UPDATE otp_tokens SET is_used = 1 WHERE email = ? AND purpose = 'login' AND is_used = 0")->execute([$email]);

            // Credentials & Status valid -> Generate 2FA Login Hashed OTP
            $otpCode = (string)random_int(100000, 999999);
            $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', strtotime("+" . OTP_EXPIRY_MINUTES . " minutes"));
            $now = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                INSERT INTO otp_tokens (user_id, email, otp_hash, purpose, attempts_left, expires_at, resend_count, last_resend_at, is_used)
                VALUES (?, ?, ?, 'login', ?, ?, 1, ?, 0)
                ON DUPLICATE KEY UPDATE 
                    otp_hash = VALUES(otp_hash),
                    attempts_left = VALUES(attempts_left),
                    expires_at = VALUES(expires_at),
                    resend_count = resend_count + 1,
                    last_resend_at = VALUES(last_resend_at),
                    is_used = 0
            ");
            $stmt->execute([$user['id'], $email, $otpHash, MAX_OTP_ATTEMPTS, $expiresAt, $now]);

            $delivery = deliverEmailOTP($email, $otpCode, 'Login 2FA', $user['full_name']);

            Security::logAudit($pdo, $user['id'], null, 'LOGIN_INIT', ['email' => $email]);

            sendJsonResponse('success', 'Security OTP sent to ' . htmlspecialchars($email), [
                'email' => $email,
                'purpose' => 'login',
                'resend_cooldown' => OTP_RESEND_COOLDOWN_SECONDS,
                'expires_in' => OTP_EXPIRY_MINUTES * 60,
                'demo_otp' => $delivery['demo_otp_preview'] ?? null
            ]);
            break;

        // -------------------------------------------------------------
        // 4. LOGIN VERIFY (Validates 2FA OTP -> Logged In)
        // -------------------------------------------------------------
        case 'login_verify':
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $userOTP = trim($_POST['otp_code'] ?? '');

            if (!$email || strlen($userOTP) !== 6) {
                sendJsonResponse('error', 'Please enter the complete 6-digit OTP.', [], 400);
            }

            $stmt = $pdo->prepare("
                SELECT o.*, u.full_name, u.email AS user_email, u.role, u.membership_status, u.auth_provider, u.id as user_id_val 
                FROM otp_tokens o 
                JOIN users u ON o.email = u.email 
                WHERE o.email = ? AND o.purpose = 'login' AND o.is_used = 0 
                ORDER BY o.id DESC LIMIT 1
            ");
            $stmt->execute([$email]);
            $token = $stmt->fetch();

            if (!$token) {
                sendJsonResponse('error', 'No active login request found. Please login again.', [], 404);
            }

            if (strtotime($token['expires_at']) < time()) {
                sendJsonResponse('error', 'OTP has expired (5-minute limit reached). Please request a new OTP.', [], 400);
            }

            if ($token['attempts_left'] <= 0) {
                sendJsonResponse('error', 'Maximum invalid OTP attempts reached. Please request a new OTP.', [], 429);
            }

            if (!password_verify($userOTP, $token['otp_hash'])) {
                $newAttempts = $token['attempts_left'] - 1;
                $pdo->prepare("UPDATE otp_tokens SET attempts_left = ? WHERE id = ?")->execute([$newAttempts, $token['id']]);

                if ($newAttempts <= 0) {
                    $lockoutUntil = date('Y-m-d H:i:s', strtotime("+" . LOGIN_LOCKOUT_MINUTES . " minutes"));
                    $pdo->prepare("UPDATE users SET locked_until = ? WHERE id = ?")->execute([$lockoutUntil, $token['user_id_val']]);
                    sendJsonResponse('error', "Security Lockout: 3 invalid OTP attempts reached. Locked for 15 minutes.", [
                        'status' => 'lockout',
                        'attempts_left' => 0
                    ], 429);
                } else {
                    sendJsonResponse('error', "Invalid OTP code. Attempts remaining: {$newAttempts}", [
                        'attempts_left' => $newAttempts
                    ], 400);
                }
            }

            // Success! Single-use OTP enforcement
            $pdo->prepare("UPDATE otp_tokens SET is_used = 1 WHERE id = ?")->execute([$token['id']]);
            $pdo->prepare("UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?")->execute([$token['user_id_val']]);
            resetRateLimit($pdo, $clientIP, $email, 'failed_login');

            // Secure Session Regeneration — populate ALL required session fields
            Security::regenerateSession();
            $_SESSION['user_id']           = (int)$token['user_id_val'];
            $_SESSION['user_name']         = $token['full_name'];
            $_SESSION['user_email']        = $token['user_email'];
            $_SESSION['user_role']         = $token['role'];
            $_SESSION['membership_status'] = $token['membership_status'];
            $_SESSION['auth_provider']     = $token['auth_provider'] ?? 'local';
            $_SESSION['login_time']        = time();

            $rememberMe = (int)($_POST['remember_me'] ?? 0) === 1;
            if ($rememberMe) {
                RememberMe::createToken($pdo, (int)$token['user_id_val']);
            }

            $targetRedirect = ($token['role'] === 'admin') ? 'admin_dashboard.php' : 'dashboard.php';

            Security::logAudit($pdo, (int)$token['user_id_val'], null, 'LOGIN_SUCCESS', [
                'role'        => $token['role'],
                'remember_me' => $rememberMe
            ]);

            sendJsonResponse('success', 'Authentication successful!', [
                'redirect' => $targetRedirect
            ]);
            break;

        // -------------------------------------------------------------
        // 5. RESEND OTP (Handles cooldown, window limits & OTP invalidation)
        // -------------------------------------------------------------
        case 'otp_resend':
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $purpose = Security::sanitizeInput($_POST['purpose'] ?? 'signup');

            if (!$email) {
                sendJsonResponse('error', 'Valid email required.', [], 400);
            }

            // Check rate limits for resending
            $rateCheck = checkRateLimit($pdo, $clientIP, $email, $purpose . '_otp', MAX_OTP_RESENDS_PER_WINDOW, RESEND_WINDOW_MINUTES);
            if (!$rateCheck['allowed']) {
                sendJsonResponse('error', $rateCheck['message'], [], 429);
            }

            $stmt = $pdo->prepare("SELECT * FROM otp_tokens WHERE email = ? AND purpose = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$email, $purpose]);
            $lastToken = $stmt->fetch();

            if ($lastToken) {
                $lastResend = strtotime($lastToken['last_resend_at']);
                $cooldownElapsed = time() - $lastResend;
                if ($cooldownElapsed < OTP_RESEND_COOLDOWN_SECONDS) {
                    $wait = OTP_RESEND_COOLDOWN_SECONDS - $cooldownElapsed;
                    sendJsonResponse('error', "Please wait {$wait} second(s) before requesting another OTP.", [], 429);
                }
            }

            // Invalidate old OTP
            $pdo->prepare("UPDATE otp_tokens SET is_used = 1 WHERE email = ? AND purpose = ? AND is_used = 0")->execute([$email, $purpose]);

            // Resolve user_id if this is a login or reset resend (not signup)
            $resendUserId = null;
            if ($purpose === 'login' || $purpose === 'reset') {
                $userIdStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $userIdStmt->execute([$email]);
                $userRow = $userIdStmt->fetch();
                $resendUserId = $userRow ? (int)$userRow['id'] : null;
            }

            // Generate fresh hashed OTP
            $newOTP = (string)random_int(100000, 999999);
            $newOTPHash = password_hash($newOTP, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', strtotime("+" . OTP_EXPIRY_MINUTES . " minutes"));
            $now = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                INSERT INTO otp_tokens (user_id, email, otp_hash, purpose, attempts_left, expires_at, resend_count, last_resend_at, is_used)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, 0)
                ON DUPLICATE KEY UPDATE 
                    user_id = COALESCE(VALUES(user_id), user_id),
                    otp_hash = VALUES(otp_hash),
                    attempts_left = VALUES(attempts_left),
                    expires_at = VALUES(expires_at),
                    resend_count = resend_count + 1,
                    last_resend_at = VALUES(last_resend_at),
                    is_used = 0
            ");
            $stmt->execute([$resendUserId, $email, $newOTPHash, $purpose, MAX_OTP_ATTEMPTS, $expiresAt, $now]);

            incrementRateLimit($pdo, $clientIP, $email, $purpose . '_otp');

            $delivery = deliverEmailOTP($email, $newOTP, ucfirst($purpose), $_SESSION['user_name'] ?? 'Member');

            Security::logAudit($pdo, $_SESSION['user_id'] ?? null, null, 'OTP_RESEND', ['email' => $email, 'purpose' => $purpose]);

            sendJsonResponse('success', 'A new OTP has been sent to ' . htmlspecialchars($email), [
                'email' => $email,
                'purpose' => $purpose,
                'resend_cooldown' => OTP_RESEND_COOLDOWN_SECONDS,
                'expires_in' => OTP_EXPIRY_MINUTES * 60,
                'demo_otp' => $delivery['demo_otp_preview'] ?? null
            ]);
            break;

        // -------------------------------------------------------------
        // 6. FORGOT PASSWORD INITIALIZATION
        // -------------------------------------------------------------
        case 'forgot_init':
            $verifyCaptcha();

            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            if (!$email) {
                sendJsonResponse('error', 'Please enter a valid email address.', [], 400);
            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                sendJsonResponse('error', 'No account found with ' . htmlspecialchars($email) . '. Please check your email address or register a new account.', [], 404);
            }
            if ((int)$user['is_verified'] !== 1) {
                sendJsonResponse('error', 'Account associated with ' . htmlspecialchars($email) . ' is not verified.', [], 403);
            }

            if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
                $diffMins = ceil((strtotime($user['locked_until']) - time()) / 60);
                sendJsonResponse('error', "Account is temporarily locked. Please try password reset after {$diffMins} minute(s).", [], 403);
            }

            // Rate Limit check for reset OTP
            $rateCheck = checkRateLimit($pdo, $clientIP, $email, 'reset_otp', MAX_OTP_RESENDS_PER_WINDOW, RESEND_WINDOW_MINUTES);
            if (!$rateCheck['allowed']) {
                sendJsonResponse('error', $rateCheck['message'], [], 429);
            }

            // Invalidate old reset OTP
            $pdo->prepare("UPDATE otp_tokens SET is_used = 1 WHERE email = ? AND purpose = 'reset' AND is_used = 0")->execute([$email]);

            $otpCode = (string)random_int(100000, 999999);
            $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', strtotime("+" . OTP_EXPIRY_MINUTES . " minutes"));
            $now = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                INSERT INTO otp_tokens (user_id, email, otp_hash, purpose, attempts_left, expires_at, resend_count, last_resend_at, is_used)
                VALUES (?, ?, ?, 'reset', ?, ?, 1, ?, 0)
                ON DUPLICATE KEY UPDATE 
                    otp_hash = VALUES(otp_hash),
                    attempts_left = VALUES(attempts_left),
                    expires_at = VALUES(expires_at),
                    resend_count = resend_count + 1,
                    last_resend_at = VALUES(last_resend_at),
                    is_used = 0
            ");
            $stmt->execute([$user['id'], $email, $otpHash, MAX_OTP_ATTEMPTS, $expiresAt, $now]);

            incrementRateLimit($pdo, $clientIP, $email, 'reset_otp');

            $delivery = deliverEmailOTP($email, $otpCode, 'Password Reset', $user['full_name']);

            Security::logAudit($pdo, $user['id'], null, 'FORGOT_INIT', ['email' => $email]);

            sendJsonResponse('success', 'Password reset OTP sent to ' . htmlspecialchars($email), [
                'email' => $email,
                'purpose' => 'reset',
                'resend_cooldown' => OTP_RESEND_COOLDOWN_SECONDS,
                'expires_in' => OTP_EXPIRY_MINUTES * 60,
                'demo_otp' => $delivery['demo_otp_preview'] ?? null
            ]);
            break;

        // -------------------------------------------------------------
        // 7. FORGOT PASSWORD VERIFY (OTP + New Password -> Reset Success)
        // -------------------------------------------------------------
        case 'forgot_verify':
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $userOTP = trim($_POST['otp_code'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';
            $confirmNewPassword = $_POST['confirm_new_password'] ?? '';

            if (!$email || strlen($userOTP) !== 6) {
                sendJsonResponse('error', 'Please enter the complete 6-digit OTP.', [], 400);
            }
            if (strlen($newPassword) < 8) {
                sendJsonResponse('error', 'New password must be at least 8 characters long.', [], 400);
            }
            if ($newPassword !== $confirmNewPassword) {
                sendJsonResponse('error', 'New passwords do not match.', [], 400);
            }

            $stmt = $pdo->prepare("SELECT o.*, u.full_name, u.role, u.membership_status, u.id as user_id_val FROM otp_tokens o JOIN users u ON o.email = u.email WHERE o.email = ? AND o.purpose = 'reset' AND o.is_used = 0 ORDER BY o.id DESC LIMIT 1");
            $stmt->execute([$email]);
            $token = $stmt->fetch();

            if (!$token) {
                sendJsonResponse('error', 'No active password reset request found. Please request a new OTP.', [], 404);
            }

            if (strtotime($token['expires_at']) < time()) {
                sendJsonResponse('error', 'Reset OTP has expired (5-minute limit). Please request a new OTP.', [], 400);
            }

            if ($token['attempts_left'] <= 0) {
                sendJsonResponse('error', 'Maximum invalid OTP attempts reached.', [], 429);
            }

            if (!password_verify($userOTP, $token['otp_hash'])) {
                $newAttempts = $token['attempts_left'] - 1;
                $pdo->prepare("UPDATE otp_tokens SET attempts_left = ? WHERE id = ?")->execute([$newAttempts, $token['id']]);

                if ($newAttempts <= 0) {
                    $lockoutUntil = date('Y-m-d H:i:s', strtotime("+" . LOGIN_LOCKOUT_MINUTES . " minutes"));
                    $pdo->prepare("UPDATE users SET locked_until = ? WHERE id = ?")->execute([$lockoutUntil, $token['user_id_val']]);
                    sendJsonResponse('error', 'Security Lockout: 3 invalid OTP attempts reached. Account locked for 15 minutes.', [
                        'status' => 'lockout',
                        'attempts_left' => 0
                    ], 429);
                } else {
                    sendJsonResponse('error', "Invalid OTP code. Attempts remaining: {$newAttempts}", [
                        'attempts_left' => $newAttempts
                    ], 400);
                }
            }

            // OTP valid -> Update Password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET password_hash = ?, failed_logins = 0, locked_until = NULL WHERE id = ?")
                    ->execute([$newPasswordHash, $token['user_id_val']]);
                
                $pdo->prepare("UPDATE otp_tokens SET is_used = 1 WHERE id = ?")->execute([$token['id']]);
                resetRateLimit($pdo, $clientIP, $email, 'failed_login');
                resetRateLimit($pdo, $clientIP, $email, 'reset_otp');
                
                $pdo->commit();

                Security::logAudit($pdo, (int)$token['user_id_val'], null, 'PASSWORD_RESET_SUCCESS', []);

                sendJsonResponse('success', 'Password reset successfully! Please login with your new password.', [
                    'redirect' => 'login.php?reset_success=1'
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Password Reset Exception: " . $e->getMessage());
                sendJsonResponse('error', 'Failed to update password due to a system error.', [], 500);
            }
            break;

        default:
            sendJsonResponse('error', 'Unknown action handler.', [], 400);
    }

} catch (Exception $e) {
    error_log("Auth API Exception: " . $e->getMessage());
    sendJsonResponse('error', 'Internal Server Error. Action could not be processed.', [], 500);
}
