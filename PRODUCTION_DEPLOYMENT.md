# Sudarshan Yuvak Mandal (SYMS) — Free Shared Hosting Production Deployment Guide

This guide provides exhaustive, step-by-step instructions for deploying the **Sudarshan Yuvak Mandal Portal** to any free or budget PHP/MySQL shared-hosting environment (such as **InfinityFree**, **Alwaysdata**, **ByetHost**, **cPanel Shared Hosting**, etc.).

---

## 1. Required PHP Version
- **Minimum:** PHP 8.0
- **Recommended:** PHP 8.1, 8.2, or 8.3
- **Compatibility:** Strictly compatible with standard shared hosting PHP runtimes. Zero reliance on CLI daemons, Node.js, Python, or root access.

---

## 2. Required PHP Extensions
Ensure the following standard PHP core extensions are enabled in your hosting control panel (usually enabled by default in all standard shared hosting environments):

| Extension | Purpose in SYMS |
| :--- | :--- |
| `pdo` & `pdo_mysql` | Secure database communication with prepared statements. |
| `openssl` | Cryptographically secure token generation and TLS mail delivery. |
| `curl` | Google & GitHub OAuth 2.0 API token exchange over HTTPS. |
| `fileinfo` | Binary MIME inspection for secure file and receipt uploads. |
| `mbstring` | UTF-8 multi-byte string handling and sanitization. |
| `json` | AJAX API responses and client-side configuration hydration. |
| `gd` *(optional)* | Image processing and dynamic CAPTCHA rendering. |

> [!NOTE]
> **Composer Dependency:** Zero runtime Composer dependencies. All modules, environment loaders, database connectors, and mailers are native and self-contained.

---

## 3. Required Database Version & Features
- **Engine:** MySQL 5.7+ / MySQL 8.0+ or MariaDB 10.3+
- **Storage Engine:** `InnoDB` (required for foreign key constraints and row-level locking)
- **Character Set:** `utf8mb4`
- **Collation:** `utf8mb4_unicode_ci` or `utf8mb4_general_ci`
- **Permissions Required:** `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP`, `INDEX`, `ALTER` on the host-assigned database.
- **Port:** Standard `3306` (or any custom port provided by the host via `DB_PORT`).

---

## 4. Required Folders
Ensure the following directory structure exists in your website's root web directory (usually `htdocs/` or `public_html/`):

```text
htdocs/ (or public_html/)
├── api/
├── assets/
│   ├── css/
│   └── js/
├── config/
├── database/
├── includes/
├── logs/
└── uploads/
    ├── landing/
    │   ├── karyakartas/
    │   ├── logo/
    │   ├── memories/
    │   ├── murtikar/
    │   └── routes/
    └── proofs/
```

---

## 5. Required Writable Folders & Permissions
Set standard UNIX permissions using FileZilla, FTP, or your host File Manager:

| Directory | Required Permission | Purpose |
| :--- | :--- | :--- |
| `uploads/` (and all subfolders) | `755` (or `775` on some hosts) | Storing member receipt proofs, route PDFs, and gallery media. |
| `logs/` | `755` (or `775`) | Storing runtime `app_errors.log` (auto-purged after 7 days). |
| Standard PHP / Asset files | `644` | Read-only script execution. |
| Standard Directories | `755` | Executable/traversable directory structure. |

---

## 6. Required Database Tables
The master production schema generates **14 relational tables**:

1. `users` — Committee accounts, members, role assignments, and OAuth IDs.
2. `otp_tokens` — Time-limited (5 min) OTP codes for registration, login & password reset.
3. `rate_limits` — Brute-force and IP throttling records.
4. `user_sessions` — Active member session tracking.
5. `remember_tokens` — Secure persistent cookie tokens.
6. `mandal_requests` — Financial, expense, income, and booking requests.
7. `notifications` — In-app notification alerts for members and admins.
8. `audit_logs` — Immutable audit trail of administrative & financial actions.
9. `system_logs` — Server error logs auto-rotated by the system.
10. `mandal_settings` — Mandal contact info, branding, address, and social links.
11. `utsav_events` — Multi-year festival metadata, theme, dates, and murtikar info.
12. `karyakartas` — Committee members directory with photo and role badges.
13. `event_memories` — Multi-year photo and video memories for the swipe carousel.
14. `event_routes` — Aagman and Visarjan procession paths, maps, and PDF links.

