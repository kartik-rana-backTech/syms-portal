<?php
/**
 * Sudarshan Yuvak Mandal - Landing Page Admin CMS API Handler
 * Handles all backend CMS CRUD operations with strict authentication, CSRF validation, input sanitation, and comprehensive audit logging.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/upload_helper.php';

// Require Admin Authentication
Security::requireRole('admin');

function landingAdminJson(string $status, string $message, array $data = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function requireCSRF(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Security::verifyCSRFToken($token)) {
        landingAdminJson('error', 'CSRF verification failed. Please refresh and try again.', [], 403);
    }
}

function setSetting(PDO $pdo, string $key, ?string $value): void {
    $pdo->prepare("INSERT INTO mandal_settings (setting_key, setting_value) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
        ->execute([$key, $value]);
}

/**
 * Helper: Sanitize & normalize Google Map Embed URL
 * Extracts src from full iframe tags and ensures clean URL format
 */
function sanitizeMapEmbedUrl(string $rawUrl): ?string {
    $rawUrl = trim($rawUrl);
    if (empty($rawUrl)) return null;

    // If full <iframe> tag pasted, extract src
    if (preg_match('/src=["\']([^"\']+)["\']/', $rawUrl, $matches)) {
        $rawUrl = $matches[1];
    }

    if (!filter_var($rawUrl, FILTER_VALIDATE_URL)) {
        return null;
    }

    return $rawUrl;
}

/**
 * Helper: Sanitize & normalize YouTube / Vimeo / Video URL
 */
function sanitizeVideoUrl(string $raw): ?string {
    $raw = trim($raw);
    if (empty($raw)) return null;

    // If iframe was pasted, extract src
    if (preg_match('/src=["\']([^"\']+)["\']/i', $raw, $matches)) {
        $raw = $matches[1];
    }

    $raw = strip_tags($raw);
    $raw = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');

    // YouTube links (watch, embed, short, shorts)
    if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([a-zA-Z0-9_-]{11})/i', $raw, $m)) {
        return 'https://www.youtube.com/watch?v=' . $m[1];
    }

    // Vimeo
    if (preg_match('/vimeo\.com\/(\d+)/i', $raw, $m)) {
        return 'https://vimeo.com/' . $m[1];
    }

    if (filter_var($raw, FILTER_VALIDATE_URL)) {
        return $raw;
    }

    return null;
}

