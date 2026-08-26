<?php
/**
 * Sudarshan Yuvak Mandal - Mandal Admin Provisioning Helper
 * Initializes the initial Mandal Admin from .env configuration ONLY when executed explicitly.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

// Security check: Only allow CLI execution or explicitly authorized setup
if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== Env::get('ADMIN_BOOTSTRAP_PASSWORD')) {
        http_response_code(403);
        exit("Direct web execution unauthorized. Please run via CLI: php database/bootstrap_admin.php\n");
    }
}

try {
    $pdo = Database::getConnection();

    $email = (string)Env::get('ADMIN_BOOTSTRAP_EMAIL', 'admin@sudarshanyuvakmandal.org');
    $password = (string)Env::get('ADMIN_BOOTSTRAP_PASSWORD', 'Admin@Sudarshan2026!');
    $name = (string)Env::get('ADMIN_BOOTSTRAP_NAME', 'Mandal Admin');
    $phone = (string)Env::get('ADMIN_BOOTSTRAP_PHONE', '9876543210');

    if (empty($email) || empty($password)) {
        exit("Error: ADMIN_BOOTSTRAP_EMAIL and ADMIN_BOOTSTRAP_PASSWORD must be configured in .env\n");
    }

    // Check if Admin user already exists
    $stmt = $pdo->prepare("SELECT id, role, membership_status FROM users WHERE email = ? OR role = 'admin'");
    $stmt->execute([$email]);
    $existingAdmin = $stmt->fetch();

    $passHash = password_hash($password, PASSWORD_DEFAULT);

    if ($existingAdmin) {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, phone = ?, password_hash = ?, is_verified = 1, role = 'admin', membership_status = 'approved'
            WHERE id = ?
        ");
        $stmt->execute([$name, $phone, $passHash, $existingAdmin['id']]);
        echo "Mandal Admin account updated successfully! (ID: {$existingAdmin['id']}, Email: {$email})\n";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, email, phone, password_hash, is_verified, role, membership_status)
            VALUES (?, ?, ?, ?, 1, 'admin', 'approved')
        ");
        $stmt->execute([$name, $email, $phone, $passHash]);
        $adminId = $pdo->lastInsertId();
        echo "Mandal Admin account created successfully! (ID: {$adminId}, Email: {$email})\n";
    }

} catch (Throwable $e) {
    echo "Bootstrap Error: " . $e->getMessage() . "\n";
}
