<?php
/**
 * Sudarshan Yuvak Mandal - Mandal Admin Management Portal
 * Location: Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat
 */

require_once __DIR__ . '/config/db.php';

// Auth Guard: Mandal Admin Only
Security::requireRole('admin');

$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Mandal Admin');
$adminEmail = htmlspecialchars($_SESSION['user_email'] ?? '');

require_once __DIR__ . '/includes/header.php';
?>

<div class="main-wrapper admin-portal-wrapper">
    <div class="admin-container">
        
        <!-- Admin Header Bar -->
        <div class="admin-header-card">
            <div class="admin-header-info">
                <div class="admin-avatar-icon">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div class="admin-title-wrap">
                    <h2 class="admin-main-title">
                        Mandal Admin Portal
                    </h2>
                    <p class="admin-sub-text">
                        Logged in as: <strong><?php echo $adminName; ?></strong> <span class="admin-email-badge">(<?php echo $adminEmail; ?>)</span>
                    </p>
                </div>
            </div>

            <div class="admin-header-actions">
                <span class="badge-role-admin">
                    <i class="fa-solid fa-crown"></i> Mandal Admin
                </span>
                <a href="logout.php" class="btn-logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>

        <!-- Analytics Metric Cards -->
        <div class="metrics-grid admin-metrics-grid">
            <!-- Active Member Count vs 50 Limit Card -->
            <div class="metric-card">
                <div class="metric-card-header">
                    <span class="metric-label">Approved Members</span>
                    <div class="metric-icon-box green">
                        <i class="fa-solid fa-users-check"></i>
                    </div>
                </div>
                <div class="metric-value-row">
                    <span id="metricApprovedCount" class="metric-val-num">0</span>
                    <span class="metric-limit-text">/ <span id="metricMaxLimit">50</span> Limit</span>
                </div>
                <div class="metric-progress-track">
                    <div id="metricProgressBar" class="metric-progress-fill"></div>
                </div>
            </div>

            <!-- Pending Registrations Card -->
            <div class="metric-card">
                <div class="metric-card-header">
                    <span class="metric-label">Pending Member Regs</span>
                    <div class="metric-icon-box yellow">
                        <i class="fa-solid fa-user-clock"></i>
                    </div>
                </div>
                <div class="metric-val-num amber" id="metricPendingCount">0</div>
                <p class="metric-sub-note">Member signups</p>
            </div>

            <!-- Pending Member Requests Card -->
            <div class="metric-card">
                <div class="metric-card-header">
                    <span class="metric-label">Pending Requests</span>
                    <div class="metric-icon-box blue">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                </div>
                <div class="metric-val-num blue" id="metricPendingRequestsCount">0</div>
                <p class="metric-sub-note">Expense/Booking items</p>
            </div>

            <!-- Total Approved Balance Card -->
            <div class="metric-card">
                <div class="metric-card-header">
                    <span class="metric-label">Total Income / Expense</span>
                    <div class="metric-icon-box purple">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                </div>
                <div class="metric-balance-row green">In: ₹<span id="metricTotalIncome">0.00</span></div>
                <div class="metric-balance-row red">Ex: ₹<span id="metricTotalExpense">0.00</span></div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="admin-content-card">
            
            <!-- Navigation Tabs -->
            <div class="admin-tabs-nav-bar" id="adminTabNav">
                <button class="admin-tab-btn active" data-tab="requests">
                    <i class="fa-solid fa-file-circle-check"></i> Member Requests Review <span id="tabBadgeRequests" class="badge">0</span>
                </button>
                <button class="admin-tab-btn" data-tab="pending">
                    <i class="fa-solid fa-user-clock"></i> Pending Regs <span id="tabBadgePending" class="badge badge-amber">0</span>
                </button>
                <button class="admin-tab-btn" data-tab="approved">
                    <i class="fa-solid fa-user-check"></i> Approved Members (<span id="tabApprovedCount">0</span>/<span id="tabApprovedMaxLimit"><?php echo MANDAL_MAX_MEMBERS; ?></span>)
                </button>
                <button class="admin-tab-btn" data-tab="ledger">
                    <i class="fa-solid fa-book-open"></i> Master Ledger
                </button>
                <button class="admin-tab-btn" data-tab="other">
                    <i class="fa-solid fa-user-slash"></i> Rejected/Suspended
                </button>
                <button class="admin-tab-btn" data-tab="audit">
                    <i class="fa-solid fa-shield-halved"></i> Audit Logs
                </button>
                <button class="admin-tab-btn" data-tab="landing" style="background: rgba(218, 77, 18, 0.08); border-color: rgba(218, 77, 18, 0.3);">
                    <i class="fa-solid fa-globe" style="color: #DA4D12;"></i> Landing Page CMS
                </button>
            </div>

            <!-- Page Alert Banner -->
            <div id="adminAlertBanner" class="alert-banner" style="margin: 16px 20px 0 20px; display: none;"></div>

            <!-- Tab 1: Member Requests Review (Expenses, Incomes, Bookings) -->
            <div id="adminTabRequests" class="admin-tab-pane">
                <div class="tab-pane-header">
                    <div>
                        <h3 class="tab-pane-title">Member Expense, Income &amp; Booking Requests</h3>
                        <p class="tab-pane-subtitle">Review submissions. You can approve as requested, or approve &amp; make public (overriding hidden requests), or reject.</p>
                    </div>
                    <button type="button" class="btn-refresh-admin-requests btn-secondary-compact">
                        <i class="fa-solid fa-arrows-rotate"></i> Refresh Requests
                    </button>
                </div>
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="admin-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                        <thead>
                            <tr style="background: #F1F5F9; color: #475569; border-bottom: 2px solid #E2E8F0;">
                                <th style="padding: 12px 16px;">Submitter</th>
                                <th style="padding: 12px 16px;">Type & Category</th>
                                <th style="padding: 12px 16px;">Title & Details</th>
                                <th style="padding: 12px 16px;">Amount</th>
                                <th style="padding: 12px 16px;">Date</th>
                                <th style="padding: 12px 16px;">Requested Visibility</th>
                                <th style="padding: 12px 16px; text-align: right;">Admin Action</th>
                            </tr>
                        </thead>
                        <tbody id="tablePendingRequestsBody">
                            <tr><td colspan="7" style="text-align: center; padding: 24px; color: #94A3B8;">Loading pending requests...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Pending Member Registrations -->
            <div id="adminTabPending" class="admin-tab-pane" style="padding: 24px; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="font-family: var(--font-heading); font-size: 18px; color: #1E293B; margin: 0;">Member Registrations Awaiting Approval</h3>
                    <button type="button" class="btn-refresh-list" style="padding: 6px 14px; border: 1px solid #CBD5E1; background: #fff; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; color: #475569; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-arrows-rotate"></i> Refresh List
                    </button>
                </div>
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="admin-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                        <thead>
                            <tr style="background: #F1F5F9; color: #475569; border-bottom: 2px solid #E2E8F0;">
                                <th style="padding: 12px 16px;">Full Name</th>
                                <th style="padding: 12px 16px;">Email Address</th>
                                <th style="padding: 12px 16px;">Mobile Number</th>
                                <th style="padding: 12px 16px;">Registered On</th>
                                <th style="padding: 12px 16px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tablePendingBody">
                            <tr><td colspan="5" style="text-align: center; padding: 24px; color: #94A3B8;">Loading pending applications...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 3: Approved Active Members (50 Limit) -->
            <div id="adminTabApproved" class="admin-tab-pane" style="padding: 24px; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="font-family: var(--font-heading); font-size: 18px; color: #1E293B; margin: 0;">Approved Active Mandal Members</h3>
                    <span style="font-size: 13px; color: #64748B; background: #F1F5F9; padding: 6px 12px; border-radius: 8px; font-weight: 600;">
                        Maximum Limit: <strong>50 Members</strong>
                    </span>
                </div>
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="admin-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                        <thead>
                            <tr style="background: #F1F5F9; color: #475569; border-bottom: 2px solid #E2E8F0;">
                                <th style="padding: 12px 16px;">Member Name</th>
                                <th style="padding: 12px 16px;">Email</th>
                                <th style="padding: 12px 16px;">Mobile</th>
                                <th style="padding: 12px 16px;">Approved On</th>
                                <th style="padding: 12px 16px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableApprovedBody">
                            <tr><td colspan="5" style="text-align: center; padding: 24px; color: #94A3B8;">Loading approved members...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 4: Master Mandal Ledger (Multi-Year Hissab + CRUD + Search) -->
            <div id="adminTabLedger" class="admin-tab-pane" style="padding: 24px; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                    <div>
                        <h3 style="font-family: var(--font-heading); font-size: 18px; color: #1E293B; margin: 0;">Master Mandal Financial &amp; Booking Ledger</h3>
                        <p style="font-size: 13px; color: #64748B; margin: 2px 0 0 0;">Complete record of all items. Multi-year accounting with live search, filters &amp; CRUD controls.</p>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" id="btnAdminSystemCleanup" style="padding: 9px 16px; background: #F8FAFC; color: #475569; border: 1px solid #CBD5E1; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-broom" style="color: #DA4D12;"></i> Clean Expired Data
                        </button>
                        <!-- Feature 4: Add Entry button -->
                        <button type="button" id="btnAddLedgerEntry" style="padding: 9px 18px; background: linear-gradient(135deg, #DA4D12, #FF9933); color: #fff; border: none; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(218,77,18,0.3);">
                            <i class="fa-solid fa-plus"></i> Add Entry
                        </button>
                    </div>
                </div>

                <!-- Master Ledger Search & Filter Toolbar -->
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; background: #F8FAFC; padding: 14px; border-radius: 12px; border: 1px solid #E2E8F0; align-items: center;">
                    <!-- Keyword Search -->
                    <div style="flex: 1 1 220px; min-width: 180px; position: relative;">
                        <input type="text" id="masterLedgerSearchInput" placeholder="Search title, donor, payee, category..." style="width: 100%; padding: 8px 12px 8px 36px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 11px; color: #94A3B8; font-size: 13px;"></i>
                    </div>

                    <!-- Year Filter -->
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <label for="masterLedgerYearFilter" style="font-size: 12px; font-weight: 700; color: #475569;">Year:</label>
                        <select id="masterLedgerYearFilter" style="padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; background: #fff; cursor: pointer;">
                            <option value="all">All Years</option>
                            <option value="<?php echo date('Y'); ?>" selected><?php echo date('Y'); ?></option>
                        </select>
                    </div>

                    <!-- Type Filter -->
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <label for="masterLedgerTypeFilter" style="font-size: 12px; font-weight: 700; color: #475569;">Type:</label>
                        <select id="masterLedgerTypeFilter" style="padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; background: #fff; cursor: pointer;">
                            <option value="all">All Records</option>
                            <option value="income_group">Collections &amp; Donations</option>
                            <option value="expense_group">Expenses</option>
                            <option value="booking">Bookings</option>
                        </select>
                    </div>

                    <!-- Sort Filter -->
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <label for="masterLedgerSortBy" style="font-size: 12px; font-weight: 700; color: #475569;">Sort:</label>
                        <select id="masterLedgerSortBy" style="padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; background: #fff; cursor: pointer;">
                            <option value="date_desc">Newest Date</option>
                            <option value="date_asc">Oldest Date</option>
                            <option value="amount_desc">Highest Amount</option>
                            <option value="amount_asc">Lowest Amount</option>
                        </select>
                    </div>

                    <button type="button" id="btnFilterMasterLedger" style="padding: 8px 14px; background: #DA4D12; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                        <i class="fa-solid fa-filter"></i> Apply
                    </button>
                </div>

                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="admin-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                        <thead>
                            <tr style="background: #F1F5F9; color: #475569; border-bottom: 2px solid #E2E8F0;">
                                <th style="padding: 12px 16px;">Date</th>
                                <th style="padding: 12px 16px;">Payer / Donor / Submitter</th>
                                <th style="padding: 12px 16px;">Type &amp; Category</th>
                                <th style="padding: 12px 16px;">Title &amp; Details</th>
                                <th style="padding: 12px 16px;">Amount</th>
                                <th style="padding: 12px 16px;">Visibility</th>
                                <th style="padding: 12px 16px;">Status</th>
                                <th style="padding: 12px 16px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableMasterLedgerBody">
                            <tr><td colspan="8" style="text-align: center; padding: 24px; color: #94A3B8;">Loading master ledger...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 5: Rejected / Suspended -->
            <div id="adminTabOther" class="admin-tab-pane" style="padding: 24px; display: none;">
                <div style="margin-bottom: 16px;">
                    <h3 style="font-family: var(--font-heading); font-size: 18px; color: #1E293B; margin: 0;">Rejected & Suspended Applications</h3>
                </div>
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="admin-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                        <thead>
                            <tr style="background: #F1F5F9; color: #475569; border-bottom: 2px solid #E2E8F0;">
                                <th style="padding: 12px 16px;">Member Name</th>
                                <th style="padding: 12px 16px;">Email</th>
                                <th style="padding: 12px 16px;">Status</th>
                                <th style="padding: 12px 16px;">Reason</th>
                                <th style="padding: 12px 16px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableOtherBody">
                            <tr><td colspan="5" style="text-align: center; padding: 24px; color: #94A3B8;">Loading rejected & suspended list...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 6: Audit Logs -->
            <div id="adminTabAudit" class="admin-tab-pane" style="padding: 24px; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                    <div>
                        <h3 style="font-family: var(--font-heading); font-size: 18px; color: #1E293B; margin: 0;">Security & Membership Audit Trail</h3>
                        <p style="font-size: 13px; color: #64748B; margin: 4px 0 0 0;">All system events, logins, and admin actions. Paginated & filterable.</p>
                    </div>
                    <!-- Feature 6: Filter controls -->
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <select id="auditActionFilter" style="padding: 8px 12px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 13px; color: #334155; background: #fff; cursor: pointer;">
                            <option value="">All Actions</option>
                            <option value="LOGIN">Login Events</option>
                            <option value="SIGNUP">Signup Events</option>
                            <option value="MEMBER">Member Actions</option>
                            <option value="REQUEST">Request Actions</option>
                            <option value="ADMIN_ENTRY">Admin Entries</option>
                            <option value="OAUTH">OAuth Events</option>
                            <option value="AUTO_LOGIN">Auto Login</option>
                        </select>
                        <input type="text" id="auditUserFilter" placeholder="Filter by user..." style="padding: 8px 12px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 13px; width: 160px;">
                        <button type="button" id="btnAuditFilter" style="padding: 8px 14px; background: #DA4D12; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                            <i class="fa-solid fa-filter"></i> Filter
                        </button>
                    </div>
                </div>
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="admin-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                        <thead>
                            <tr style="background: #F1F5F9; color: #475569; border-bottom: 2px solid #E2E8F0;">
                                <th style="padding: 12px 16px;">Timestamp</th>
                                <th style="padding: 12px 16px;">Action</th>
                                <th style="padding: 12px 16px;">User / Actor</th>
                                <th style="padding: 12px 16px;">IP</th>
                                <th style="padding: 12px 16px;">Details</th>
                            </tr>
                        </thead>
                        <tbody id="tableAuditBody">
                            <tr><td colspan="5" style="text-align: center; padding: 24px; color: #94A3B8;">Loading audit logs...</td></tr>
                        </tbody>
                    </table>
                </div>
                <!-- Feature 6: Load More -->
                <button type="button" id="btnLoadMoreAudit" class="btn-load-more" style="display: none;">
                    <i class="fa-solid fa-chevron-down"></i> Load More Logs
                </button>
                <div id="auditLogCount" style="text-align: center; font-size: 12px; color: #94A3B8; margin-top: 8px;"></div>
            </div>

            <!-- Tab 7: Landing Page CMS -->
            <div id="adminTabLanding" class="admin-tab-pane" style="padding: 24px; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3 style="font-family: var(--font-heading); font-size: 20px; color: #1E293B; margin: 0; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-globe" style="color: #DA4D12;"></i> Public Landing Page Management
                        </h3>
                        <p style="font-size: 13px; color: #64748B; margin: 4px 0 0 0;">Manage public website content year-by-year. Changes appear live on the homepage without altering internal finance records.</p>
                    </div>
                    <a href="index.php" target="_blank" style="padding: 8px 16px; background: #FFF7ED; color: #C2410C; border: 1px solid #FFEDD5; border-radius: 8px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Preview Live Website
                    </a>
                </div>

                <!-- Landing Sub-Tabs Navigation -->
                <div style="display: flex; gap: 8px; border-bottom: 2px solid #E2E8F0; margin-bottom: 24px; overflow-x: auto; padding-bottom: 2px;">
                    <button type="button" class="landing-subtab-btn active" data-subtab="settings" style="padding: 10px 18px; border: none; background: none; font-size: 13px; font-weight: 700; color: #DA4D12; border-bottom: 2px solid #DA4D12; cursor: pointer; display: flex; align-items: center; gap: 6px; white-space: nowrap;">
                        <i class="fa-solid fa-gear"></i> Mandal Info &amp; Logo
                    </button>
                    <button type="button" class="landing-subtab-btn" data-subtab="events" style="padding: 10px 18px; border: none; background: none; font-size: 13px; font-weight: 600; color: #64748B; cursor: pointer; display: flex; align-items: center; gap: 6px; white-space: nowrap;">
                        <i class="fa-solid fa-calendar-days"></i> Yearly Events &amp; Murtikar
                    </button>
                    <button type="button" class="landing-subtab-btn" data-subtab="karyakartas" style="padding: 10px 18px; border: none; background: none; font-size: 13px; font-weight: 600; color: #64748B; cursor: pointer; display: flex; align-items: center; gap: 6px; white-space: nowrap;">
                        <i class="fa-solid fa-users"></i> Karyakartas
                    </button>
                    <button type="button" class="landing-subtab-btn" data-subtab="routes" style="padding: 10px 18px; border: none; background: none; font-size: 13px; font-weight: 600; color: #64748B; cursor: pointer; display: flex; align-items: center; gap: 6px; white-space: nowrap;">
                        <i class="fa-solid fa-route"></i> Procession Routes
                    </button>
                    <button type="button" class="landing-subtab-btn" data-subtab="memories" style="padding: 10px 18px; border: none; background: none; font-size: 13px; font-weight: 600; color: #64748B; cursor: pointer; display: flex; align-items: center; gap: 6px; white-space: nowrap;">
                        <i class="fa-solid fa-images"></i> Memories Gallery
                    </button>
                </div>

                <!-- Subtab 1: Mandal Info & Logo -->
                <div id="landingSubtabSettings" class="landing-subtab-pane">
                    <form id="formMandalSettings" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                            <h4 style="font-size: 15px; font-weight: 700; color: #1E293B; margin: 0 0 16px 0;">Mandal Branding &amp; Logo</h4>
                            <div style="display: flex; gap: 24px; align-items: center; flex-wrap: wrap;">
                                <div>
                                    <img id="settingLogoPreview" src="" alt="Mandal Logo" style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid #DA4D12; display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                                </div>
                                <div style="flex: 1; min-width: 240px;">
                                    <label class="form-label" for="settingLogoInput">Upload Mandal Logo (PNG / JPG / WEBP, Max 5MB)</label>
                                    <input type="file" id="settingLogoInput" name="logo" class="form-input" accept="image/png,image/jpeg,image/webp,image/svg+xml" style="padding: 8px;">
                                    <p style="font-size: 12px; color: #64748B; margin: 4px 0 0 0;">This logo appears on the landing page hero section, navigation bar, and footer.</p>
                                </div>
                            </div>
                        </div>

                        <div class="admin-modal-grid">
                            <div class="form-group">
                                <label class="form-label" for="settingMandalName">Mandal Official Name *</label>
                                <input type="text" id="settingMandalName" name="mandal_name" class="form-input" placeholder="e.g. Sudarshan Yuvak Mandal" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="settingFoundingYear">Establishment Year</label>
                                <input type="number" id="settingFoundingYear" name="founding_year" class="form-input" placeholder="e.g. 2012" min="1950" max="2100">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="settingAddress">Location &amp; Address *</label>
                            <input type="text" id="settingAddress" name="address" class="form-input" placeholder="Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="settingAboutText">About Mandal Story &amp; Description</label>
                            <textarea id="settingAboutText" name="about_text" class="form-input" rows="4" style="height: auto; padding: 12px;" placeholder="Tell the history, devotion, and community initiatives of the Mandal..."></textarea>
                        </div>

                        <div class="admin-modal-grid">
                            <div class="form-group">
                                <label class="form-label" for="settingPhone">Official Contact Phone</label>
                                <input type="tel" id="settingPhone" name="phone" class="form-input" placeholder="e.g. 9876543210">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="settingWhatsapp">Official WhatsApp Number</label>
                                <input type="tel" id="settingWhatsapp" name="whatsapp" class="form-input" placeholder="e.g. 9876543210 (without +91)">
                            </div>
                        </div>

                        <div class="admin-modal-grid">
                            <div class="form-group">
                                <label class="form-label" for="settingEmail">Public Contact Email</label>
                                <input type="email" id="settingEmail" name="email" class="form-input" placeholder="contact@sudarshanmandal.org">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="settingContactPerson">Primary Contact Person</label>
                                <input type="text" id="settingContactPerson" name="contact_person" class="form-input" placeholder="e.g. President / Secretary Name">
                            </div>
                        </div>

                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                            <h4 style="font-size: 15px; font-weight: 700; color: #1E293B; margin: 0 0 16px 0;">Social Media Links</h4>
                            <div class="admin-modal-grid">
                                <div class="form-group">
                                    <label class="form-label" for="settingInstagram"><i class="fa-brands fa-instagram" style="color: #E1306C;"></i> Instagram URL</label>
                                    <input type="url" id="settingInstagram" name="instagram_url" class="form-input" placeholder="https://instagram.com/sudarshan_mandal">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="settingYoutube"><i class="fa-brands fa-youtube" style="color: #FF0000;"></i> YouTube Channel URL</label>
                                    <input type="url" id="settingYoutube" name="youtube_url" class="form-input" placeholder="https://youtube.com/@sudarshanmandal">
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" for="settingFacebook"><i class="fa-brands fa-facebook" style="color: #1877F2;"></i> Facebook Page URL</label>
                                <input type="url" id="settingFacebook" name="facebook_url" class="form-input" placeholder="https://facebook.com/sudarshanmandal">
                            </div>
                        </div>

                        <div style="text-align: right;">
                            <button type="submit" class="btn-submit" style="padding: 12px 28px; font-size: 14px; font-weight: 700;">
                                <span class="btn-text-icon"><i class="fa-solid fa-floppy-disk"></i> Save Mandal Settings</span>
                                <span class="btn-spinner"></span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Subtab 2: Yearly Events & Murtikar -->
                <div id="landingSubtabEvents" class="landing-subtab-pane" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h4 style="font-size: 16px; font-weight: 700; color: #1E293B; margin: 0;">Configured Ganesh Utsav Years</h4>
                        <button type="button" id="btnAddEvent" class="btn-submit" style="padding: 8px 16px; font-size: 13px; font-weight: 700;">
                            <i class="fa-solid fa-plus"></i> Add Year Event
                        </button>
                    </div>
                    <div class="table-responsive" style="overflow-x: auto;">
                        <table class="admin-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                            <thead>
                                <tr style="background: #F1F5F9; color: #475569; border-bottom: 2px solid #E2E8F0;">
                                    <th style="padding: 12px 16px;">Year</th>
                                    <th style="padding: 12px 16px;">Theme</th>
                                    <th style="padding: 12px 16px;">Ganesh Aagman</th>
                                    <th style="padding: 12px 16px;">Visarjan</th>
                                    <th style="padding: 12px 16px;">Murtikar</th>
                                    <th style="padding: 12px 16px;">Active Countdown</th>
                                    <th style="padding: 12px 16px; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tableEventsBody">
                                <tr><td colspan="7" style="text-align: center; padding: 24px; color: #94A3B8;">Loading events...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Subtab 3: Karyakartas (Unified Mandal Committee - Independent of Years) -->
                <div id="landingSubtabKaryakartas" class="landing-subtab-pane" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <h4 style="margin: 0; font-size: 15px; color: #1E293B; font-weight: 700;">Active Mandal Committee (Karyakartas)</h4>
                            <p style="margin: 2px 0 0 0; font-size: 12px; color: #64748B;">Permanent Mandal team displayed on the public landing page.</p>
                        </div>
                        <button type="button" id="btnAddKaryakarta" class="btn-submit" style="padding: 8px 16px; font-size: 13px; font-weight: 700;">
                            <i class="fa-solid fa-user-plus"></i> Add Karyakarta
                        </button>
                    </div>
                    <div class="table-responsive" style="overflow-x: auto;">
                        <table class="admin-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                            <thead>
                                <tr style="background: #F1F5F9; color: #475569; border-bottom: 2px solid #E2E8F0;">
                                    <th style="padding: 12px 16px;">Member</th>
                                    <th style="padding: 12px 16px;">Role / Designation</th>
                                    <th style="padding: 12px 16px;">Contact Links</th>
                                    <th style="padding: 12px 16px;">Display Order</th>
                                    <th style="padding: 12px 16px;">Visibility</th>
                                    <th style="padding: 12px 16px; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tableKaryakartasBody">
                                <tr><td colspan="6" style="text-align: center; padding: 24px; color: #94A3B8;">Loading karyakartas...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Subtab 4: Procession Routes -->
                <div id="landingSubtabRoutes" class="landing-subtab-pane" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <label for="selectRouteYear" style="font-weight: 700; font-size: 14px; color: #1E293B;">Select Year:</label>
                            <select id="selectRouteYear" class="form-input" style="width: auto; padding: 6px 14px; font-weight: 700; color: #DA4D12;"></select>
                        </div>
                        <button type="button" id="btnAddRoute" class="btn-submit" style="padding: 8px 16px; font-size: 13px; font-weight: 700;">
                            <i class="fa-solid fa-plus"></i> Add Procession Route
                        </button>
                    </div>
                    <div class="table-responsive" style="overflow-x: auto;">
                        <table class="admin-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                            <thead>
                                <tr style="background: #F1F5F9; color: #475569; border-bottom: 2px solid #E2E8F0;">
                                    <th style="padding: 12px 16px;">Route Type</th>
                                    <th style="padding: 12px 16px;">Title &amp; Route Info</th>
                                    <th style="padding: 12px 16px;">Google Map</th>
                                    <th style="padding: 12px 16px;">Route PDF</th>
                                    <th style="padding: 12px 16px;">Order</th>
                                    <th style="padding: 12px 16px; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tableRoutesBody">
                                <tr><td colspan="6" style="text-align: center; padding: 24px; color: #94A3B8;">Loading routes...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Subtab 5: Memory Gallery -->
                <div id="landingSubtabMemories" class="landing-subtab-pane" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <label for="selectMemoryYear" style="font-weight: 700; font-size: 14px; color: #1E293B;">Select Year:</label>
                            <select id="selectMemoryYear" class="form-input" style="width: auto; padding: 6px 14px; font-weight: 700; color: #DA4D12;"></select>
                        </div>
                        <button type="button" id="btnAddMemory" class="btn-submit" style="padding: 8px 16px; font-size: 13px; font-weight: 700;">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Add Photo / Video Memory
                        </button>
                    </div>
                    <div class="table-responsive" style="overflow-x: auto;">
                        <table class="admin-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                            <thead>
                                <tr style="background: #F1F5F9; color: #475569; border-bottom: 2px solid #E2E8F0;">
                                    <th style="padding: 12px 16px;">Media Preview</th>
                                    <th style="padding: 12px 16px;">Title &amp; Story</th>
                                    <th style="padding: 12px 16px;">Type</th>
                                    <th style="padding: 12px 16px;">Order</th>
                                    <th style="padding: 12px 16px;">Status</th>
                                    <th style="padding: 12px 16px; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tableMemoriesBody">
                                <tr><td colspan="6" style="text-align: center; padding: 24px; color: #94A3B8;">Loading memories...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

