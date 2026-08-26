<?php
/**
 * Sudarshan Yuvak Mandal — Official Public Portal & Landing Page
 * Location: Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat, Gujarat
 * Dynamically managed by Mandal Admin. Safe public access.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/includes/logger.php';

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <meta name="description" content="Sudarshan Yuvak Mandal — Official Ganesh Utsav Portal. Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat. Live countdown, procession routes, karyakarta team, murtikar, and memory gallery.">
  <meta name="keywords" content="Sudarshan Yuvak Mandal, Ganesh Utsav Surat, Bhathena Ganesh, Ranchhod Nagar Mandal, Aagman Route, Visarjan Route, Karyakarta, Ganesh Chaturthi <?php echo date('Y'); ?>">
  <meta name="theme-color" content="#FF5500">

  <!-- Open Graph Meta Tags -->
  <meta property="og:title" content="Sudarshan Yuvak Mandal | Ganesh Utsav Official Portal, Surat">
  <meta property="og:description" content="Official Ganesh Utsav Portal of Sudarshan Yuvak Mandal, Bhathena, Surat. Real-time countdown, procession routes, team contacts, and festival memories.">
  <meta property="og:type" content="website">
  
  <!-- Permissions Policy for Embedded Maps & Sensor Features -->
  <meta http-equiv="Permissions-Policy" content="accelerometer=*, gyroscope=*, magnetometer=*, geolocation=*">
  
  <title>Sudarshan Yuvak Mandal | Official Ganesh Utsav Portal, Surat</title>

  <!-- Google Fonts Preconnect & Stylesheets -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Rozha+One&family=Tiro+Devanagari+Sanskrit:ital@0;1&display=swap" rel="stylesheet">
  
  <!-- FontAwesome 6 Pro Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Main Landing Page Stylesheet -->
  <link rel="stylesheet" href="assets/css/landing.css?v=<?php echo filemtime(__DIR__ . '/assets/css/landing.css'); ?>">
</head>
<body>

<!-- Ambient Glow Orbs -->
<div class="bg-glow-orb glow-orb-1" aria-hidden="true"></div>
<div class="bg-glow-orb glow-orb-2" aria-hidden="true"></div>
<div class="bg-glow-orb glow-orb-3" aria-hidden="true"></div>

<!-- Festive Floating Canvas -->
<canvas id="landingCanvas" aria-hidden="true"></canvas>

<!-- ================================================================
     SACRED TOP ANNOUNCEMENT BAR
     ================================================================ -->
<div class="sacred-top-bar" role="region" aria-label="Auspicious Header">
  <span class="shloka-text">॥ ॐ गं गणपतये नमः ॥ श्री गणेशाय नमः ॥</span>
  <span class="location-pill"><i class="fa-solid fa-location-dot"></i> Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat</span>
</div>

<!-- ================================================================
     STICKY NAVIGATION BAR
     ================================================================ -->
<nav class="lp-nav" id="lpNav" role="navigation" aria-label="Main Navigation">
  <div class="nav-inner">
    
    <!-- Brand Logo & Title -->
    <a href="#home" class="nav-brand" aria-label="Sudarshan Yuvak Mandal Home">
      <div class="nav-logo-wrapper">
        <div id="navLogoIcon" class="nav-logo-placeholder" aria-hidden="true">ॐ</div>
        <img id="navLogo" class="nav-logo" src="" alt="Mandal Logo" style="display:none;" loading="eager">
      </div>
      <div class="nav-title-group">
        <span class="nav-title" id="navTitle">Sudarshan Yuvak Mandal</span>
        <span class="nav-subtitle">Ganesh Utsav • Surat</span>
      </div>
    </a>

    <!-- Navigation Links -->
    <ul class="nav-links" id="navLinks" role="list">
      <li><a href="#home">Home</a></li>
      <li><a href="#about">About</a></li>
      <li><a href="#event">This Year</a></li>
      <li><a href="#karyakartas">Karyakartas</a></li>
      <li><a href="#routes">Routes</a></li>
      <li><a href="#gallery">Gallery</a></li>
      <li><a href="#contact">Contact</a></li>
      <li>
        <a href="login.php" class="nav-login-btn" id="navLoginBtn">
          <i class="fa-solid fa-right-to-bracket"></i> Member Portal
        </a>
      </li>
    </ul>

    <!-- Mobile Hamburger Toggle -->
    <button class="nav-hamburger" id="navHamburger" aria-label="Toggle navigation menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- ================================================================
     HERO SECTION
     ================================================================ -->
<section class="hero-section" id="home" aria-label="Hero Introduction">
  <div class="hero-background-pattern" aria-hidden="true"></div>
  <div class="hero-content">

    <!-- Mandal Emblem with Rotating Halo -->
    <div class="hero-emblem-container">
      <div class="hero-mandala-halo" aria-hidden="true"></div>
      <div class="hero-pulse-ring" aria-hidden="true"></div>
      <div id="heroLogoIcon" class="hero-logo-icon" aria-label="Sudarshan Emblem">ॐ</div>
      <img id="heroLogo" class="hero-logo" src="" alt="Sudarshan Yuvak Mandal Emblem" style="display:none;" loading="eager">
    </div>

    <!-- Live Status Pill Badge -->
    <div class="hero-divine-badge">
      <span class="pulse-dot" aria-hidden="true"></span>
      <span>Official Ganesh Utsav Portal</span>
    </div>

    <!-- Mandal Name Headline -->
    <h1 class="hero-mandal-name" id="heroMandalName">
      <span class="gradient-text">Sudarshan</span> Yuvak Mandal
    </h1>

    <p class="hero-tagline" id="heroTagline">Ganesh Utsav Celebrations &amp; Seva</p>
    
    <div class="hero-address-pill" id="heroAddress">
      <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
      <span>Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat, Gujarat</span>
    </div>

    <!-- 3D COUNTDOWN TIMER -->
    <div class="countdown-master-box reveal" role="timer" aria-live="polite" aria-label="Festival Countdown">
      <div class="countdown-header-tag">
        <i class="fa-solid fa-om" aria-hidden="true"></i>
        <span>Ganesh Aagman Countdown</span>
        <i class="fa-solid fa-om" aria-hidden="true"></i>
      </div>

      <div id="countdownBox">
        <div class="countdown-grid-cards">
          <div class="cd-digit-card">
            <span class="cd-number" id="cdDays">--</span>
            <span class="cd-unit-label">Days</span>
          </div>
          <span class="cd-separator" aria-hidden="true">:</span>
          <div class="cd-digit-card">
            <span class="cd-number" id="cdHours">--</span>
            <span class="cd-unit-label">Hours</span>
          </div>
          <span class="cd-separator" aria-hidden="true">:</span>
          <div class="cd-digit-card">
            <span class="cd-number" id="cdMins">--</span>
            <span class="cd-unit-label">Mins</span>
          </div>
          <span class="cd-separator" aria-hidden="true">:</span>
          <div class="cd-digit-card">
            <span class="cd-number" id="cdSecs">--</span>
            <span class="cd-unit-label">Secs</span>
          </div>
        </div>
        <div class="countdown-event-title" id="countdownEventName">
          <i class="fa-solid fa-calendar-check" style="color: var(--primary);"></i>
          <span>Counting down to Ganesh Arrival</span>
        </div>
      </div>
    </div>

    <!-- Quick Action CTA Buttons -->
    <div class="hero-cta-group reveal">
      <a href="#event" class="btn-cta-primary">
        <i class="fa-solid fa-calendar-star"></i> This Year's Celebrations
      </a>
      <a href="#routes" class="btn-cta-secondary">
        <i class="fa-solid fa-route"></i> Procession Routes
      </a>
      <a href="#gallery" class="btn-cta-secondary">
        <i class="fa-solid fa-photo-film"></i> Photo Gallery
      </a>
    </div>

  </div>
</section>

<!-- ================================================================
     ABOUT MANDAL SECTION
     ================================================================ -->
<section class="lp-section bg-alt" id="about" aria-label="About the Mandal">
  <div class="container">
    
    <div class="section-head reveal">
      <span class="section-eyebrow"><i class="fa-solid fa-om"></i> Devotion &amp; Heritage</span>
      <h2 class="section-title">Our Sacred <span class="highlight">Legacy</span></h2>
      <p class="section-subtitle">Dedicated to unity, cultural preservation, and the glorious celebration of Lord Shree Ganesha.</p>
    </div>

    <div class="about-grid-layout">
      
      <!-- Left: Story & Shloka Card -->
      <div class="about-story-card reveal">
        <div class="shloka-banner-box">
          <p class="shloka-sanskrit">वक्रतुण्ड महाकाय सूर्यकोटि समप्रभ ।<br>निर्विघ्नं कुरु मे देव सर्वकार्येषु सर्वदा ॥</p>
          <p class="shloka-trans">"O Lord Ganesha with the curved trunk and immense aura, remove all obstacles from our endeavours forever."</p>
        </div>
        <p class="about-paragraph" id="aboutText">
          Welcome to Sudarshan Yuvak Mandal, established in Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat. For years, our committee and youth have united with devotion to organize grand Ganesh Utsav celebrations, Aarti processions, and social initiatives for our community.
        </p>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px;">
          <span style="background: rgba(255,85,0,0.08); color: var(--primary); padding: 6px 14px; border-radius: var(--radius-full); font-size: 12px; font-weight: 700;">
            <i class="fa-solid fa-hands-praying"></i> Akhand Bhakti
          </span>
          <span style="background: rgba(245,158,11,0.12); color: var(--gold-dark); padding: 6px 14px; border-radius: var(--radius-full); font-size: 12px; font-weight: 700;">
            <i class="fa-solid fa-people-group"></i> Youth Unity
          </span>
          <span style="background: rgba(16,185,129,0.1); color: #059669; padding: 6px 14px; border-radius: var(--radius-full); font-size: 12px; font-weight: 700;">
            <i class="fa-solid fa-bowl-rice"></i> Maha Prasad &amp; Seva
          </span>
        </div>
      </div>

      <!-- Right: Feature / Stat Cards -->
      <div class="about-stats-grid">
        <div class="stat-card reveal reveal-delay-1">
          <div class="stat-icon orange"><i class="fa-solid fa-calendar-check"></i></div>
          <div class="stat-number" id="foundingYear">—</div>
          <div class="stat-label">Year Established</div>
        </div>

        <div class="stat-card reveal reveal-delay-2">
          <div class="stat-icon gold"><i class="fa-solid fa-users"></i></div>
          <div class="stat-number" id="statKaryakartasCount">50+</div>
          <div class="stat-label">Active Karyakartas</div>
        </div>

        <div class="stat-card reveal reveal-delay-1">
          <div class="stat-icon green"><i class="fa-solid fa-heart-pulse"></i></div>
          <div class="stat-number">100%</div>
          <div class="stat-label">Community Seva</div>
        </div>

        <div class="stat-card reveal reveal-delay-2">
          <div class="stat-icon red"><i class="fa-solid fa-location-dot"></i></div>
          <div class="stat-number">Bhathena</div>
          <div class="stat-label">Surat, Gujarat</div>
        </div>
      </div>

    </div>

  </div>
</section>

<!-- ================================================================
     THIS YEAR'S FESTIVAL & MURTIKAR SPOTLIGHT
     ================================================================ -->
<section class="lp-section" id="event" aria-label="This Year's Ganesh Utsav">
  <div class="container">

    <div class="section-head reveal">
      <span class="section-eyebrow"><i class="fa-solid fa-calendar-days"></i> Annual Utsav</span>
      <h2 class="section-title">Ganesh Chaturthi <span class="highlight" id="eventYearHeading">Celebrations</span></h2>
      <p class="section-subtitle">Festival schedules, arrival dates, immersion timings, and our honored murtikar.</p>
    </div>

    <div id="eventSection">
      <div class="event-master-card reveal">
        <div class="event-card-inner-grid">

          <!-- Left: Event Information -->
          <div>
            <div class="event-year-badge-row">
              <span class="event-big-year" id="eventYear">—</span>
              <div class="event-theme-box" id="eventThemeBox">
                <span class="event-theme-label">Utsav Theme</span>
                <div class="event-theme-value" id="eventTheme">To Be Announced</div>
              </div>
            </div>

            <div class="event-dates-grid">
              <div class="event-date-item" id="eventArrivalBox">
                <div class="date-item-tag">
                  <i class="fa-solid fa-truck-arrow-right"></i> Ganesh Aagman (Arrival)
                </div>
                <div class="date-item-val" id="eventArrival">—</div>
              </div>

              <div class="event-date-item" id="eventVisarjanBox">
                <div class="date-item-tag">
                  <i class="fa-solid fa-water"></i> Visarjan (Immersion)
                </div>
                <div class="date-item-val" id="eventVisarjan">—</div>
              </div>
            </div>
          </div>

          <!-- Right: Murtikar Spotlight (Optional) -->
          <div class="murtikar-spotlight-card" id="murtikarCard">
            <div class="murtikar-avatar-wrap">
              <div id="murtikarPhotoPlaceholder" class="murtikar-placeholder-avatar" aria-label="Murtikar icon">
                <i class="fa-solid fa-hands"></i>
              </div>
              <img id="murtikarPhoto" class="murtikar-img" src="" alt="Murtikar Photo" style="display:none;" loading="lazy">
            </div>

            <h3 class="murtikar-artist-name" id="murtikarName">Murtikar Announced Soon</h3>
            <span class="murtikar-role-pill"><i class="fa-solid fa-award"></i> Respected Idol Sculptor</span>
            <p class="murtikar-bio-text" id="murtikarInfo">Dedicated craftsman creating our divine idol with traditional reverence.</p>
          </div>

        </div>
      </div>
    </div>

  </div>
</section>

<!-- ================================================================
     KARYAKARTAS TEAM SECTION
     ================================================================ -->
<section class="lp-section bg-alt" id="karyakartas" aria-label="Karyakarta Committee Members">
  <div class="container">

    <div class="section-head reveal">
      <span class="section-eyebrow"><i class="fa-solid fa-users"></i> Dedicated Team</span>
      <h2 class="section-title">Our <span class="highlight">Karyakarta</span> Committee</h2>
      <p class="section-subtitle">The hardworking youth and leaders who selflessly manage every aspect of the Ganesh Utsav.</p>
    </div>

    <!-- Karyakarta Dynamic Grid -->
    <div class="kk-grid-layout" id="kkGrid" role="list" aria-label="Committee Members List">
      <!-- Injected by landing.js -->
    </div>

  </div>
</section>

<!-- ================================================================
     PROCESSION ROUTES (AAGMAN & VISARJAN)
     ================================================================ -->
<section class="lp-section" id="routes" aria-label="Procession Routes">
  <div class="container">

    <div class="section-head reveal">
      <span class="section-eyebrow"><i class="fa-solid fa-route"></i> Live Routes</span>
      <h2 class="section-title">Aagman &amp; Visarjan <span class="highlight">Routes</span></h2>
      <p class="section-subtitle">Detailed procession paths, landmark intersections, interactive Google Map, and downloadable map PDFs.</p>
    </div>

    <!-- Segmented Tab Switcher -->
    <div class="routes-segmented-control reveal" id="routeTabs" role="tablist" aria-label="Route Type Filter"></div>

    <!-- Dynamic Route Cards Grid -->
    <div class="routes-cards-grid" id="routesGrid">
      <!-- Injected by landing.js -->
    </div>

  </div>
</section>

<!-- ================================================================
     CHERISHED MEMORIES & VIDEO GALLERY
     ================================================================ -->
<section class="lp-section bg-alt" id="gallery" aria-label="Festive Memories Gallery">
  <div class="container">

    <div class="section-head reveal">
      <span class="section-eyebrow"><i class="fa-solid fa-camera-retro"></i> Gallery</span>
      <h2 class="section-title">Cherished <span class="highlight">Memories</span></h2>
      <p class="section-subtitle">Photos and video moments from past and present Ganesh Utsav celebrations.</p>
    </div>

    <!-- Year Filter Bar -->
    <div class="gallery-filter-bar reveal" id="yearFilterContainer" role="group" aria-label="Filter memories by year">
      <!-- Injected by landing.js -->
    </div>

    <!-- Gallery Cards Grid -->
    <div class="gallery-cards-grid" id="galleryGrid" role="list" aria-label="Memory Photos and Videos">
      <!-- Injected by landing.js -->
    </div>

  </div>
</section>

<!-- ================================================================
     CONTACT & LOCATION HUB
     ================================================================ -->
<section class="lp-section" id="contact" aria-label="Contact and Location">
  <div class="container">

    <div class="section-head reveal">
      <span class="section-eyebrow"><i class="fa-solid fa-address-card"></i> Get In Touch</span>
      <h2 class="section-title">Reach Our <span class="highlight">Mandal</span></h2>
      <p class="section-subtitle">Have questions or want to participate in Ganesh Utsav? Contact our committee directly.</p>
    </div>

    <!-- Contact Cards Grid -->
    <div class="contact-cards-grid">
      <div class="contact-box reveal">
        <div class="contact-box-icon" style="background: rgba(255, 85, 0, 0.1); color: var(--primary);">
          <i class="fa-solid fa-location-dot"></i>
        </div>
        <div class="contact-box-label">Mandal Location</div>
        <div class="contact-box-value" id="contactAddress">
          Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat, Gujarat
        </div>
      </div>

      <div class="contact-box reveal reveal-delay-1">
        <div class="contact-box-icon" style="background: rgba(37, 211, 102, 0.12); color: #16A34A;">
          <i class="fa-brands fa-whatsapp"></i>
        </div>
        <div class="contact-box-label">WhatsApp Direct</div>
        <div class="contact-box-value">
          <a id="contactWhatsapp" href="#" target="_blank" rel="noopener">Send WhatsApp Message</a>
        </div>
      </div>

      <div class="contact-box reveal reveal-delay-2">
        <div class="contact-box-icon" style="background: rgba(245, 158, 11, 0.12); color: var(--gold-dark);">
          <i class="fa-solid fa-phone"></i>
        </div>
        <div class="contact-box-label">Phone Contact</div>
        <div class="contact-box-value">
          <a id="contactPhone" href="#">—</a>
        </div>
      </div>

      <div class="contact-box reveal reveal-delay-3">
        <div class="contact-box-icon" style="background: rgba(239, 68, 68, 0.1); color: #DC2626;">
          <i class="fa-solid fa-envelope"></i>
        </div>
        <div class="contact-box-label">Email Address</div>
        <div class="contact-box-value">
          <a id="contactEmail" href="#">—</a>
        </div>
      </div>
    </div>

    <!-- Social Media Links -->
    <div class="social-links-bar reveal">
      <a id="contactFb" href="#" target="_blank" rel="noopener" class="social-circle-btn facebook" style="display:none;" aria-label="Facebook Page">
        <i class="fa-brands fa-facebook-f"></i>
      </a>
      <a id="contactIg" href="#" target="_blank" rel="noopener" class="social-circle-btn instagram" style="display:none;" aria-label="Instagram Profile">
        <i class="fa-brands fa-instagram"></i>
      </a>
      <a id="contactYt" href="#" target="_blank" rel="noopener" class="social-circle-btn youtube" style="display:none;" aria-label="YouTube Channel">
        <i class="fa-brands fa-youtube"></i>
      </a>
      <a id="contactWaCircle" href="#" target="_blank" rel="noopener" class="social-circle-btn whatsapp" aria-label="WhatsApp Chat">
        <i class="fa-brands fa-whatsapp"></i>
      </a>
    </div>

  </div>
</section>

<!-- ================================================================
     LIGHTBOX MEDIA MODAL (Photos & Videos)
     ================================================================ -->
<div class="lightbox-modal" id="lightboxOverlay" role="dialog" aria-modal="true" aria-label="Media Preview">
  <button class="lightbox-close-btn" id="lightboxClose" aria-label="Close modal">
    <i class="fa-solid fa-xmark"></i>
  </button>
  <img class="lightbox-img-el" id="lightboxImg" src="" alt="Fullscreen view">
  <div id="lightboxVideo" style="display:none;"></div>
  <div class="lightbox-caption-text" id="lightboxCaption"></div>
</div>

<!-- ================================================================
     FLOATING BACK TO TOP BUTTON
     ================================================================ -->
<button class="btn-back-to-top" id="btnBackToTop" aria-label="Scroll to top">
  <i class="fa-solid fa-chevron-up"></i>
</button>

<!-- ================================================================
     FOOTER
     ================================================================ -->
<footer class="lp-site-footer" role="contentinfo">
  <div class="container">
    <span class="footer-om-symbol" aria-hidden="true">ॐ</span>
    <h3 class="footer-mandal-title" id="footerMandalName">Sudarshan Yuvak Mandal</h3>
    <p class="footer-address" id="footerAddress">
      Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat, Gujarat
    </p>

    <div class="footer-bottom-row">
      <span>&copy; <?php echo date('Y'); ?> Sudarshan Yuvak Mandal. All Rights Reserved.</span>
      <span>
        <a href="login.php"><i class="fa-solid fa-lock"></i> Committee Member Login</a>
      </span>
    </div>
  </div>
</footer>

<!-- Landing Page Controller Script -->
<script src="assets/js/landing.js?v=<?php echo filemtime(__DIR__ . '/assets/js/landing.js'); ?>"></script>

</body>
</html>
