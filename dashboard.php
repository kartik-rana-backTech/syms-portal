<?php
/**
 * Sudarshan Yuvak Mandal - Member Dashboard & Costing Analytics Portal
 * Location: Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat
 */

require_once __DIR__ . '/config/db.php';

// Auth Guard: Require Approved Member
Security::requireRole('member');
Security::requireApprovedMember();

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Member');
$userEmail = htmlspecialchars($_SESSION['user_email'] ?? '');

require_once __DIR__ . '/includes/header.php';
?>

<div class="main-wrapper admin-portal-wrapper">
    <div class="dashboard-container">
        
        <!-- Member Header Card -->
        <div class="dash-card" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px); border-radius: 16px; padding: 20px 24px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); border: 1px solid rgba(255, 255, 255, 0.6); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; width: 100%; min-width: 0; box-sizing: border-box;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="width: 54px; height: 54px; background: linear-gradient(135deg, #DA4D12 0%, #FF9933 100%); color: #fff; border-radius: 50%; font-size: 26px; font-weight: 800; display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 18px rgba(218, 77, 18, 0.3);">
                    <?php echo strtoupper(substr($userName, 0, 1)); ?>
                </div>
                <div>
                    <h2 style="font-family: var(--font-heading); font-size: 22px; font-weight: 800; color: var(--text-main); margin: 0;">
                        Namaste, <?php echo $userName; ?>!
                    </h2>
                    <div style="margin-top: 4px; display: flex; align-items: center; gap: 8px;">
                        <span class="badge-status approved" style="font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px;">
                            <i class="fa-solid fa-circle-check"></i> Approved Mandal Member
                        </span>
                        <span style="font-size: 13px; color: var(--text-muted);"><?php echo $userEmail; ?></span>
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 16px;">
                <!-- Notification Bell Widget -->
                <div style="position: relative;" id="notifBellWrapper">
                    <button type="button" id="btnNotifBell" style="background: #F1F5F9; border: 1px solid #CBD5E1; width: 44px; height: 44px; border-radius: 12px; font-size: 18px; color: #475569; cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative;">
                        <i class="fa-solid fa-bell"></i>
                        <span id="notifBadge" style="position: absolute; top: -4px; right: -4px; background: #EF4444; color: #fff; font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 10px; display: none;">0</span>
                    </button>
                    <!-- Notification Popup Menu -->
                    <div id="notifDropdown" style="display: none; position: absolute; right: 0; top: 52px; width: 340px; background: #ffffff; border: 1px solid #E2E8F0; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.12); z-index: 100; overflow: hidden;">
                        <div style="padding: 14px 16px; background: #F8FAFC; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center;">
                            <strong style="font-size: 14px; color: #1E293B;"><i class="fa-solid fa-bell"></i> Notifications</strong>
                            <button type="button" id="btnMarkNotifsRead" style="background: transparent; border: none; font-size: 12px; color: #DA4D12; font-weight: 600; cursor: pointer;">Mark all read</button>
                        </div>
                        <div id="notifListContainer" style="max-height: 300px; overflow-y: auto; padding: 8px 0;">
                            <div style="padding: 16px; text-align: center; color: #94A3B8; font-size: 13px;">No new notifications.</div>
                        </div>
                    </div>
                </div>

                <a href="logout.php" style="padding: 10px 20px; font-size: 14px; text-decoration: none; border-radius: 10px; background: #EF4444; color: #fff; display: inline-flex; align-items: center; gap: 8px; font-weight: 600;">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>

        <!-- Mandal Costing & Financial Analytics Header Cards -->
        <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div style="background: #ffffff; border-radius: 14px; padding: 20px; border: 1px solid #E2E8F0; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 13px; font-weight: 600; color: #64748B;">Total Collection (Income)</span>
                    <div style="width: 34px; height: 34px; background: #DEF7EC; color: #059669; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                </div>
                <div style="font-size: 24px; font-weight: 800; color: #059669;">₹<span id="analyticsTotalIncome">0.00</span></div>
            </div>

            <div style="background: #ffffff; border-radius: 14px; padding: 20px; border: 1px solid #E2E8F0; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 13px; font-weight: 600; color: #64748B;">Total Mandal Expenses</span>
                    <div style="width: 34px; height: 34px; background: #FDE8E8; color: #E02424; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>
                <div style="font-size: 24px; font-weight: 800; color: #E02424;">₹<span id="analyticsTotalExpense">0.00</span></div>
            </div>

            <div style="background: #ffffff; border-radius: 14px; padding: 20px; border: 1px solid #E2E8F0; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 13px; font-weight: 600; color: #64748B;">Net Mandal Balance</span>
                    <div style="width: 34px; height: 34px; background: #E0F2FE; color: #0284C7; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-vault"></i>
                    </div>
                </div>
                <div style="font-size: 24px; font-weight: 800; color: #0284C7;">₹<span id="analyticsNetBalance">0.00</span></div>
            </div>
        </div>

        <!-- Main Content Card with Sub-tabs -->
        <div style="background: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04); overflow: hidden;">
            
            <!-- Navigation Tabs -->
            <div style="display: flex; border-bottom: 1px solid #E2E8F0; background: #F8FAFC; overflow-x: auto;" id="memberTabNav">
                <button class="member-tab-btn active" data-tab="analytics" style="padding: 16px 20px; border: none; background: transparent; font-size: 14px; font-weight: 700; color: #DA4D12; border-bottom: 3px solid #DA4D12; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap;">
                    <i class="fa-solid fa-chart-pie"></i> Costing Analytics & Breakdown
                </button>
                <button class="member-tab-btn" data-tab="submit" style="padding: 16px 20px; border: none; background: transparent; font-size: 14px; font-weight: 600; color: #64748B; border-bottom: 3px solid transparent; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap;">
                    <i class="fa-solid fa-plus-circle"></i> Submit New Request
                </button>
                <button class="member-tab-btn" data-tab="submissions" style="padding: 16px 20px; border: none; background: transparent; font-size: 14px; font-weight: 600; color: #64748B; border-bottom: 3px solid transparent; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap;">
                    <i class="fa-solid fa-list-check"></i> My Submissions
                </button>
                <button class="member-tab-btn" data-tab="ledger" style="padding: 16px 20px; border: none; background: transparent; font-size: 14px; font-weight: 600; color: #64748B; border-bottom: 3px solid transparent; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap;">
                    <i class="fa-solid fa-book-open"></i> Public Ledger
                </button>
            </div>

            <!-- Tab 1: Costing Analytics & Category Breakdown -->
            <div id="memberTabAnalytics" class="member-tab-pane" style="padding: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px; margin-bottom: 20px;">
                    <div>
                        <h3 style="font-family: var(--font-heading); font-size: 18px; color: #1E293B; margin-bottom: 4px;">Mandal Costing &amp; Expense Breakdown</h3>
                        <p style="font-size: 13px; color: #64748B;">Transparent overview of Mandal expenditure categorized by purpose for all approved members.</p>
                    </div>
                    <!-- Multi-Year Hissab Selector -->
                    <div style="display: flex; align-items: center; gap: 8px; background: #F8FAFC; padding: 6px 12px; border-radius: 10px; border: 1px solid #E2E8F0;">
                        <label for="analyticsYearFilter" style="font-size: 13px; font-weight: 700; color: #1E293B; white-space: nowrap;"><i class="fa-solid fa-calendar-check" style="color: #DA4D12;"></i> Hissab Year:</label>
                        <select id="analyticsYearFilter" style="padding: 6px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; font-weight: 600; color: #1E293B; background: #fff; cursor: pointer;">
                            <option value="all">All Years (Overall)</option>
                            <option value="<?php echo date('Y'); ?>" selected><?php echo date('Y'); ?> (Current Year)</option>
                        </select>
                    </div>
                </div>
                <div id="categoryBreakdownContainer" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                    <div style="padding: 24px; text-align: center; color: #94A3B8; grid-column: 1 / -1;">Loading costing analytics...</div>
                </div>
            </div>

            <!-- Tab 2: Submit Request Form (Dynamic Custom Input & Optional Proof Upload) -->
            <div id="memberTabSubmit" class="member-tab-pane" style="padding: 28px; display: none;">
                <div style="margin-bottom: 20px;">
                    <h3 style="font-family: var(--font-heading); font-size: 18px; color: #1E293B; margin-bottom: 4px;">Issue Expense, Income, Booking or Custom Request</h3>
                    <p style="font-size: 13px; color: #64748B;">Submit requests to the Mandal Admin. You can type custom types/categories and optionally attach photo/PDF receipts.</p>
                </div>

                <!-- Bug 3 Fix: Alert banner lives INSIDE the Submit tab so it's always visible after submit -->
                <div id="memberAlertBanner" class="alert-banner" style="display: none; margin-bottom: 16px;"></div>

                <form id="memberRequestForm" method="POST" enctype="multipart/form-data">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 16px;">
                        <!-- Request Type Dropdown -->
                        <div class="form-group">
                            <label class="form-label" for="reqType">Request Type *</label>
                            <div class="input-wrapper">
                                <select id="reqType" name="request_type" class="form-input" style="padding-left: 40px;" required>
                                    <option value="expense">Expense Request</option>
                                    <option value="income">Income / Collection Record</option>
                                    <option value="booking">Event / Hall Booking</option>
                                    <option value="donation">Donation / Sponsorship</option>
                                    <option value="custom">Custom Request Type...</option>
                                </select>
                                <i class="fa-solid fa-layer-group input-icon"></i>
                            </div>
                        </div>

                        <!-- Custom Request Type Input (Hidden by default) -->
                        <div class="form-group" id="customTypeGroup" style="display: none;">
                            <label class="form-label" for="customTypeInput">Specify Custom Type *</label>
                            <div class="input-wrapper">
                                <input type="text" id="customTypeInput" name="custom_request_type" class="form-input" placeholder="e.g. Maintenance, Sound Equipment">
                                <i class="fa-solid fa-pen input-icon"></i>
                            </div>
                        </div>

                        <!-- Category Dropdown -->
                        <div class="form-group">
                            <label class="form-label" for="reqCategory">Category / Purpose *</label>
                            <div class="input-wrapper">
                                <select id="reqCategory" name="category" class="form-input" style="padding-left: 40px;" required>
                                    <option value="Decoration">Decoration &amp; Pandal</option>
                                    <option value="Prasad &amp; Catering">Prasad &amp; Food Catering</option>
                                    <option value="Sound &amp; Lights">Sound System &amp; DJ Lights</option>
                                    <option value="Electricity &amp; Maintenance">Electricity &amp; Repairs</option>
                                    <option value="Visarjan Procession">Visarjan Procession &amp; Band</option>
                                    <option value="custom">Custom Purpose...</option>
                                </select>
                                <i class="fa-solid fa-tags input-icon"></i>
                            </div>
                        </div>

                        <!-- Custom Category Input (Hidden by default) -->
                        <div class="form-group" id="customCategoryGroup" style="display: none;">
                            <label class="form-label" for="customCategoryInput">Specify Custom Purpose *</label>
                            <div class="input-wrapper">
                                <input type="text" id="customCategoryInput" name="custom_category" class="form-input" placeholder="e.g. General Cleaning, Security">
                                <i class="fa-solid fa-pen input-icon"></i>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 16px;">
                        <!-- Request Title -->
                        <div class="form-group">
                            <label class="form-label" for="reqTitle">Request Title *</label>
                            <div class="input-wrapper">
                                <input type="text" id="reqTitle" name="title" class="form-input" placeholder="Brief summary of request" required>
                                <i class="fa-solid fa-heading input-icon"></i>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="form-group">
                            <label class="form-label" for="reqAmount">Amount (₹) *</label>
                            <div class="input-wrapper">
                                <input type="number" id="reqAmount" name="amount" class="form-input" step="0.01" min="0" placeholder="0.00" required>
                                <i class="fa-solid fa-indian-rupee-sign input-icon"></i>
                            </div>
                        </div>

                        <!-- Event Date -->
                        <div class="form-group">
                            <label class="form-label" for="reqDate">Transaction / Event Date *</label>
                            <div class="input-wrapper">
                                <input type="date" id="reqDate" name="event_date" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                                <i class="fa-solid fa-calendar-days input-icon"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label" for="reqDescription">Description &amp; Remarks (Optional Donor/Payer Details)</label>
                        <textarea id="reqDescription" name="description" class="form-input" rows="2" placeholder="Provide details, e.g. 'Paid by Ramu Bhai for Sound', 'Donation from Society Member'..." style="height: auto; padding: 12px;"></textarea>
                    </div>

                    <!-- Optional Proof File Upload -->
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" for="reqProofFile">Attach Receipt / Bill Proof (Optional)</label>
                        <div class="input-wrapper">
                            <input type="file" id="reqProofFile" name="proof_file" class="form-input" accept="image/jpeg,image/png,image/webp,application/pdf" style="padding-top: 10px;">
                            <i class="fa-solid fa-paperclip input-icon"></i>
                        </div>
                        <span style="font-size: 11px; color: #64748B; margin-top: 4px; display: block;">Supported formats: JPG, PNG, WEBP, PDF (Max size: 5 MB). Proof attachment is completely optional.</span>
                    </div>

                    <!-- Privacy Flag Toggle -->
                    <div style="background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 12px; padding: 14px 18px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                        <input type="checkbox" id="reqIsHidden" name="is_hidden" value="1" style="width: 18px; height: 18px; accent-color: #DA4D12; cursor: pointer;">
                        <label for="reqIsHidden" style="font-size: 14px; font-weight: 600; color: #92400E; cursor: pointer;">
                            🔒 Request Private / Hidden Visibility <span style="font-weight: normal; font-size: 12px; color: #B45309; display: block;">If checked, this record will be visible ONLY to you and the Mandal Admin (hidden from other members). Note: Admin may choose to make it public upon approval.</span>
                        </label>
                    </div>

                    <button type="submit" class="btn-submit">
                        <span class="btn-text-icon"><i class="fa-solid fa-paper-plane"></i> Submit Request for Admin Approval</span>
                        <span class="btn-spinner"></span>
                    </button>
                </form>
            </div>

            <!-- Tab 3: My Submissions List -->
            <div id="memberTabSubmissions" class="member-tab-pane" style="padding: 24px; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="font-family: var(--font-heading); font-size: 18px; color: #1E293B; margin: 0;">My Submitted Requests</h3>
                    <button type="button" class="btn-refresh-member-list" style="padding: 6px 14px; border: 1px solid #CBD5E1; background: #fff; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; color: #475569; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-arrows-rotate"></i> Refresh
                    </button>
                </div>
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="admin-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                        <thead>
                            <tr style="background: #F1F5F9; color: #475569; border-bottom: 2px solid #E2E8F0;">
                                <th style="padding: 12px 16px;">Type &amp; Category</th>
                                <th style="padding: 12px 16px;">Title</th>
                                <th style="padding: 12px 16px;">Amount</th>
                                <th style="padding: 12px 16px;">Date</th>
                                <th style="padding: 12px 16px;">Proof</th>
                                <th style="padding: 12px 16px;">Visibility</th>
                                <th style="padding: 12px 16px;">Status</th>
                            </tr>
                        </thead>
                        <tbody id="tableMySubmissionsBody">
                            <tr><td colspan="7" style="text-align: center; padding: 24px; color: #94A3B8;">Loading your submissions...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 4: Mandal Public Ledger (Multi-Year Hissab + Live Search & Sort) -->
            <div id="memberTabLedger" class="member-tab-pane" style="padding: 24px; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                    <div>
                        <h3 style="font-family: var(--font-heading); font-size: 18px; color: #1E293B; margin: 0;">Mandal Public Financial &amp; Booking Feed</h3>
                        <p style="font-size: 13px; color: #64748B; margin-top: 2px;">Showing approved transparent records with payer/donor details and attachments across years.</p>
                    </div>
                </div>

                <!-- Search, Year, Type & Sort Controls Toolbar -->
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; background: #F8FAFC; padding: 14px; border-radius: 12px; border: 1px solid #E2E8F0; align-items: center;">
                    <!-- Keyword Search -->
                    <div style="flex: 1 1 200px; min-width: 180px; position: relative;">
                        <input type="text" id="publicLedgerSearchInput" placeholder="Search title, donor, payee, category..." style="width: 100%; padding: 8px 12px 8px 36px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 11px; color: #94A3B8; font-size: 13px;"></i>
                    </div>

                    <!-- Year Filter -->
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <label for="publicLedgerYearFilter" style="font-size: 12px; font-weight: 700; color: #475569;">Year:</label>
                        <select id="publicLedgerYearFilter" style="padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; background: #fff; cursor: pointer;">
                            <option value="all">All Years</option>
                            <option value="<?php echo date('Y'); ?>" selected><?php echo date('Y'); ?></option>
                        </select>
                    </div>

                    <!-- Type Filter -->
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <label for="publicLedgerTypeFilter" style="font-size: 12px; font-weight: 700; color: #475569;">Type:</label>
                        <select id="publicLedgerTypeFilter" style="padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; background: #fff; cursor: pointer;">
                            <option value="all">All Records</option>
                            <option value="income_group">Collections &amp; Donations</option>
                            <option value="expense_group">Expenses</option>
                            <option value="booking">Bookings</option>
                        </select>
                    </div>

                    <!-- Sort Filter -->
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <label for="publicLedgerSortBy" style="font-size: 12px; font-weight: 700; color: #475569;">Sort:</label>
                        <select id="publicLedgerSortBy" style="padding: 8px 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; background: #fff; cursor: pointer;">
                            <option value="date_desc">Newest Date</option>
                            <option value="date_asc">Oldest Date</option>
                            <option value="amount_desc">Highest Amount</option>
                            <option value="amount_asc">Lowest Amount</option>
                        </select>
                    </div>

                    <button type="button" id="btnFilterPublicLedger" style="padding: 8px 14px; background: #DA4D12; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
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
                                <th style="padding: 12px 16px;">Proof Attachment</th>
                                <th style="padding: 12px 16px; text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="tablePublicLedgerBody">
                            <tr><td colspan="6" style="text-align: center; padding: 24px; color: #94A3B8;">Loading public ledger...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
