<?php
/**
 * Sudarshan Yuvak Mandal - Mandal Admin Management API Endpoint
 * Protected by Mandal Admin Authorization & Atomic 50-Member Limit Guard
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

// Authorization Check: Must be logged in as Mandal Admin
Security::requireRole('admin');

function sendAdminResponse(string $status, string $message, array $data = [], int $httpCode = 200): void {
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
        sendAdminResponse('error', 'Invalid HTTP Method. POST required.', [], 405);
    }

    $action = Security::sanitizeInput($_POST['action'] ?? '');
    $adminId = (int)$_SESSION['user_id'];

    // Verify CSRF Token for state-changing actions
    if ($action !== 'get_stats' && $action !== 'get_pending_members' && $action !== 'get_approved_members' && $action !== 'get_other_members' && $action !== 'get_pending_requests' && $action !== 'get_all_requests' && $action !== 'get_audit_logs') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCSRFToken($csrfToken)) {
            sendAdminResponse('error', 'Security token invalid or expired. Please refresh.', [], 403);
        }
    }

    switch ($action) {

        // -------------------------------------------------------------
        // 1. GET STATS (Member count & Request count analytics)
        // -------------------------------------------------------------
        case 'get_stats':
            $memStmt = $pdo->query("
                SELECT 
                    SUM(CASE WHEN role = 'member' AND membership_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN role = 'member' AND membership_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN role = 'member' AND membership_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                    SUM(CASE WHEN role = 'member' AND membership_status = 'suspended' THEN 1 ELSE 0 END) as suspended_count
                FROM users
            ");
            $memStats = $memStmt->fetch();

            $reqStmt = $pdo->query("
                SELECT 
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN status = 'approved' AND request_type = 'income' THEN amount ELSE 0 END) as total_income,
                    SUM(CASE WHEN status = 'approved' AND request_type = 'expense' THEN amount ELSE 0 END) as total_expense
                FROM mandal_requests
            ");
            $reqStats = $reqStmt->fetch();

            sendAdminResponse('success', 'Stats retrieved', [
                'stats' => [
                    'pending' => (int)($memStats['pending_count'] ?? 0),
                    'approved' => (int)($memStats['approved_count'] ?? 0),
                    'rejected' => (int)($memStats['rejected_count'] ?? 0),
                    'suspended' => (int)($memStats['suspended_count'] ?? 0),
                    'max_limit' => MANDAL_MAX_MEMBERS,
                    'pending_requests' => (int)($reqStats['pending_requests'] ?? 0),
                    'approved_requests' => (int)($reqStats['approved_requests'] ?? 0),
                    'total_income' => (float)($reqStats['total_income'] ?? 0),
                    'total_expense' => (float)($reqStats['total_expense'] ?? 0)
                ]
            ]);
            break;

        // -------------------------------------------------------------
        // 2. GET PENDING MEMBERS (Registration Approvals)
        // -------------------------------------------------------------
        case 'get_pending_members':
            $stmt = $pdo->query("
                SELECT id, full_name, email, phone, is_verified, created_at 
                FROM users 
                WHERE role = 'member' AND membership_status = 'pending'
                ORDER BY id ASC
            ");
            $members = $stmt->fetchAll();
            sendAdminResponse('success', 'Pending members list retrieved', ['members' => $members]);
            break;

        // -------------------------------------------------------------
        // 3. GET APPROVED MEMBERS (Active 50 Limit Directory)
        // -------------------------------------------------------------
        case 'get_approved_members':
            $stmt = $pdo->query("
                SELECT u.id, u.full_name, u.email, u.phone, u.approved_at, a.full_name as approved_by_name
                FROM users u
                LEFT JOIN users a ON u.approved_by = a.id
                WHERE u.role = 'member' AND u.membership_status = 'approved'
                ORDER BY u.approved_at DESC
            ");
            $members = $stmt->fetchAll();
            sendAdminResponse('success', 'Approved members list retrieved', [
                'members' => $members,
                'count' => count($members),
                'max_limit' => MANDAL_MAX_MEMBERS
            ]);
            break;

        // -------------------------------------------------------------
        // 4. GET OTHER MEMBERS (Rejected / Suspended)
        // -------------------------------------------------------------
        case 'get_other_members':
            $stmt = $pdo->query("
                SELECT u.id, u.full_name, u.email, u.phone, u.membership_status, u.rejection_reason, u.updated_at
                FROM users u
                WHERE u.role = 'member' AND u.membership_status IN ('rejected', 'suspended', 'inactive')
                ORDER BY u.updated_at DESC
            ");
            $members = $stmt->fetchAll();
            sendAdminResponse('success', 'Other members list retrieved', ['members' => $members]);
            break;

        // -------------------------------------------------------------
        // 5. APPROVE MEMBER (Atomic 50-Member Limit Guard)
        // -------------------------------------------------------------
        case 'approve_member':
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                sendAdminResponse('error', 'Invalid member ID specified.', [], 400);
            }

            $pdo->beginTransaction();
            try {
                $countStmt = $pdo->query("
                    SELECT COUNT(*) as active_count 
                    FROM users 
                    WHERE role = 'member' AND membership_status = 'approved' 
                    FOR UPDATE
                ");
                $activeCount = (int)$countStmt->fetch()['active_count'];

                if ($activeCount >= MANDAL_MAX_MEMBERS) {
                    $pdo->rollBack();
                    sendAdminResponse('error', "Approval Limit Reached: Maximum Mandal limit of " . MANDAL_MAX_MEMBERS . " active approved members has been reached.", [], 400);
                }

                $userStmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND role = 'member' AND membership_status = 'pending' FOR UPDATE");
                $userStmt->execute([$userId]);
                $user = $userStmt->fetch();

                if (!$user) {
                    $pdo->rollBack();
                    sendAdminResponse('error', 'Member not found or not in pending approval status.', [], 404);
                }

                $updateStmt = $pdo->prepare("
                    UPDATE users 
                    SET membership_status = 'approved', approved_at = NOW(), approved_by = ?, rejection_reason = NULL 
                    WHERE id = ?
                ");
                $updateStmt->execute([$adminId, $userId]);

                $pdo->commit();

                Security::logAudit($pdo, $userId, $adminId, 'MEMBER_APPROVED', [
                    'member_email' => $user['email'],
                    'new_active_count' => $activeCount + 1
                ]);

                sendAdminResponse('success', 'Member "' . htmlspecialchars($user['full_name']) . '" approved successfully!', []);

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Approve Member Exception: " . $e->getMessage());
                sendAdminResponse('error', 'Failed to approve member.', [], 500);
            }
            break;

        // -------------------------------------------------------------
        // 6. REJECT MEMBER
        // -------------------------------------------------------------
        case 'reject_member':
            $userId = (int)($_POST['user_id'] ?? 0);
            $reason = Security::sanitizeInput($_POST['reason'] ?? 'Application rejected by Mandal Admin.');

            if ($userId <= 0) {
                sendAdminResponse('error', 'Invalid member ID specified.', [], 400);
            }

            $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND role = 'member'");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                sendAdminResponse('error', 'Member not found.', [], 404);
            }

            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET membership_status = 'rejected', rejection_reason = ?, approved_by = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$reason, $adminId, $userId]);

            Security::logAudit($pdo, $userId, $adminId, 'MEMBER_REJECTED', ['reason' => $reason]);

            sendAdminResponse('success', 'Member application for "' . htmlspecialchars($user['full_name']) . '" rejected.', []);
            break;

        // -------------------------------------------------------------
        // 7. SUSPEND MEMBER
        // -------------------------------------------------------------
        case 'suspend_member':
            $userId = (int)($_POST['user_id'] ?? 0);
            $reason = Security::sanitizeInput($_POST['reason'] ?? 'Member suspended by Mandal Admin.');

            if ($userId <= 0) {
                sendAdminResponse('error', 'Invalid member ID specified.', [], 400);
            }

            $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND role = 'member' AND membership_status = 'approved'");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                sendAdminResponse('error', 'Approved active member not found.', [], 404);
            }

            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET membership_status = 'suspended', rejection_reason = ?, approved_by = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$reason, $adminId, $userId]);

            Security::logAudit($pdo, $userId, $adminId, 'MEMBER_SUSPENDED', ['reason' => $reason]);

            sendAdminResponse('success', 'Member "' . htmlspecialchars($user['full_name']) . '" suspended. Membership slot freed up.', []);
            break;

        // -------------------------------------------------------------
        // 8. REACTIVATE MEMBER
        // -------------------------------------------------------------
        case 'reactivate_member':
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($userId <= 0) {
                sendAdminResponse('error', 'Invalid member ID specified.', [], 400);
            }

            $pdo->beginTransaction();
            try {
                $countStmt = $pdo->query("
                    SELECT COUNT(*) as active_count 
                    FROM users 
                    WHERE role = 'member' AND membership_status = 'approved' 
                    FOR UPDATE
                ");
                $activeCount = (int)$countStmt->fetch()['active_count'];

                if ($activeCount >= MANDAL_MAX_MEMBERS) {
                    $pdo->rollBack();
                    sendAdminResponse('error', "Reactivation Limit Reached: Maximum limit of " . MANDAL_MAX_MEMBERS . " active approved members is reached.", [], 400);
                }

                $userStmt = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ? AND role = 'member' FOR UPDATE");
                $userStmt->execute([$userId]);
                $user = $userStmt->fetch();

                if (!$user) {
                    $pdo->rollBack();
                    sendAdminResponse('error', 'Member not found.', [], 404);
                }

                $updateStmt = $pdo->prepare("
                    UPDATE users 
                    SET membership_status = 'approved', approved_at = NOW(), approved_by = ?, rejection_reason = NULL 
                    WHERE id = ?
                ");
                $updateStmt->execute([$adminId, $userId]);

                $pdo->commit();

                Security::logAudit($pdo, $userId, $adminId, 'MEMBER_REACTIVATED', []);

                sendAdminResponse('success', 'Member "' . htmlspecialchars($user['full_name']) . '" reactivated as approved member!', []);

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Reactivate Member Exception: " . $e->getMessage());
                sendAdminResponse('error', 'Failed to reactivate member.', [], 500);
            }
            break;

        // -------------------------------------------------------------
        // 9. GET PENDING MEMBER REQUESTS (Expense / Income / Booking)
        // -------------------------------------------------------------
        case 'get_pending_requests':
            $stmt = $pdo->query("
                SELECT r.id, r.user_id, r.request_type, r.category, r.title, r.description, r.amount, r.event_date, r.is_hidden, r.proof_file, r.created_at, u.full_name as member_name, u.email as member_email
                FROM mandal_requests r
                JOIN users u ON r.user_id = u.id
                WHERE r.status = 'pending'
                ORDER BY r.id ASC
            ");
            $requests = $stmt->fetchAll();
            sendAdminResponse('success', 'Pending requests retrieved', ['requests' => $requests]);
            break;

        // -------------------------------------------------------------
        // 10. GET ALL REQUESTS (Master Mandal Ledger with Multi-Year & Search)
        // -------------------------------------------------------------
        case 'get_all_requests':
            $yearFilter = trim($_POST['year'] ?? '');
            $typeFilter = trim($_POST['type'] ?? '');
            $searchTerm = trim($_POST['search'] ?? '');
            $sortBy     = trim($_POST['sort'] ?? 'date_desc');

            $where = ["1=1"];
            $params = [];

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
                SELECT r.id, r.user_id, r.request_type, r.category, r.title, r.description, r.amount, r.event_date, r.is_hidden, r.proof_file, r.status, r.rejection_reason, r.reviewed_at,
                       u.full_name as member_name, u.email as member_email, rev.full_name as reviewer_name
                FROM mandal_requests r
                JOIN users u ON r.user_id = u.id
                LEFT JOIN users rev ON r.reviewed_by = rev.id
                WHERE $whereSql
                ORDER BY $orderClause
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $requests = $stmt->fetchAll();

            // Fetch distinct available years
            $yearsStmt = $pdo->query("SELECT DISTINCT YEAR(event_date) as yr FROM mandal_requests WHERE event_date IS NOT NULL AND event_date != '0000-00-00' ORDER BY yr DESC");
            $availableYears = array_values(array_filter(array_map(function($r) { return (int)$r['yr']; }, $yearsStmt->fetchAll())));
            if (empty($availableYears)) $availableYears = [(int)date('Y')];

            sendAdminResponse('success', 'Master ledger requests retrieved', [
                'requests' => $requests,
                'count' => count($requests),
                'available_years' => $availableYears,
                'selected_year' => $yearFilter ?: 'all'
            ]);
            break;

        // -------------------------------------------------------------
        // 11. APPROVE MEMBER REQUEST (Supports Privacy Override / Make Public)
        // -------------------------------------------------------------
        case 'approve_request':
            $requestId = (int)($_POST['request_id'] ?? 0);
            $overridePublic = (int)($_POST['override_public'] ?? 0) === 1 ? 1 : 0;

            if ($requestId <= 0) {
                sendAdminResponse('error', 'Invalid request ID specified.', [], 400);
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    SELECT r.id, r.user_id, r.title, r.request_type, r.is_hidden, u.full_name as member_name 
                    FROM mandal_requests r
                    JOIN users u ON r.user_id = u.id
                    WHERE r.id = ? AND r.status = 'pending'
                    FOR UPDATE
                ");
                $stmt->execute([$requestId]);
                $req = $stmt->fetch();

                if (!$req) {
                    $pdo->rollBack();
                    sendAdminResponse('error', 'Request not found or already processed.', [], 404);
                }

                // If Admin overrides hidden request, set is_hidden = 0 (Public)
                $finalHidden = ($overridePublic === 1) ? 0 : (int)$req['is_hidden'];

                $updateStmt = $pdo->prepare("
                    UPDATE mandal_requests 
                    SET status = 'approved', is_hidden = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$finalHidden, $adminId, $requestId]);

                // Create In-App Notification for member
                $privacyNotice = ($overridePublic === 1 && (int)$req['is_hidden'] === 1) 
                    ? ' (Admin accepted the record but set visibility to Public)' 
                    : '';

                $notifMsg = "Your " . ucfirst($req['request_type']) . " request '" . $req['title'] . "' has been APPROVED by the Mandal Admin." . $privacyNotice;

                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                    VALUES (?, 'Request Approved', ?, 'approval', 0, NOW())
                ");
                $notifStmt->execute([$req['user_id'], $notifMsg]);

                $pdo->commit();

                Security::logAudit($pdo, (int)$req['user_id'], $adminId, 'REQUEST_APPROVED', [
                    'request_id' => $requestId,
                    'title' => $req['title'],
                    'override_public' => $overridePublic,
                    'final_hidden' => $finalHidden
                ]);

                sendAdminResponse('success', 'Request "' . htmlspecialchars($req['title']) . '" approved successfully!', []);

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Approve Request Exception: " . $e->getMessage());
                sendAdminResponse('error', 'Failed to approve request.', [], 500);
            }
            break;

        // -------------------------------------------------------------
        // 12. REJECT MEMBER REQUEST
        // -------------------------------------------------------------
        case 'reject_request':
            $requestId = (int)($_POST['request_id'] ?? 0);
            $reason = Security::sanitizeInput($_POST['reason'] ?? 'Request rejected by Mandal Admin.');

            if ($requestId <= 0) {
                sendAdminResponse('error', 'Invalid request ID specified.', [], 400);
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    SELECT r.id, r.user_id, r.title, r.request_type, u.full_name as member_name 
                    FROM mandal_requests r
                    JOIN users u ON r.user_id = u.id
                    WHERE r.id = ? AND r.status = 'pending'
                    FOR UPDATE
                ");
                $stmt->execute([$requestId]);
                $req = $stmt->fetch();

                if (!$req) {
                    $pdo->rollBack();
                    sendAdminResponse('error', 'Request not found or already processed.', [], 404);
                }

                $updateStmt = $pdo->prepare("
                    UPDATE mandal_requests 
                    SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$reason, $adminId, $requestId]);

                // Create In-App Notification for member
                $notifMsg = "Your " . ucfirst($req['request_type']) . " request '" . $req['title'] . "' was REJECTED by the Mandal Admin. Reason: " . $reason;

                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                    VALUES (?, 'Request Rejected', ?, 'rejection', 0, NOW())
                ");
                $notifStmt->execute([$req['user_id'], $notifMsg]);

                $pdo->commit();

                Security::logAudit($pdo, (int)$req['user_id'], $adminId, 'REQUEST_REJECTED', [
                    'request_id' => $requestId,
                    'title' => $req['title'],
                    'reason' => $reason
                ]);

                sendAdminResponse('success', 'Request "' . htmlspecialchars($req['title']) . '" rejected.', []);

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Reject Request Exception: " . $e->getMessage());
                sendAdminResponse('error', 'Failed to reject request.', [], 500);
            }
            break;

        // -------------------------------------------------------------
        // 13. GET AUDIT LOGS (Paginated + Filtered — Feature 6)
        // -------------------------------------------------------------
        case 'get_audit_logs':
            $page       = max(1, (int)($_POST['page'] ?? 1));
            $perPage    = 50;
            $offset     = ($page - 1) * $perPage;
            $actionFilter = Security::sanitizeInput($_POST['action_filter'] ?? '');
            $userFilter   = Security::sanitizeInput($_POST['user_filter'] ?? '');

            // Build WHERE clauses dynamically for filtering
            $where = [];
            $params = [];
            if (!empty($actionFilter)) {
                $where[] = 'a.action LIKE ?';
                $params[] = '%' . $actionFilter . '%';
            }
            if (!empty($userFilter)) {
                $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
                $params[] = '%' . $userFilter . '%';
                $params[] = '%' . $userFilter . '%';
            }
            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            // Count total (for pagination)
            $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id $whereClause");
            $countStmt->execute($params);
            $totalCount = (int)$countStmt->fetch()['total'];

            // Fetch page
            $logParams = array_merge($params, [$perPage, $offset]);
            $stmt = $pdo->prepare("
                SELECT a.id, a.action, a.ip_address, a.details_json, a.created_at,
                       u.full_name as user_name, u.email as user_email,
                       actor.full_name as actor_name
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN users actor ON a.actor_id = actor.id
                $whereClause
                ORDER BY a.id DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute($logParams);
            $logs = $stmt->fetchAll();

            sendAdminResponse('success', 'Audit logs retrieved', [
                'logs'        => $logs,
                'total_count' => $totalCount,
                'page'        => $page,
                'per_page'    => $perPage,
                'has_more'    => ($offset + $perPage) < $totalCount
            ]);
            break;

        // -------------------------------------------------------------
        // 14. ADMIN DIRECT INSERT LEDGER ENTRY
        // -------------------------------------------------------------
        case 'admin_insert_entry':
            $reqType     = Security::sanitizeInput($_POST['request_type'] ?? 'expense');
            $category    = Security::sanitizeInput($_POST['category'] ?? 'General');
            $title       = Security::sanitizeInput($_POST['title'] ?? '');
            $description = Security::sanitizeInput($_POST['description'] ?? '');
            $amount      = (float)($_POST['amount'] ?? 0);
            $eventDate   = trim($_POST['event_date'] ?? '');
            $isHidden    = (int)($_POST['is_hidden'] ?? 0) === 1 ? 1 : 0;

            if (empty($title) || strlen($title) < 3) {
                sendAdminResponse('error', 'Title must be at least 3 characters.', [], 400);
            }
            if (empty($eventDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
                sendAdminResponse('error', 'Invalid event date format.', [], 400);
            }
            if ($amount < 0) {
                sendAdminResponse('error', 'Amount cannot be negative.', [], 400);
            }

            $stmt = $pdo->prepare("
                INSERT INTO mandal_requests (user_id, request_type, category, title, description, amount, event_date, is_hidden, status, reviewed_by, reviewed_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), NOW())
            ");
            $stmt->execute([$adminId, $reqType, $category, $title, $description, $amount, $eventDate, $isHidden, $adminId]);
            $newId = (int)$pdo->lastInsertId();

            Security::logAudit($pdo, $adminId, $adminId, 'ADMIN_ENTRY_INSERTED', [
                'entry_id' => $newId, 'type' => $reqType, 'category' => $category,
                'title' => $title, 'amount' => $amount
            ]);

            sendAdminResponse('success', "Entry \"" . htmlspecialchars($title) . "\" added to the ledger!", ['entry_id' => $newId]);
            break;

        // -------------------------------------------------------------
        // 15. ADMIN UPDATE LEDGER ENTRY
        // -------------------------------------------------------------
        case 'admin_update_entry':
            $entryId     = (int)($_POST['entry_id'] ?? 0);
            $reqType     = Security::sanitizeInput($_POST['request_type'] ?? 'expense');
            $category    = Security::sanitizeInput($_POST['category'] ?? 'General');
            $title       = Security::sanitizeInput($_POST['title'] ?? '');
            $description = Security::sanitizeInput($_POST['description'] ?? '');
            $amount      = (float)($_POST['amount'] ?? 0);
            $eventDate   = trim($_POST['event_date'] ?? '');
            $isHidden    = (int)($_POST['is_hidden'] ?? 0) === 1 ? 1 : 0;

            if ($entryId <= 0) sendAdminResponse('error', 'Invalid entry ID.', [], 400);
            if (empty($title) || strlen($title) < 3) sendAdminResponse('error', 'Title must be at least 3 characters.', [], 400);
            if (empty($eventDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) sendAdminResponse('error', 'Invalid event date.', [], 400);

            // Verify entry exists
            $chkStmt = $pdo->prepare("SELECT id, title FROM mandal_requests WHERE id = ?");
            $chkStmt->execute([$entryId]);
            $existing = $chkStmt->fetch();
            if (!$existing) sendAdminResponse('error', 'Entry not found.', [], 404);

            $upStmt = $pdo->prepare("
                UPDATE mandal_requests 
                SET request_type=?, category=?, title=?, description=?, amount=?, event_date=?, is_hidden=?, reviewed_by=?, reviewed_at=NOW()
                WHERE id=?
            ");
            $upStmt->execute([$reqType, $category, $title, $description, $amount, $eventDate, $isHidden, $adminId, $entryId]);

            Security::logAudit($pdo, null, $adminId, 'ADMIN_ENTRY_UPDATED', [
                'entry_id' => $entryId, 'old_title' => $existing['title'], 'new_title' => $title,
                'type' => $reqType, 'amount' => $amount
            ]);

            sendAdminResponse('success', "Entry \"" . htmlspecialchars($title) . "\" updated successfully!", []);
            break;

        // -------------------------------------------------------------
        // 16. ADMIN DELETE LEDGER ENTRY
        // -------------------------------------------------------------
        case 'admin_delete_entry':
            $entryId = (int)($_POST['entry_id'] ?? 0);
            if ($entryId <= 0) sendAdminResponse('error', 'Invalid entry ID.', [], 400);

            $chkStmt = $pdo->prepare("SELECT id, title, request_type, amount FROM mandal_requests WHERE id = ?");
            $chkStmt->execute([$entryId]);
            $entry = $chkStmt->fetch();
            if (!$entry) sendAdminResponse('error', 'Entry not found.', [], 404);

            $pdo->prepare("DELETE FROM mandal_requests WHERE id = ?")->execute([$entryId]);

            Security::logAudit($pdo, null, $adminId, 'ADMIN_ENTRY_DELETED', [
                'entry_id' => $entryId, 'title' => $entry['title'],
                'type' => $entry['request_type'], 'amount' => $entry['amount']
            ]);

            sendAdminResponse('success', "Entry \"" . htmlspecialchars($entry['title']) . "\" deleted from the ledger.", []);
            break;

        // -------------------------------------------------------------
        // 17. SYSTEM CLEANUP (Purge Stale OTPs & Old Rate Limits)
        // -------------------------------------------------------------
        case 'admin_system_cleanup':
            // 1. Delete OTP tokens older than 24 hours
            $otpDel = $pdo->prepare("DELETE FROM otp_tokens WHERE expires_at < NOW() - INTERVAL 24 HOUR");
            $otpDel->execute();
            $otpCount = $otpDel->rowCount();

            // 2. Delete stale rate limits older than 2 hours
            $rlDel = $pdo->prepare("DELETE FROM rate_limits WHERE window_start < NOW() - INTERVAL 2 HOUR");
            $rlDel->execute();
            $rlCount = $rlDel->rowCount();

            Security::logAudit($pdo, null, $adminId, 'SYSTEM_CLEANUP_RUN', [
                'purged_otps' => $otpCount,
                'purged_rate_limits' => $rlCount
            ]);

            sendAdminResponse('success', "Maintenance complete: Purged {$otpCount} expired OTPs and {$rlCount} stale rate records.", [
                'purged_otps' => $otpCount,
                'purged_rate_limits' => $rlCount
            ]);
            break;

        default:
            sendAdminResponse('error', 'Unknown admin action handler.', [], 400);
    }

} catch (Exception $e) {
    error_log("Admin API Exception: " . $e->getMessage());
    sendAdminResponse('error', 'Internal Server Error.', [], 500);
}