<!-- Modal: Action Reason Dialog (for Reject Member / Reject Request / Suspend Member) -->
<div id="reasonModal" class="modal-overlay">
    <div class="otp-modal-card" style="max-width: 440px;">
        <h3 class="otp-title" id="reasonModalTitle">Specify Reason</h3>
        <p class="otp-subtitle" id="reasonModalSub">Enter reason for this action.</p>
        
        <form id="reasonForm">
            <input type="hidden" id="reasonTargetId" value="">
            <input type="hidden" id="reasonTargetAction" value="">
            
            <div class="form-group" style="text-align: left; margin: 16px 0;">
                <label class="form-label" for="reasonInput">Reason / Remarks</label>
                <textarea id="reasonInput" class="form-input" rows="3" placeholder="Provide reason for this action..." required style="height: auto; padding: 10px;"></textarea>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn-cancel-modal" id="btnCancelReason" style="padding: 10px 18px; border: 1px solid #CBD5E1; background: #fff; border-radius: 8px; font-weight: 600; cursor: pointer; color: #475569;">
                    Cancel
                </button>
                <button type="submit" class="btn-submit" id="btnConfirmReason" style="padding: 10px 20px; font-weight: 600; font-size: 14px;">
                    Confirm Action
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Feature 4: Admin Entry CRUD Modal (Insert & Update) -->
<div id="adminEntryModal" class="modal-overlay">
    <div class="admin-entry-modal-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
            <div>
                <h3 id="adminEntryModalTitle">Add Ledger Entry</h3>
                <p id="adminEntryModalSub">Add a new approved entry directly to the Mandal ledger.</p>
            </div>
            <button type="button" id="btnCloseEntryModal" style="background: none; border: none; font-size: 20px; color: #94A3B8; cursor: pointer; padding: 4px;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="adminEntryForm">
            <input type="hidden" id="entryModalMode" value="insert">
            <input type="hidden" id="entryModalId" value="">

            <div class="admin-modal-grid">
                <div class="form-group">
                    <label class="form-label" for="entryType">Type *</label>
                    <div class="input-wrapper">
                        <select id="entryType" name="request_type" class="form-input" style="padding-left: 40px;" required>
                            <option value="expense">Expense</option>
                            <option value="income">Income / Collection</option>
                            <option value="booking">Booking</option>
                            <option value="donation">Donation</option>
                        </select>
                        <i class="fa-solid fa-layer-group input-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="entryCategory">Category *</label>
                    <div class="input-wrapper">
                        <input type="text" id="entryCategory" name="category" class="form-input" placeholder="e.g. Decoration, Prasad" required>
                        <i class="fa-solid fa-tags input-icon"></i>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="entryTitle">Title *</label>
                <div class="input-wrapper">
                    <input type="text" id="entryTitle" name="title" class="form-input" placeholder="Brief description of entry" required>
                    <i class="fa-solid fa-heading input-icon"></i>
                </div>
            </div>

            <div class="admin-modal-grid">
                <div class="form-group">
                    <label class="form-label" for="entryAmount">Amount (₹)</label>
                    <div class="input-wrapper">
                        <input type="number" id="entryAmount" name="amount" class="form-input" step="0.01" min="0" placeholder="0.00">
                        <i class="fa-solid fa-indian-rupee-sign input-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="entryDate">Event Date *</label>
                    <div class="input-wrapper">
                        <input type="date" id="entryDate" name="event_date" class="form-input" required>
                        <i class="fa-solid fa-calendar input-icon"></i>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="entryDesc">Description / Notes</label>
                <textarea id="entryDesc" name="description" class="form-input" rows="2" placeholder="Additional details..." style="height: auto; padding: 10px;"></textarea>
            </div>

            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 10px; padding: 12px 14px;">
                <input type="checkbox" id="entryIsHidden" name="is_hidden" value="1" style="width: 16px; height: 16px; accent-color: #DA4D12;">
                <label for="entryIsHidden" style="font-size: 13px; font-weight: 600; color: #92400E; cursor: pointer;">🔒 Private / Hidden from members</label>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" id="btnCancelEntryModal" style="padding: 10px 18px; border: 1px solid #CBD5E1; background: #fff; border-radius: 8px; font-weight: 600; cursor: pointer; color: #475569;">Cancel</button>
                <button type="submit" class="btn-submit" id="btnSaveEntry" style="padding: 10px 24px; font-size: 14px; font-weight: 700;">
                    <span class="btn-text-icon"><i class="fa-solid fa-floppy-disk"></i> Save Entry</span>
                    <span class="btn-spinner"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Feature 4: Confirm Delete Modal -->
