<?php
/**
 * Sudarshan Yuvak Mandal - Enterprise Login & Signup Portal
 * Location: Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat
 */

require_once __DIR__ . '/config/db.php';

// Redirect if already logged in based on role
if (isset($_SESSION['user_id'])) {
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        header('Location: admin_dashboard.php');
        exit;
    } else if (($_SESSION['membership_status'] ?? '') === 'approved') {
        header('Location: dashboard.php');
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

$noticeMessage = '';
$noticeType = '';

if (isset($_GET['registered'])) {
    $noticeMessage = '✅ Email verified successfully! Your application is pending approval by the Mandal Admin. You will be able to log in once approved.';
    $noticeType = 'success';
} else if (isset($_GET['error']) && $_GET['error'] === 'pending_approval') {
    $noticeMessage = '⏳ Your registration is pending approval by the Mandal Admin. Please check back later.';
    $noticeType = 'warning';
} else if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $noticeMessage = '🔒 You do not have permission to access that resource.';
    $noticeType = 'error';
} else if (isset($_GET['error']) && $_GET['error'] === 'auth_required') {
    $noticeMessage = '🔒 Please log in to access the portal.';
    $noticeType = 'warning';
} else if (isset($_GET['reset_success'])) {
    $noticeMessage = '🔑 Password reset successfully! Please login with your new password.';
    $noticeType = 'success';
} else if (isset($_GET['logout'])) {
    $noticeMessage = '👋 You have been securely logged out.';
    $noticeType = 'success';
} else if (isset($_GET['error']) && $_GET['error'] === 'google_not_configured') {
    $noticeMessage = '⚙️ Google OAuth 2.0 is not configured yet. Set GOOGLE_CLIENT_ID & GOOGLE_CLIENT_SECRET in .env file.';
    $noticeType = 'warning';
} else if (isset($_GET['error']) && $_GET['error'] === 'github_not_configured') {
    $noticeMessage = '⚙️ GitHub OAuth 2.0 is not configured yet. Set GITHUB_CLIENT_ID & GITHUB_CLIENT_SECRET in .env file.';
    $noticeType = 'warning';
} else if (isset($_GET['error']) && $_GET['error'] === 'account_suspended') {
    $noticeMessage = '🚫 Your account has been suspended by the Mandal Admin. Please contact the Mandal office.';
    $noticeType = 'error';
} else if (isset($_GET['error']) && $_GET['error'] === 'account_rejected') {
    $noticeMessage = '❌ Your membership application was rejected by the Mandal Admin.';
    $noticeType = 'error';
} else if (isset($_GET['error']) && $_GET['error'] === 'account_inactive') {
    $noticeMessage = '⚠️ Your account is currently inactive.';
    $noticeType = 'warning';
} else if (isset($_GET['error']) && $_GET['error'] === 'account_locked') {
    $noticeMessage = '🔒 Security Lockout: Account is locked due to security policy. Please try again later.';
    $noticeType = 'error';
} else if (isset($_GET['error']) && $_GET['error'] === 'oauth_no_email') {
    $noticeMessage = '⚠️ Could not fetch email from social account. Please sign up using standard registration.';
    $noticeType = 'error';
} else if (isset($_GET['error']) && in_array($_GET['error'], ['google_token_failed', 'google_user_failed', 'github_token_failed', 'github_user_failed', 'oauth_invalid_state', 'oauth_failed', 'oauth_server_error', 'invalid_oauth_action'])) {
    $noticeMessage = '❌ Social authentication failed or was cancelled. Please try again.';
    $noticeType = 'error';
}
?>

