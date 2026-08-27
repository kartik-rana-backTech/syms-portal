# Sudarshan Yuvak Mandal (SYMS) — Management System & Festive Web Portal

A secure, modern, full-stack web application and content management system (CMS) tailored for **Sudarshan Yuvak Mandal** (Bhathena, Surat). The system features a public festival landing hub, committee member portal, financial & expense tracking, approval workflows, multi-year festival archives, and administrative control.

---

## 🌟 Key Highlights & Features

### 🏛️ 1. Public Festive Landing Hub (`landing.php`)
- **Hero & Live Countdown**: Saffron-themed divine design with dynamic countdown timer to Ganesh Chaturthi. Auto-rolls over to subsequent festival years.
- **Dynamic Multi-Year Engine**: Switch between historical and upcoming utsav years (2024 to future) with instant cache-first loading (`< 5ms`).
- **Interactive Procession Routes (Aagman & Visarjan)**:
  - Route maps with landmark intersections, checkpoints, and downloadable PDFs.
  - Interactive Google Maps modal with zero scroll-blocking violations.
  - Direct turn-by-turn navigation link to native Google Maps.
- **Cherished Memories & Video Gallery**:
  - **CSS Scroll-Snap Swipe Card Carousel**: Responsive, smooth touch swiping and 3D card elevation effects keeping page height compact regardless of memory count.
  - **Layout Switcher**: One-click toggle between **🎡 Swipe Cards** and **⊞ Grid View**.
  - **High-Resolution Lightbox**: Supports full-view image modal and embedded YouTube/HTML5 video playback.
- **Murtikar Details & Sthapana Location**: Integrated Google Maps pin and committee contact hub.

### 🛡️ 2. Comprehensive Admin CMS (`admin_dashboard.php`)
- **Mandal Profile & Settings**: Live configuration for Mandal name, tagline, address, contacts, social links, and mandal logo.
- **Festival Year Manager**: Activate years, define themes, slogan, Sthapana/Visarjan dates, and murti details.
- **Committee (Karyakartas) Management**: Manage committee members with role badges and profile photos.
- **Route Editor**: Create and update Aagman and Visarjan procession routes, embed maps, and upload PDFs.
- **Memories Uploader**: Multi-year photo and video curation.
- **Robust CSRF & Security**: Fail-safe dynamic CSRF verification across all AJAX and form submissions.

### 👥 3. Member Portal & Financial Workflow (`dashboard.php`)
- **Role-Based Access Control**: Separate workflows for `admin` and `member`.
- **Expense & Request Management**: Submit, review, approve, and track festival expenses with receipt proofs.
- **Financial Analytics & Export**: Cost estimation, categorized expenditure summaries, and audit trail logs.
- **Audit Logging**: Comprehensive traceability on every financial and administrative action.

---

## 🛠️ Technology Stack

- **Backend**: PHP 8.x (Native Object-Oriented Architecture, PDO MySQL)
- **Database**: MySQL 8.x / MariaDB (Optimized indexes, transactions, and foreign keys)
- **Frontend**: Vanilla JavaScript (ES6+), Modern CSS3 (CSS Custom Properties, Glassmorphism, CSS Scroll-Snap)
- **Icons & Typography**: FontAwesome 6 Pro/Free, Google Fonts (Outfit & Cinzel)
- **Environment**: Apache / XAMPP on Windows/Linux

---

## 📂 Project Directory Structure

```text
manager/
├── .env.example             # Template for database & mail credentials
├── .gitignore               # Ignored files, credentials, and uploads
├── admin_dashboard.php      # Admin Control Panel & CMS
├── dashboard.php            # Member Dashboard & Expense Tracker
├── index.php                # Entrypoint router
├── landing.php              # Public Landing Page & Festival Portal
├── login.php                # Secure Authentication & OTP Portal
├── logout.php               # Session termination handler
├── SYSTEM_DESIGN.md         # Database optimizations and architecture specs
├── api/                     # Backend API Handlers
│   ├── admin_handler.php
│   ├── admin_landing_handler.php
│   ├── member_handler.php
│   ├── public_landing_api.php
│   └── request_handler.php
├── assets/                  # Frontend Static Assets
│   ├── css/                 # Stylesheets (landing.css, dashboard.css, etc.)
│   └── js/                  # Scripts (landing.js, admin_landing.js, etc.)
├── config/                  # Core Configuration & Security Helpers
│   ├── db.php               # Database connection & Security utilities
│   └── mail.php             # Email & OTP dispatcher
├── database/                # SQL Schema & Migrations
│   └── schema.sql           # Initial database schema
├── includes/                # Shared PHP Components (header, footer, nav)
└── uploads/                 # Media & Document Upload Directories
```

---

## 🚀 Setup & Local Installation

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (PHP 8.0 or higher with PDO & cURL extensions enabled)
- MySQL / MariaDB Server

### Installation Steps

1. **Clone the Repository**:
   ```bash
   git clone <repository-url>
   cd manager
   ```

2. **Configure Environment Variables**:
   Copy `.env.example` to `.env` and fill in your database credentials:
   ```bash
   cp .env.example .env
   ```
   Example configuration:
   ```ini
   DB_HOST=localhost
   DB_NAME=mandal_db
   DB_USER=root
   DB_PASS=
   APP_ENV=development
   ```

3. **Import Database Schema**:
   - Open **phpMyAdmin** (`http://localhost/phpmyadmin/`).
   - Create a database named `mandal_db` (UTF8mb4 collation).
   - Import `database/schema.sql`.

4. **Launch Local Server**:
   - Place project inside your web server directory (e.g. `C:/xampp/htdocs/PHP_project/manager`).
   - Start **Apache** and **MySQL** modules from XAMPP Control Panel.
   - Access the portal at `http://localhost/PHP_project/manager/landing.php`.

---

## 🔒 Security Implementations

- **CSRF Token Protection**: Dual validation via hidden POST tokens and `X-CSRF-Token` headers.
- **SQL Injection Prevention**: 100% parameterized queries using PDO prepared statements.
- **XSS Mitigation**: Strict HTML entity sanitization on all user-supplied and dynamic outputs.
- **Secure File Uploads**: File MIME-type verification, randomized filenames, and `.htaccess` execution guards.
- **Session Hardening**: `HttpOnly`, `SameSite=Lax`, and regeneration upon login.

---

## 📄 License & Attribution

Developed with devotion for **Sudarshan Yuvak Mandal, Bhathena, Surat**.  
All rights reserved © 2026.