<div id="deleteConfirmModal" class="modal-overlay">
    <div class="otp-modal-card" style="max-width: 400px;">
        <div style="width: 56px; height: 56px; background: #FEE2E2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 24px; color: #DC2626;">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <h3 class="otp-title" style="color: #DC2626;">Delete Entry?</h3>
        <p class="otp-subtitle" id="deleteConfirmMsg">This action cannot be undone.</p>
        <input type="hidden" id="deleteTargetId" value="">
        <div style="display: flex; gap: 12px; justify-content: center; margin-top: 20px;">
            <button type="button" id="btnCancelDelete" style="padding: 10px 24px; border: 1px solid #CBD5E1; background: #fff; border-radius: 8px; font-weight: 600; cursor: pointer; color: #475569;">Cancel</button>
            <button type="button" id="btnConfirmDelete" style="padding: 10px 24px; background: #EF4444; color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                <span class="btn-text-icon"><i class="fa-solid fa-trash-can"></i> Yes, Delete</span>
                <span class="btn-spinner" style="border-color: rgba(255,255,255,0.3); border-top-color: #fff;"></span>
            </button>
        </div>
    </div>
</div>

<!-- Landing CMS Modal 1: Event Modal -->
<div id="eventModal" class="modal-overlay">
    <div class="admin-entry-modal-card" style="max-width: 540px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
            <div>
                <h3 id="eventModalTitle">Add Ganesh Utsav Event</h3>
                <p style="font-size: 13px; color: #64748B; margin: 0;">Configure yearly event dates, theme, and respected murtikar.</p>
            </div>
            <button type="button" id="btnCloseEventModal" style="background: none; border: none; font-size: 20px; color: #94A3B8; cursor: pointer; padding: 4px;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="eventForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="admin-modal-grid">
                <div class="form-group">
                    <label class="form-label" for="eventYearInput">Utsav Year *</label>
                    <input type="number" id="eventYearInput" name="year" class="form-input" min="2000" max="2100" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="eventThemeInput">Year Theme / Slogan</label>
                    <input type="text" id="eventThemeInput" name="theme" class="form-input" placeholder="e.g. Eco-Friendly Clay Ganesha">
                </div>
            </div>

            <div class="admin-modal-grid">
                <div class="form-group">
                    <label class="form-label" for="eventArrivalInput">Ganesh Aagman Date *</label>
                    <input type="date" id="eventArrivalInput" name="ganesh_arrival_date" class="form-input" required>
                    <small style="color: #DA4D12; font-size: 11px; font-weight: 600;">* Drives live countdown timer on homepage</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="eventVisarjanInput">Visarjan Date</label>
                    <input type="date" id="eventVisarjanInput" name="ganesh_visarjan_date" class="form-input">
                </div>
            </div>

            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                <h4 style="font-size: 13px; font-weight: 700; color: #1E293B; margin: 0 0 10px 0;">Murtikar Information</h4>
                <div class="form-group">
                    <label class="form-label" for="murtikarNameInput">Murtikar Name / Studio</label>
                    <input type="text" id="murtikarNameInput" name="murtikar_name" class="form-input" placeholder="e.g. Shri Rajeshbhai Murtikar">
                </div>
                <div class="form-group">
                    <label class="form-label" for="murtikarInfoInput">Murtikar Details / Specialty</label>
                    <textarea id="murtikarInfoInput" name="murtikar_info" class="form-input" rows="2" style="height: auto; padding: 8px;" placeholder="Location, clay style, heritage..."></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="murtikarPhotoInput">Murtikar / Idol Photo</label>
                    <input type="file" id="murtikarPhotoInput" name="murtikar_photo" class="form-input" accept="image/*" style="padding: 6px;">
                    <img id="murtikarPhotoPreview" src="" alt="Preview" style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover; margin-top: 8px; display: none;">
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; background: #FFF7ED; border: 1px solid #FFEDD5; border-radius: 10px; padding: 12px 14px;">
                <input type="checkbox" id="eventIsActiveInput" name="is_active" value="1" style="width: 16px; height: 16px; accent-color: #DA4D12;">
                <label for="eventIsActiveInput" style="font-size: 13px; font-weight: 700; color: #C2410C; cursor: pointer;">
                    ⭐ Set as Current Active Year (Powers Homepage Countdown &amp; Details)
                </label>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" id="btnCancelEventModal" style="padding: 10px 18px; border: 1px solid #CBD5E1; background: #fff; border-radius: 8px; font-weight: 600; cursor: pointer; color: #475569;">Cancel</button>
                <button type="submit" class="btn-submit" style="padding: 10px 24px; font-size: 14px; font-weight: 700;">
                    <span class="btn-text-icon"><i class="fa-solid fa-floppy-disk"></i> Save Event</span>
                    <span class="btn-spinner"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Landing CMS Modal 2: Karyakarta Modal -->
