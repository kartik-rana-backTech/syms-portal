<?php
/**
 * Sudarshan Yuvak Mandal - Temporary Production Database Connectivity Diagnostic
 * Uses the application's existing Database::getConnection() and Env::get()
 * 
 * SECURITY NOTICE:
 * This is a temporary diagnostic tool. Delete this file immediately from your server
 * after successful verification!
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(0);

header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');

require_once __DIR__ . '/config/db.php';

$envStatus   = 'FAIL';
$mysqlStatus = 'FAIL';
$dbStatus    = 'FAIL';
$tableStatus = 'FAIL';

try {
    // 1. Verify .env is loaded and required database keys exist
    $host = Env::get('DB_HOST');
    $name = Env::get('DB_NAME');
    $user = Env::get('DB_USER');
    
    if (!empty($host) && !empty($name) && !empty($user)) {
        $envStatus = 'PASS';
    }

    // 2. Verify PDO Connection via application's existing Database singleton
    $pdo = Database::getConnection(true);
    if ($pdo instanceof PDO) {
        $mysqlStatus = 'PASS';

        // 3. Test Database Access (SELECT 1 Query)
        $queryStmt = $pdo->query('SELECT 1');
        if ($queryStmt && $queryStmt->fetchColumn() == 1) {
            $dbStatus = 'PASS';
        }

        // 4. Verify application tables exist
        $tableStmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($tableStmt && $tableStmt->rowCount() > 0) {
            $tableStatus = 'PASS';
        }
    }
} catch (Throwable $e) {
    // Silently catch errors to strictly protect credentials and internal details
}

echo "ENV CONFIGURATION: {$envStatus}\n";
echo "MYSQL CONNECTION: {$mysqlStatus}\n";
echo "DATABASE ACCESS: {$dbStatus}\n";
echo "APPLICATION TABLE CHECK: {$tableStatus}\n";