<div class="main-wrapper">
    <div class="portal-container">
        
        <!-- Back to Public Website Link -->
        <div style="text-align: left; margin-bottom: 16px;">
            <a href="index.php" style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #DA4D12; background: rgba(255, 255, 255, 0.85); padding: 8px 16px; border-radius: 20px; border: 1px solid rgba(218, 77, 18, 0.2); box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-decoration: none; transition: transform 0.2s;">
                <i class="fa-solid fa-arrow-left"></i> Back to Public Website
            </a>
        </div>

        <!-- Brand Header Card -->
        <div class="brand-card">
            <div class="brand-emblem-wrapper">
                <div class="brand-emblem-halo"></div>
                <div class="brand-emblem">
                    <i class="fa-solid fa-om"></i>
                </div>
            </div>
            <h1 class="brand-title">Sudarshan Yuvak Mandal</h1>
            <div class="brand-tagline">Ganesh Utsav Member Portal</div>
            <div class="brand-address">
                <i class="fa-solid fa-location-dot"></i> Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat
            </div>
        </div>

        <!-- Glassmorphism Card -->
        <div class="auth-card" id="authCard">
            
            <!-- Navigation Tabs -->
            <div class="auth-tabs" id="authTabs">
                <button type="button" class="tab-btn active" data-tab="login">
                    <i class="fa-solid fa-right-to-bracket"></i> Login
                </button>
                <button type="button" class="tab-btn" data-tab="signup">
                    <i class="fa-solid fa-user-plus"></i> New Registration
                </button>
                <button type="button" class="tab-btn" data-tab="forgot" id="tabForgotBtn" style="display: none;">
                    <i class="fa-solid fa-unlock-keyhole"></i> Reset Password
                </button>
            </div>

            <!-- Page Alert Banner -->
            <div id="pageAlertBanner" class="alert-banner <?php echo $noticeType; ?>" style="<?php echo !empty($noticeMessage) ? 'display:flex;' : 'display:none;'; ?>">
                <?php if (!empty($noticeMessage)): ?>
                    <i class="fa-solid <?php echo $noticeType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
                    <span><?php echo htmlspecialchars($noticeMessage); ?></span>
                <?php endif; ?>
            </div>

            <!-- 1. LOGIN FORM -->
            <form id="loginForm" class="auth-form" method="POST" autocomplete="off">
                <div class="form-group">
                    <label class="form-label" for="loginEmail">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" id="loginEmail" name="email" class="form-input" placeholder="name@example.com" required autocomplete="username">
                        <i class="fa-solid fa-envelope input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="loginPassword">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="loginPassword" name="password" class="form-input" placeholder="Enter your password" required autocomplete="current-password">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <button type="button" class="toggle-password" data-target="loginPassword">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <div style="text-align: right; margin-top: 6px;">
                        <a href="#" id="linkForgotPassword" class="forgot-pass-link">
                            <i class="fa-solid fa-key" style="font-size: 11px;"></i> Forgot Password?
                        </a>
                    </div>
                </div>

                <!-- CAPTCHA Verification -->
                <div class="captcha-container">
                    <div class="captcha-header">
                        <span class="form-label" style="margin:0;">Security Verification</span>
                        <button type="button" class="btn-refresh-captcha" title="Refresh Captcha Code">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                    </div>
                    <div class="captcha-image-wrapper form-group" style="margin-bottom: 10px;">
                        <img src="includes/captcha.php" alt="CAPTCHA Code" class="captcha-img">
                    </div>
                    <div class="input-wrapper">
                        <input type="text" name="captcha_input" class="form-input" style="padding-left: 16px;" placeholder="Enter characters shown above" maxlength="5" required autocomplete="off">
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <span class="btn-text-icon"><i class="fa-solid fa-shield-halved"></i> Verify & Proceed with OTP</span>
                    <span class="btn-spinner"></span>
                </button>

                <!-- Social OAuth SSO Section -->
                <div class="oauth-divider">
                    <span>OR CONTINUE WITH SOCIAL SSO</span>
                </div>
                <div class="oauth-buttons-grid">
                    <a href="api/oauth_handler.php?action=google_login" class="btn-oauth btn-google" title="Sign in with Google">
                        <svg class="oauth-icon" viewBox="0 0 24 24" width="18" height="18">
                            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.11-6.72-4.96H1.29v3.15C3.26 21.3 7.31 24 12 24z"/>
                            <path fill="#FBBC05" d="M5.28 14.24c-.25-.72-.38-1.49-.38-2.24s.13-1.52.38-2.24V6.61H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.39l3.99-3.15z"/>
                            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.61l3.99 3.15c.95-2.85 3.6-4.96 6.72-4.96z"/>
                        </svg>
                        <span>Google</span>
                    </a>
                    <a href="api/oauth_handler.php?action=github_login" class="btn-oauth btn-github" title="Sign in with GitHub">
                        <i class="fa-brands fa-github oauth-icon" style="font-size: 18px;"></i>
                        <span>GitHub</span>
                    </a>
                </div>
            </form>

            <!-- 2. SIGNUP FORM -->
            <form id="signupForm" class="auth-form" method="POST" autocomplete="off" style="display: none;">
                <div class="form-group">
                    <label class="form-label" for="signupName">Full Name</label>
                    <div class="input-wrapper">
                        <input type="text" id="signupName" name="full_name" class="form-input" placeholder="e.g. Rahul Patel" required autocomplete="name">
                        <i class="fa-solid fa-user input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="signupEmail">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" id="signupEmail" name="email" class="form-input" placeholder="name@example.com" required autocomplete="email">
                        <i class="fa-solid fa-envelope input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="signupPhone">Mobile Number (10 Digits)</label>
                    <div class="input-wrapper">
                        <input type="tel" id="signupPhone" name="phone" class="form-input" placeholder="9876543210" pattern="[0-9]{10}" maxlength="10" required autocomplete="tel">
                        <i class="fa-solid fa-mobile-screen-button input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="signupPassword">Create Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="signupPassword" name="password" class="form-input" placeholder="Min. 8 characters" minlength="8" required autocomplete="new-password">
                        <i class="fa-solid fa-key input-icon"></i>
                        <button type="button" class="toggle-password" data-target="signupPassword">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <!-- Password Strength Meter -->
                    <div class="password-strength-box" id="passwordStrengthBox">
                        <div class="strength-header">
                            <span class="strength-label-text" id="strengthLabelText">Strength: <strong>Too Short</strong></span>
                        </div>
                        <div class="strength-bar-wrapper">
                            <div class="strength-bar-fill" id="strengthBarFill"></div>
                        </div>
                        <div class="password-checklist">
                            <span class="chk-item" id="chkLength"><i class="fa-solid fa-circle-notch"></i> 8+ Chars</span>
                            <span class="chk-item" id="chkUpper"><i class="fa-solid fa-circle-notch"></i> Uppercase</span>
                            <span class="chk-item" id="chkNumber"><i class="fa-solid fa-circle-notch"></i> Number</span>
                            <span class="chk-item" id="chkSpecial"><i class="fa-solid fa-circle-notch"></i> Symbol</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="signupConfirmPassword">Confirm Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="signupConfirmPassword" name="confirm_password" class="form-input" placeholder="Re-enter password" required autocomplete="new-password">
                        <i class="fa-solid fa-check-double input-icon"></i>
                        <button type="button" class="toggle-password" data-target="signupConfirmPassword">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <div id="passwordMatchStatus" class="password-match-hint" style="display: none;"></div>
                </div>

                <!-- CAPTCHA Verification -->
                <div class="captcha-container">
                    <div class="captcha-header">
                        <span class="form-label" style="margin:0;">Security Verification</span>
                        <button type="button" class="btn-refresh-captcha" title="Refresh Captcha Code">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                    </div>
                    <div class="captcha-image-wrapper form-group" style="margin-bottom: 10px;">
                        <img src="includes/captcha.php" alt="CAPTCHA Code" class="captcha-img">
                    </div>
                    <div class="input-wrapper">
                        <input type="text" name="captcha_input" class="form-input" style="padding-left: 16px;" placeholder="Enter characters shown above" maxlength="5" required autocomplete="off">
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <span class="btn-text-icon"><i class="fa-solid fa-paper-plane"></i> Send Email Verification OTP</span>
                    <span class="btn-spinner"></span>
                </button>

                <!-- Social OAuth SSO Section -->
                <div class="oauth-divider">
                    <span>OR REGISTER WITH SOCIAL SSO</span>
                </div>
                <div class="oauth-buttons-grid">
                    <a href="api/oauth_handler.php?action=google_login" class="btn-oauth btn-google" title="Sign up with Google">
                        <svg class="oauth-icon" viewBox="0 0 24 24" width="18" height="18">
                            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.11-6.72-4.96H1.29v3.15C3.26 21.3 7.31 24 12 24z"/>
                            <path fill="#FBBC05" d="M5.28 14.24c-.25-.72-.38-1.49-.38-2.24s.13-1.52.38-2.24V6.61H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.39l3.99-3.15z"/>
                            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.61l3.99 3.15c.95-2.85 3.6-4.96 6.72-4.96z"/>
                        </svg>
                        <span>Google</span>
                    </a>
                    <a href="api/oauth_handler.php?action=github_login" class="btn-oauth btn-github" title="Sign up with GitHub">
                        <i class="fa-brands fa-github oauth-icon" style="font-size: 18px;"></i>
                        <span>GitHub</span>
                    </a>
                </div>
            </form>

            <!-- 3. FORGOT PASSWORD FORM -->
            <form id="forgotForm" class="auth-form" method="POST" autocomplete="off" style="display: none;">
                <div style="margin-bottom: 16px; text-align: center;">
                    <h3 style="font-family: var(--font-heading); font-size: 18px; margin-bottom: 4px; color: var(--text-main);">Reset Account Password</h3>
                    <p style="font-size: 13px; color: var(--text-muted);">Enter your registered email address to receive a password reset OTP.</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="forgotEmail">Registered Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" id="forgotEmail" name="email" class="form-input" placeholder="name@example.com" required autocomplete="email">
                        <i class="fa-solid fa-envelope input-icon"></i>
                    </div>
                </div>

                <!-- CAPTCHA Verification -->
                <div class="captcha-container">
                    <div class="captcha-header">
                        <span class="form-label" style="margin:0;">Security Verification</span>
                        <button type="button" class="btn-refresh-captcha" title="Refresh Captcha Code">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                    </div>
                    <div class="captcha-image-wrapper form-group" style="margin-bottom: 10px;">
                        <img src="includes/captcha.php" alt="CAPTCHA Code" class="captcha-img">
                    </div>
                    <div class="input-wrapper">
                        <input type="text" name="captcha_input" class="form-input" style="padding-left: 16px;" placeholder="Enter characters shown above" maxlength="5" required autocomplete="off">
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <span class="btn-text-icon"><i class="fa-solid fa-paper-plane"></i> Send Password Reset OTP</span>
                    <span class="btn-spinner"></span>
                </button>

                <div style="text-align: center; margin-top: 16px;">
                    <a href="#" id="linkBackToLogin" class="forgot-pass-link">
                        <i class="fa-solid fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- 4. OTP VERIFICATION MODAL -->