try {
    $pdo    = Database::getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // ===================================================================
    // GET actions
    // ===================================================================
    if ($method === 'GET') {
        switch ($action) {

            case 'get_settings':
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM mandal_settings");
                $settings = [];
                while ($r = $stmt->fetch()) {
                    $settings[$r['setting_key']] = $r['setting_value'];
                }
                landingAdminJson('success', 'Settings retrieved', ['settings' => $settings]);

            case 'get_events':
                $rows = $pdo->query("SELECT * FROM utsav_events ORDER BY year DESC")->fetchAll();

                // Get founding year from settings
                $foundingStmt = $pdo->query("SELECT setting_value FROM mandal_settings WHERE setting_key = 'founding_year'");
                $foundingYear = (int)($foundingStmt->fetchColumn() ?: 2024);
                if ($foundingYear < 1950 || $foundingYear > (int)date('Y')) $foundingYear = 2024;

                // Dynamic Available Years directly matching configured utsav_events
                $allYears = array_values(array_unique(array_map(fn($e) => (int)$e['year'], $rows)));
                if (empty($allYears)) {
                    $allYears = [(int)date('Y')];
                }
                rsort($allYears);

                landingAdminJson('success', 'Events retrieved', [
                    'events'          => $rows,
                    'founding_year'   => $foundingYear,
                    'available_years' => $allYears
                ]);

            case 'get_karyakartas':
                $stmt = $pdo->query("SELECT * FROM karyakartas ORDER BY display_order ASC, id ASC");
                landingAdminJson('success', 'Karyakartas retrieved', ['karyakartas' => $stmt->fetchAll()]);

            case 'get_memories':
                $year = (int)($_GET['year'] ?? 0);
                if ($year < 2000 || $year > 2100) $year = (int)date('Y');
                $stmt = $pdo->prepare("SELECT * FROM event_memories WHERE utsav_year = ? ORDER BY display_order ASC, id ASC");
                $stmt->execute([$year]);
                landingAdminJson('success', 'Memories retrieved', ['memories' => $stmt->fetchAll()]);

            case 'get_routes':
                $year = (int)($_GET['year'] ?? 0);
                if ($year < 2000 || $year > 2100) $year = (int)date('Y');
                $stmt = $pdo->prepare("SELECT * FROM event_routes WHERE utsav_year = ? ORDER BY route_type ASC, display_order ASC");
                $stmt->execute([$year]);
                landingAdminJson('success', 'Routes retrieved', ['routes' => $stmt->fetchAll()]);

            default:
                landingAdminJson('error', 'Unknown GET action.', [], 400);
        }
    }

    // ===================================================================
    // POST actions (mutations — CSRF required)
    // ===================================================================
    if ($method !== 'POST') {
        landingAdminJson('error', 'Method not allowed.', [], 405);
    }

    switch ($action) {

        // ----------------------------------------------------------------
        // 1. MANDAL SETTINGS
        // ----------------------------------------------------------------
        case 'save_settings':
            requireCSRF();

            $mandalName = Security::sanitizeInput($_POST['mandal_name'] ?? '');
            $aboutText  = Security::sanitizeInput($_POST['about_text'] ?? '');
            $address    = Security::sanitizeInput($_POST['address'] ?? '');
            $phone      = preg_replace('/[^\d+]/', '', trim($_POST['phone'] ?? ''));
            $whatsapp   = preg_replace('/[^\d+]/', '', trim($_POST['whatsapp'] ?? ''));
            $email      = trim($_POST['email'] ?? '');
            $foundingYr = trim($_POST['founding_year'] ?? '');
            $contactPsn = Security::sanitizeInput($_POST['contact_person'] ?? '');
            $facebook   = trim($_POST['facebook_url'] ?? '');
            $instagram  = trim($_POST['instagram_url'] ?? '');
            $youtube    = trim($_POST['youtube_url'] ?? '');

            // Validation
            if (empty($mandalName)) {
                landingAdminJson('error', 'Mandal Name is required.', [], 400);
            }
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                landingAdminJson('error', 'Invalid email address provided.', [], 400);
            }
            if (!empty($facebook) && !filter_var($facebook, FILTER_VALIDATE_URL)) {
                landingAdminJson('error', 'Invalid Facebook URL.', [], 400);
            }
            if (!empty($instagram) && !filter_var($instagram, FILTER_VALIDATE_URL)) {
                landingAdminJson('error', 'Invalid Instagram URL.', [], 400);
            }
            if (!empty($youtube) && !filter_var($youtube, FILTER_VALIDATE_URL)) {
                landingAdminJson('error', 'Invalid YouTube URL.', [], 400);
            }

            setSetting($pdo, 'mandal_name', $mandalName);
            setSetting($pdo, 'about_text', $aboutText);
            setSetting($pdo, 'address', $address);
            setSetting($pdo, 'phone', $phone ?: null);
            setSetting($pdo, 'whatsapp', $whatsapp ?: null);
            setSetting($pdo, 'email', $email ?: null);
            setSetting($pdo, 'founding_year', $foundingYr ?: null);
            setSetting($pdo, 'contact_person', $contactPsn ?: null);
            setSetting($pdo, 'facebook_url', $facebook ?: null);
            setSetting($pdo, 'instagram_url', $instagram ?: null);
            setSetting($pdo, 'youtube_url', $youtube ?: null);

            // Logo upload (optional)
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $oldLogoStmt = $pdo->prepare("SELECT setting_value FROM mandal_settings WHERE setting_key = 'logo_path'");
                $oldLogoStmt->execute();
                $oldLogo = $oldLogoStmt->fetchColumn();
                if ($oldLogo) UploadHelper::deleteLandingFile($oldLogo);

                $upload = UploadHelper::processLandingUpload($_FILES['logo'], 'logo', 'logo');
                if (!$upload['success']) {
                    landingAdminJson('error', $upload['message'], [], 400);
                }
                setSetting($pdo, 'logo_path', $upload['filepath']);
            }

            Security::logAudit($pdo, (int)$_SESSION['user_id'], null, 'LANDING_SETTINGS_UPDATED', []);
            landingAdminJson('success', 'Mandal settings saved successfully!');

        // ----------------------------------------------------------------
        // 2. UTSAV EVENTS
        // ----------------------------------------------------------------
        case 'save_event':
            requireCSRF();
            $year            = (int)($_POST['year'] ?? 0);
            $theme           = Security::sanitizeInput($_POST['theme'] ?? '');
            $arrivalDate     = trim($_POST['ganesh_arrival_date'] ?? '');
            $visarjanDate    = trim($_POST['ganesh_visarjan_date'] ?? '');
            $murtikarName    = Security::sanitizeInput($_POST['murtikar_name'] ?? '');
            $murtikarInfo    = Security::sanitizeInput($_POST['murtikar_info'] ?? '');
            $isActive        = (int)($_POST['is_active'] ?? 0) === 1 ? 1 : 0;

            if ($year < 2000 || $year > 2100) {
                landingAdminJson('error', 'Please provide a valid festival year between 2000 and 2100.', [], 400);
            }

            // Date validation
            if (!empty($arrivalDate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrivalDate)) {
                landingAdminJson('error', 'Invalid Aagman date format (expected YYYY-MM-DD).', [], 400);
            }
            if (!empty($visarjanDate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $visarjanDate)) {
                landingAdminJson('error', 'Invalid Visarjan date format (expected YYYY-MM-DD).', [], 400);
            }
            if (!empty($arrivalDate) && !empty($visarjanDate) && $arrivalDate > $visarjanDate) {
                landingAdminJson('error', 'Ganesh Aagman date cannot be after Visarjan date.', [], 400);
            }

            // Handle murtikar photo upload (optional)
            $murtikarPhoto = null;
            if (isset($_FILES['murtikar_photo']) && $_FILES['murtikar_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $oldPhotoStmt = $pdo->prepare("SELECT murtikar_photo FROM utsav_events WHERE year = ?");
                $oldPhotoStmt->execute([$year]);
                $oldPhoto = $oldPhotoStmt->fetchColumn();
                if ($oldPhoto) UploadHelper::deleteLandingFile($oldPhoto);

                $upload = UploadHelper::processLandingUpload($_FILES['murtikar_photo'], 'murtikar', 'murtikar_' . $year);
                if (!$upload['success']) landingAdminJson('error', $upload['message'], [], 400);
                $murtikarPhoto = $upload['filepath'];
            }

            if ($isActive) {
                $pdo->exec("UPDATE utsav_events SET is_active = 0");
            }

            $pdo->prepare("
                INSERT INTO utsav_events (year, theme, ganesh_arrival_date, ganesh_visarjan_date, murtikar_name, murtikar_info, murtikar_photo, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    theme = VALUES(theme),
                    ganesh_arrival_date = VALUES(ganesh_arrival_date),
                    ganesh_visarjan_date = VALUES(ganesh_visarjan_date),
                    murtikar_name = VALUES(murtikar_name),
                    murtikar_info = VALUES(murtikar_info),
                    murtikar_photo = COALESCE(VALUES(murtikar_photo), murtikar_photo),
                    is_active = VALUES(is_active)
            ")->execute([$year, $theme ?: null, $arrivalDate ?: null, $visarjanDate ?: null,
                         $murtikarName ?: null, $murtikarInfo ?: null, $murtikarPhoto, $isActive]);

            Security::logAudit($pdo, (int)$_SESSION['user_id'], null, 'LANDING_EVENT_SAVED', ['year' => $year]);
            landingAdminJson('success', "Utsav {$year} details saved successfully!");

        case 'delete_event':
            requireCSRF();
            $year = (int)($_POST['year'] ?? 0);
            if ($year < 2000 || $year > 2100) landingAdminJson('error', 'Invalid year.', [], 400);

            $stmt = $pdo->prepare("SELECT murtikar_photo FROM utsav_events WHERE year = ?");
            $stmt->execute([$year]);
            $photo = $stmt->fetchColumn();
            if ($photo) UploadHelper::deleteLandingFile($photo);

            $pdo->prepare("DELETE FROM utsav_events WHERE year = ?")->execute([$year]);
            landingAdminJson('success', "Festival record for {$year} deleted.");

        case 'set_active_event':
            requireCSRF();
            $year = (int)($_POST['year'] ?? 0);
            if ($year < 2000 || $year > 2100) landingAdminJson('error', 'Invalid year.', [], 400);
            $pdo->exec("UPDATE utsav_events SET is_active = 0");
            $pdo->prepare("UPDATE utsav_events SET is_active = 1 WHERE year = ?")->execute([$year]);
            landingAdminJson('success', "Year {$year} set as the active Ganesh Utsav festival.");

        // ----------------------------------------------------------------
        // 3. KARYAKARTAS (Committee Members)
        // ----------------------------------------------------------------
        case 'save_karyakarta':
            requireCSRF();
            $id           = (int)($_POST['id'] ?? 0);
            $utsavYear    = (int)($_POST['utsav_year'] ?? 0);
            if ($utsavYear <= 0) $utsavYear = (int)date('Y');
            $fullName     = Security::sanitizeInput($_POST['full_name'] ?? '');
            $role         = Security::sanitizeInput($_POST['role'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $whatsapp     = preg_replace('/[^\d+]/', '', trim($_POST['whatsapp'] ?? ''));
            $showEmail    = (int)($_POST['show_email'] ?? 1) === 0 ? 0 : 1;
            $showWhatsapp = (int)($_POST['show_whatsapp'] ?? 1) === 0 ? 0 : 1;
            $dispOrder    = max(0, min(999, (int)($_POST['display_order'] ?? 0)));
            $isVisible    = (int)($_POST['is_visible'] ?? 1) === 0 ? 0 : 1;

            if (empty($fullName)) {
                landingAdminJson('error', 'Karyakarta Name is required.', [], 400);
            }
            if (empty($role)) {
                landingAdminJson('error', 'Role / Designation is required.', [], 400);
            }
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                landingAdminJson('error', 'Invalid email address.', [], 400);
            }

            // Handle photo upload (optional)
            $photoPath = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($id > 0) {
                    $oldStmt = $pdo->prepare("SELECT photo_path FROM karyakartas WHERE id = ?");
                    $oldStmt->execute([$id]);
                    $oldPath = $oldStmt->fetchColumn();
                    if ($oldPath) UploadHelper::deleteLandingFile($oldPath);
                }
                $upload = UploadHelper::processLandingUpload($_FILES['photo'], 'karyakartas', 'kk');
                if (!$upload['success']) landingAdminJson('error', $upload['message'], [], 400);
                $photoPath = $upload['filepath'];
            }

            if ($id > 0) {
                $sql = "UPDATE karyakartas SET utsav_year=?, full_name=?, role=?, email=?, whatsapp=?,
                        show_email=?, show_whatsapp=?, display_order=?, is_visible=?" .
                       ($photoPath ? ", photo_path=?" : "") . " WHERE id=?";
                $params = [$utsavYear, $fullName, $role, $email ?: null, $whatsapp ?: null,
                           $showEmail, $showWhatsapp, $dispOrder, $isVisible];
                if ($photoPath) $params[] = $photoPath;
                $params[] = $id;
                $pdo->prepare($sql)->execute($params);
                landingAdminJson('success', "Karyakarta \"{$fullName}\" updated successfully!");
            } else {
                $pdo->prepare("INSERT INTO karyakartas (utsav_year, full_name, role, photo_path, email, whatsapp, show_email, show_whatsapp, display_order, is_visible)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$utsavYear, $fullName, $role, $photoPath, $email ?: null, $whatsapp ?: null, $showEmail, $showWhatsapp, $dispOrder, $isVisible]);
                landingAdminJson('success', "Karyakarta \"{$fullName}\" added successfully!");
            }

        case 'delete_karyakarta':
            requireCSRF();
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) landingAdminJson('error', 'Invalid ID.', [], 400);
            $stmt = $pdo->prepare("SELECT photo_path, full_name FROM karyakartas WHERE id = ?");
            $stmt->execute([$id]);
            $kk = $stmt->fetch();
            if (!$kk) landingAdminJson('error', 'Karyakarta not found.', [], 404);
            if ($kk['photo_path']) UploadHelper::deleteLandingFile($kk['photo_path']);
            $pdo->prepare("DELETE FROM karyakartas WHERE id = ?")->execute([$id]);
            landingAdminJson('success', "\"{$kk['full_name']}\" removed.");

        // Copy committee members from one year to another
        case 'copy_karyakartas_from_year':
            requireCSRF();
            $fromYear = (int)($_POST['from_year'] ?? 0);
            $toYear   = (int)($_POST['to_year'] ?? 0);

            if ($fromYear < 2000 || $toYear < 2000 || $fromYear === $toYear) {
                landingAdminJson('error', 'Please provide valid source and destination years.', [], 400);
            }

            $srcStmt = $pdo->prepare("SELECT full_name, role, photo_path, email, whatsapp, show_email, show_whatsapp, display_order, is_visible FROM karyakartas WHERE utsav_year = ?");
            $srcStmt->execute([$fromYear]);
            $srcMembers = $srcStmt->fetchAll();

            if (empty($srcMembers)) {
                landingAdminJson('error', "No members found in year {$fromYear} to copy.", [], 404);
            }

            $insertStmt = $pdo->prepare("
                INSERT INTO karyakartas (utsav_year, full_name, role, photo_path, email, whatsapp, show_email, show_whatsapp, display_order, is_visible)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $copied = 0;
            foreach ($srcMembers as $m) {
                $insertStmt->execute([
                    $toYear, $m['full_name'], $m['role'], $m['photo_path'], $m['email'],
                    $m['whatsapp'], $m['show_email'], $m['show_whatsapp'], $m['display_order'], $m['is_visible']
                ]);
                $copied++;
            }

            landingAdminJson('success', "Successfully copied {$copied} committee members from {$fromYear} to {$toYear}!");

        // ----------------------------------------------------------------
        // 4. MEMORY GALLERY
        // ----------------------------------------------------------------
        case 'save_memory':
            requireCSRF();
            $id          = (int)($_POST['id'] ?? 0);
            $utsavYear   = (int)($_POST['utsav_year'] ?? 0);
            $title       = Security::sanitizeInput($_POST['title'] ?? '');
            $description = Security::sanitizeInput($_POST['description'] ?? '');
            $mediaType   = in_array($_POST['media_type'] ?? '', ['photo', 'video']) ? $_POST['media_type'] : 'photo';
            $videoUrl    = sanitizeVideoUrl($_POST['video_url'] ?? '');
            $dispOrder   = max(0, min(999, (int)($_POST['display_order'] ?? 0)));
            $isVisible   = (int)($_POST['is_visible'] ?? 1) === 0 ? 0 : 1;

            if (empty($title)) {
                landingAdminJson('error', 'Memory Title is required.', [], 400);
            }
            if ($utsavYear < 2000 || $utsavYear > 2100) {
                landingAdminJson('error', 'Valid festival year is required.', [], 400);
            }

            $filePath = null;

            // 1. Photo File Upload
            $photoFile = $_FILES['photo_file'] ?? $_FILES['media_file'] ?? null;
            if ($mediaType === 'photo' && $photoFile && isset($photoFile['error']) && $photoFile['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($id > 0) {
                    $oldStmt = $pdo->prepare("SELECT file_path FROM event_memories WHERE id = ?");
                    $oldStmt->execute([$id]);
                    $oldP = $oldStmt->fetchColumn();
                    if ($oldP) UploadHelper::deleteLandingFile($oldP);
                }
                $upload = UploadHelper::processLandingUpload($photoFile, 'memories', 'mem', false);
                if (!$upload['success']) landingAdminJson('error', $upload['message'], [], 400);
                $filePath = $upload['filepath'];
            }

            // 2. Video File Upload
            $videoFile = $_FILES['video_file'] ?? $_FILES['media_file'] ?? null;
            if ($mediaType === 'video' && $videoFile && isset($videoFile['error']) && $videoFile['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($id > 0) {
                    $oldStmt = $pdo->prepare("SELECT file_path FROM event_memories WHERE id = ?");
                    $oldStmt->execute([$id]);
                    $oldP = $oldStmt->fetchColumn();
                    if ($oldP) UploadHelper::deleteLandingFile($oldP);
                }
                $upload = UploadHelper::processLandingUpload($videoFile, 'memories', 'vid', true);
                if (!$upload['success']) landingAdminJson('error', $upload['message'], [], 400);
                $filePath = $upload['filepath'];
            }

            if ($id > 0) {
                $sql = "UPDATE event_memories SET utsav_year=?, title=?, description=?, media_type=?, video_url=?, display_order=?, is_visible=?" .
                       ($filePath ? ", file_path=?" : "") . " WHERE id=?";
                $params = [$utsavYear, $title, $description ?: null, $mediaType, $videoUrl ?: null, $dispOrder, $isVisible];
                if ($filePath) $params[] = $filePath;
                $params[] = $id;
                $pdo->prepare($sql)->execute($params);
                landingAdminJson('success', "Memory \"{$title}\" updated!");
            } else {
                $pdo->prepare("INSERT INTO event_memories (utsav_year, title, description, media_type, file_path, video_url, display_order, is_visible)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$utsavYear, $title, $description ?: null, $mediaType, $filePath, $videoUrl ?: null, $dispOrder, $isVisible]);
                landingAdminJson('success', "Memory \"{$title}\" added!");
            }

        case 'delete_memory':
            requireCSRF();
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) landingAdminJson('error', 'Invalid ID.', [], 400);
            $stmt = $pdo->prepare("SELECT file_path, title FROM event_memories WHERE id = ?");
            $stmt->execute([$id]);
            $mem = $stmt->fetch();
            if (!$mem) landingAdminJson('error', 'Memory not found.', [], 404);
            if ($mem['file_path']) UploadHelper::deleteLandingFile($mem['file_path']);
            $pdo->prepare("DELETE FROM event_memories WHERE id = ?")->execute([$id]);
            landingAdminJson('success', "\"{$mem['title']}\" deleted.");

        // ----------------------------------------------------------------
        // 5. ROUTES (with sanitized map embed URL)
        // ----------------------------------------------------------------
        case 'save_route':
            requireCSRF();
            $id          = (int)($_POST['id'] ?? 0);
            $utsavYear   = (int)($_POST['utsav_year'] ?? 0);
            $routeType   = in_array($_POST['route_type'] ?? '', ['aagman', 'visarjan']) ? $_POST['route_type'] : 'aagman';
            $title       = Security::sanitizeInput($_POST['title'] ?? '');
            $description = Security::sanitizeInput($_POST['description'] ?? '');
            $mapEmbed    = sanitizeMapEmbedUrl($_POST['map_embed_url'] ?? '');
            $dispOrder   = max(0, min(999, (int)($_POST['display_order'] ?? 0)));

            if (empty($title)) {
                landingAdminJson('error', 'Route Title is required.', [], 400);
            }
            if ($utsavYear < 2000 || $utsavYear > 2100) {
                landingAdminJson('error', 'Valid festival year is required.', [], 400);
            }

            $pdfPath = null;
            if (isset($_FILES['route_pdf']) && $_FILES['route_pdf']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($id > 0) {
                    $oldStmt = $pdo->prepare("SELECT route_pdf_path FROM event_routes WHERE id = ?");
                    $oldStmt->execute([$id]);
                    $oldP = $oldStmt->fetchColumn();
                    if ($oldP) UploadHelper::deleteLandingFile($oldP);
                }
                $upload = UploadHelper::processLandingUpload($_FILES['route_pdf'], 'routes', $routeType . '_' . $utsavYear);
                if (!$upload['success']) landingAdminJson('error', $upload['message'], [], 400);
                $pdfPath = $upload['filepath'];
            }

            if ($id > 0) {
                $sql = "UPDATE event_routes SET utsav_year=?, route_type=?, title=?, description=?, map_embed_url=?, display_order=?" .
                       ($pdfPath ? ", route_pdf_path=?" : "") . " WHERE id=?";
                $params = [$utsavYear, $routeType, $title, $description ?: null, $mapEmbed, $dispOrder];
                if ($pdfPath) $params[] = $pdfPath;
                $params[] = $id;
                $pdo->prepare($sql)->execute($params);
                landingAdminJson('success', "Route \"{$title}\" updated!");
            } else {
                $pdo->prepare("INSERT INTO event_routes (utsav_year, route_type, title, description, map_embed_url, route_pdf_path, display_order)
                               VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$utsavYear, $routeType, $title, $description ?: null, $mapEmbed, $pdfPath, $dispOrder]);
                landingAdminJson('success', "Route \"{$title}\" added!");
            }

        case 'delete_route':
            requireCSRF();
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) landingAdminJson('error', 'Invalid ID.', [], 400);
            $stmt = $pdo->prepare("SELECT route_pdf_path, title FROM event_routes WHERE id = ?");
            $stmt->execute([$id]);
            $route = $stmt->fetch();
            if (!$route) landingAdminJson('error', 'Route not found.', [], 404);
            if ($route['route_pdf_path']) UploadHelper::deleteLandingFile($route['route_pdf_path']);
            $pdo->prepare("DELETE FROM event_routes WHERE id = ?")->execute([$id]);
            landingAdminJson('success', "\"{$route['title']}\" deleted.");

        default:
            landingAdminJson('error', 'Unknown admin action.', [], 400);
    }

} catch (Throwable $e) {
    Logger::error("Admin Landing Handler Exception: " . $e->getMessage());
    landingAdminJson('error', 'Internal Server Error: ' . $e->getMessage(), [], 500);
}
