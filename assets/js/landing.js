/**
 * Sudarshan Yuvak Mandal — Public Landing Page Controller
 * Handles live countdown, interactive particle canvas, gallery filtering, lightbox modal, routes switcher, and smooth animations.
 */

(function () {
  'use strict';

  let allMemories    = [];
  let allRoutes      = [];
  let activeFilter   = 'all';
  let activeRouteTab = 'aagman';

  const CACHE_KEY = 'smd_landing_cache_v2';

  function applyLandingData(data) {
    if (!data || data.status !== 'success') return;
    renderHeroAndSettings(data.settings, data.active_event);
    renderCountdown(data.active_event, data.countdown_info);
    renderAbout(data.settings, data);
    renderEventInfo(data.active_event);
    renderKaryakartas(data.karyakartas || []);
    renderRoutes(data.routes || [], data.active_event?.year);
    renderGallery(data.memories || [], data.gallery_years || [], data.active_year || data.active_event?.year);
    renderContact(data.settings);
  }

  // -----------------------------------------------------------------------
  // Bootstrap Application (Instant Paint + Stale-While-Revalidate)
  // -----------------------------------------------------------------------
  async function init() {
    setupNavScroll();
    setupHamburger();
    setupBackToTop();
    setupRevealObserver();
    setupLightbox();
    initFestiveParticles();

    // 1. Instant Cache-First Paint (< 5ms)
    try {
      const cached = sessionStorage.getItem(CACHE_KEY);
      if (cached) {
        const parsed = JSON.parse(cached);
        if (parsed && parsed.data) {
          applyLandingData(parsed.data);
        }
      }
    } catch (e) {}

    // 2. Background Revalidation from API
    try {
      const res = await fetch('api/public_landing_api.php?action=all', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (res.ok) {
        const data = await res.json();
        if (data.status === 'success') {
          try {
            sessionStorage.setItem(CACHE_KEY, JSON.stringify({ time: Date.now(), data }));
          } catch (e) {}
          applyLandingData(data);
        }
      }
    } catch (err) {
      console.warn('Background landing sync:', err);
    }
  }

  // -----------------------------------------------------------------------
  // 1. HERO & MANDAL BRANDING
  // -----------------------------------------------------------------------
  function renderHeroAndSettings(settings, event) {
    if (!settings) return;

    const mandalName = settings.mandal_name || 'Sudarshan Yuvak Mandal';
    const address = settings.address || 'Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat, Gujarat';

    document.title = `${mandalName} | Official Ganesh Utsav Portal, Surat`;

    const nameEl = document.getElementById('heroMandalName');
    if (nameEl) {
      nameEl.innerHTML = `<span class="gradient-text">${escHtml(mandalName)}</span>`;
    }

    const navTitleEl = document.getElementById('navTitle');
    if (navTitleEl) navTitleEl.textContent = mandalName;

    const footerTitleEl = document.getElementById('footerMandalName');
    if (footerTitleEl) footerTitleEl.textContent = mandalName;

    const tagEl = document.getElementById('heroTagline');
    if (tagEl) {
      tagEl.textContent = event && event.theme
        ? `Ganesh Utsav ${event.year} — ${event.theme}`
        : 'Ganesh Utsav Celebrations & Seva';
    }

    const addrEl = document.getElementById('heroAddress');
    if (addrEl) {
      addrEl.innerHTML = `<i class="fa-solid fa-location-dot" aria-hidden="true"></i> <span>${escHtml(address)}</span>`;
    }

    const footerAddr = document.getElementById('footerAddress');
    if (footerAddr) footerAddr.textContent = address;

    const logoPath = settings.logo_path;
    if (logoPath) {
      const heroLogo = document.getElementById('heroLogo');
      const heroLogoIcon = document.getElementById('heroLogoIcon');
      if (heroLogo) {
        heroLogo.src = logoPath;
        heroLogo.style.display = 'block';
        if (heroLogoIcon) heroLogoIcon.style.display = 'none';
      }

      const navLogo = document.getElementById('navLogo');
      const navLogoIcon = document.getElementById('navLogoIcon');
      if (navLogo) {
        navLogo.src = logoPath;
        navLogo.style.display = 'block';
        if (navLogoIcon) navLogoIcon.style.display = 'none';
      }
    }
  }

  // -----------------------------------------------------------------------
  // 2. LIVE COUNTDOWN TIMER (Dynamic Multi-Year & Auto-Rollover Engine)
  // -----------------------------------------------------------------------
  let countdownTimerId = null;

  function renderCountdown(event, countdownInfo) {
    const box = document.getElementById('countdownBox');
    if (!box) return;

    if (countdownTimerId) {
      clearInterval(countdownTimerId);
      countdownTimerId = null;
    }

    const state = countdownInfo?.state || 'upcoming';
    const targetDateStr = countdownInfo?.target_date || event?.ganesh_arrival_date;
    const targetYear = countdownInfo?.target_year || event?.year || new Date().getFullYear();

    if (!targetDateStr) {
      box.innerHTML = `
        <div style="text-align: center; padding: 14px 0; color: var(--text-muted);">
          <i class="fa-solid fa-clock-rotate-left" style="font-size: 22px; color: var(--primary); margin-bottom: 6px; display: block;"></i>
          <strong>Ganesh Utsav dates will be announced soon.</strong>
        </div>`;
      return;
    }

    const targetDate = new Date(targetDateStr + 'T00:00:00');
    const nameEl = document.getElementById('countdownEventName');

    if (nameEl) {
      if (state === 'live') {
        nameEl.innerHTML = `<i class="fa-solid fa-om" style="color: var(--primary);"></i> <span>🙏 <strong>Ganesh Utsav ${targetYear} Is LIVE!</strong> Visarjan Countdown:</span>`;
      } else if (state === 'rollover') {
        nameEl.innerHTML = `<i class="fa-solid fa-sparkles" style="color: var(--primary);"></i> <span>🌸 Next Celebration — <strong>Ganesh Chaturthi ${targetYear} (${formatDate(targetDateStr)})</strong></span>`;
      } else {
        nameEl.innerHTML = `<i class="fa-solid fa-calendar-check" style="color: var(--primary);"></i> <span>Ganesh Aagman ${targetYear}: <strong>${formatDate(targetDateStr)}</strong></span>`;
      }
    }

    function updateDigits() {
      const now = new Date();
      let diff = targetDate - now;

      const dEl = document.getElementById('cdDays');
      const hEl = document.getElementById('cdHours');
      const mEl = document.getElementById('cdMins');
      const sEl = document.getElementById('cdSecs');

      if (!dEl || !hEl || !mEl || !sEl) return;

      if (diff <= 0) {
        dEl.textContent = '00';
        hEl.textContent = '00';
        mEl.textContent = '00';
        sEl.textContent = '00';
        if (nameEl && state === 'upcoming') {
          nameEl.innerHTML = `🎉 <strong>Ganesh Chaturthi ${targetYear} Celebrations Are Live! Jai Shree Ganesh!</strong>`;
        }
        return;
      }

      const days  = Math.floor(diff / (1000 * 60 * 60 * 24));
      const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const mins  = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
      const secs  = Math.floor((diff % (1000 * 60)) / 1000);

      dEl.textContent = String(days).padStart(2, '0');
      hEl.textContent = String(hours).padStart(2, '0');
      mEl.textContent = String(mins).padStart(2, '0');
      sEl.textContent = String(secs).padStart(2, '0');
    }

    updateDigits();
    countdownTimerId = setInterval(updateDigits, 1000);
  }

  // -----------------------------------------------------------------------
  // 3. ABOUT SECTION
  // -----------------------------------------------------------------------
  function renderAbout(settings, data) {
    if (!settings) return;
    const aboutEl = document.getElementById('aboutText');
    if (aboutEl && settings.about_text) {
      aboutEl.textContent = settings.about_text;
    }

    const yrEl = document.getElementById('foundingYear');
    if (yrEl) {
      yrEl.textContent = settings.founding_year || 'Legacy';
    }

    const kkCountEl = document.getElementById('statKaryakartasCount');
    if (kkCountEl && data && data.karyakartas) {
      const count = data.karyakartas.length;
      kkCountEl.textContent = count > 0 ? `${count}+` : '50+';
    }
  }

  // -----------------------------------------------------------------------
  // 4. THIS YEAR'S CELEBRATION & MURTIKAR (All features optional)
  // -----------------------------------------------------------------------
  function renderEventInfo(event) {
    const sec = document.getElementById('eventSection');
    if (!sec) return;

    if (!event) {
      sec.innerHTML = `
        <div class="empty-data-placeholder">
          <i class="fa-solid fa-calendar-xmark"></i>
          <p>Annual festival details will be published by the Mandal committee soon.</p>
        </div>`;
      return;
    }

    const yrHeading = document.getElementById('eventYearHeading');
    if (yrHeading) yrHeading.textContent = `${event.year} Celebrations`;

    const yrEl = document.getElementById('eventYear');
    if (yrEl) yrEl.textContent = event.year;

    // Theme (optional)
    const themeBox = document.getElementById('eventThemeBox');
    const themeEl = document.getElementById('eventTheme');
    if (event.theme) {
      if (themeEl) themeEl.textContent = event.theme;
      if (themeBox) themeBox.style.display = 'block';
    } else if (themeBox) {
      themeBox.style.display = 'none';
    }

    // Arrival & Visarjan Dates (optional)
    const arrivalEl = document.getElementById('eventArrival');
    if (arrivalEl) arrivalEl.textContent = formatDate(event.ganesh_arrival_date);

    const visarjanBox = document.getElementById('eventVisarjanBox');
    const visarjanEl = document.getElementById('eventVisarjan');
    if (event.ganesh_visarjan_date) {
      if (visarjanEl) visarjanEl.textContent = formatDate(event.ganesh_visarjan_date);
      if (visarjanBox) visarjanBox.style.display = 'block';
    } else if (visarjanBox) {
      visarjanBox.style.display = 'none';
    }

    // Murtikar Details (Completely optional: hide card if not configured)
    const murtikarCard = document.getElementById('murtikarCard');
    const hasMurtikar = event.murtikar_name || event.murtikar_photo || event.murtikar_info;

    if (murtikarCard) {
      if (!hasMurtikar) {
        murtikarCard.style.display = 'none';
        const innerGrid = document.querySelector('.event-card-inner-grid');
        if (innerGrid) innerGrid.style.gridTemplateColumns = '1fr';
      } else {
        murtikarCard.style.display = 'block';
        const murtikarName = document.getElementById('murtikarName');
        if (murtikarName) murtikarName.textContent = event.murtikar_name || 'Respected Murtikar';

        const murtikarInfo = document.getElementById('murtikarInfo');
        if (murtikarInfo) murtikarInfo.textContent = event.murtikar_info || 'Renowned sculptor crafting our sacred idol.';

        const photoEl = document.getElementById('murtikarPhoto');
        const placeholderEl = document.getElementById('murtikarPhotoPlaceholder');
        if (event.murtikar_photo && photoEl) {
          photoEl.src = event.murtikar_photo;
          photoEl.style.display = 'block';
          if (placeholderEl) placeholderEl.style.display = 'none';
        } else {
          if (photoEl) photoEl.style.display = 'none';
          if (placeholderEl) placeholderEl.style.display = 'flex';
        }
      }
    }
  }

  // -----------------------------------------------------------------------
  // 5. KARYAKARTAS TEAM (Email & WhatsApp only, no phone numbers)
  // -----------------------------------------------------------------------
  function renderKaryakartas(list) {
    const grid = document.getElementById('kkGrid');
    if (!grid) return;

    if (!list || !list.length) {
      grid.innerHTML = `
        <div class="empty-data-placeholder">
          <i class="fa-solid fa-users"></i>
          <p>Committee members list for this year will be updated soon.</p>
        </div>`;
      return;
    }

    grid.innerHTML = list.map((kk, idx) => {
      const initials = (kk.full_name || '?')
        .trim()
        .split(/\s+/)
        .map(w => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

      const avatarHtml = kk.photo_path
        ? `<img src="${escHtml(kk.photo_path)}" alt="${escHtml(kk.full_name)}" class="kk-avatar-img" loading="lazy" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
           <div class="kk-avatar-initials" style="display:none;">${initials}</div>`
        : `<div class="kk-avatar-initials">${initials}</div>`;

      // WhatsApp direct link
      const waBtn = kk.whatsapp
        ? `<a href="https://wa.me/91${kk.whatsapp.replace(/\D/g, '')}" target="_blank" rel="noopener" class="btn-kk-contact btn-whatsapp" aria-label="WhatsApp ${escHtml(kk.full_name)}"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>`
        : '';

      // Email direct link (No phone)
      const emailBtn = kk.email
        ? `<a href="mailto:${escHtml(kk.email)}" class="btn-kk-contact btn-email" aria-label="Email ${escHtml(kk.full_name)}"><i class="fa-solid fa-envelope"></i> Email</a>`
        : '';

      return `
        <div class="kk-card-item reveal" style="animation-delay: ${(idx * 0.04).toFixed(2)}s;">
          <div class="kk-avatar-frame">
            ${avatarHtml}
          </div>
          <h3 class="kk-member-name">${escHtml(kk.full_name)}</h3>
          <span class="kk-member-role">${escHtml(kk.role)}</span>
          ${(waBtn || emailBtn) ? `<div class="kk-action-buttons">${waBtn}${emailBtn}</div>` : ''}
        </div>
      `;
    }).join('');

    observeReveals();
  }

  // -----------------------------------------------------------------------
  // 6. PROCESSION ROUTES (Safe map embed - prevents X-Frame-Options errors)
  // -----------------------------------------------------------------------
  function renderRoutes(routes, year) {
    allRoutes = routes;
    const grid = document.getElementById('routesGrid');
    const tabsContainer = document.getElementById('routeTabs');
    if (!grid || !tabsContainer) return;

    if (!routes || !routes.length) {
      grid.innerHTML = `
        <div class="empty-data-placeholder">
          <i class="fa-solid fa-route"></i>
          <p>Procession routes and map details will be published closer to the festival.</p>
        </div>`;
      tabsContainer.innerHTML = '';
      return;
    }

    const hasAagman   = routes.some(r => r.route_type === 'aagman');
    const hasVisarjan = routes.some(r => r.route_type === 'visarjan');

    tabsContainer.innerHTML = '';

    if (hasAagman) {
      const aBtn = createRouteTabBtn('aagman', '🚶 Ganesh Aagman Route', activeRouteTab === 'aagman');
      tabsContainer.appendChild(aBtn);
    }
    if (hasVisarjan) {
      const vBtn = createRouteTabBtn('visarjan', '🌊 Visarjan Route', (!hasAagman || activeRouteTab === 'visarjan'));
      tabsContainer.appendChild(vBtn);
    }

    if (!hasAagman && hasVisarjan) activeRouteTab = 'visarjan';

    renderRouteCards();
  }

  function createRouteTabBtn(type, label, isActive) {
    const btn = document.createElement('button');
    btn.className = `route-tab-pill ${isActive ? 'active' : ''}`;
    btn.dataset.type = type;
    btn.innerHTML = label;
    btn.addEventListener('click', () => {
      document.querySelectorAll('.route-tab-pill').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeRouteTab = type;
      renderRouteCards();
    });
    return btn;
  }

  function isEmbeddableMap(url) {
    if (!url) return false;
    const lower = url.toLowerCase();
    return lower.includes('embed') || lower.includes('output=embed');
  }

  function renderRouteCards() {
    const grid = document.getElementById('routesGrid');
    if (!grid) return;

    const filtered = allRoutes.filter(r => r.route_type === activeRouteTab);

    if (!filtered.length) {
      grid.innerHTML = `
        <div class="empty-data-placeholder">
          <i class="fa-solid fa-map-location-dot"></i>
          <p>No ${activeRouteTab === 'aagman' ? 'Aagman' : 'Visarjan'} route entries published yet.</p>
        </div>`;
      return;
    }

    grid.innerHTML = filtered.map(r => {
      let mapHtml = '';
      if (r.map_embed_url) {
        if (isEmbeddableMap(r.map_embed_url)) {
          mapHtml = `<iframe class="route-map-frame" src="${escHtml(r.map_embed_url)}" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="${escHtml(r.title)} Route Map"></iframe>`;
        } else {
          // Render as external link to avoid X-Frame-Options blocking
          mapHtml = `
            <a href="${escHtml(r.map_embed_url)}" target="_blank" rel="noopener" class="btn-route-pdf" style="background:linear-gradient(135deg,#3B82F6,#1D4ED8);margin-bottom:10px;">
              <i class="fa-solid fa-map-location-dot"></i> Open Route in Google Maps
            </a>
          `;
        }
      }

      return `
        <div class="route-detail-card reveal">
          <div class="route-card-top">
            <span class="route-pill-badge ${r.route_type}">
              ${r.route_type === 'aagman' ? '🚶 Aagman' : '🌊 Visarjan'}
            </span>
            <h3 class="route-title-text">${escHtml(r.title)}</h3>
          </div>
          <div class="route-card-main">
            ${r.description ? `<p class="route-desc-text">${escHtml(r.description)}</p>` : ''}
            ${mapHtml}
            ${r.route_pdf_path ? `
              <a href="${escHtml(r.route_pdf_path)}" target="_blank" download class="btn-route-pdf">
                <i class="fa-solid fa-file-pdf"></i> Download Route Map (PDF)
              </a>
            ` : ''}
          </div>
        </div>
      `;
    }).join('');

    observeReveals();
  }

  // -----------------------------------------------------------------------
  // 7. FESTIVAL MEMORIES & VIDEO GALLERY (On-Demand Year Fetching)
  // -----------------------------------------------------------------------
  const yearMemoriesCache = {};

  function renderGallery(memories, years, activeYear) {
    const selectedYear = String(activeYear || (years && years[0]) || new Date().getFullYear());
    yearMemoriesCache[selectedYear] = memories || [];
    renderGalleryYearFilters(years, selectedYear);
    renderGalleryGrid(selectedYear, memories);
  }

  function renderGalleryYearFilters(years, activeYear) {
    const container = document.getElementById('yearFilterContainer');
    if (!container) return;

    container.innerHTML = '';
    const safeYears = (years && years.length) ? years : [String(activeYear)];

    safeYears.forEach(y => {
      const isSelected = String(y) === String(activeYear);
      container.appendChild(createGalleryYearBtn(String(y), `📅 ${y}`, isSelected));
    });
  }

  function createGalleryYearBtn(val, label, isActive) {
    const btn = document.createElement('button');
    btn.className = `btn-gallery-year ${isActive ? 'active' : ''}`;
    btn.textContent = label;
    btn.dataset.year = val;
    btn.addEventListener('click', async () => {
      document.querySelectorAll('.btn-gallery-year').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      if (yearMemoriesCache[val]) {
        renderGalleryGrid(val, yearMemoriesCache[val]);
        return;
      }

      const grid = document.getElementById('galleryGrid');
      if (grid) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#FF5500;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><p style="margin-top:10px;color:#64748B;">Loading memories...</p></div>';
      }

      try {
        const res = await fetch(`api/public_landing_api.php?action=memories_by_year&year=${val}`);
        const data = await res.json();
        const mems = data.memories || [];
        yearMemoriesCache[val] = mems;
        renderGalleryGrid(val, mems);
      } catch (e) {
        renderGalleryGrid(val, []);
      }
    });
    return btn;
  }

  function renderGalleryGrid(filter, specificMemories) {
    const grid = document.getElementById('galleryGrid');
    if (!grid) return;

    const list = specificMemories || [];

    if (!list.length) {
      grid.innerHTML = `
        <div class="empty-data-placeholder">
          <i class="fa-solid fa-images"></i>
          <p>No photos or videos uploaded for year ${escHtml(filter)} yet.</p>
        </div>`;
      return;
    }

    grid.innerHTML = list.map(mem => {
      const isVideo = mem.media_type === 'video';
      const ytId = isVideo && mem.video_url ? getYouTubeId(mem.video_url) : null;
      const embedUrl = isVideo ? getEmbedUrl(mem.video_url || '') : '';
      const localVideo = isVideo && mem.file_path ? mem.file_path : null;

      if (isVideo) {
        let mediaPreviewHtml = '';
        if (ytId) {
          mediaPreviewHtml = `
            <img src="https://img.youtube.com/vi/${ytId}/hqdefault.jpg" alt="${escHtml(mem.title)}" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
            <div class="gallery-play-btn-circle"><i class="fa-solid fa-play"></i></div>
          `;
        } else if (localVideo) {
          mediaPreviewHtml = `
            <video src="${escHtml(localVideo)}" preload="metadata" muted playsinline style="width:100%;height:100%;object-fit:cover;pointer-events:none;"></video>
            <div class="gallery-play-btn-circle"><i class="fa-solid fa-play"></i></div>
          `;
        } else {
          mediaPreviewHtml = `
            <div style="width:100%;height:100%;background:#0F172A;display:flex;align-items:center;justify-content:center;">
              <div class="gallery-play-btn-circle"><i class="fa-solid fa-play"></i></div>
            </div>
          `;
        }

        return `
          <div class="gallery-tile-item reveal" data-type="video" data-embed="${escHtml(embedUrl)}" data-local="${escHtml(localVideo || '')}" data-title="${escHtml(mem.title)}" tabindex="0" role="button" aria-label="Play video ${escHtml(mem.title)}">
            <div style="width:100%;height:100%;background:#0D0500;display:flex;align-items:center;justify-content:center;position:relative;">
              ${mediaPreviewHtml}
            </div>
            <div class="gallery-tile-overlay">
              <h4 class="gallery-tile-title">${escHtml(mem.title)}</h4>
              <span class="gallery-tile-meta"><i class="fa-solid fa-video"></i> Video • ${mem.utsav_year}</span>
            </div>
          </div>
        `;
      } else {
        return `
          <div class="gallery-tile-item reveal" data-type="photo" data-src="${escHtml(mem.file_path || '')}" data-title="${escHtml(mem.title)}" tabindex="0" role="button" aria-label="View photo ${escHtml(mem.title)}">
            <img src="${escHtml(mem.file_path || '')}" alt="${escHtml(mem.title)}" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
            <div class="gallery-tile-overlay">
              <h4 class="gallery-tile-title">${escHtml(mem.title)}</h4>
              <span class="gallery-tile-meta"><i class="fa-solid fa-camera"></i> ${mem.utsav_year}</span>
            </div>
          </div>
        `;
      }
    }).join('');

    grid.querySelectorAll('.gallery-tile-item').forEach(item => {
      item.addEventListener('click', () => openLightbox(item));
      item.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openLightbox(item);
        }
      });
    });

    observeReveals();
  }

  function getYouTubeId(url) {
    if (!url) return null;
    const m = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([a-zA-Z0-9_-]{11})/i);
    return m ? m[1] : null;
  }

  function getEmbedUrl(url) {
    if (!url) return '';
    const ytId = getYouTubeId(url);
    if (ytId) return `https://www.youtube.com/embed/${ytId}`;
    const vmMatch = url.match(/vimeo\.com\/(\d+)/i);
    if (vmMatch) return `https://player.vimeo.com/video/${vmMatch[1]}`;
    return url;
  }

  // -----------------------------------------------------------------------
  // LIGHTBOX LOGIC
  // -----------------------------------------------------------------------
  function setupLightbox() {
    const overlay = document.getElementById('lightboxOverlay');
    const closeBtn = document.getElementById('lightboxClose');
    if (!overlay) return;

    closeBtn?.addEventListener('click', closeLightbox);
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeLightbox();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeLightbox();
    });
  }

  function openLightbox(item) {
    const overlay = document.getElementById('lightboxOverlay');
    const imgEl   = document.getElementById('lightboxImg');
    const videoEl = document.getElementById('lightboxVideo');
    const capEl   = document.getElementById('lightboxCaption');
    if (!overlay) return;

    const type  = item.dataset.type;
    const title = item.dataset.title || '';

    if (type === 'photo') {
      const src = item.dataset.src;
      if (!src) return;
      imgEl.src = src;
      imgEl.style.display = 'block';
      if (videoEl) {
        videoEl.innerHTML = '';
        videoEl.style.display = 'none';
      }
    } else if (type === 'video') {
      imgEl.style.display = 'none';
      imgEl.src = '';
      if (videoEl) {
        videoEl.style.display = 'block';
        const embedUrl = item.dataset.embed;
        const localSrc = item.dataset.local;
        if (embedUrl) {
          videoEl.innerHTML = `
            <iframe src="${escHtml(embedUrl)}?autoplay=1" style="width:min(90vw,900px);height:min(75vh,520px);border:none;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.8);" allow="autoplay; fullscreen" title="${escHtml(title)}"></iframe>`;
        } else if (localSrc) {
          videoEl.innerHTML = `
            <video src="${escHtml(localSrc)}" controls autoplay playsinline style="max-width:min(90vw,900px);max-height:min(75vh,520px);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.8);background:#000;outline:none;"></video>`;
        }
      }
    }

    if (capEl) capEl.textContent = title;
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    const overlay = document.getElementById('lightboxOverlay');
    const imgEl   = document.getElementById('lightboxImg');
    const videoEl = document.getElementById('lightboxVideo');
    if (!overlay) return;

    overlay.classList.remove('active');
    if (imgEl) imgEl.src = '';
    if (videoEl) videoEl.innerHTML = '';
    document.body.style.overflow = '';
  }

  // -----------------------------------------------------------------------
  // 8. CONTACT & SOCIAL
  // -----------------------------------------------------------------------
  function renderContact(settings) {
    if (!settings) return;

    const addrEl = document.getElementById('contactAddress');
    if (addrEl && settings.address) addrEl.textContent = settings.address;

    const phoneEl = document.getElementById('contactPhone');
    if (phoneEl && settings.phone) {
      phoneEl.href = 'tel:+91' + settings.phone.replace(/\D/g, '');
      phoneEl.textContent = settings.phone;
    }

    const emailEl = document.getElementById('contactEmail');
    if (emailEl && settings.email) {
      emailEl.href = 'mailto:' + settings.email;
      emailEl.textContent = settings.email;
    }

    const waEl = document.getElementById('contactWhatsapp');
    const waCircle = document.getElementById('contactWaCircle');
    if (settings.whatsapp) {
      const waUrl = 'https://wa.me/91' + settings.whatsapp.replace(/\D/g, '');
      if (waEl) waEl.href = waUrl;
      if (waCircle) waCircle.href = waUrl;
    }

    const fbEl = document.getElementById('contactFb');
    if (fbEl && settings.facebook_url) {
      fbEl.href = settings.facebook_url;
      fbEl.style.display = 'flex';
    }

    const igEl = document.getElementById('contactIg');
    if (igEl && settings.instagram_url) {
      igEl.href = settings.instagram_url;
      igEl.style.display = 'flex';
    }

    const ytEl = document.getElementById('contactYt');
    if (ytEl && settings.youtube_url) {
      ytEl.href = settings.youtube_url;
      ytEl.style.display = 'flex';
    }
  }

  // -----------------------------------------------------------------------
  // NAVIGATION, SCROLL OBSERVER & BACK-TO-TOP
  // -----------------------------------------------------------------------
  function setupNavScroll() {
    const nav = document.getElementById('lpNav');
    if (!nav) return;
    window.addEventListener('scroll', () => {
      nav.classList.toggle('scrolled', window.scrollY > 50);
    }, { passive: true });
  }

  function setupHamburger() {
    const btn = document.getElementById('navHamburger');
    const links = document.getElementById('navLinks');
    if (!btn || !links) return;

    btn.addEventListener('click', () => {
      const isOpen = links.classList.toggle('open');
      btn.setAttribute('aria-expanded', String(isOpen));
    });

    links.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', () => {
        links.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
      });
    });
  }

  function setupBackToTop() {
    const btn = document.getElementById('btnBackToTop');
    if (!btn) return;

    window.addEventListener('scroll', () => {
      btn.classList.toggle('show', window.scrollY > 400);
    }, { passive: true });

    btn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  let revealObserver;
  function setupRevealObserver() {
    revealObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    observeReveals();
  }

  function observeReveals() {
    document.querySelectorAll('.reveal:not(.visible)').forEach(el => {
      if (revealObserver) revealObserver.observe(el);
    });
  }

  // -----------------------------------------------------------------------
  // FESTIVE CANVAS PARTICLE ENGINE
  // -----------------------------------------------------------------------
  function initFestiveParticles() {
    const canvas = document.getElementById('landingCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let W = canvas.width  = window.innerWidth;
    let H = canvas.height = window.innerHeight;

    const symbols = ['✦', '✨', '🌸', '✿', 'ॐ', '॥', '🌼'];
    const particleCount = Math.min(30, Math.floor(W / 40));

    const particles = Array.from({ length: particleCount }, () => ({
      x: Math.random() * W,
      y: Math.random() * H,
      size: 11 + Math.random() * 14,
      speedY: 0.3 + Math.random() * 0.6,
      speedX: (Math.random() - 0.5) * 0.4,
      rotation: Math.random() * Math.PI * 2,
      rotationSpeed: (Math.random() - 0.5) * 0.02,
      symbol: symbols[Math.floor(Math.random() * symbols.length)],
      opacity: 0.12 + Math.random() * 0.2,
      color: Math.random() > 0.4 ? '#FF5500' : '#F59E0B'
    }));

    window.addEventListener('resize', () => {
      W = canvas.width  = window.innerWidth;
      H = canvas.height = window.innerHeight;
    }, { passive: true });

    function animate() {
      ctx.clearRect(0, 0, W, H);

      particles.forEach(p => {
        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate(p.rotation);
        ctx.globalAlpha = p.opacity;
        ctx.font = `${p.size}px 'Tiro Devanagari Sanskrit', serif`;
        ctx.fillStyle = p.color;
        ctx.fillText(p.symbol, -p.size / 2, p.size / 2);
        ctx.restore();

        p.y -= p.speedY;
        p.x += p.speedX;
        p.rotation += p.rotationSpeed;

        if (p.y < -30) {
          p.y = H + 30;
          p.x = Math.random() * W;
        }
        if (p.x < -30 || p.x > W + 30) {
          p.x = Math.random() * W;
        }
      });

      requestAnimationFrame(animate);
    }

    animate();
  }

  // -----------------------------------------------------------------------
  // HELPERS
  // -----------------------------------------------------------------------
  function escHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatDate(str) {
    if (!str) return '—';
    try {
      const d = new Date(str + 'T00:00:00');
      return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' });
    } catch (e) {
      return str;
    }
  }

  // -----------------------------------------------------------------------
  // DOM Ready
  // -----------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', init);

})();