<div id="karyakartaModal" class="modal-overlay">
    <div class="admin-entry-modal-card" style="max-width: 500px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
            <div>
                <h3 id="karyakartaModalTitle">Add Karyakarta</h3>
                <p style="font-size: 13px; color: #64748B; margin: 0;">Add committee member with optional photo and direct contact links.</p>
            </div>
            <button type="button" id="btnCloseKaryakartaModal" style="background: none; border: none; font-size: 20px; color: #94A3B8; cursor: pointer; padding: 4px;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="karyakartaForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" id="kkIdInput" name="id" value="">
            <input type="hidden" id="kkYearInput" name="utsav_year" value="">

            <div class="admin-modal-grid">
                <div class="form-group">
                    <label class="form-label" for="kkNameInput">Full Name *</label>
                    <input type="text" id="kkNameInput" name="full_name" class="form-input" placeholder="e.g. Ramesh Patel" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="kkRoleInput">Role / Designation *</label>
                    <input type="text" id="kkRoleInput" name="role" class="form-input" placeholder="e.g. President / Decoration Head" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="kkPhotoInput">Member Photo (Optional)</label>
                <input type="file" id="kkPhotoInput" name="photo" class="form-input" accept="image/*" style="padding: 6px;">
                <img id="kkPhotoPreview" src="" alt="Preview" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-top: 8px; display: none;">
            </div>

            <div class="admin-modal-grid">
                <div class="form-group">
                    <label class="form-label" for="kkEmailInput">Email Address</label>
                    <input type="email" id="kkEmailInput" name="email" class="form-input" placeholder="member@example.com">
                    <div style="margin-top: 6px; display: flex; align-items: center; gap: 6px;">
                        <input type="checkbox" id="kkShowEmailInput" name="show_email" value="1" checked style="accent-color: #DA4D12;">
                        <label for="kkShowEmailInput" style="font-size: 11px; color: #64748B; cursor: pointer;">Show email on landing page</label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="kkWhatsappInput">WhatsApp Number</label>
                    <input type="tel" id="kkWhatsappInput" name="whatsapp" class="form-input" placeholder="9876543210">
                    <div style="margin-top: 6px; display: flex; align-items: center; gap: 6px;">
                        <input type="checkbox" id="kkShowWhatsappInput" name="show_whatsapp" value="1" checked style="accent-color: #DA4D12;">
                        <label for="kkShowWhatsappInput" style="font-size: 11px; color: #64748B; cursor: pointer;">Show WhatsApp chat link</label>
                    </div>
                </div>
            </div>

            <div class="admin-modal-grid">
                <div class="form-group">
                    <label class="form-label" for="kkOrderInput">Display Order (0 = First)</label>
                    <input type="number" id="kkOrderInput" name="display_order" class="form-input" value="0" min="0">
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="kkIsVisibleInput" name="is_visible" value="1" checked style="width: 18px; height: 18px; accent-color: #DA4D12;">
                        <label for="kkIsVisibleInput" style="font-size: 13px; font-weight: 700; color: #1E293B; cursor: pointer;">Visible on Website</label>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 10px;">
                <button type="button" id="btnCancelKaryakartaModal" style="padding: 10px 18px; border: 1px solid #CBD5E1; background: #fff; border-radius: 8px; font-weight: 600; cursor: pointer; color: #475569;">Cancel</button>
                <button type="submit" class="btn-submit" style="padding: 10px 24px; font-size: 14px; font-weight: 700;">
                    <span class="btn-text-icon"><i class="fa-solid fa-floppy-disk"></i> Save Karyakarta</span>
                    <span class="btn-spinner"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Landing CMS Modal 3: Route Modal -->