---

## 7. Required SQL Import
Use the host-agnostic master SQL file located at:
`database/production_schema.sql`

### How to Import:
1. Log into your hosting control panel (e.g., cPanel / VistaPanel).
2. Open **phpMyAdmin** and select your host-assigned database name.
3. Click the **Import** tab.
4. Choose `database/production_schema.sql` from your computer and click **Go**.
5. All 14 tables and initial festival records will be created cleanly.

---

## 8. Required Environment Values (`.env`)
Create a `.env` file in the root `htdocs/` directory of your web host.

> [!IMPORTANT]
> Replace all `<placeholders>` with the exact values provided by your hosting provider. Do not commit this file to public repositories.

```ini
# ================================================================
# Database Connection (Supplied by your Free Hosting Provider)
# ================================================================
DB_HOST=<your-host-mysql-hostname>     # e.g., sql105.infinityfree.com or localhost
DB_PORT=3306                           # Standard MySQL port
DB_NAME=<your-host-database-name>      # e.g., if0_38492019_syms
DB_USER=<your-host-database-username>  # e.g., if0_38492019
DB_PASS=<your-host-database-password>

# ================================================================
# Application Mode & Base URL
# ================================================================
APP_ENV=production
APP_BASE_URL=https://<your-domain-or-subdomain> # e.g., https://sudarshan.infinityfreeapp.com

# ================================================================
# Email & Real OTP Delivery (Gmail SMTP or Transactional Mail)
# ================================================================
OTP_SERVICE_MODE=production
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=<your-mandal-email@gmail.com>
SMTP_PASSWORD=<your-16-character-gmail-app-password>
SMTP_FROM_EMAIL=<your-mandal-email@gmail.com>
SMTP_FROM_NAME=Sudarshan Yuvak Mandal

# ================================================================
# Security Limits & Constraints
# ================================================================
MANDAL_MAX_MEMBERS=50
OTP_EXPIRY_MINUTES=5
OTP_RESEND_COOLDOWN_SECONDS=60
MAX_OTP_ATTEMPTS=3
MAX_OTP_RESENDS_PER_WINDOW=3
RESEND_WINDOW_MINUTES=15
MAX_FAILED_LOGINS=5
LOGIN_LOCKOUT_MINUTES=15

# ================================================================
# Initial Admin Provisioning (One-Time Setup)
# ================================================================
ADMIN_BOOTSTRAP_EMAIL=<admin-login-email@yourdomain.com>
ADMIN_BOOTSTRAP_PASSWORD=<A-Strong-Admin-Password-2026!>
ADMIN_BOOTSTRAP_NAME=Mandal Admin
ADMIN_BOOTSTRAP_PHONE=9876543210

# ================================================================
# Optional: OAuth 2.0 Social Logins (Google & GitHub)
# ================================================================
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
```

---

## 9. SMTP Configuration Requirements
- **Gmail App Password:** If using Gmail, create a dedicated 16-character App Password at [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords).
- **Socket / Port Firewall Handling:** The built-in `SmtpMailer` attempts TLS on port `587`. If a free shared host blocks outbound socket ports, it automatically falls back to native PHP `mail()` without breaking user experience.
- **Fail-Safe Response:** User registration and OTP requests fail gracefully with user-friendly alerts rather than exposing raw SMTP server socket traces.

---

## 10. External API Configuration
1. **Google Maps Navigation:** Uses public intent URLs (`https://www.google.com/maps/search/?api=1&query=...`). Zero API keys required.
2. **YouTube Video Embeds:** Uses privacy-enhanced embed endpoints. Zero API keys required.
3. **OAuth 2.0 Callbacks (Optional):** If using Google/GitHub SSO, add your production callback URL to your Google Cloud Console / GitHub OAuth settings:
   `https://<your-domain>/api/oauth_handler.php?action=google_callback`
   `https://<your-domain>/api/oauth_handler.php?action=github_callback`

---

