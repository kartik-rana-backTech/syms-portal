<?php
/**
 * Sudarshan Yuvak Mandal - Public Landing Page Data API
 * High Performance, Zero-auth, Cached API with ETag and 304 Not Modified support.
 * Optimized for Free-Tier hosts & High Traffic Spikes.
 */

declare(strict_types=1);

// Enable gzip compression if client supports it
if (extension_loaded('zlib') && !ob_start('ob_gzhandler')) {
    ob_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=60, stale-while-revalidate=300');

require_once __DIR__ . '/../config/db.php';

function landingJson(array $data, int $code = 200, string $etag = ''): void {
    if (!empty($etag)) {
        header('ETag: "' . $etag . '"');
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
            http_response_code(304);
            exit;
        }
    }
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = Database::getConnection();
    $action = $_GET['action'] ?? 'all';

    switch ($action) {

        // ---------------------------------------------------------------
        // 1. Full page data in one ultra-fast, lightweight call
        // ---------------------------------------------------------------
        case 'all':
            // 1. Mandal Settings (Mandal level persistent configuration)
            $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM mandal_settings");
            $settings = [];
            while ($row = $settingsStmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            $foundingYear = isset($settings['founding_year']) && is_numeric($settings['founding_year'])
                ? (int)$settings['founding_year']
                : 2024;
            if ($foundingYear < 1950 || $foundingYear > (int)date('Y')) {
                $foundingYear = 2024;
            }

            // 2. Active Utsav Event
            $activeStmt = $pdo->query("SELECT id, year, theme, ganesh_arrival_date, ganesh_visarjan_date, murtikar_name, murtikar_info, murtikar_photo, is_active, updated_at FROM utsav_events WHERE is_active = 1 LIMIT 1");
            $activeEvent = $activeStmt->fetch() ?: null;
            $activeYear = $activeEvent ? (int)$activeEvent['year'] : (int)date('Y');

            // 3. Available Gallery Years (distinct years that contain memories)
            $yearsStmt = $pdo->query("
                SELECT DISTINCT utsav_year as year 
                FROM event_memories 
                WHERE is_visible = 1 
                ORDER BY utsav_year DESC
            ");
            $galleryYears = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array((string)$activeYear, $galleryYears, true)) {
                array_unshift($galleryYears, (string)$activeYear);
            }

            // 4. Karyakartas (Global Mandal Committee - Independent Entity)
            $kkStmt = $pdo->query("
                SELECT id, full_name, role, photo_path,
                       CASE WHEN show_email = 1 THEN email ELSE NULL END as email,
                       CASE WHEN show_whatsapp = 1 THEN whatsapp ELSE NULL END as whatsapp,
                       display_order
                FROM karyakartas
                WHERE is_visible = 1
                ORDER BY display_order ASC, id ASC
            ");
            $karyakartas = $kkStmt->fetchAll();

            // 5. Routes (Procession routes for active year, with graceful fallback)
            $routeStmt = $pdo->prepare("
                SELECT id, route_type, title, description, map_embed_url, route_pdf_path, display_order
                FROM event_routes
                WHERE utsav_year = ?
                ORDER BY route_type ASC, display_order ASC
            ");
            $routeStmt->execute([$activeYear]);
            $routes = $routeStmt->fetchAll();

            if (empty($routes)) {
                $fallbackRouteStmt = $pdo->query("
                    SELECT id, route_type, title, description, map_embed_url, route_pdf_path, display_order
                    FROM event_routes
                    WHERE utsav_year = (SELECT utsav_year FROM event_routes ORDER BY utsav_year DESC LIMIT 1)
                    ORDER BY route_type ASC, display_order ASC
                ");
                $routes = $fallbackRouteStmt->fetchAll();
            }

            // 6. Current Active Year Memories ONLY (super-fast payload, no heavy all-years memory overhead)
            $memoriesStmt = $pdo->prepare("
                SELECT id, utsav_year, title, description, media_type, file_path, video_url, display_order
                FROM event_memories
                WHERE is_visible = 1 AND utsav_year = ?
                ORDER BY display_order ASC, id ASC
                LIMIT 12
            ");
            $memoriesStmt->execute([$activeYear]);
            $memories = $memoriesStmt->fetchAll();

            $totalMembersCount = count($karyakartas);

            $payload = [
                'status'            => 'success',
                'settings'          => $settings,
                'active_event'      => $activeEvent,
                'active_year'       => $activeYear,
                'gallery_years'     => $galleryYears,
                'karyakartas'       => $karyakartas,
                'karyakartas_count' => $totalMembersCount,
                'routes'            => $routes,
                'memories'          => $memories,
                'founding_year'     => $foundingYear,
                'server_time'       => time(),
            ];

            // Generate Fast ETag for caching
            $etag = md5(json_encode($payload));
            landingJson($payload, 200, $etag);
            break;

        // ---------------------------------------------------------------
        // 2. On-Demand Gallery filtered by specific chosen year
        // ---------------------------------------------------------------
        case 'memories_by_year':
            $year = (int)($_GET['year'] ?? 0);
            if ($year < 2000 || $year > 2100) {
                landingJson(['status' => 'error', 'message' => 'Invalid year.'], 400);
            }
            $stmt = $pdo->prepare("
                SELECT id, utsav_year, title, description, media_type, file_path, video_url, display_order
                FROM event_memories
                WHERE utsav_year = ? AND is_visible = 1
                ORDER BY display_order ASC, id ASC
            ");
            $stmt->execute([$year]);
            $memories = $stmt->fetchAll();
            $etag = md5('mem_' . $year . '_' . count($memories));
            landingJson(['status' => 'success', 'memories' => $memories, 'year' => $year], 200, $etag);
            break;

        // ---------------------------------------------------------------
        // 3. All past events
        // ---------------------------------------------------------------
        case 'all_events':
            $stmt = $pdo->query("SELECT year, theme, ganesh_arrival_date, ganesh_visarjan_date, murtikar_name, is_active FROM utsav_events ORDER BY year DESC");
            $events = $stmt->fetchAll();
            landingJson(['status' => 'success', 'events' => $events], 200, md5(json_encode($events)));
            break;

        default:
            landingJson(['status' => 'error', 'message' => 'Unknown action.'], 400);
    }

} catch (Throwable $e) {
    Logger::error("Public Landing API Error: " . $e->getMessage());
    landingJson(['status' => 'error', 'message' => 'Server error. Please try again later.'], 500);
}