<div id="routeModal" class="modal-overlay">
    <div class="admin-entry-modal-card" style="max-width: 520px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
            <div>
                <h3 id="routeModalTitle">Add Procession Route</h3>
                <p style="font-size: 13px; color: #64748B; margin: 0;">Configure Aagman / Visarjan route with Google Map embed and PDF download.</p>
            </div>
            <button type="button" id="btnCloseRouteModal" style="background: none; border: none; font-size: 20px; color: #94A3B8; cursor: pointer; padding: 4px;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="routeForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" id="routeIdInput" name="id" value="">
            <input type="hidden" id="routeYearInput" name="utsav_year" value="">

            <div class="admin-modal-grid">
                <div class="form-group">
                    <label class="form-label" for="routeTypeInput">Route Type *</label>
                    <select id="routeTypeInput" name="route_type" class="form-input" required>
                        <option value="aagman">🚶 Aagman (Arrival)</option>
                        <option value="visarjan">🌊 Visarjan (Immersion)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="routeOrderInput">Display Order</label>
                    <input type="number" id="routeOrderInput" name="display_order" class="form-input" value="0" min="0">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="routeTitleInput">Route Title *</label>
                <input type="text" id="routeTitleInput" name="title" class="form-input" placeholder="e.g. Main Street Aagman Procession" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="routeDescInput">Route Description &amp; Landmarks</label>
                <textarea id="routeDescInput" name="description" class="form-input" rows="3" style="height: auto; padding: 8px;" placeholder="Starting at 4 PM from Ranchhod Nagar, via Bhathena Crossroad, ending at Mandal Pandal..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="routeMapUrlInput">Google Maps Embed HTML / Link</label>
                <textarea id="routeMapUrlInput" name="map_embed_url" class="form-input" rows="2" style="height: auto; font-family: monospace; font-size: 12px;" placeholder="Paste Google Maps embed code (<iframe src=...>) or map link"></textarea>
                <small style="color: #64748B; font-size: 11px;">You can paste the full Google Maps <code>&lt;iframe&gt;</code> embed code or URL directly.</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="routePdfInput">Upload Route Map PDF (Optional)</label>
                <input type="file" id="routePdfInput" name="route_pdf" class="form-input" accept="application/pdf" style="padding: 6px;">
                <a id="routePdfPreview" href="#" target="_blank" style="font-size: 12px; color: #DA4D12; margin-top: 6px; display: none; font-weight: 600;">
                    <i class="fa-solid fa-file-pdf"></i> View Current PDF
                </a>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 10px;">
                <button type="button" id="btnCancelRouteModal" style="padding: 10px 18px; border: 1px solid #CBD5E1; background: #fff; border-radius: 8px; font-weight: 600; cursor: pointer; color: #475569;">Cancel</button>
                <button type="submit" class="btn-submit" style="padding: 10px 24px; font-size: 14px; font-weight: 700;">
                    <span class="btn-text-icon"><i class="fa-solid fa-floppy-disk"></i> Save Route</span>
                    <span class="btn-spinner"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Landing CMS Modal 4: Memory Modal -->
