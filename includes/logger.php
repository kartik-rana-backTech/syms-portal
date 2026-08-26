<?php
/**
 * Sudarshan Yuvak Mandal - Enterprise Self-Cleaning Logger Engine
 * Automatically captures runtime errors & purges logs > 7 days to conserve disk space.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

class Logger {

    private static string $logFile = __DIR__ . '/../logs/app_errors.log';
    private static int $lastPurgeTime = 0;

    /**
     * Log Error Message
     */
    public static function error(string $message, array $context = []): void {
        self::log('error', $message, $context);
    }

    /**
     * Log Warning Message
     */
    public static function warning(string $message, array $context = []): void {
        self::log('warning', $message, $context);
    }

    /**
     * Log Info Message
     */
    public static function info(string $message, array $context = []): void {
        self::log('info', $message, $context);
    }

    /**
     * Centralized Logger Function
     */
    public static function log(string $level, string $message, array $context = []): void {
        try {
            $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
            
            // 1. Write to MySQL system_logs Table
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("INSERT INTO system_logs (level, message, context_json, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$level, $message, $contextJson]);
            } catch (Exception $e) {
                // Ignore DB error during logging to prevent infinite loops
            }

            // 2. Write to Disk Log File
            $logDir = dirname(self::$logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $dateStr = date('Y-m-d H:i:s');
            $formattedLog = "[{$dateStr}] [{$level}] {$message}";
            if ($contextJson) {
                $formattedLog .= " Context: {$contextJson}";
            }
            $formattedLog .= PHP_EOL;

            @file_put_contents(self::$logFile, $formattedLog, FILE_APPEND | LOCK_EX);

            // 3. Trigger Auto-Purge Mechanism (once per 10 minutes max to optimize IO)
            if (time() - self::$lastPurgeTime > 600) {
                self::$lastPurgeTime = time();
                self::autoPurgeLogs();
            }

        } catch (Throwable $t) {
            error_log("Logger Throwable: " . $t->getMessage());
        }
    }

    /**
     * Auto-Purging System Logs Older than 7 Days (Conserves Free Disk Space)
     */
    public static function autoPurgeLogs(int $retentionDays = 7): void {
        try {
            // 1. Purge DB system_logs table
            $pdo = Database::getConnection();
            $pdo->prepare("DELETE FROM system_logs WHERE created_at < NOW() - INTERVAL ? DAY")->execute([$retentionDays]);
            
            // Also purge old rate limits
            $pdo->prepare("DELETE FROM rate_limits WHERE updated_at < NOW() - INTERVAL ? DAY")->execute([$retentionDays]);

            // 2. Rotate & trim file log if size > 2 MB
            if (file_exists(self::$logFile) && filesize(self::$logFile) > 2 * 1024 * 1024) {
                $lines = file(self::$logFile);
                if (is_array($lines) && count($lines) > 500) {
                    $trimmed = array_slice($lines, -200); // Keep last 200 lines
                    file_put_contents(self::$logFile, implode('', $trimmed), LOCK_EX);
                }
            }
        } catch (Throwable $t) {
            error_log("AutoPurge Throwable: " . $t->getMessage());
        }
    }
}
