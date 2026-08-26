<?php
/**
 * Sudarshan Yuvak Mandal - Public Landing Page Data API
 * High Performance, Zero-auth, Cached API with ETag and 304 Not Modified support.
 * 100% Dynamic Multi-Year Calendar & Countdown Engine (2024 to 2035+).
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

/**
 * Standard Hindu Calendar Ganesh Chaturthi Festival Dates (2024 - 2035)
 */
function getStandardGaneshDates(int $year): array {
    $calendar = [
        2024 => ['arrival' => '2024-09-07', 'visarjan' => '2024-09-17'],
        2025 => ['arrival' => '2025-08-27', 'visarjan' => '2025-09-06'],
        2026 => ['arrival' => '2026-09-14', 'visarjan' => '2026-09-24'],
        2027 => ['arrival' => '2027-09-04', 'visarjan' => '2027-09-14'],
        2028 => ['arrival' => '2028-08-24', 'visarjan' => '2028-09-03'],
        2029 => ['arrival' => '2029-09-12', 'visarjan' => '2029-09-22'],
        2030 => ['arrival' => '2030-09-01', 'visarjan' => '2030-09-11'],
        2031 => ['arrival' => '2031-09-20', 'visarjan' => '2031-09-30'],
        2032 => ['arrival' => '2032-09-08', 'visarjan' => '2032-09-18'],
        2033 => ['arrival' => '2033-08-29', 'visarjan' => '2033-09-08'],
        2034 => ['arrival' => '2034-09-16', 'visarjan' => '2034-09-26'],
        2035 => ['arrival' => '2035-09-06', 'visarjan' => '2035-09-16'],
    ];

    if (isset($calendar[$year])) {
        return $calendar[$year];
    }
    // Approximate fallback for distant future years (mid September)
    return [
        'arrival'  => sprintf('%04d-09-10', $year),
        'visarjan' => sprintf('%04d-09-20', $year)
    ];
}

try {
    $pdo = Database::getConnection();
    $action = $_GET['action'] ?? 'all';

    switch ($action) {

        // ---------------------------------------------------------------
        // 1. Full page data in one ultra-fast, dynamic payload
        // ---------------------------------------------------------------
        case 'all':
            // 1. Mandal Settings
            $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM mandal_settings");
            $settings = [];
            while ($row = $settingsStmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            $foundingYear = isset($settings['founding_year']) && is_numeric($settings['founding_year'])
                ? (int)$settings['founding_year']
                : 2024;
            if ($foundingYear < 1950 || $foundingYear > 2100) {
                $foundingYear = 2024;
            }

            // 2. Active & Configured Events
            $eventsStmt = $pdo->query("SELECT * FROM utsav_events ORDER BY year DESC");
            $allEvents = $eventsStmt->fetchAll();

            $activeEvent = null;
            foreach ($allEvents as $ev) {
                if ((int)$ev['is_active'] === 1) {
                    $activeEvent = $ev;
                    break;
                }
            }
            if (!$activeEvent && !empty($allEvents)) {
                $activeEvent = $allEvents[0]; // Most recent year
            }

            $currentYear = (int)date('Y');
            $activeYear = $activeEvent ? (int)$activeEvent['year'] : $currentYear;

            // 3. Dynamic Available Years (all consecutive years from founding_year to max configured/current year)
            $maxYear = max($currentYear + 1, $activeYear);
            foreach ($allEvents as $ev) {
                if ((int)$ev['year'] > $maxYear) {
                    $maxYear = (int)$ev['year'];
                }
            }

            $galleryYears = [];
            for ($y = $maxYear; $y >= $foundingYear; $y--) {
                $galleryYears[] = (string)$y;
            }

            // 4. Karyakartas (Global Mandal Committee - 100% Independent of Years)
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

            // 6. Current Active Year Memories
            $memoriesStmt = $pdo->prepare("
                SELECT id, utsav_year, title, description, media_type, file_path, video_url, display_order
                FROM event_memories
                WHERE is_visible = 1 AND utsav_year = ?
                ORDER BY display_order ASC, id ASC
                LIMIT 12
            ");
            $memoriesStmt->execute([$activeYear]);
            $memories = $memoriesStmt->fetchAll();

            // 7. Dynamic Countdown & Next Festival Date Calculator
            $today = date('Y-m-d');
            $arrivalDate = $activeEvent['ganesh_arrival_date'] ?? null;
            $visarjanDate = $activeEvent['ganesh_visarjan_date'] ?? null;

            if (empty($arrivalDate)) {
                $std = getStandardGaneshDates($activeYear);
                $arrivalDate = $std['arrival'];
                $visarjanDate = $std['visarjan'];
            }

            $countdownState = 'upcoming';
            $countdownTargetDate = $arrivalDate;
            $countdownYear = $activeYear;
            $countdownLabel = "Ganesh Aagman {$activeYear}";

            if ($today >= $arrivalDate && $visarjanDate && $today <= $visarjanDate) {
                // Festival is ongoing live
                $countdownState = 'live';
                $countdownTargetDate = $visarjanDate;
                $countdownYear = $activeYear;
                $countdownLabel = "Visarjan {$activeYear}";
            } elseif ($visarjanDate && $today > $visarjanDate) {
                // Festival has ended for active year -> Auto roll over to NEXT year!
                $nextYear = $activeYear + 1;
                $nextEventStmt = $pdo->prepare("SELECT * FROM utsav_events WHERE year = ? LIMIT 1");
                $nextEventStmt->execute([$nextYear]);
                $nextEvent = $nextEventStmt->fetch();

                if ($nextEvent && !empty($nextEvent['ganesh_arrival_date'])) {
                    $countdownTargetDate = $nextEvent['ganesh_arrival_date'];
                } else {
                    $nextStd = getStandardGaneshDates($nextYear);
                    $countdownTargetDate = $nextStd['arrival'];
                }
                $countdownState = 'rollover';
                $countdownYear = $nextYear;
                $countdownLabel = "Ganesh Utsav {$nextYear}";
            }

            $payload = [
                'status'            => 'success',
                'settings'          => $settings,
                'active_event'      => $activeEvent,
                'active_year'       => $activeYear,
                'gallery_years'     => $galleryYears,
                'karyakartas'       => $karyakartas,
                'karyakartas_count' => count($karyakartas),
                'routes'            => $routes,
                'memories'          => $memories,
                'founding_year'     => $foundingYear,
                'countdown_info'    => [
                    'state'       => $countdownState,
                    'target_date' => $countdownTargetDate,
                    'target_year' => $countdownYear,
                    'label'       => $countdownLabel,
                ],
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
            if ($year < 1950 || $year > 2100) {
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
