<?php
/**
 * Sudarshan Yuvak Mandal - Secure Proof Attachment File Upload Handler
 * Validates optional receipt/bill image & PDF uploads with strict MIME security checks.
 */

declare(strict_types=1);

require_once __DIR__ . '/logger.php';

class UploadHelper {

    private static string $uploadDir = __DIR__ . '/../uploads/proofs/';
    private static int $maxSizeBytes = 5242880; // 5 MB Max
    private static array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    private static array $allowedMimeTypes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'application/pdf'
    ];

    /**
     * Process Optional Proof Attachment Upload
     */
    public static function processProofUpload(?array $fileInput): array {
        if (!$fileInput || !isset($fileInput['error']) || $fileInput['error'] === UPLOAD_ERR_NO_FILE) {
            // Proof is optional; return success with null filepath
            return ['success' => true, 'filepath' => null];
        }

        if ($fileInput['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error code: ' . $fileInput['error']];
        }

        // Validate File Size
        if ($fileInput['size'] > self::$maxSizeBytes) {
            return ['success' => false, 'message' => 'Proof attachment size exceeds 5 MB limit.'];
        }

        // Validate File Extension
        $originalName = $fileInput['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::$allowedExtensions, true)) {
            return ['success' => false, 'message' => 'Invalid file format. Only JPG, PNG, WEBP, and PDF documents are allowed.'];
        }

        // Validate MIME Type using finfo
        $tmpPath = $fileInput['tmp_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpPath);

        if (!in_array($mimeType, self::$allowedMimeTypes, true)) {
            return ['success' => false, 'message' => 'Security Error: Invalid file content type (' . htmlspecialchars($mimeType) . ').'];
        }

        // Prepare Destination Directory with .htaccess security guard
        if (!is_dir(self::$uploadDir)) {
            @mkdir(self::$uploadDir, 0755, true);
        }

        $htaccessPath = self::$uploadDir . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "# Disable PHP Execution in Uploads Directory\n<FilesMatch \"\.(php|php5|php7|php8|phtml|phar)$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
            @file_put_contents($htaccessPath, $htaccessContent);
        }

        // Generate Random Safe Filename
        $filename = 'proof_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = self::$uploadDir . $filename;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            Logger::error("Failed to move uploaded file to {$destPath}");
            return ['success' => false, 'message' => 'Failed to save uploaded proof attachment.'];
        }

        // Return Relative Path for Web Access
        $relativePath = 'uploads/proofs/' . $filename;
        return ['success' => true, 'filepath' => $relativePath];
    }

    /**
     * Process Landing Page Media Upload (photos, videos, PDFs, logos)
     * @param array  $fileInput   $_FILES entry
     * @param string $subDir      Subdirectory under uploads/landing/ (logo|murtikar|karyakartas|memories|routes)
     * @param string $prefix      Filename prefix for clarity
     * @param bool   $allowVideo  Whether to accept MP4/WEBM video files
     */
    public static function processLandingUpload(?array $fileInput, string $subDir, string $prefix = 'file', bool $allowVideo = false): array {
        if (!$fileInput || !isset($fileInput['error']) || $fileInput['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => true, 'filepath' => null];
        }
        if ($fileInput['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error code: ' . $fileInput['error']];
        }

        $maxSize = 20971520; // 20 MB for landing media
        if ($fileInput['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File size exceeds 20 MB limit.'];
        }

        $ext = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];

        if ($allowVideo) {
            $allowedExts = array_merge($allowedExts, ['mp4', 'webm', 'mov']);
            $allowedMimes = array_merge($allowedMimes, ['video/mp4', 'video/webm', 'video/quicktime']);
        }

        if (!in_array($ext, $allowedExts, true)) {
            return ['success' => false, 'message' => 'Invalid file format. Allowed: ' . implode(', ', array_map('strtoupper', $allowedExts)) . '.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fileInput['tmp_name']);
        if (!in_array($mimeType, $allowedMimes, true)) {
            return ['success' => false, 'message' => 'Security Error: Invalid file content type (' . htmlspecialchars($mimeType) . ').'];
        }

        $uploadDir = __DIR__ . '/../uploads/landing/' . trim($subDir, '/') . '/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        // Write .htaccess security guard
        $htPath = $uploadDir . '.htaccess';
        if (!file_exists($htPath)) {
            @file_put_contents($htPath, "# Block PHP Execution\n<FilesMatch \"\\.(php|php5|php7|php8|phtml|phar)$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n");
        }

        $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($fileInput['tmp_name'], $destPath)) {
            Logger::error("Landing upload failed to move file to {$destPath}");
            return ['success' => false, 'message' => 'Failed to save uploaded file. Check directory permissions.'];
        }

        return ['success' => true, 'filepath' => 'uploads/landing/' . $subDir . '/' . $filename];
    }

    /**
     * Delete a landing page uploaded file safely
     */
    public static function deleteLandingFile(?string $relativePath): void {
        if (empty($relativePath)) return;
        $absPath = __DIR__ . '/../' . ltrim($relativePath, '/');
        if (file_exists($absPath) && strpos(realpath($absPath), realpath(__DIR__ . '/../uploads/landing/')) === 0) {
            @unlink($absPath);
        }
    }
}
