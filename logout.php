<?php
/**
 * Sudarshan Yuvak Mandal - Secure Logout Handler
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

try {
    $pdo = Database::getConnection();
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($userId > 0) {
        RememberMe::clearToken($pdo, $userId);
        Security::logAudit($pdo, $userId, null, 'USER_LOGOUT');
    }
} catch (Throwable $t) {
    Logger::error("Logout Throwable: " . $t->getMessage());
}

// Unset all session variables
$_SESSION = array();

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

header("Location: login.php?logout=1");
exit;
