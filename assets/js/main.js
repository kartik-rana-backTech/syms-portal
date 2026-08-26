/**
 * Sudarshan Yuvak Mandal - Enterprise Frontend Controller & AJAX Manager
 * Anti-Double Submission, Dynamic CAPTCHA, OTP Modal & Countdown Timers
 */

(function () {
    'use strict';

    // Global Request Lock against double-submission
    let isSubmitting = false;

    // Global HTML Escape Utility
    function escapeHTML(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Timers
    let expiryInterval = null;
    let resendInterval = null;
    let currentEmail = '';
    let currentPurpose = 'signup';

    document.addEventListener('DOMContentLoaded', () => {
        initTabs();
        initForms();
        initCaptchaRefresh();
        initOTPInputs();
        initPasswordToggle();
        initPasswordValidation();
        initMemberDashboard();
        initAdminDashboard();
    });

    // -------------------------------------------------------------
    // 1. Tab Navigation (Login vs Signup)
    // -------------------------------------------------------------
    function initTabs() {
        const tabBtns = document.querySelectorAll('.tab-btn');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const targetTab = btn.getAttribute('data-tab');
                switchTab(targetTab);
            });
        });

        const forgotLink = document.getElementById('linkForgotPassword');
        const backLoginLink = document.getElementById('linkBackToLogin');

        if (forgotLink) {
            forgotLink.addEventListener('click', (e) => {
                e.preventDefault();
                switchTab('forgot');
            });
        }

        if (backLoginLink) {
            backLoginLink.addEventListener('click', (e) => {
                e.preventDefault();
                switchTab('login');
            });
        }
    }

    function switchTab(targetTab) {
        const tabBtns = document.querySelectorAll('.tab-btn');
        const forms = document.querySelectorAll('.auth-form');

        tabBtns.forEach(b => {
            if (b.getAttribute('data-tab') === targetTab) {
                b.classList.add('active');
                if (targetTab === 'forgot') b.style.display = 'inline-flex';
            } else {
                b.classList.remove('active');
                if (b.id === 'tabForgotBtn' && targetTab !== 'forgot') {
                    b.style.display = 'none';
                }
            }
        });

        forms.forEach(f => {
            lockFormFields(f, false);
            if (f.id === targetTab + 'Form') {
                f.style.display = 'block';
            } else {
                f.style.display = 'none';
            }
        });

        hideAlerts();
        refreshCaptcha();
    }

    // -------------------------------------------------------------
    // 2. Form Submissions with Anti-Double-Submit Lock
    // -------------------------------------------------------------
    function initForms() {
        const signupForm = document.getElementById('signupForm');
        const loginForm = document.getElementById('loginForm');
        const forgotForm = document.getElementById('forgotForm');
        const otpForm = document.getElementById('otpVerifyForm');
        const resendBtn = document.getElementById('btnResendOTP');

        if (signupForm) {
            signupForm.addEventListener('submit', (e) => {
                e.preventDefault();
                // Bug 2 Fix: Enforce strong password before submit
                const passVal = document.getElementById('signupPassword')?.value || '';
                if (!isPasswordStrong(passVal)) {
                    showAlert('error', '🔒 Password is too weak. It must have 8+ characters, an uppercase letter, a number, and a symbol.');
                    document.getElementById('signupPassword')?.focus();
                    return;
                }
                handleFormSubmit(signupForm, 'signup_init', (data) => {
                    currentEmail = data.email;
                    currentPurpose = 'signup';
                    openOTPModal(data);
                });
            });
        }

        if (loginForm) {
            loginForm.addEventListener('submit', (e) => {
                e.preventDefault();
                handleFormSubmit(loginForm, 'login_init', (data) => {
                    currentEmail = data.email;
                    currentPurpose = 'login';
                    openOTPModal(data);
                });
            });
        }

        if (forgotForm) {
            forgotForm.addEventListener('submit', (e) => {
                e.preventDefault();
                handleFormSubmit(forgotForm, 'forgot_init', (data) => {
                    currentEmail = data.email;
                    currentPurpose = 'reset';
                    openOTPModal(data);
                });
            });
        }

        if (otpForm) {
            otpForm.addEventListener('submit', (e) => {
                e.preventDefault();
                
                // Collect 6-digit OTP
                const digits = Array.from(document.querySelectorAll('.otp-digit-input')).map(input => input.value.trim()).join('');
                if (digits.length !== 6) {
                    showModalAlert('error', 'Please enter all 6 digits of the OTP.');
                    return;
                }

                let action = 'signup_verify';
                const payload = { email: currentEmail, otp_code: digits };

                if (currentPurpose === 'login') {
                    action = 'login_verify';
                    // Bug 5 Fix: Remember Me checkbox is outside #otpVerifyForm — must read it explicitly
                    const rememberMeChk = document.getElementById('chkRememberMe');
                    if (rememberMeChk && rememberMeChk.checked) {
                        payload.remember_me = '1';
                    }
                } else if (currentPurpose === 'reset') {
                    action = 'forgot_verify';
                    const newPass = document.getElementById('resetNewPassword').value;
                    const confirmPass = document.getElementById('resetConfirmPassword').value;
                    
                    // Bug 2 Fix: Enforce strong password on reset too
                    if (!isPasswordStrong(newPass)) {
                        showModalAlert('error', '🔒 New password is too weak. It must have 8+ characters, an uppercase letter, a number, and a symbol.');
                        document.getElementById('resetNewPassword')?.focus();
                        return;
                    }
                    if (newPass !== confirmPass) {
                        showModalAlert('error', 'New passwords do not match.');
                        return;
                    }
                    payload.new_password = newPass;
                    payload.confirm_new_password = confirmPass;
                }

                const submitBtn = otpForm.querySelector('.btn-submit');
                
                handleGenericAction(action, payload, submitBtn, (data) => {
                    showModalAlert('success', data.message || 'Verification successful!');
                    setTimeout(() => {
                        window.location.href = data.redirect || 'dashboard.php';
                    }, 1200);
                }, (errorMsg, resData) => {
                    if (resData && (resData.status === 'lockout' || resData.is_locked || (resData.attempts_left !== undefined && resData.attempts_left <= 0))) {
                        closeOTPModal();
                        showAlert('error', errorMsg || '🔒 Security Lockout: Account/Mobile number locked for 15 minutes due to 3 invalid OTP attempts.');
                    } else {
                        showModalAlert('error', errorMsg);
                    }
                });
            });
        }

        if (resendBtn) {
            resendBtn.addEventListener('click', () => {
                if (resendBtn.disabled) return;
                
                handleGenericAction('otp_resend', { email: currentEmail, purpose: currentPurpose }, resendBtn, (data) => {
                    showModalAlert('success', data.message || 'New OTP sent!');

                    // Re-enable inputs & submit button on resend
                    document.querySelectorAll('.otp-digit-input').forEach(i => {
                        i.value = '';
                        i.disabled = false;
                    });
                    const modalSubmitBtn = document.querySelector('#otpVerifyForm .btn-submit');
                    if (modalSubmitBtn) modalSubmitBtn.disabled = false;

                    startTimers(data.expires_in || 300, data.resend_cooldown || 60);
                }, (errorMsg) => {
                    showModalAlert('error', errorMsg);
                });
            });
        }
    }

    // Bug 2 Fix: Password strength gate utility (all 4 criteria must pass)
    function isPasswordStrong(val) {
        const hasLen     = val.length >= 8;
        const hasUpper   = /[A-Z]/.test(val);
        const hasNum     = /[0-9]/.test(val);
        const hasSpec    = /[^A-Za-z0-9]/.test(val);
        return hasLen && hasUpper && hasNum && hasSpec;
    }

    // Bug 4 Fix: Safe JSON parser — handles PHP warnings/notices prepended before JSON
    async function safeParseJson(response) {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch {
            // PHP may have echoed a warning before the JSON — strip non-JSON prefix
            const jsonStart = text.indexOf('{');
            if (jsonStart !== -1) {
                try { return JSON.parse(text.slice(jsonStart)); } catch { /* fall through */ }
            }
            return { status: 'error', message: 'Server returned an unexpected response. Please try again.' };
        }
    }

    // Generic Form Handler with Anti-Double Submit Lock
    function handleFormSubmit(formElement, actionName, onSuccess) {
        if (isSubmitting) {
            return;
        }

        const submitBtn = formElement.querySelector('.btn-submit');
        const formData = new FormData(formElement);
        formData.append('action', actionName);
        formData.append('csrf_token', window.APP_CONFIG.csrfToken);

        setLoadingState(submitBtn, true);
        lockFormFields(formElement, true);
        hideAlerts();

        // Bug 4 Fix: Use safeParseJson to prevent SyntaxError console spam on PHP warnings
        fetch('api/auth_handler.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => safeParseJson(response))
        .then(data => {
            setLoadingState(submitBtn, false);
            if (data.status === 'success') {
                showAlert('success', data.message);
                if (typeof onSuccess === 'function') {
                    onSuccess(data);
                }
            } else {
                lockFormFields(formElement, false);
                showAlert('error', data.message || 'An error occurred. Please try again.');
                refreshCaptcha();
            }
        })
        .catch(() => {
            setLoadingState(submitBtn, false);
            lockFormFields(formElement, false);
            showAlert('error', 'Network connection error. Please check your connection.');
            refreshCaptcha();
        });
    }

    // Generic Action Handler for Modal & Resend (Bug 4 Fix: safe JSON parsing)
    function handleGenericAction(actionName, payload, btnElement, onSuccess, onError) {
        if (isSubmitting) return;

        const formData = new FormData();
        formData.append('action', actionName);
        formData.append('csrf_token', window.APP_CONFIG.csrfToken);
        for (const key in payload) {
            formData.append(key, payload[key]);
        }

        if (btnElement) setLoadingState(btnElement, true);

        fetch('api/auth_handler.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => safeParseJson(res))
        .then(data => {
            if (btnElement) setLoadingState(btnElement, false);
            if (data && data.status === 'success') {
                if (typeof onSuccess === 'function') onSuccess(data);
            } else {
                const msg = (data && data.message) ? data.message : 'Verification failed. Please try again.';
                if (typeof onError === 'function') onError(msg, data || {});
            }
        })
        .catch(() => {
            if (btnElement) setLoadingState(btnElement, false);
            if (typeof onError === 'function') onError('Network connection error. Please try again.', { status: 'error' });
        });
    }

    // Anti-Double Submit Button State Controller
    function setLoadingState(btn, loading) {
        if (!btn) return;
        if (loading) {
            isSubmitting = true;
            btn.disabled = true;
            btn.classList.add('loading');
        } else {
            isSubmitting = false;
            btn.disabled = false;
            btn.classList.remove('loading');
        }
    }

    // -------------------------------------------------------------
    // 3. CAPTCHA Refresh Controller
    // -------------------------------------------------------------
    function initCaptchaRefresh() {
        const refreshBtns = document.querySelectorAll('.btn-refresh-captcha');
        refreshBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                refreshCaptcha();
            });
        });
    }

    function refreshCaptcha() {
        const captchaImgs = document.querySelectorAll('.captcha-img');
        const timestamp = new Date().getTime();
        captchaImgs.forEach(img => {
            img.src = 'includes/captcha.php?t=' + timestamp;
        });
        // Clear captcha input fields
        document.querySelectorAll('input[name="captcha_input"]').forEach(input => input.value = '');
    }

    // -------------------------------------------------------------
    // 4. OTP Inputs Auto-Advance & Paste Logic
    // -------------------------------------------------------------
    function initOTPInputs() {
        const otpInputs = document.querySelectorAll('.otp-digit-input');

        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const val = input.value.replace(/[^0-9]/g, '');
                input.value = val;

                if (val && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
                if (pastedData.length === 6) {
                    for (let i = 0; i < 6; i++) {
                        otpInputs[i].value = pastedData[i];
                    }
                    otpInputs[5].focus();
                }
            });
        });
    }

    // -------------------------------------------------------------
    // 5. OTP Modal & Countdown Timers
    // -------------------------------------------------------------
    function openOTPModal(data) {
        const modal = document.getElementById('otpModal');
        const emailSpan = document.getElementById('modalOtpEmail');
        const modalTitle = document.getElementById('otpModalTitle');
        const resetPassFields = document.getElementById('resetPasswordFields');
        
        if (emailSpan) emailSpan.textContent = data.email;

        if (currentPurpose === 'reset') {
            if (modalTitle) modalTitle.textContent = 'Password Reset OTP Verification';
            if (resetPassFields) resetPassFields.style.display = 'block';
        } else {
            if (modalTitle) modalTitle.textContent = 'Email OTP Verification';
            if (resetPassFields) resetPassFields.style.display = 'none';
        }

        // Lock form & tabs to prevent tab switching or field altering
        const activeForm = document.getElementById(currentPurpose + 'Form');
        lockFormFields(activeForm, true);
        lockTabs(true);
        
        // Reset OTP Input boxes & buttons
        document.querySelectorAll('.otp-digit-input').forEach(i => {
            i.value = '';
            i.disabled = false;
        });
        const modalSubmitBtn = modal.querySelector('.btn-submit');
        if (modalSubmitBtn) modalSubmitBtn.disabled = false;

        hideModalAlert();

        startTimers(data.expires_in || 300, data.resend_cooldown || 60);

        modal.classList.add('active');
        // Focus first OTP digit
        setTimeout(() => {
            const firstInput = document.querySelector('.otp-digit-input');
            if (firstInput) firstInput.focus();
        }, 300);
    }

    function closeOTPModal() {
        const modal = document.getElementById('otpModal');
        if (modal) modal.classList.remove('active');
        clearInterval(expiryInterval);
        clearInterval(resendInterval);

        const activeForm = document.getElementById(currentPurpose + 'Form');
        lockFormFields(activeForm, false);
        lockTabs(false);
        refreshCaptcha();
    }

    function startTimers(expirySeconds, cooldownSeconds) {
        clearInterval(expiryInterval);
        clearInterval(resendInterval);

        const timerDisplay = document.getElementById('otpTimerDisplay');
        const resendBtn = document.getElementById('btnResendOTP');
        const cooldownDisplay = document.getElementById('resendCooldownDisplay');

        // 1. Expiry Countdown (5 mins)
        let expTime = expirySeconds;
        updateExpiryUI(expTime);

        expiryInterval = setInterval(() => {
            expTime--;
            updateExpiryUI(expTime);
            if (expTime <= 0) {
                clearInterval(expiryInterval);
                closeOTPModal();
                showAlert('warning', 'OTP Expired: The 5-minute verification window has expired. Please submit security verification again to receive a fresh OTP.');
            }
        }, 1000);

        function updateExpiryUI(s) {
            if (!timerDisplay) return;
            const mins = Math.floor(s / 60);
            const secs = s % 60;
            timerDisplay.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        // 2. Resend Cooldown Timer (60s)
        let cdTime = cooldownSeconds;
        if (resendBtn) resendBtn.disabled = true;

        resendInterval = setInterval(() => {
            cdTime--;
            if (cooldownDisplay) cooldownDisplay.textContent = `(${cdTime}s)`;
            if (cdTime <= 0) {
                clearInterval(resendInterval);
                if (resendBtn) resendBtn.disabled = false;
                if (cooldownDisplay) cooldownDisplay.textContent = '';
            }
        }, 1000);
    }

    // Password Visibility Toggle
    function initPasswordToggle() {
        const toggleBtns = document.querySelectorAll('.toggle-password');
        toggleBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.getAttribute('data-target');
                const input = document.getElementById(targetId);
                if (input) {
                    if (input.type === 'password') {
                        input.type = 'text';
                        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
                    } else {
                        input.type = 'password';
                        btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
                    }
                }
            });
        });
    }

    // Alert Utilities
    function showAlert(type, message) {
        const alertBox = document.getElementById('pageAlertBanner');
        if (!alertBox) return;
        const safeMsg = (message && typeof message === 'string') ? message : 'An error occurred. Please try again.';
        alertBox.className = 'alert-banner ' + type;
        alertBox.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'}"></i> <span>${safeMsg}</span>`;
        alertBox.style.display = 'flex';
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideAlerts() {
        const alertBox = document.getElementById('pageAlertBanner');
        if (alertBox) alertBox.style.display = 'none';
    }

    function showModalAlert(type, message) {
        const alertBox = document.getElementById('modalAlertBanner');
        if (!alertBox) return;
        const safeMsg = (message && typeof message === 'string') ? message : 'An error occurred. Please try again.';
        alertBox.className = 'alert-banner ' + type;
        alertBox.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'}"></i> <span>${safeMsg}</span>`;
        alertBox.style.display = 'flex';
    }

    function hideModalAlert() {
        const alertBox = document.getElementById('modalAlertBanner');
        if (alertBox) alertBox.style.display = 'none';
    }

    // Form Input & Container Locking Helper
    function lockFormFields(formElement, lock) {
        if (!formElement) return;
        const controls = formElement.querySelectorAll('input:not([type="hidden"]), button');
        controls.forEach(ctrl => {
            if (lock) {
                ctrl.disabled = true;
                ctrl.classList.add('locked');
            } else {
                ctrl.disabled = false;
                ctrl.classList.remove('locked');
            }
        });

        if (lock) {
            formElement.classList.add('form-locked');
            lockTabs(true);
        } else {
            formElement.classList.remove('form-locked');
            lockTabs(false);
        }
    }

    function lockTabs(lock) {
        const tabsContainer = document.getElementById('authTabs');
        if (!tabsContainer) return;
        if (lock) {
            tabsContainer.classList.add('tabs-locked');
            tabsContainer.querySelectorAll('.tab-btn').forEach(b => b.disabled = true);
        } else {
            tabsContainer.classList.remove('tabs-locked');
            tabsContainer.querySelectorAll('.tab-btn').forEach(b => b.disabled = false);
        }
    }

    // Password Strength & Match Validation Engine
    // Password Strength & Match Validation Engine
    function initPasswordValidation() {
        const passInput = document.getElementById('signupPassword');
        const confirmInput = document.getElementById('signupConfirmPassword');
        const matchStatus = document.getElementById('passwordMatchStatus');

        const resetPassInput = document.getElementById('resetNewPassword');
        const resetConfirmInput = document.getElementById('resetConfirmPassword');
        const resetMatchStatus = document.getElementById('resetPasswordMatchStatus');

        if (passInput) {
            passInput.addEventListener('input', () => {
                evaluatePasswordStrength(passInput.value, '');
                if (confirmInput && confirmInput.value.length > 0) {
                    checkPasswordMatch(passInput, confirmInput, matchStatus);
                }
            });
        }

        if (confirmInput) {
            confirmInput.addEventListener('input', () => {
                checkPasswordMatch(passInput, confirmInput, matchStatus);
            });
        }

        if (resetPassInput) {
            resetPassInput.addEventListener('input', () => {
                evaluatePasswordStrength(resetPassInput.value, 'reset');
                if (resetConfirmInput && resetConfirmInput.value.length > 0) {
                    checkPasswordMatch(resetPassInput, resetConfirmInput, resetMatchStatus);
                }
            });
        }

        if (resetConfirmInput) {
            resetConfirmInput.addEventListener('input', () => {
                checkPasswordMatch(resetPassInput, resetConfirmInput, resetMatchStatus);
            });
        }

        function evaluatePasswordStrength(val, prefix = '') {
            const prefixCap = prefix ? prefix.charAt(0).toUpperCase() + prefix.slice(1) : '';
            const barFill = document.getElementById((prefix ? prefix : '') + (prefix ? 'StrengthBarFill' : 'strengthBarFill'));
            const labelText = document.getElementById((prefix ? prefix : '') + (prefix ? 'StrengthLabelText' : 'strengthLabelText'));

            const chkLength = document.getElementById((prefix ? prefix : '') + (prefix ? 'ChkLength' : 'chkLength'));
            const chkUpper = document.getElementById((prefix ? prefix : '') + (prefix ? 'ChkUpper' : 'chkUpper'));
            const chkNumber = document.getElementById((prefix ? prefix : '') + (prefix ? 'ChkNumber' : 'chkNumber'));
            const chkSpecial = document.getElementById((prefix ? prefix : '') + (prefix ? 'ChkSpecial' : 'chkSpecial'));

            const hasLen = val.length >= 8;
            const hasUpper = /[A-Z]/.test(val);
            const hasNum = /[0-9]/.test(val);
            const hasSpec = /[^A-Za-z0-9]/.test(val);

            updateChk(chkLength, hasLen, '8+ Chars');
            updateChk(chkUpper, hasUpper, 'Uppercase');
            updateChk(chkNumber, hasNum, 'Number');
            updateChk(chkSpecial, hasSpec, 'Symbol');

            let score = 0;
            if (hasLen) score++;
            if (hasUpper) score++;
            if (hasNum) score++;
            if (hasSpec) score++;

            if (!barFill || !labelText) return;

            barFill.className = 'strength-bar-fill';
            if (val.length === 0) {
                barFill.style.width = '0%';
                labelText.innerHTML = 'Strength: <strong>Too Short</strong>';
            } else if (score <= 1) {
                barFill.classList.add('weak');
                labelText.innerHTML = 'Strength: <strong style="color:#EF4444;">Weak</strong>';
            } else if (score === 2) {
                barFill.classList.add('fair');
                labelText.innerHTML = 'Strength: <strong style="color:#F59E0B;">Fair</strong>';
            } else if (score === 3) {
                barFill.classList.add('good');
                labelText.innerHTML = 'Strength: <strong style="color:#3B82F6;">Good</strong>';
            } else if (score === 4) {
                barFill.classList.add('strong');
                labelText.innerHTML = 'Strength: <strong style="color:#10B981;">Strong</strong>';
            }
        }

        function updateChk(el, isValid, label) {
            if (!el) return;
            if (isValid) {
                el.classList.add('valid');
                el.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + label;
            } else {
                el.classList.remove('valid');
                el.innerHTML = '<i class="fa-solid fa-circle-notch"></i> ' + label;
            }
        }

        function checkPasswordMatch(p1Input, p2Input, statusEl) {
            if (!p1Input || !p2Input || !statusEl) return;
            const p1 = p1Input.value;
            const p2 = p2Input.value;

            if (p2.length === 0) {
                statusEl.style.display = 'none';
                return;
            }

            statusEl.style.display = 'flex';
            if (p1 === p2) {
                statusEl.className = 'password-match-hint match';
                statusEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> Passwords match';
            } else {
                statusEl.className = 'password-match-hint mismatch';
                statusEl.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Passwords do not match';
            }
        }
    }

    // -------------------------------------------------------------
    // 6. Member Dashboard & Dynamic Request Controller
    // -------------------------------------------------------------
    function initMemberDashboard() {
        const tabNav = document.getElementById('memberTabNav');
        if (!tabNav) return; // Not on Member Dashboard page

        const tabBtns = tabNav.querySelectorAll('.member-tab-btn');
        const tabPanes = document.querySelectorAll('.member-tab-pane');
        const reqForm = document.getElementById('memberRequestForm');
        const refreshSubmissionsBtn = document.querySelector('.btn-refresh-member-list');

        // Dynamic Custom Type & Category Toggles
        const reqTypeSelect = document.getElementById('reqType');
        const customTypeGroup = document.getElementById('customTypeGroup');
        const reqCategorySelect = document.getElementById('reqCategory');
        const customCategoryGroup = document.getElementById('customCategoryGroup');

        if (reqTypeSelect && customTypeGroup) {
            reqTypeSelect.addEventListener('change', () => {
                if (reqTypeSelect.value === 'custom') {
                    customTypeGroup.style.display = 'block';
                    document.getElementById('customTypeInput').required = true;
                } else {
                    customTypeGroup.style.display = 'none';
                    document.getElementById('customTypeInput').required = false;
                }
            });
        }

        if (reqCategorySelect && customCategoryGroup) {
            reqCategorySelect.addEventListener('change', () => {
                if (reqCategorySelect.value === 'custom') {
                    customCategoryGroup.style.display = 'block';
                    document.getElementById('customCategoryInput').required = true;
                } else {
                    customCategoryGroup.style.display = 'none';
                    document.getElementById('customCategoryInput').required = false;
                }
            });
        }

        // Notification Bell
        const btnBell = document.getElementById('btnNotifBell');
        const notifDropdown = document.getElementById('notifDropdown');
        const btnMarkRead = document.getElementById('btnMarkNotifsRead');

        fetchNotifications();
        loadCostingAnalytics();

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetTab = btn.getAttribute('data-tab');

                tabBtns.forEach(b => {
                    b.classList.remove('active');
                    b.style.color = '#64748B';
                    b.style.borderBottomColor = 'transparent';
                });
                btn.classList.add('active');
                btn.style.color = '#DA4D12';
                btn.style.borderBottomColor = '#DA4D12';

                tabPanes.forEach(pane => pane.style.display = 'none');
                const activePane = document.getElementById('memberTab' + targetTab.charAt(0).toUpperCase() + targetTab.slice(1));
                if (activePane) activePane.style.display = 'block';

                if (targetTab === 'analytics') loadCostingAnalytics();
                else if (targetTab === 'submissions') loadMySubmissions();
                else if (targetTab === 'ledger') loadPublicLedger();
            });
        });

        if (refreshSubmissionsBtn) {
            refreshSubmissionsBtn.addEventListener('click', () => {
                loadMySubmissions();
            });
        }

        // Notification Bell Toggle
        if (btnBell && notifDropdown) {
            btnBell.addEventListener('click', (e) => {
                e.stopPropagation();
                if (notifDropdown.style.display === 'block') {
                    notifDropdown.style.display = 'none';
                } else {
                    notifDropdown.style.display = 'block';
                    fetchNotifications();
                }
            });

            document.addEventListener('click', (e) => {
                if (!notifDropdown.contains(e.target) && e.target !== btnBell) {
                    notifDropdown.style.display = 'none';
                }
            });
        }

        if (btnMarkRead) {
            btnMarkRead.addEventListener('click', () => {
                handleMemberAction('mark_notifications_read', {}, () => {
                    fetchNotifications();
                });
            });
        }

        // Request Form Submission (Supports Dynamic Inputs & File Upload)
        if (reqForm) {
            reqForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const submitBtn = reqForm.querySelector('.btn-submit');
                const formData = new FormData(reqForm);
                formData.append('action', 'submit_request');
                formData.append('csrf_token', window.APP_CONFIG.csrfToken);

                setLoadingState(submitBtn, true);
                hideMemberAlert();

                fetch('api/request_handler.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    setLoadingState(submitBtn, false);
                    if (data && data.status === 'success') {
                        showMemberAlert('success', data.message);
                        reqForm.reset();
                        document.getElementById('reqDate').value = new Date().toISOString().split('T')[0];
                        if (customTypeGroup) customTypeGroup.style.display = 'none';
                        if (customCategoryGroup) customCategoryGroup.style.display = 'none';
                        loadCostingAnalytics();
                        loadMySubmissions();
                    } else {
                        showMemberAlert('error', data.message || 'Submission failed.');
                    }
                })
                .catch(err => {
                    setLoadingState(submitBtn, false);
                    showMemberAlert('error', 'Network error. Please try again.');
                    console.error(err);
                });
            });
        }

        // Multi-Year & Search Filter Event Handlers for Member Dashboard
        const analyticsYearSelect = document.getElementById('analyticsYearFilter');
        if (analyticsYearSelect) {
            analyticsYearSelect.addEventListener('change', () => {
                loadCostingAnalytics(analyticsYearSelect.value);
            });
        }

        const btnFilterPublic = document.getElementById('btnFilterPublicLedger');
        const searchInputPublic = document.getElementById('publicLedgerSearchInput');
        const yearSelectPublic = document.getElementById('publicLedgerYearFilter');
        const typeSelectPublic = document.getElementById('publicLedgerTypeFilter');
        const sortSelectPublic = document.getElementById('publicLedgerSortBy');

        if (btnFilterPublic) {
            btnFilterPublic.addEventListener('click', () => loadPublicLedger());
        }
        if (yearSelectPublic) yearSelectPublic.addEventListener('change', () => loadPublicLedger());
        if (typeSelectPublic) typeSelectPublic.addEventListener('change', () => loadPublicLedger());
        if (sortSelectPublic) sortSelectPublic.addEventListener('change', () => loadPublicLedger());
        if (searchInputPublic) {
            searchInputPublic.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') loadPublicLedger();
            });
        }

        function loadCostingAnalytics(selectedYear = null) {
            const yr = selectedYear !== null ? selectedYear : (document.getElementById('analyticsYearFilter')?.value || '');
            handleMemberAction('get_costing_analytics', { year: yr }, (data) => {
                if (data && data.analytics) {
                    const a = data.analytics;
                    const incEl = document.getElementById('analyticsTotalIncome');
                    const expEl = document.getElementById('analyticsTotalExpense');
                    const netEl = document.getElementById('analyticsNetBalance');
                    const catContainer = document.getElementById('categoryBreakdownContainer');

                    if (incEl) incEl.textContent = parseFloat(a.total_income).toFixed(2);
                    if (expEl) expEl.textContent = parseFloat(a.total_expense).toFixed(2);
                    if (netEl) netEl.textContent = parseFloat(a.net_balance).toFixed(2);

                    // Sync Available Years dropdowns across Member tabs if provided
                    if (a.available_years && a.available_years.length > 0) {
                        syncYearDropdowns(['analyticsYearFilter', 'publicLedgerYearFilter'], a.available_years, a.selected_year);
                    }

                    if (catContainer && a.category_breakdown) {
                        if (a.category_breakdown.length === 0) {
                            catContainer.innerHTML = '<div style="padding: 24px; text-align: center; color: #94A3B8; grid-column: 1 / -1;">No approved Mandal expense records available for this selected year.</div>';
                            return;
                        }
                        let html = '';
                        a.category_breakdown.forEach(c => {
                            html += `
                            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 18px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <strong style="font-size: 14px; color: #1E293B;">${escapeHTML(c.category)}</strong>
                                    <span style="font-size: 12px; font-weight: 700; color: #DA4D12;">₹${parseFloat(c.total_amount).toFixed(2)}</span>
                                </div>
                                <div style="width: 100%; height: 6px; background: #E2E8F0; border-radius: 4px; overflow: hidden; margin-bottom: 6px;">
                                    <div style="width: ${c.percentage}%; height: 100%; background: linear-gradient(90deg, #DA4D12 0%, #FF9933 100%);"></div>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 11px; color: #64748B;">
                                    <span>${c.count} items approved</span>
                                    <span>${c.percentage}% of total expenses</span>
                                </div>
                            </div>`;
                        });
                        catContainer.innerHTML = html;
                    }
                }
            });
        }

        function fetchNotifications() {
            handleMemberAction('get_my_notifications', {}, (data) => {
                const badge = document.getElementById('notifBadge');
                const container = document.getElementById('notifListContainer');

                if (data.unread_count > 0 && badge) {
                    badge.textContent = data.unread_count;
                    badge.style.display = 'inline';
                } else if (badge) {
                    badge.style.display = 'none';
                }

                if (container && data.notifications) {
                    if (data.notifications.length === 0) {
                        container.innerHTML = '<div style="padding: 16px; text-align: center; color: #94A3B8; font-size: 13px;">No new notifications.</div>';
                        return;
                    }
                    let html = '';
                    data.notifications.forEach(n => {
                        const bg = n.is_read == 0 ? '#F0FDF4' : '#FFFFFF';
                        html += `
                        <div style="padding: 12px 16px; border-bottom: 1px solid #F1F5F9; background: ${bg};">
                            <div style="font-size: 13px; font-weight: 700; color: #1E293B;">${escapeHTML(n.title)}</div>
                            <div style="font-size: 12px; color: #475569; margin-top: 2px;">${escapeHTML(n.message)}</div>
                            <div style="font-size: 10px; color: #94A3B8; margin-top: 4px;">${escapeHTML(n.created_at)}</div>
                        </div>`;
                    });
                    container.innerHTML = html;
                }
            });
        }

        function loadMySubmissions() {
            const tbody = document.getElementById('tableMySubmissionsBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 24px; color: #94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading submissions...</td></tr>';

            handleMemberAction('get_my_requests', {}, (data) => {
                if (!data.requests || data.requests.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 32px; color: #94A3B8;">No requests submitted yet. Use the "Submit New Request" tab to create one.</td></tr>';
                    return;
                }
                let html = '';
                data.requests.forEach(r => {
                    const badgeClass = r.status === 'approved' ? 'approved' : (r.status === 'rejected' ? 'rejected' : 'pending');
                    const isPrivateBadge = r.is_hidden == 1 ? '<span style="color:#d97706; font-size:11px; font-weight:600;"><i class="fa-solid fa-lock"></i> Private</span>' : '<span style="color:#059669; font-size:11px; font-weight:600;"><i class="fa-solid fa-globe"></i> Public</span>';
                    const amountStr = parseFloat(r.amount) > 0 ? '₹' + parseFloat(r.amount).toFixed(2) : '-';
                    const proofLink = r.proof_file ? `<a href="${escapeHTML(r.proof_file)}" target="_blank" style="color:#0284C7; font-size:12px; font-weight:600; text-decoration:none;"><i class="fa-solid fa-file-lines"></i> View Proof</a>` : '<span style="color:#94A3B8; font-size:12px;">None</span>';
                    const rejectReason = r.rejection_reason ? `<div style="font-size:11px; color:#DC2626; margin-top:2px;">Reason: ${escapeHTML(r.rejection_reason)}</div>` : '';

                    html += `
                    <tr>
                        <td><strong style="color:#1E293B; text-transform:uppercase;">${escapeHTML(r.request_type)}</strong><div style="font-size:12px; color:#64748B;">${escapeHTML(r.category)}</div></td>
                        <td><strong style="color:#1E293B;">${escapeHTML(r.title)}</strong><div style="font-size:12px; color:#64748B;">${escapeHTML(r.description || '-')}</div></td>
                        <td style="font-weight:700; color:#DA4D12;">${amountStr}</td>
                        <td style="font-size:13px; color:#64748B;">${escapeHTML(r.event_date)}</td>
                        <td>${proofLink}</td>
                        <td>${isPrivateBadge}</td>
                        <td><span class="badge-status ${badgeClass}">${escapeHTML(r.status)}</span>${rejectReason}</td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            });
        }

        function syncYearDropdowns(elementIds, years, currentSelected) {
            elementIds.forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                const val = currentSelected || el.value || 'all';
                let opts = '<option value="all">All Years (Overall)</option>';
                years.forEach(y => {
                    opts += `<option value="${y}">${y}</option>`;
                });
                el.innerHTML = opts;
                el.value = val;
            });
        }

        function loadPublicLedger() {
            const tbody = document.getElementById('tablePublicLedgerBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 24px; color: #94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading public ledger...</td></tr>';

            const payload = {
                year: document.getElementById('publicLedgerYearFilter')?.value || '',
                type: document.getElementById('publicLedgerTypeFilter')?.value || '',
                search: document.getElementById('publicLedgerSearchInput')?.value || '',
                sort: document.getElementById('publicLedgerSortBy')?.value || 'date_desc'
            };

            handleMemberAction('get_public_feed', payload, (data) => {
                if (!data.feed || data.feed.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 32px; color: #94A3B8;">No approved public ledger records match your search/filter criteria.</td></tr>';
                    return;
                }
                let html = '';
                data.feed.forEach(r => {
                    const amountStr = parseFloat(r.amount) > 0 ? '₹' + parseFloat(r.amount).toFixed(2) : '-';
                    const privateTag = r.is_hidden == 1 ? ' <span style="font-size:10px; background:#FEF3C7; color:#B45309; padding:2px 6px; border-radius:4px;"><i class="fa-solid fa-lock"></i> Your Private Record</span>' : '';
                    const proofLink = r.proof_file ? `<a href="${escapeHTML(r.proof_file)}" target="_blank" style="color:#0284C7; font-size:12px; font-weight:600; text-decoration:none;"><i class="fa-solid fa-file-lines"></i> View Proof</a>` : '<span style="color:#94A3B8; font-size:12px;">-</span>';

                    // Donor / Payer identification badges
                    let partyBadge = '';
                    const reqLower = (r.request_type || '').toLowerCase();
                    if (['income', 'collection', 'donation', 'sponsorship'].includes(reqLower)) {
                        partyBadge = `<div style="font-size:11px; color:#059669; font-weight:600; margin-top:2px;"><i class="fa-solid fa-hand-holding-dollar"></i> Collection / Donation</div>`;
                    } else if (reqLower === 'booking') {
                        partyBadge = `<div style="font-size:11px; color:#2563EB; font-weight:600; margin-top:2px;"><i class="fa-solid fa-calendar-check"></i> Event Booking</div>`;
                    } else {
                        partyBadge = `<div style="font-size:11px; color:#D97706; font-weight:600; margin-top:2px;"><i class="fa-solid fa-receipt"></i> Expense Submitter</div>`;
                    }

                    html += `
                    <tr>
                        <td style="font-size:13px; color:#64748B; white-space:nowrap;">${escapeHTML(r.event_date)}</td>
                        <td>
                            <div style="font-weight:700; color:#1E293B;">${escapeHTML(r.member_name)} ${privateTag}</div>
                            ${partyBadge}
                        </td>
                        <td><strong style="color:#1E293B; text-transform:uppercase;">${escapeHTML(r.request_type)}</strong><div style="font-size:12px; color:#64748B;">${escapeHTML(r.category)}</div></td>
                        <td><strong style="color:#1E293B;">${escapeHTML(r.title)}</strong><div style="font-size:12px; color:#64748B;">${escapeHTML(r.description || '-')}</div></td>
                        <td>${proofLink}</td>
                        <td style="font-weight:700; color:#DA4D12; text-align:right;">${amountStr}</td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            });
        }

        function handleMemberAction(actionName, payload, onSuccess, onError) {
            const formData = new FormData();
            formData.append('action', actionName);
            formData.append('csrf_token', window.APP_CONFIG.csrfToken);
            for (const k in payload) {
                formData.append(k, payload[k]);
            }

            fetch('api/request_handler.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.status === 'success') {
                    if (typeof onSuccess === 'function') onSuccess(data);
                } else {
                    const msg = (data && data.message) ? data.message : 'Action failed.';
                    if (typeof onError === 'function') onError(msg);
                }
            })
            .catch(err => {
                console.error('Member Request API error:', err);
                if (typeof onError === 'function') onError('Network error. Please try again.');
            });
        }

        function showMemberAlert(type, msg) {
            const alertBox = document.getElementById('memberAlertBanner');
            if (!alertBox) return;
            alertBox.className = 'alert-banner ' + type;
            alertBox.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'}"></i> <span>${escapeHTML(msg)}</span>`;
            alertBox.style.display = 'flex';
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function hideMemberAlert() {
            const alertBox = document.getElementById('memberAlertBanner');
            if (alertBox) alertBox.style.display = 'none';
        }
    }

    // -------------------------------------------------------------
    // 7. Mandal Admin Dashboard Controller & Member Approval Logic
    // -------------------------------------------------------------
    function initAdminDashboard() {
        const tabNav = document.getElementById('adminTabNav');
        if (!tabNav) return; // Not on Admin Dashboard page

        const tabBtns = tabNav.querySelectorAll('.admin-tab-btn');
        const tabPanes = document.querySelectorAll('.admin-tab-pane');
        const refreshBtns = document.querySelectorAll('.btn-refresh-list');
        const refreshReqsBtn = document.querySelector('.btn-refresh-admin-requests');

        // Reason Modal
        const reasonModal = document.getElementById('reasonModal');
        const reasonForm = document.getElementById('reasonForm');
        const btnCancelReason = document.getElementById('btnCancelReason');

        fetchAdminStats();
        loadPendingRequests();

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetTab = btn.getAttribute('data-tab');

                tabBtns.forEach(b => {
                    b.classList.remove('active');
                    b.style.color = '#64748B';
                    b.style.borderBottomColor = 'transparent';
                });
                btn.classList.add('active');
                btn.style.color = '#DA4D12';
                btn.style.borderBottomColor = '#DA4D12';

                tabPanes.forEach(pane => pane.style.display = 'none');
                const activePane = document.getElementById('adminTab' + targetTab.charAt(0).toUpperCase() + targetTab.slice(1));
                if (activePane) activePane.style.display = 'block';

                if (targetTab === 'requests') loadPendingRequests();
                else if (targetTab === 'pending') loadPendingMembers();
                else if (targetTab === 'approved') loadApprovedMembers();
                else if (targetTab === 'ledger') loadMasterLedger();
                else if (targetTab === 'other') loadOtherMembers();
                else if (targetTab === 'audit') loadAuditLogs();
            });
        });

        refreshBtns.forEach(b => {
            b.addEventListener('click', () => {
                fetchAdminStats();
                const activeTabBtn = document.querySelector('.admin-tab-btn.active');
                if (activeTabBtn) {
                    const t = activeTabBtn.getAttribute('data-tab');
                    if (t === 'pending') loadPendingMembers();
                    else if (t === 'approved') loadApprovedMembers();
                    else if (t === 'other') loadOtherMembers();
                    else if (t === 'audit') loadAuditLogs();
                }
            });
        });

        if (refreshReqsBtn) {
            refreshReqsBtn.addEventListener('click', () => {
                fetchAdminStats();
                loadPendingRequests();
            });
        }

        // Reason Modal Submit (Reject Member / Reject Request / Suspend Member)
        if (reasonForm) {
            reasonForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const targetId = document.getElementById('reasonTargetId').value;
                const act = document.getElementById('reasonTargetAction').value;
                const reason = document.getElementById('reasonInput').value;

                if (!targetId || !act || !reason) return;

                const submitBtn = document.getElementById('btnConfirmReason');
                setLoadingState(submitBtn, true);

                const payload = (act === 'reject_request') ? { request_id: targetId, reason: reason } : { user_id: targetId, reason: reason };

                handleAdminAction(act, payload, () => {
                    setLoadingState(submitBtn, false);
                    closeReasonModal();
                    fetchAdminStats();
                    loadPendingRequests();
                    loadPendingMembers();
                    loadApprovedMembers();
                    loadOtherMembers();
                    loadMasterLedger();
                }, (msg) => {
                    setLoadingState(submitBtn, false);
                    showAdminAlert('error', msg);
                });
            });
        }

        if (btnCancelReason) {
            btnCancelReason.addEventListener('click', closeReasonModal);
        }

        function openReasonModal(targetId, actionType, title, subtitle) {
            document.getElementById('reasonTargetId').value = targetId;
            document.getElementById('reasonTargetAction').value = actionType;
            document.getElementById('reasonInput').value = '';
            document.getElementById('reasonModalTitle').textContent = title;
            document.getElementById('reasonModalSub').textContent = subtitle;
            if (reasonModal) reasonModal.classList.add('active');
        }

        function closeReasonModal() {
            if (reasonModal) reasonModal.classList.remove('active');
        }

        function fetchAdminStats() {
            handleAdminAction('get_stats', {}, (data) => {
                if (data && data.stats) {
                    const st = data.stats;
                    const approvedEl = document.getElementById('metricApprovedCount');
                    const maxEl = document.getElementById('metricMaxLimit');
                    const pendingEl = document.getElementById('metricPendingCount');
                    const pendingReqsEl = document.getElementById('metricPendingRequestsCount');
                    const otherEl = document.getElementById('metricOtherCount');
                    const progressEl = document.getElementById('metricProgressBar');
                    const totalIncEl = document.getElementById('metricTotalIncome');
                    const totalExpEl = document.getElementById('metricTotalExpense');

                    const tabPendingBadge = document.getElementById('tabBadgePending');
                    const tabRequestsBadge = document.getElementById('tabBadgeRequests');
                    const tabApprovedCount = document.getElementById('tabApprovedCount');

                    if (approvedEl) approvedEl.textContent = st.approved;
                    if (maxEl) maxEl.textContent = st.max_limit;
                    if (pendingEl) pendingEl.textContent = st.pending;
                    if (pendingReqsEl) pendingReqsEl.textContent = st.pending_requests;
                    if (otherEl) otherEl.textContent = st.rejected + st.suspended;
                    if (totalIncEl) totalIncEl.textContent = parseFloat(st.total_income).toFixed(2);
                    if (totalExpEl) totalExpEl.textContent = parseFloat(st.total_expense).toFixed(2);

                    if (tabPendingBadge) tabPendingBadge.textContent = st.pending;
                    if (tabRequestsBadge) tabRequestsBadge.textContent = st.pending_requests;
                    if (tabApprovedCount) tabApprovedCount.textContent = st.approved;

                    if (progressEl) {
                        const pct = Math.min(100, Math.round((st.approved / st.max_limit) * 100));
                        progressEl.style.width = pct + '%';
                        if (pct >= 100) progressEl.style.background = '#EF4444';
                        else progressEl.style.background = 'linear-gradient(90deg, #10B981 0%, #059669 100%)';
                    }
                }
            });
        }

        function loadPendingRequests() {
            const tbody = document.getElementById('tablePendingRequestsBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 24px; color: #94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading pending member requests...</td></tr>';

            handleAdminAction('get_pending_requests', {}, (data) => {
                if (!data.requests || data.requests.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 32px; color: #64748B;"><i class="fa-solid fa-clipboard-check" style="font-size: 24px; margin-bottom: 8px; display: block; color: #10B981;"></i> No pending member requests. All clear!</td></tr>';
                    return;
                }
                let html = '';
                data.requests.forEach(r => {
                    const isPrivateBadge = r.is_hidden == 1 ? '<span style="color:#D97706; font-weight:700; background:#FEF3C7; padding:2px 8px; border-radius:10px; font-size:11px;"><i class="fa-solid fa-lock"></i> Private / Hidden</span>' : '<span style="color:#059669; font-weight:700; background:#DEF7EC; padding:2px 8px; border-radius:10px; font-size:11px;"><i class="fa-solid fa-globe"></i> Public</span>';
                    const amountStr = parseFloat(r.amount) > 0 ? '₹' + parseFloat(r.amount).toFixed(2) : '-';

                    html += `
                    <tr>
                        <td style="font-weight: 700; color: #1E293B;">${escapeHTML(r.member_name)}<div style="font-size:12px; color:#64748B;">${escapeHTML(r.member_email)}</div></td>
                        <td><strong style="color:#1E293B; text-transform:uppercase;">${escapeHTML(r.request_type)}</strong><div style="font-size:12px; color:#64748B;">${escapeHTML(r.category)}</div></td>
                        <td><strong style="color:#1E293B;">${escapeHTML(r.title)}</strong><div style="font-size:12px; color:#64748B;">${escapeHTML(r.description || '-')}</div></td>
                        <td style="font-weight: 700; color: #DA4D12;">${amountStr}</td>
                        <td style="font-size: 13px; color: #64748B;">${escapeHTML(r.event_date)}</td>
                        <td>${isPrivateBadge}</td>
                        <td style="text-align: right; white-space: nowrap;">
                            <button class="btn-act-approve btn-act-approve-req" data-id="${r.id}"><i class="fa-solid fa-check"></i> Approve</button>
                            ${r.is_hidden == 1 ? `<button class="btn-act-reactivate btn-act-override-req" data-id="${r.id}" style="margin-left:4px;" title="Approve & Force Public"><i class="fa-solid fa-eye"></i> Make Public</button>` : ''}
                            <button class="btn-act-reject btn-act-reject-req" data-id="${r.id}" style="margin-left:4px;"><i class="fa-solid fa-xmark"></i> Reject</button>
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;

                // Bind Request Action Buttons
                tbody.querySelectorAll('.btn-act-approve-req').forEach(b => {
                    b.addEventListener('click', () => {
                        const id = b.getAttribute('data-id');
                        setLoadingState(b, true);
                        handleAdminAction('approve_request', { request_id: id, override_public: 0 }, (res) => {
                            setLoadingState(b, false);
                            showAdminAlert('success', res.message);
                            fetchAdminStats();
                            loadPendingRequests();
                        }, (errMsg) => {
                            setLoadingState(b, false);
                            showAdminAlert('error', errMsg);
                        });
                    });
                });

                tbody.querySelectorAll('.btn-act-override-req').forEach(b => {
                    b.addEventListener('click', () => {
                        const id = b.getAttribute('data-id');
                        setLoadingState(b, true);
                        handleAdminAction('approve_request', { request_id: id, override_public: 1 }, (res) => {
                            setLoadingState(b, false);
                            showAdminAlert('success', 'Request accepted & set to Public!');
                            fetchAdminStats();
                            loadPendingRequests();
                        }, (errMsg) => {
                            setLoadingState(b, false);
                            showAdminAlert('error', errMsg);
                        });
                    });
                });

                tbody.querySelectorAll('.btn-act-reject-req').forEach(b => {
                    b.addEventListener('click', () => {
                        const id = b.getAttribute('data-id');
                        openReasonModal(id, 'reject_request', 'Reject Member Request', 'Specify reason for rejecting this Expense/Income/Booking request.');
                    });
                });
            });
        }

        // Master Ledger Multi-Year & Search Filter Handlers
        const btnFilterMaster = document.getElementById('btnFilterMasterLedger');
        const searchInputMaster = document.getElementById('masterLedgerSearchInput');
        const yearSelectMaster = document.getElementById('masterLedgerYearFilter');
        const typeSelectMaster = document.getElementById('masterLedgerTypeFilter');
        const sortSelectMaster = document.getElementById('masterLedgerSortBy');
        const btnCleanup = document.getElementById('btnAdminSystemCleanup');

        if (btnFilterMaster) btnFilterMaster.addEventListener('click', () => loadMasterLedger());
        if (yearSelectMaster) yearSelectMaster.addEventListener('change', () => loadMasterLedger());
        if (typeSelectMaster) typeSelectMaster.addEventListener('change', () => loadMasterLedger());
        if (sortSelectMaster) sortSelectMaster.addEventListener('change', () => loadMasterLedger());
        if (searchInputMaster) {
            searchInputMaster.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') loadMasterLedger();
            });
        }

        if (btnCleanup) {
            btnCleanup.addEventListener('click', () => {
                setLoadingState(btnCleanup, true);
                handleAdminAction('admin_system_cleanup', {}, (res) => {
                    setLoadingState(btnCleanup, false);
                    showAdminAlert('success', res.message);
                    fetchAdminStats();
                }, (err) => {
                    setLoadingState(btnCleanup, false);
                    showAdminAlert('error', err);
                });
            });
        }

        function loadMasterLedger() {
            const tbody = document.getElementById('tableMasterLedgerBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 24px; color: #94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading master ledger...</td></tr>';

            const payload = {
                year: document.getElementById('masterLedgerYearFilter')?.value || '',
                type: document.getElementById('masterLedgerTypeFilter')?.value || '',
                search: document.getElementById('masterLedgerSearchInput')?.value || '',
                sort: document.getElementById('masterLedgerSortBy')?.value || 'date_desc'
            };

            handleAdminAction('get_all_requests', payload, (data) => {
                if (!data.requests || data.requests.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 32px; color: #94A3B8;">No request ledger records match your search/filter criteria.</td></tr>';
                    return;
                }

                // Sync Available Years dropdown if available
                if (data.available_years && data.available_years.length > 0 && yearSelectMaster) {
                    const currentVal = data.selected_year || yearSelectMaster.value || 'all';
                    let opts = '<option value="all">All Years (Overall)</option>';
                    data.available_years.forEach(y => {
                        opts += `<option value="${y}">${y}</option>`;
                    });
                    yearSelectMaster.innerHTML = opts;
                    yearSelectMaster.value = currentVal;
                }

                let html = '';
                data.requests.forEach(r => {
                    const badgeClass = r.status === 'approved' ? 'approved' : (r.status === 'rejected' ? 'rejected' : 'pending');
                    const isPrivateBadge = r.is_hidden == 1 ? '<span style="color:#D97706; font-size:11px; font-weight:600;"><i class="fa-solid fa-lock"></i> Private</span>' : '<span style="color:#059669; font-size:11px; font-weight:600;"><i class="fa-solid fa-globe"></i> Public</span>';
                    const amountStr = parseFloat(r.amount) > 0 ? '₹' + parseFloat(r.amount).toFixed(2) : '-';

                    // Party identification badge
                    let partyBadge = '';
                    const reqLower = (r.request_type || '').toLowerCase();
                    if (['income', 'collection', 'donation', 'sponsorship'].includes(reqLower)) {
                        partyBadge = `<div style="font-size:11px; color:#059669; font-weight:600;"><i class="fa-solid fa-hand-holding-dollar"></i> Donor / Collection</div>`;
                    } else if (reqLower === 'booking') {
                        partyBadge = `<div style="font-size:11px; color:#2563EB; font-weight:600;"><i class="fa-solid fa-calendar-check"></i> Booking Submitter</div>`;
                    } else {
                        partyBadge = `<div style="font-size:11px; color:#D97706; font-weight:600;"><i class="fa-solid fa-receipt"></i> Payee / Submitter</div>`;
                    }

                    html += `
                    <tr>
                        <td style="font-size:13px; color:#64748B; white-space:nowrap;">${escapeHTML(r.event_date)}</td>
                        <td>
                            <div style="font-weight:700; color:#1E293B;">${escapeHTML(r.member_name)}</div>
                            ${partyBadge}
                        </td>
                        <td><strong style="color:#1E293B; text-transform:uppercase;">${escapeHTML(r.request_type)}</strong><div style="font-size:12px; color:#64748B;">${escapeHTML(r.category)}</div></td>
                        <td><strong style="color:#1E293B;">${escapeHTML(r.title)}</strong><div style="font-size:12px; color:#64748B;">${escapeHTML(r.description || '-')}</div></td>
                        <td style="font-weight:700; color:#DA4D12;">${amountStr}</td>
                        <td>${isPrivateBadge}</td>
                        <td><span class="badge-status ${badgeClass}">${escapeHTML(r.status)}</span></td>
                        <td style="text-align: right; white-space: nowrap;">
                            <button class="btn-act-edit btn-ledger-edit" data-id="${r.id}" data-type="${escapeHTML(r.request_type)}" data-cat="${escapeHTML(r.category)}" data-title="${escapeHTML(r.title)}" data-desc="${escapeHTML(r.description || '')}" data-amount="${r.amount}" data-date="${escapeHTML(r.event_date)}" data-hidden="${r.is_hidden}">
                                <i class="fa-solid fa-pen"></i> Edit
                            </button>
                            <button class="btn-act-delete btn-ledger-delete" data-id="${r.id}" data-title="${escapeHTML(r.title)}" style="margin-left: 4px;">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;

                // Bind Edit buttons
                tbody.querySelectorAll('.btn-ledger-edit').forEach(b => {
                    b.addEventListener('click', () => openEntryModal('update', {
                        id: b.dataset.id, type: b.dataset.type, category: b.dataset.cat,
                        title: b.dataset.title, description: b.dataset.desc,
                        amount: b.dataset.amount, date: b.dataset.date, hidden: b.dataset.hidden
                    }));
                });

                // Bind Delete buttons
                tbody.querySelectorAll('.btn-ledger-delete').forEach(b => {
                    b.addEventListener('click', () => {
                        document.getElementById('deleteTargetId').value = b.dataset.id;
                        document.getElementById('deleteConfirmMsg').textContent = `Delete "${b.dataset.title}"? This cannot be undone.`;
                        document.getElementById('deleteConfirmModal').classList.add('active');
                    });
                });
            });
        }

        function loadPendingMembers() {
            const tbody = document.getElementById('tablePendingBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 24px; color: #94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading pending applications...</td></tr>';

            handleAdminAction('get_pending_members', {}, (data) => {
                if (!data.members || data.members.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 32px; color: #64748B;"><i class="fa-solid fa-clipboard-check" style="font-size: 24px; margin-bottom: 8px; display: block; color: #10B981;"></i> No pending member applications. All clear!</td></tr>';
                    return;
                }
                let html = '';
                data.members.forEach(m => {
                    html += `
                    <tr>
                        <td style="font-weight: 700; color: #1E293B;">${escapeHTML(m.full_name)}</td>
                        <td>${escapeHTML(m.email)}</td>
                        <td>${escapeHTML(m.phone)}</td>
                        <td style="font-size: 13px; color: #64748B;">${escapeHTML(m.created_at)}</td>
                        <td style="text-align: right; white-space: nowrap;">
                            <button class="btn-act-approve" data-id="${m.id}"><i class="fa-solid fa-check"></i> Approve</button>
                            <button class="btn-act-reject" data-id="${m.id}" style="margin-left: 6px;"><i class="fa-solid fa-xmark"></i> Reject</button>
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;

                // Bind Action Buttons
                tbody.querySelectorAll('.btn-act-approve').forEach(b => {
                    b.addEventListener('click', () => {
                        const id = b.getAttribute('data-id');
                        setLoadingState(b, true);
                        handleAdminAction('approve_member', { user_id: id }, (res) => {
                            setLoadingState(b, false);
                            showAdminAlert('success', res.message);
                            fetchAdminStats();
                            loadPendingMembers();
                        }, (errMsg) => {
                            setLoadingState(b, false);
                            showAdminAlert('error', errMsg);
                        });
                    });
                });

                tbody.querySelectorAll('.btn-act-reject').forEach(b => {
                    b.addEventListener('click', () => {
                        const id = b.getAttribute('data-id');
                        openReasonModal(id, 'reject_member', 'Reject Member Application', 'Specify why this registration application is being rejected.');
                    });
                });
            });
        }

        function loadApprovedMembers() {
            const tbody = document.getElementById('tableApprovedBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 24px; color: #94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading approved members...</td></tr>';

            handleAdminAction('get_approved_members', {}, (data) => {
                if (!data.members || data.members.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 32px; color: #94A3B8;">No approved members yet.</td></tr>';
                    return;
                }
                let html = '';
                data.members.forEach(m => {
                    html += `
                    <tr>
                        <td style="font-weight: 700; color: #1E293B;">${escapeHTML(m.full_name)}</td>
                        <td>${escapeHTML(m.email)}</td>
                        <td>${escapeHTML(m.phone)}</td>
                        <td style="font-size: 13px; color: #64748B;">${escapeHTML(m.approved_at || '-')}</td>
                        <td style="text-align: right;">
                            <button class="btn-act-suspend" data-id="${m.id}"><i class="fa-solid fa-user-slash"></i> Suspend</button>
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;

                tbody.querySelectorAll('.btn-act-suspend').forEach(b => {
                    b.addEventListener('click', () => {
                        const id = b.getAttribute('data-id');
                        openReasonModal(id, 'suspend_member', 'Suspend Active Member', 'Specify reason for suspending this member. Their slot will be freed up.');
                    });
                });
            });
        }

        function loadOtherMembers() {
            const tbody = document.getElementById('tableOtherBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 24px; color: #94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</td></tr>';

            handleAdminAction('get_other_members', {}, (data) => {
                if (!data.members || data.members.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 32px; color: #94A3B8;">No rejected or suspended member records.</td></tr>';
                    return;
                }
                let html = '';
                data.members.forEach(m => {
                    const badgeClass = m.membership_status === 'rejected' ? 'rejected' : 'suspended';
                    html += `
                    <tr>
                        <td style="font-weight: 700; color: #1E293B;">${escapeHTML(m.full_name)}</td>
                        <td>${escapeHTML(m.email)}</td>
                        <td><span class="badge-status ${badgeClass}">${escapeHTML(m.membership_status)}</span></td>
                        <td style="font-size: 13px; color: #64748B;">${escapeHTML(m.rejection_reason || '-')}</td>
                        <td style="text-align: right;">
                            <button class="btn-act-reactivate" data-id="${m.id}"><i class="fa-solid fa-rotate-left"></i> Reactivate</button>
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;

                tbody.querySelectorAll('.btn-act-reactivate').forEach(b => {
                    b.addEventListener('click', () => {
                        const id = b.getAttribute('data-id');
                        setLoadingState(b, true);
                        handleAdminAction('reactivate_member', { user_id: id }, (res) => {
                            setLoadingState(b, false);
                            showAdminAlert('success', res.message);
                            fetchAdminStats();
                            loadOtherMembers();
                        }, (errMsg) => {
                            setLoadingState(b, false);
                            showAdminAlert('error', errMsg);
                        });
                    });
                });
            });
        }

        // Feature 6: Audit Log state for pagination
        let auditPage = 1;
        let auditHasMore = false;
        let auditActionFilterVal = '';
        let auditUserFilterVal = '';

        function loadAuditLogs(reset = true) {
            const tbody = document.getElementById('tableAuditBody');
            const loadMoreBtn = document.getElementById('btnLoadMoreAudit');
            const countEl = document.getElementById('auditLogCount');
            if (!tbody) return;

            if (reset) {
                auditPage = 1;
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 24px; color: #94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading logs...</td></tr>';
                if (loadMoreBtn) loadMoreBtn.style.display = 'none';
            } else {
                // Append mode — add spinner row
                tbody.innerHTML += '<tr id="auditLoadingRow"><td colspan="5" style="text-align: center; padding: 12px; color: #94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i></td></tr>';
            }

            handleAdminAction('get_audit_logs', {
                page: auditPage,
                action_filter: auditActionFilterVal,
                user_filter: auditUserFilterVal
            }, (data) => {
                // Remove loading row if appending
                const loadingRow = document.getElementById('auditLoadingRow');
                if (loadingRow) loadingRow.remove();

                if (reset && (!data.logs || data.logs.length === 0)) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 32px; color: #94A3B8;">No security audit logs found.</td></tr>';
                    if (loadMoreBtn) loadMoreBtn.style.display = 'none';
                    if (countEl) countEl.textContent = '';
                    return;
                }

                let html = '';
                (data.logs || []).forEach(l => {
                    const userDisplay = l.user_name ? escapeHTML(l.user_name) : (l.user_email ? `<span style="color:#94A3B8; font-size:11px;">${escapeHTML(l.user_email)}</span>` : '-');
                    const actorDisplay = l.actor_name ? `<div style="font-size:11px; color:#64748B;">by: ${escapeHTML(l.actor_name)}</div>` : '';
                    const actionCls = getAuditActionClass(l.action);
                    const detailsHtml = formatAuditDetails(l.details_json, l.action);

                    html += `
                    <tr>
                        <td style="color: #64748B; white-space: nowrap; font-size: 12px;">${escapeHTML(l.created_at)}</td>
                        <td><span class="audit-action-badge ${actionCls}">${escapeHTML(l.action)}</span></td>
                        <td>${userDisplay}${actorDisplay}</td>
                        <td><code style="font-size:11px;">${escapeHTML(l.ip_address || '-')}</code></td>
                        <td>${detailsHtml}</td>
                    </tr>`;
                });

                if (reset) {
                    tbody.innerHTML = html;
                } else {
                    tbody.insertAdjacentHTML('beforeend', html);
                }

                auditHasMore = data.has_more;
                if (loadMoreBtn) loadMoreBtn.style.display = auditHasMore ? 'flex' : 'none';
                if (countEl && data.total_count !== undefined) {
                    const showing = Math.min(auditPage * data.per_page, data.total_count);
                    countEl.textContent = `Showing ${showing} of ${data.total_count} log entries`;
                }
            });
        }

        function getAuditActionClass(action) {
            if (!action) return '';
            const a = action.toUpperCase();
            if (a.includes('LOGIN') || a.includes('OAUTH') || a.includes('AUTO')) return 'action-login';
            if (a.includes('MEMBER') || a.includes('SIGNUP') || a.includes('APPROVE') || a.includes('REJECT') || a.includes('SUSPEND') || a.includes('REACTIVATE')) return 'action-member';
            if (a.includes('REQUEST') || a.includes('ENTRY')) return 'action-request';
            if (a.includes('LOCKOUT') || a.includes('FAILED') || a.includes('SECURITY')) return 'action-security';
            return '';
        }

        function formatAuditDetails(jsonStr, action) {
            if (!jsonStr) return '<span style="color:#94A3B8; font-size:11px;">—</span>';
            try {
                const obj = JSON.parse(jsonStr);
                let pills = '';
                for (const [k, v] of Object.entries(obj)) {
                    if (v === null || v === undefined || v === '') continue;
                    const label = k.replace(/_/g, ' ');
                    const valStr = typeof v === 'boolean' ? (v ? 'Yes ✓' : 'No ✗') : String(v);
                    const cls = getAuditActionClass(action);
                    pills += `<span class="audit-detail-pill ${cls}" title="${escapeHTML(label)}: ${escapeHTML(valStr)}">${escapeHTML(label)}: <strong>${escapeHTML(valStr)}</strong></span>`;
                }
                return pills || '<span style="color:#94A3B8; font-size:11px;">—</span>';
            } catch {
                return `<span style="font-family:monospace; font-size:11px; color:#64748B;">${escapeHTML(jsonStr.substring(0, 80))}</span>`;
            }
        }

        // Bind audit filter button
        const auditFilterBtn = document.getElementById('btnAuditFilter');
        if (auditFilterBtn) {
            auditFilterBtn.addEventListener('click', () => {
                auditActionFilterVal = document.getElementById('auditActionFilter')?.value || '';
                auditUserFilterVal = document.getElementById('auditUserFilter')?.value || '';
                loadAuditLogs(true);
            });
        }

        // Audit filter on Enter key in user filter input
        const auditUserInput = document.getElementById('auditUserFilter');
        if (auditUserInput) {
            auditUserInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') auditFilterBtn?.click();
            });
        }

        // Load More button
        const loadMoreAuditBtn = document.getElementById('btnLoadMoreAudit');
        if (loadMoreAuditBtn) {
            loadMoreAuditBtn.addEventListener('click', () => {
                if (!auditHasMore) return;
                auditPage++;
                loadAuditLogs(false);
            });
        }

        // ================================================================
        // Feature 4: Admin CRUD Modal Logic
        // ================================================================
        const adminEntryModal = document.getElementById('adminEntryModal');
        const adminEntryForm = document.getElementById('adminEntryForm');
        const deleteConfirmModal = document.getElementById('deleteConfirmModal');

        function openEntryModal(mode, prefill = {}) {
            document.getElementById('entryModalMode').value = mode;
            document.getElementById('entryModalId').value = prefill.id || '';
            document.getElementById('entryType').value = prefill.type || 'expense';
            document.getElementById('entryCategory').value = prefill.category || '';
            document.getElementById('entryTitle').value = prefill.title || '';
            document.getElementById('entryDesc').value = prefill.description || '';
            document.getElementById('entryAmount').value = prefill.amount || '';
            document.getElementById('entryDate').value = prefill.date || new Date().toISOString().split('T')[0];
            document.getElementById('entryIsHidden').checked = prefill.hidden == 1;

            if (mode === 'insert') {
                document.getElementById('adminEntryModalTitle').textContent = 'Add Ledger Entry';
                document.getElementById('adminEntryModalSub').textContent = 'Add a new approved entry directly to the Mandal ledger.';
            } else {
                document.getElementById('adminEntryModalTitle').textContent = 'Edit Ledger Entry';
                document.getElementById('adminEntryModalSub').textContent = 'Update the details of this ledger record.';
            }

            if (adminEntryModal) adminEntryModal.classList.add('active');
        }

        function closeEntryModal() {
            if (adminEntryModal) adminEntryModal.classList.remove('active');
        }

        // Add Entry button
        const btnAddEntry = document.getElementById('btnAddLedgerEntry');
        if (btnAddEntry) btnAddEntry.addEventListener('click', () => openEntryModal('insert'));

        // Close / Cancel entry modal
        document.getElementById('btnCloseEntryModal')?.addEventListener('click', closeEntryModal);
        document.getElementById('btnCancelEntryModal')?.addEventListener('click', closeEntryModal);

        // Entry form submit (Insert or Update)
        if (adminEntryForm) {
            adminEntryForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const mode = document.getElementById('entryModalMode').value;
                const saveBtn = document.getElementById('btnSaveEntry');
                const action = mode === 'insert' ? 'admin_insert_entry' : 'admin_update_entry';

                const payload = {
                    request_type: document.getElementById('entryType').value,
                    category: document.getElementById('entryCategory').value,
                    title: document.getElementById('entryTitle').value,
                    description: document.getElementById('entryDesc').value,
                    amount: document.getElementById('entryAmount').value || '0',
                    event_date: document.getElementById('entryDate').value,
                    is_hidden: document.getElementById('entryIsHidden').checked ? '1' : '0'
                };
                if (mode === 'update') payload.entry_id = document.getElementById('entryModalId').value;

                setLoadingState(saveBtn, true);
                handleAdminAction(action, payload, (res) => {
                    setLoadingState(saveBtn, false);
                    closeEntryModal();
                    showAdminAlert('success', res.message);
                    fetchAdminStats();
                    loadMasterLedger();
                }, (err) => {
                    setLoadingState(saveBtn, false);
                    showAdminAlert('error', err);
                });
            });
        }

        // Delete confirm
        const btnConfirmDelete = document.getElementById('btnConfirmDelete');
        const btnCancelDelete = document.getElementById('btnCancelDelete');

        if (btnCancelDelete) btnCancelDelete.addEventListener('click', () => {
            if (deleteConfirmModal) deleteConfirmModal.classList.remove('active');
        });

        if (btnConfirmDelete) {
            btnConfirmDelete.addEventListener('click', () => {
                const entryId = document.getElementById('deleteTargetId').value;
                if (!entryId) return;
                setLoadingState(btnConfirmDelete, true);
                handleAdminAction('admin_delete_entry', { entry_id: entryId }, (res) => {
                    setLoadingState(btnConfirmDelete, false);
                    if (deleteConfirmModal) deleteConfirmModal.classList.remove('active');
                    showAdminAlert('success', res.message);
                    fetchAdminStats();
                    loadMasterLedger();
                }, (err) => {
                    setLoadingState(btnConfirmDelete, false);
                    showAdminAlert('error', err);
                });
            });
        }

        function handleAdminAction(actionName, payload, onSuccess, onError) {
            const formData = new FormData();
            formData.append('action', actionName);
            formData.append('csrf_token', window.APP_CONFIG.csrfToken);
            for (const k in payload) {
                formData.append(k, payload[k]);
            }

            fetch('api/admin_handler.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.status === 'success') {
                    if (typeof onSuccess === 'function') onSuccess(data);
                } else {
                    const msg = (data && data.message) ? data.message : 'Action failed.';
                    if (typeof onError === 'function') onError(msg);
                }
            })
            .catch(err => {
                console.error('Admin API error:', err);
                if (typeof onError === 'function') onError('Network error. Please try again.');
            });
        }

        function showAdminAlert(type, msg) {
            const alertBox = document.getElementById('adminAlertBanner');
            if (!alertBox) return;
            alertBox.className = 'alert-banner ' + type;
            alertBox.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'}"></i> <span>${escapeHTML(msg)}</span>`;
            alertBox.style.display = 'flex';
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            // Auto-hide after 5s
            setTimeout(() => { if (alertBox) alertBox.style.display = 'none'; }, 5000);
        }

        function escapeHTML(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }
    }

})();

