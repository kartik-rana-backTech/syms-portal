<?php
/**
 * Sudarshan Yuvak Mandal - Enterprise OAuth 2.0 API Handler
 * Handles Google & GitHub Single Sign-On (SSO) Authentication, User Auto-Registration & Session Creation
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$action = Security::sanitizeInput($_GET['action'] ?? $_POST['action'] ?? '');

// Dynamic Base URL Resolver (prioritizes .env, auto-detects current host origin)
$envBaseUrl = Env::get('APP_BASE_URL', '');
if (!empty($envBaseUrl) && !str_contains((string)$envBaseUrl, 'localhost')) {
    $appBaseUrl = rtrim((string)$envBaseUrl, '/');
} else {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
             (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
    $appBaseUrl = rtrim("{$proto}://{$host}{$scriptDir}", '/');
}

// Helper for HTTP requests (cURL fallback with safe timeouts)
function httpPostJson(string $url, array $postFields, array $headers = []): ?array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    $response = curl_exec($ch);
    if ($response === false) {
        Logger::error("OAuth cURL POST error to {$url}: " . curl_error($ch));
    }
    curl_close($ch);

    if (!$response) return null;
    return json_decode((string)$response, true);
}

function httpGetJson(string $url, array $headers = []): ?array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['User-Agent: SudarshanYuvakMandal-OAuthApp'], $headers));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    $response = curl_exec($ch);
    if ($response === false) {
        Logger::error("OAuth cURL GET error to {$url}: " . curl_error($ch));
    }
    curl_close($ch);

    if (!$response) return null;
    return json_decode((string)$response, true);
}

// Helper to auto-login or register user in DB
function processOAuthUser(PDO $pdo, string $provider, string $providerId, string $email, string $fullName, ?string $avatarUrl): void {
    if (empty($email)) {
        header("Location: ../login.php?error=oauth_no_email");
        exit;
    }

    $idColumn = ($provider === 'google') ? 'google_id' : 'github_id';

    // 1. Check by Provider ID first
    $stmt = $pdo->prepare("SELECT * FROM users WHERE {$idColumn} = ?");
    $stmt->execute([$providerId]);
    $user = $stmt->fetch();

    // 2. If not found by Provider ID, check by Email
    if (!$user) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }

    $pdo->beginTransaction();
    try {
        if ($user) {
            $userId = (int)$user['id'];

            // Security check for locked accounts — before modifying anything
            if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
                header("Location: ../login.php?error=account_locked");
                exit;
            }

            // Update existing user with OAuth credentials
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET {$idColumn} = ?, avatar_url = COALESCE(avatar_url, ?), auth_provider = ?, is_verified = 1, failed_logins = 0 
                WHERE id = ?
            ");
            $updateStmt->execute([$providerId, $avatarUrl, $provider, $userId]);
            $role = $user['role'];
            $membershipStatus = $user['membership_status'];
        } else {
            // Auto-register new user via OAuth (Default: member, pending approval)
            $randomPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("
                INSERT INTO users (full_name, email, phone, password_hash, {$idColumn}, avatar_url, auth_provider, is_verified, role, membership_status) 
                VALUES (?, ?, NULL, ?, ?, ?, ?, 1, 'member', 'pending')
            ");
            $insertStmt->execute([$fullName, $email, $randomPass, $providerId, $avatarUrl, $provider]);
            $userId = (int)$pdo->lastInsertId();
            $role = 'member';
            $membershipStatus = 'pending';
        }

        $pdo->commit();

        // Status validation for Member role (after commit)
        if ($role === 'member') {
            switch ($membershipStatus) {
                case 'pending':
                    // New registration via OAuth — redirect to informational pending page
                    header("Location: ../login.php?registered=1");
                    exit;
                case 'rejected':
                    header("Location: ../login.php?error=account_rejected");
                    exit;
                case 'suspended':
                    header("Location: ../login.php?error=account_suspended");
                    exit;
                case 'inactive':
                    header("Location: ../login.php?error=account_inactive");
                    exit;
                case 'approved':
                    break;
            }
        }

        // Authenticated Session Initialization for Approved Members & Admins
        Security::regenerateSession();
        $_SESSION['user_id']           = $userId;
        $_SESSION['user_name']         = $fullName;
        $_SESSION['user_email']        = $email;
        $_SESSION['user_role']         = $role;
        $_SESSION['membership_status'] = $membershipStatus;
        $_SESSION['auth_provider']     = $provider;
        $_SESSION['avatar_url']        = $avatarUrl;
        $_SESSION['login_time']        = time();

        // Auto-create 30-day Remember Me token for all OAuth logins.
        // OAuth users have already authenticated with a trusted provider (Google/GitHub),
        // so persistent login is always safe — no checkbox needed.
        RememberMe::createToken($pdo, $userId);

        Security::logAudit($pdo, $userId, null, 'OAUTH_LOGIN_SUCCESS', ['provider' => $provider, 'email' => $email]);

        if ($role === 'admin') {
            header("Location: ../admin_dashboard.php");
        } else {
            header("Location: ../dashboard.php");
        }
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        Logger::error("OAuth User Processing Exception: " . $e->getMessage());
        header("Location: ../login.php?error=oauth_failed");
        exit;
    }
}

try {
    $pdo = Database::getConnection();

    switch ($action) {

        // -------------------------------------------------------------
        // 1. GOOGLE LOGIN INITIALIZATION
        // -------------------------------------------------------------
        case 'google_login':
            $clientId = Env::get('GOOGLE_CLIENT_ID');
            if (empty($clientId) || $clientId === 'YOUR_GOOGLE_CLIENT_ID_HERE') {
                header("Location: ../login.php?error=google_not_configured");
                exit;
            }

            $state = bin2hex(random_bytes(16));
            $_SESSION['oauth_state'] = $state;
            $redirectUri = $appBaseUrl . '/api/oauth_handler.php?action=google_callback';

            $authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'prompt' => 'select_account'
            ]);

            header("Location: " . $authUrl);
            exit;

        // -------------------------------------------------------------
        // 2. GOOGLE CALLBACK
        // -------------------------------------------------------------
        case 'google_callback':
            $code = $_GET['code'] ?? '';
            $state = $_GET['state'] ?? '';
            $savedState = $_SESSION['oauth_state'] ?? '';
            unset($_SESSION['oauth_state']);

            if (empty($code) || empty($state) || !hash_equals($savedState, $state)) {
                header("Location: ../login.php?error=oauth_invalid_state");
                exit;
            }

            $clientId = Env::get('GOOGLE_CLIENT_ID');
            $clientSecret = Env::get('GOOGLE_CLIENT_SECRET');
            $redirectUri = $appBaseUrl . '/api/oauth_handler.php?action=google_callback';

            // Exchange authorization code for access token
            $tokenData = httpPostJson('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code'
            ]);

            $accessToken = $tokenData['access_token'] ?? null;
            if (!$accessToken) {
                Logger::error("Google Token Exchange Failed: " . json_encode($tokenData));
                header("Location: ../login.php?error=google_token_failed");
                exit;
            }

            // Fetch user info
            $userInfo = httpGetJson('https://www.googleapis.com/oauth2/v3/userinfo', [
                "Authorization: Bearer {$accessToken}"
            ]);

            if (!$userInfo || empty($userInfo['sub'])) {
                header("Location: ../login.php?error=google_user_failed");
                exit;
            }

            $googleId = (string)$userInfo['sub'];
            $email = filter_var($userInfo['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
            $fullName = $userInfo['name'] ?? explode('@', $email)[0];
            $avatarUrl = $userInfo['picture'] ?? null;

            processOAuthUser($pdo, 'google', $googleId, $email, $fullName, $avatarUrl);
            break;

        // -------------------------------------------------------------
        // 3. GITHUB LOGIN INITIALIZATION
        // -------------------------------------------------------------
        case 'github_login':
            $clientId = Env::get('GITHUB_CLIENT_ID');
            if (empty($clientId) || $clientId === 'YOUR_GITHUB_CLIENT_ID_HERE') {
                header("Location: ../login.php?error=github_not_configured");
                exit;
            }

            $state = bin2hex(random_bytes(16));
            $_SESSION['oauth_state'] = $state;
            $redirectUri = $appBaseUrl . '/api/oauth_handler.php?action=github_callback';

            $authUrl = "https://github.com/login/oauth/authorize?" . http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'scope' => 'user:email read:user',
                'state' => $state
            ]);

            header("Location: " . $authUrl);
            exit;

        // -------------------------------------------------------------
        // 4. GITHUB CALLBACK
        // -------------------------------------------------------------
        case 'github_callback':
            $code = $_GET['code'] ?? '';
            $state = $_GET['state'] ?? '';
            $savedState = $_SESSION['oauth_state'] ?? '';
            unset($_SESSION['oauth_state']);

            if (empty($code) || empty($state) || !hash_equals($savedState, $state)) {
                header("Location: ../login.php?error=oauth_invalid_state");
                exit;
            }

            $clientId = Env::get('GITHUB_CLIENT_ID');
            $clientSecret = Env::get('GITHUB_CLIENT_SECRET');
            $redirectUri = $appBaseUrl . '/api/oauth_handler.php?action=github_callback';

            // Exchange code for access token
            $tokenData = httpPostJson('https://github.com/login/oauth/access_token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri
            ], ['Accept: application/json']);

            $accessToken = $tokenData['access_token'] ?? null;
            if (!$accessToken) {
                Logger::error("GitHub Token Exchange Failed: " . json_encode($tokenData));
                header("Location: ../login.php?error=github_token_failed");
                exit;
            }

            // Fetch GitHub profile
            $userProfile = httpGetJson('https://api.github.com/user', [
                "Authorization: Bearer {$accessToken}"
            ]);

            if (!$userProfile || empty($userProfile['id'])) {
                header("Location: ../login.php?error=github_user_failed");
                exit;
            }

            $githubId = (string)$userProfile['id'];
            $fullName = $userProfile['name'] ?? $userProfile['login'] ?? 'GitHub Member';
            $avatarUrl = $userProfile['avatar_url'] ?? null;
            $email = filter_var($userProfile['email'] ?? '', FILTER_VALIDATE_EMAIL);

            // If primary email is private in public profile, fetch user emails list
            if (!$email) {
                $userEmails = httpGetJson('https://api.github.com/user/emails', [
                    "Authorization: Bearer {$accessToken}"
                ]);
                if (is_array($userEmails)) {
                    foreach ($userEmails as $emObj) {
                        if (!empty($emObj['primary']) && !empty($emObj['verified'])) {
                            $email = filter_var($emObj['email'], FILTER_VALIDATE_EMAIL);
                            break;
                        }
                    }
                }
            }

            processOAuthUser($pdo, 'github', $githubId, (string)$email, $fullName, $avatarUrl);
            break;

        default:
            header("Location: ../login.php?error=invalid_oauth_action");
            exit;
    }

} catch (Exception $e) {
    Logger::error("OAuth API Exception: " . $e->getMessage());
    header("Location: ../login.php?error=oauth_server_error");
    exit;
}