<div id="otpModal" class="modal-overlay">
    <div class="otp-modal-card">
        <div class="otp-icon-header">
            <i class="fa-solid fa-envelope-circle-check"></i>
        </div>
        <h3 class="otp-title" id="otpModalTitle">Email OTP Verification</h3>
        <p class="otp-subtitle">Enter 6-digit code sent to <span id="modalOtpEmail" class="otp-email-highlight">user@example.com</span></p>

        <div id="modalAlertBanner" class="alert-banner"></div>

        <form id="otpVerifyForm" method="POST">
            <div class="otp-inputs-grid">
                <input type="text" class="otp-digit-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" required>
                <input type="text" class="otp-digit-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" required>
                <input type="text" class="otp-digit-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" required>
                <input type="text" class="otp-digit-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" required>
                <input type="text" class="otp-digit-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" required>
                <input type="text" class="otp-digit-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" required>
            </div>

            <!-- Remember Me for 30 Days Checkbox -->
            <div id="rememberMeBox" style="margin: 14px 0; text-align: left; background: #F8FAFC; padding: 10px 14px; border-radius: 10px; border: 1px solid #E2E8F0; display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" id="chkRememberMe" name="remember_me" value="1" style="width: 16px; height: 16px; accent-color: #DA4D12; cursor: pointer;">
                <label for="chkRememberMe" style="font-size: 13px; font-weight: 600; color: #334155; cursor: pointer;">
                    Keep me logged in for 30 days <span style="font-weight: normal; font-size: 11px; color: #64748B; display: block;">(Saves OTP costs & avoids re-logging in on this device)</span>
                </label>
            </div>

            <!-- Password Reset Fields (Only active for Password Reset flow) -->
            <div id="resetPasswordFields" style="display: none; margin-top: 16px;">
                <div class="form-group" style="text-align: left;">
                    <label class="form-label" for="resetNewPassword">New Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="resetNewPassword" name="new_password" class="form-input" placeholder="Min. 8 characters" minlength="8" autocomplete="new-password">
                        <i class="fa-solid fa-key input-icon"></i>
                        <button type="button" class="toggle-password" data-target="resetNewPassword">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <!-- Password Strength Meter for Reset Password -->
                    <div class="password-strength-box" id="resetStrengthBox" style="margin-top: 8px;">
                        <div class="strength-header">
                            <span class="strength-label-text" id="resetStrengthLabelText">Strength: <strong>Too Short</strong></span>
                        </div>
                        <div class="strength-bar-wrapper">
                            <div class="strength-bar-fill" id="resetStrengthBarFill"></div>
                        </div>
                        <div class="password-checklist">
                            <span class="chk-item" id="resetChkLength"><i class="fa-solid fa-circle-notch"></i> 8+ Chars</span>
                            <span class="chk-item" id="resetChkUpper"><i class="fa-solid fa-circle-notch"></i> Uppercase</span>
                            <span class="chk-item" id="resetChkNumber"><i class="fa-solid fa-circle-notch"></i> Number</span>
                            <span class="chk-item" id="resetChkSpecial"><i class="fa-solid fa-circle-notch"></i> Symbol</span>
                        </div>
                    </div>
                </div>
                <div class="form-group" style="text-align: left;">
                    <label class="form-label" for="resetConfirmPassword">Confirm New Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="resetConfirmPassword" name="confirm_new_password" class="form-input" placeholder="Re-enter new password" autocomplete="new-password">
                        <i class="fa-solid fa-check-double input-icon"></i>
                        <button type="button" class="toggle-password" data-target="resetConfirmPassword">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <div id="resetPasswordMatchStatus" class="password-match-hint" style="display: none;"></div>
                </div>
            </div>

            <div class="otp-timer-box">
                <i class="fa-regular fa-clock"></i>
                <span>OTP expires in <strong id="otpTimerDisplay" class="timer-countdown">05:00</strong></span>
            </div>

            <button type="submit" class="btn-submit">
                <span class="btn-text-icon"><i class="fa-solid fa-circle-check"></i> Verify OTP & Proceed</span>
                <span class="btn-spinner"></span>
            </button>
        </form>

        <div class="resend-section">
            Didn't receive the OTP? 
            <button type="button" id="btnResendOTP" class="btn-resend-otp" disabled>
                Resend OTP <span id="resendCooldownDisplay">(60s)</span>
            </button>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