## 11. `.htaccess` Requirements
The included `.htaccess` file is pre-configured for Apache shared hosts:
- **Directory Indexing:** Disabled (`Options -Indexes`).
- **File Shielding:** Denies direct HTTP requests to `.env`, `*.sql`, `*.log`, `.git*`, `config/`, `logs/`, and `scratch/`.
- **Security Headers:** Enforces `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, and `Referrer-Policy: strict-origin-when-cross-origin`.
- **Upload Directory Protection:** Prevents execution of scripts (`.php`, `.phtml`, `.phar`) inside `/uploads/`.

---

## 12. HTTPS Requirements
- **Free SSL Certificate:** Enable free Let's Encrypt / ZeroSSL in your hosting control panel (e.g. under "Free SSL Certificates" in InfinityFree).
- **Cookie Security:** The application automatically enables `session.cookie_secure = 1` whenever the incoming request is over HTTPS.
- **Asset Protocol:** All stylesheets, Google Fonts, and FontAwesome icons are loaded with HTTPS CDN URLs.

---

## 13. Complete Deployment Steps

### Step 1: Create Free Hosting Account & Database
1. Register on your chosen free host (e.g., [InfinityFree](https://www.infinityfree.com/) or [Alwaysdata](https://www.alwaysdata.com/)).
2. Create a new website/account and choose your free subdomain or custom domain.
3. Go to **MySQL Databases** in the control panel and create a database (e.g. `if0_xxxx_syms`).
4. Note down your database credentials:
   - MySQL Hostname
   - Database Name
   - Database Username
   - Database Password

### Step 2: Import Master Database Schema
1. Open **phpMyAdmin** from your hosting control panel.
2. Click on your database in the left sidebar.
3. Click **Import** in the top navigation bar.
4. Click **Choose File**, select `database/production_schema.sql`, and click **Go**.

### Step 3: Upload Files via FTP / File Manager
1. Connect via FTP (using FileZilla or the hosting browser File Manager).
2. Enter the web root folder (`htdocs/` or `public_html/`).
3. Upload all project files into `htdocs/`.

### Step 4: Create `.env` File
1. In the `htdocs/` folder on the host, create a new file named `.env`.
2. Paste the configuration from **Section 8** with your actual database and email details.
3. Save the file.

### Step 5: Initialize the First Admin Account
1. Open your browser and visit:
   `https://<your-domain>/database/bootstrap_admin.php?key=<YOUR_ADMIN_BOOTSTRAP_PASSWORD>`
2. The page will confirm: *"Mandal Admin account created successfully!"*
3. **Security Cleanup:** Once created, you can safely delete `database/bootstrap_admin.php` from your server via File Manager.

---

## 14. Post-Deployment Testing Checklist

| Test Item | Verification Method | Expected Result |
| :--- | :--- | :--- |
| **Public Landing Page** | Visit `https://<your-domain>/` | Loads hero countdown, festival dates, committee members, and route maps in `< 100ms`. |
| **Memories Swipe Carousel** | Scroll to gallery on mobile and desktop | Smooth CSS scroll-snap swipe gestures, 3D card elevation, lightbox media preview. |
| **Route Map Modal** | Click "View Live Map" on Aagman & Visarjan | Opens interactive modal cleanly without scroll-blocking or embed errors. |
| **Admin Login** | Log in at `https://<your-domain>/login.php` | Logs into `admin_dashboard.php` successfully using bootstrap admin credentials. |
| **Admin CMS Actions** | Edit a route / change theme in Admin Dashboard | Saves changes with HTTP 200 via dynamic CSRF verification. |
| **Member Registration & OTP** | Register a test member account on login page | Sends 6-digit OTP email to inbox within 5 seconds. |
| **Security Shield Check** | Direct URL to `https://<your-domain>/.env` | Returns HTTP 403 Forbidden. |

---

## 15. Rollback Procedure

If any issue arises during deployment:
1. **Database Rollback:**
   - Open phpMyAdmin, select all tables, choose **Drop**, and re-import `database/production_schema.sql`.
2. **File Rollback:**
   - Delete all files in `htdocs/` via FTP and re-upload the latest verified release from your GitHub repository `https://github.com/kartik-rana-backTech/syms-portal`.
3. **Logs Inspection:**
   - Inspect `logs/app_errors.log` via File Manager to check for database connection or SMTP delivery issues.