<div id="memoryModal" class="modal-overlay">
    <div class="admin-entry-modal-card" style="max-width: 500px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
            <div>
                <h3 id="memoryModalTitle">Add Memory (Photo / Video)</h3>
                <p style="font-size: 13px; color: #64748B; margin: 0;">Add photos or videos to the public festival memory gallery.</p>
            </div>
            <button type="button" id="btnCloseMemoryModal" style="background: none; border: none; font-size: 20px; color: #94A3B8; cursor: pointer; padding: 4px;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="memoryForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" id="memIdInput" name="id" value="">
            <input type="hidden" id="memYearInput" name="utsav_year" value="">

            <div class="admin-modal-grid">
                <div class="form-group">
                    <label class="form-label" for="memoryMediaTypeInput">Media Type *</label>
                    <select id="memoryMediaTypeInput" name="media_type" class="form-input" required>
                        <option value="photo">📷 Photo</option>
                        <option value="video">🎥 Video (YouTube / Upload)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="memOrderInput">Display Order</label>
                    <input type="number" id="memOrderInput" name="display_order" class="form-input" value="0" min="0">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="memTitleInput">Title / Caption *</label>
                <input type="text" id="memTitleInput" name="title" class="form-input" placeholder="e.g. Maha Aarti Celebration <?php echo date('Y'); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="memDescInput">Description / Story</label>
                <textarea id="memDescInput" name="description" class="form-input" rows="2" style="height: auto; padding: 8px;" placeholder="Optional story or context..."></textarea>
            </div>

            <!-- Photo Upload Field -->
            <div class="form-group" id="memPhotoGroup">
                <label class="form-label" for="memPhotoInput">Upload Photo (JPG / PNG / WEBP, Max 20MB)</label>
                <input type="file" id="memPhotoInput" name="photo_file" class="form-input" accept="image/jpeg,image/png,image/webp,image/gif" style="padding: 6px;">
                <img id="memPhotoPreview" src="" alt="Preview" style="width: 80px; height: 60px; border-radius: 6px; object-fit: cover; margin-top: 8px; display: none;">
            </div>

            <!-- Video Options Field -->
            <div id="memVideoGroup" style="display: none; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                <div class="form-group">
                    <label class="form-label" for="memVideoUrlInput">YouTube / Vimeo Video URL or Embed Link</label>
                    <input type="text" id="memVideoUrlInput" name="video_url" class="form-input" placeholder="e.g. https://www.youtube.com/watch?v=... or embed code">
                    <small style="color: #64748B; font-size: 11px;">YouTube, Shorts, or Vimeo links embed smoothly with no server storage.</small>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="memVideoFileInput">OR Upload MP4 Video File (Max 30MB)</label>
                    <input type="file" id="memVideoFileInput" name="video_file" class="form-input" accept="video/mp4,video/webm" style="padding: 6px;">
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                <input type="checkbox" id="memIsVisibleInput" name="is_visible" value="1" checked style="width: 18px; height: 18px; accent-color: #DA4D12;">
                <label for="memIsVisibleInput" style="font-size: 13px; font-weight: 700; color: #1E293B; cursor: pointer;">Visible on Public Gallery</label>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 10px;">
                <button type="button" id="btnCancelMemoryModal" style="padding: 10px 18px; border: 1px solid #CBD5E1; background: #fff; border-radius: 8px; font-weight: 600; cursor: pointer; color: #475569;">Cancel</button>
                <button type="submit" class="btn-submit" style="padding: 10px 24px; font-size: 14px; font-weight: 700;">
                    <span class="btn-text-icon"><i class="fa-solid fa-floppy-disk"></i> Save Memory</span>
                    <span class="btn-spinner"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/admin_landing.js?v=<?php echo filemtime(__DIR__ . '/assets/js/admin_landing.js'); ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
