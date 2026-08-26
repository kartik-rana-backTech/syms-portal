<?php
/**
 * Sudarshan Yuvak Mandal - Enterprise Database Connection, Security Guards & Remember Me Engine
 * Location: Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/../includes/logger.php';

// Secure Session Initialization Parameters
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax'); // Must be Lax (not Strict) to allow OAuth cross-site redirects
    ini_set('session.gc_maxlifetime', '28800'); // 8 Hours Session
    
    session_name('SMD_SESSID');
    session_start();
}

// Global Security Headers
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Global Application Constants
define('MANDAL_MAX_MEMBERS', (int)Env::get('MANDAL_MAX_MEMBERS', 50));

class Database {
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $host = Env::get('DB_HOST', 'localhost');
            $dbname = Env::get('DB_NAME', 'sudarshan_yuvak_mandal');
            $user = Env::get('DB_USER', 'root');
            $pass = Env::get('DB_PASS', '');

            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                Logger::error("Database Connection Failure: " . $e->getMessage());
                http_response_code(500);
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Database connection failed. Please check MySQL service.'
                ]));
            }
        }
        return self::$pdo;
    }
}

class Security {

    public static function initSession(): void {
        // Initialize CSRF token for brand-new sessions
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        // Remember Me Auto-Login: fires any time user_id is absent but cookie exists.
        // Must be outside the csrf_token block so it works on stale/expired sessions too.
        if (!isset($_SESSION['user_id']) && isset($_COOKIE['smd_remember'])) {
            try {
                $pdo = Database::getConnection();
                RememberMe::checkAndAutoLogin($pdo);
            } catch (Throwable $t) {
                Logger::error("RememberMe AutoLogin Throwable: " . $t->getMessage());
            }
        }
    }

    public static function getCSRFToken(): string {
        self::initSession();
        return $_SESSION['csrf_token'];
    }

    public static function generateCSRFToken(): string {
        return self::getCSRFToken();
    }

    public static function verifyCSRFToken(?string $token): bool {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function sanitizeInput($data): string {
        if (is_array($data)) return '';
        $data = trim((string)$data);
        return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function getClientIP(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }

    public static function requireAuth(): void {
        self::initSession();
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            if (self::isAjax()) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
                exit;
            }
            header('Location: login.php?error=auth_required');
            exit;
        }
    }

    public static function requireRole(string $role): void {
        self::requireAuth();
        if (($_SESSION['user_role'] ?? '') !== $role) {
            if (self::isAjax()) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Unauthorized access for role ' . $role]);
                exit;
            }
            header('Location: login.php?error=unauthorized');
            exit;
        }
    }

    public static function requireApprovedMember(): void {
        self::requireRole('member');
        if (($_SESSION['membership_status'] ?? '') !== 'approved') {
            if (self::isAjax()) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Your membership application is currently pending Mandal Admin approval.']);
                exit;
            }
            header('Location: login.php?error=pending_approval');
            exit;
        }
    }

    public static function isAjax(): bool {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public static function regenerateSession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function logAudit(PDO $pdo, ?int $userId, ?int $actorId, string $action, array $details = []): void {
        try {
            $ip = self::getClientIP();
            $json = !empty($details) ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, actor_id, action, ip_address, details_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$userId, $actorId, $action, $ip, $json]);
        } catch (Exception $e) {
            Logger::error("Audit Log Failure: " . $e->getMessage());
        }
    }
}

class RememberMe {

    private static string $cookieName = 'smd_remember';

    public static function createToken(PDO $pdo, int $userId): void {
        try {
            $selector = bin2hex(random_bytes(16));
            $validator = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $validator);
            $expiresAt = date('Y-m-d H:i:s', time() + (30 * 86400)); // 30 Days

            // Delete old tokens for user
            $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$userId]);

            $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $selector, $tokenHash, $expiresAt]);

            $cookieValue = $selector . ':' . $validator;
            setcookie(self::$cookieName, $cookieValue, [
                'expires' => time() + (30 * 86400),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
            ]);
        } catch (Throwable $t) {
            Logger::error("Create RememberMe Token Throwable: " . $t->getMessage());
        }
    }

    public static function checkAndAutoLogin(PDO $pdo): bool {
        if (!isset($_COOKIE[self::$cookieName])) return false;

        $parts = explode(':', $_COOKIE[self::$cookieName]);
        if (count($parts) !== 2) return false;

        [$selector, $validator] = $parts;

        $stmt = $pdo->prepare("
            SELECT r.user_id, r.token_hash, u.full_name, u.email, u.role, u.membership_status, u.auth_provider, u.avatar_url
            FROM remember_tokens r
            JOIN users u ON r.user_id = u.id
            WHERE r.selector = ? AND r.expires_at > NOW()
        ");
        $stmt->execute([$selector]);
        $tokenRow = $stmt->fetch();

        if (!$tokenRow) {
            self::clearToken($pdo, null);
            return false;
        }

        $calcHash = hash('sha256', $validator);
        if (!hash_equals($tokenRow['token_hash'], $calcHash)) {
            self::clearToken($pdo, (int)$tokenRow['user_id']);
            return false;
        }

        // Authenticate into Session — populate ALL fields needed by dashboards and guards
        session_regenerate_id(true);
        $_SESSION['user_id']           = (int)$tokenRow['user_id'];
        $_SESSION['user_name']         = $tokenRow['full_name'];
        $_SESSION['user_email']        = $tokenRow['email'];
        $_SESSION['user_role']         = $tokenRow['role'];
        $_SESSION['membership_status'] = $tokenRow['membership_status'];
        $_SESSION['auth_provider']     = $tokenRow['auth_provider'] ?? 'local';
        $_SESSION['avatar_url']        = $tokenRow['avatar_url'] ?? null;
        $_SESSION['login_time']        = time();
        // Regenerate CSRF token for the new session
        $_SESSION['csrf_token']        = bin2hex(random_bytes(32));

        Security::logAudit($pdo, (int)$tokenRow['user_id'], null, 'AUTO_LOGIN_REMEMBER_ME');

        return true;
    }

    public static function clearToken(PDO $pdo, ?int $userId): void {
        if ($userId) {
            $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$userId]);
        }
        setcookie(self::$cookieName, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
}

Security::initSession();
