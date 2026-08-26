<?php
/**
 * Sudarshan Yuvak Mandal - Member Dynamic Request, Optional Proof Attachment & Financial Analytics API
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/smtp_mailer.php';
require_once __DIR__ . '/../includes/upload_helper.php';
require_once __DIR__ . '/../includes/logger.php';

// Authorization Guard: Require Approved Member
Security::requireRole('member');
Security::requireApprovedMember();

function sendRequestResponse(string $status, string $message, array $data = [], int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message,
        'timestamp' => time()
    ], $data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = Database::getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method !== 'POST') {
        sendRequestResponse('error', 'Invalid HTTP Method. POST required.', [], 405);
    }

    $action = Security::sanitizeInput($_POST['action'] ?? '');
    $userId = (int)$_SESSION['user_id'];
    $userName = (string)$_SESSION['user_name'];
    $userEmail = (string)$_SESSION['user_email'];

    // Verify CSRF Token for state-changing actions
    if ($action !== 'get_my_requests' && $action !== 'get_public_feed' && $action !== 'get_my_notifications' && $action !== 'get_costing_analytics') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCSRFToken($csrfToken)) {
            sendRequestResponse('error', 'Security token invalid or expired. Please refresh the page.', [], 403);
        }
    }

    switch ($action) {

        // -------------------------------------------------------------
        // 1. SUBMIT DYNAMIC REQUEST (Supports Custom Inputs & Optional Proof)
        // -------------------------------------------------------------
        case 'submit_request':
            $rawType = Security::sanitizeInput($_POST['request_type'] ?? 'expense');
            $customType = Security::sanitizeInput($_POST['custom_request_type'] ?? '');
            $requestType = ($rawType === 'custom' && !empty($customType)) ? $customType : $rawType;

            $rawCategory = Security::sanitizeInput($_POST['category'] ?? 'General');
            $customCategory = Security::sanitizeInput($_POST['custom_category'] ?? '');
            $category = ($rawCategory === 'custom' && !empty($customCategory)) ? $customCategory : $rawCategory;

            $title = Security::sanitizeInput($_POST['title'] ?? '');
            $description = Security::sanitizeInput($_POST['description'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $eventDate = trim($_POST['event_date'] ?? '');
            $isHidden = (int)($_POST['is_hidden'] ?? 0) === 1 ? 1 : 0;

            if (empty($title) || strlen($title) < 3) {
                sendRequestResponse('error', 'Please enter a valid request title (minimum 3 characters).', [], 400);
            }
            if (empty($category)) {
                sendRequestResponse('error', 'Please specify a category or purpose.', [], 400);
            }
            if (empty($eventDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
                sendRequestResponse('error', 'Please select a valid event/transaction date.', [], 400);
            }
            if ($amount < 0) {
                sendRequestResponse('error', 'Amount cannot be negative.', [], 400);
            }

            // Handle Optional Proof File Upload
            $proofPath = null;
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadRes = UploadHelper::processProofUpload($_FILES['proof_file']);
                if (!$uploadRes['success']) {
                    sendRequestResponse('error', $uploadRes['message'], [], 400);
                }
                $proofPath = $uploadRes['filepath'];
            }

            // Insert into mandal_requests
            $stmt = $pdo->prepare("
                INSERT INTO mandal_requests (user_id, request_type, category, title, description, amount, event_date, is_hidden, proof_file, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $userId,
                $requestType,
                $category,
                $title,
                $description,
                $amount,
                $eventDate,
                $isHidden,
                $proofPath
            ]);

            $requestId = (int)$pdo->lastInsertId();

            // Send Admin Email Alert Notification (Non-blocking / Safe)
            try {
                $adminEmail = (string)Env::get('ADMIN_BOOTSTRAP_EMAIL', 'admin@sudarshanyuvakmandal.org');
                SmtpMailer::sendAdminNotificationEmail($adminEmail, [
                    'member_name' => $userName,
                    'member_email' => $userEmail,
                    'request_type' => $requestType,
                    'category' => $category,
                    'title' => $title,
                    'amount' => $amount,
                    'event_date' => $eventDate,
                    'is_hidden' => $isHidden,
                    'description' => $description
                ]);
            } catch (Throwable $mailErr) {
                error_log("Non-critical Admin Notification Email Exception: " . $mailErr->getMessage());
            }

            Security::logAudit($pdo, $userId, null, 'MEMBER_REQUEST_SUBMITTED', [
                'request_id' => $requestId,
                'type' => $requestType,
                'category' => $category,
                'title' => $title,
                'amount' => $amount,
                'is_hidden' => $isHidden,
                'has_proof' => ($proofPath !== null)
            ]);

            $visibilityNotice = ($isHidden === 1) ? ' (Requested as Private record)' : ' (Public record)';
            $proofNotice = ($proofPath !== null) ? ' Proof attachment uploaded successfully.' : '';

            sendRequestResponse('success', 'Request "' . htmlspecialchars($title) . '" submitted successfully to Mandal Admin for review!' . $visibilityNotice . $proofNotice, [
                'request_id' => $requestId
            ]);
            break;

        // -------------------------------------------------------------
        // 2. GET MANDAL FINANCIAL COSTING & ANALYTICS DASHBOARD DATA
        // -------------------------------------------------------------
        // -------------------------------------------------------------
        // 2. GET MANDAL FINANCIAL COSTING & ANALYTICS (Multi-Year Support)
        // -------------------------------------------------------------
        case 'get_costing_analytics':
            $selectedYear = trim($_POST['year'] ?? '');
            
            // Fetch list of distinct available years from records
            $yearsStmt = $pdo->query("
                SELECT DISTINCT YEAR(event_date) as yr 
                FROM mandal_requests 
                WHERE event_date IS NOT NULL AND event_date != '0000-00-00'
                ORDER BY yr DESC
            ");
            $availableYears = array_values(array_filter(array_map(function($r) { return (int)$r['yr']; }, $yearsStmt->fetchAll())));
            if (empty($availableYears)) {
                $availableYears = [(int)date('Y')];
            }

            // Build WHERE condition for year if specified
            $yearWhere = "";
            $params = [];
            if (!empty($selectedYear) && $selectedYear !== 'all' && is_numeric($selectedYear)) {
                $yearWhere = " AND YEAR(event_date) = ? ";
                $params[] = (int)$selectedYear;
            }

            // 1. Overall Balance Totals for Approved Items
            $totalsQuery = "
                SELECT 
                    SUM(CASE WHEN status = 'approved' AND LOWER(request_type) IN ('income', 'collection', 'donation', 'sponsorship') THEN amount ELSE 0 END) as total_income,
                    SUM(CASE WHEN status = 'approved' AND LOWER(request_type) NOT IN ('income', 'collection', 'donation', 'sponsorship') THEN amount ELSE 0 END) as total_expense,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count
                FROM mandal_requests
                WHERE 1=1 $yearWhere
            ";
            $totalsStmt = $pdo->prepare($totalsQuery);
            $totalsStmt->execute($params);
            $totals = $totalsStmt->fetch();

            $totalIncome = (float)($totals['total_income'] ?? 0);
            $totalExpense = (float)($totals['total_expense'] ?? 0);
            $netBalance = $totalIncome - $totalExpense;
            $approvedCount = (int)($totals['approved_count'] ?? 0);

            // 2. Expense Breakdown by Category
            $catQuery = "
                SELECT category, SUM(amount) as cat_total, COUNT(*) as cat_count
                FROM mandal_requests
                WHERE status = 'approved' AND LOWER(request_type) NOT IN ('income', 'collection', 'donation', 'sponsorship') $yearWhere
                GROUP BY category
                ORDER BY cat_total DESC
            ";
            $catStmt = $pdo->prepare($catQuery);
            $catStmt->execute($params);
            $catRows = $catStmt->fetchAll();

            $categories = [];
            foreach ($catRows as $c) {
                $amt = (float)$c['cat_total'];
                $pct = ($totalExpense > 0) ? round(($amt / $totalExpense) * 100, 1) : 0;
                $categories[] = [
                    'category' => $c['category'],
                    'total_amount' => $amt,
                    'count' => (int)$c['cat_count'],
                    'percentage' => $pct
                ];
            }

            sendRequestResponse('success', 'Costing analytics retrieved', [
                'analytics' => [
                    'selected_year' => $selectedYear ?: 'all',
                    'available_years' => $availableYears,
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpense,
                    'net_balance' => $netBalance,
                    'approved_count' => $approvedCount,
                    'category_breakdown' => $categories
                ]
            ]);
            break;

        // -------------------------------------------------------------
        // 3. GET MY SUBMITTED REQUESTS
        // -------------------------------------------------------------
        case 'get_my_requests':
            $stmt = $pdo->prepare("
                SELECT id, request_type, category, title, description, amount, event_date, is_hidden, proof_file, status, rejection_reason, reviewed_at, created_at
                FROM mandal_requests
                WHERE user_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$userId]);
            $requests = $stmt->fetchAll();
            sendRequestResponse('success', 'Member requests retrieved', ['requests' => $requests]);
            break;

        // -------------------------------------------------------------
        // 4. GET MANDAL PUBLIC LEDGER (With Year, Search, and Sort Filters)
        // -------------------------------------------------------------
        case 'get_public_feed':
            $yearFilter = trim($_POST['year'] ?? '');
            $typeFilter = trim($_POST['type'] ?? '');
            $searchTerm = trim($_POST['search'] ?? '');
            $sortBy     = trim($_POST['sort'] ?? 'date_desc');

            $where = ["r.status = 'approved'", "(r.is_hidden = 0 OR r.user_id = ?)"];
            $params = [$userId];

            if (!empty($yearFilter) && $yearFilter !== 'all' && is_numeric($yearFilter)) {
                $where[] = "YEAR(r.event_date) = ?";
                $params[] = (int)$yearFilter;
            }

            if (!empty($typeFilter) && $typeFilter !== 'all') {
                if ($typeFilter === 'income_group') {
                    $where[] = "LOWER(r.request_type) IN ('income', 'collection', 'donation', 'sponsorship')";
                } elseif ($typeFilter === 'expense_group') {
                    $where[] = "LOWER(r.request_type) NOT IN ('income', 'collection', 'donation', 'sponsorship', 'booking')";
                } else {
                    $where[] = "r.request_type = ?";
                    $params[] = $typeFilter;
                }
            }

            if (!empty($searchTerm)) {
                $where[] = "(r.title LIKE ? OR r.description LIKE ? OR r.category LIKE ? OR u.full_name LIKE ?)";
                $wildcard = '%' . $searchTerm . '%';
                $params[] = $wildcard;
                $params[] = $wildcard;
                $params[] = $wildcard;
                $params[] = $wildcard;
            }

            // Sorting
            $orderClause = "r.event_date DESC, r.created_at DESC";
            if ($sortBy === 'date_asc') {
                $orderClause = "r.event_date ASC, r.created_at ASC";
            } elseif ($sortBy === 'amount_desc') {
                $orderClause = "r.amount DESC";
            } elseif ($sortBy === 'amount_asc') {
                $orderClause = "r.amount ASC";
            } elseif ($sortBy === 'title_asc') {
                $orderClause = "r.title ASC";
            }

            $whereSql = implode(' AND ', $where);
            $query = "
                SELECT r.id, r.request_type, r.category, r.title, r.description, r.amount, r.event_date, r.is_hidden, r.proof_file, r.created_at,
                       u.full_name as member_name, u.email as member_email
                FROM mandal_requests r
                JOIN users u ON r.user_id = u.id
                WHERE $whereSql
                ORDER BY $orderClause
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $feed = $stmt->fetchAll();

            sendRequestResponse('success', 'Public ledger retrieved', [
                'feed' => $feed,
                'count' => count($feed)
            ]);
            break;

        // -------------------------------------------------------------
        // 5. GET MY IN-APP NOTIFICATIONS
        // -------------------------------------------------------------
        case 'get_my_notifications':
            $stmt = $pdo->prepare("
                SELECT id, title, message, type, is_read, created_at
                FROM notifications
                WHERE user_id = ?
                ORDER BY id DESC
                LIMIT 50
            ");
            $stmt->execute([$userId]);
            $notifs = $stmt->fetchAll();

            $unreadStmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
            $unreadStmt->execute([$userId]);
            $unreadCount = (int)$unreadStmt->fetch()['unread_count'];

            sendRequestResponse('success', 'Notifications retrieved', [
                'notifications' => $notifs,
                'unread_count' => $unreadCount
            ]);
            break;

        // -------------------------------------------------------------
        // 6. MARK NOTIFICATIONS AS READ
        // -------------------------------------------------------------
        case 'mark_notifications_read':
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$userId]);
            sendRequestResponse('success', 'Notifications marked as read', []);
            break;

        default:
            sendRequestResponse('error', 'Unknown request action.', [], 400);
    }

} catch (Exception $e) {
    Logger::error("Request API Exception: " . $e->getMessage());
    sendRequestResponse('error', 'Internal Server Error.', [], 500);
}
