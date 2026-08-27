/**
 * Sudarshan Yuvak Mandal - Admin Landing Page Management Controller
 * Handles Mandal Settings, Utsav Events, Karyakartas, Routes, and Memory Gallery.
 */

(function () {
  'use strict';

  const AdminLanding = {
    currentYear: new Date().getFullYear(),

    init() {
      this.initSubTabs();
      this.initSettingsForm();
      this.initEventHandlers();
      this.initKaryakartaHandlers();
      this.initRouteHandlers();
      this.initMemoryHandlers();
      this.loadAll();
    },

    loadAll() {
      this.loadSettings();
      // Load events first to populate year dropdowns, then load year-dependent data
      this.loadEvents(() => {
        this.loadKaryakartas();
        this.loadRoutes();
        this.loadMemories();
      });
    },

    // -------------------------------------------------------------
    // Sub-Tabs Navigation
    // -------------------------------------------------------------
    initSubTabs() {
      const subTabBtns = document.querySelectorAll('.landing-subtab-btn');
      const subTabPanes = document.querySelectorAll('.landing-subtab-pane');

      subTabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          const target = btn.getAttribute('data-subtab');
          subTabBtns.forEach(b => b.classList.remove('active'));
          btn.classList.add('active');

          subTabPanes.forEach(p => p.style.display = 'none');
          const activePane = document.getElementById('landingSubtab' + target.charAt(0).toUpperCase() + target.slice(1));
          if (activePane) activePane.style.display = 'block';

          if (target === 'settings') this.loadSettings();
          else if (target === 'events') this.loadEvents();
          else if (target === 'karyakartas') this.loadKaryakartas();
          else if (target === 'routes') this.loadRoutes();
          else if (target === 'memories') this.loadMemories();
        });
      });
    },

    // -------------------------------------------------------------
    // 1. MANDAL SETTINGS & LOGO
    // -------------------------------------------------------------
    initSettingsForm() {
      const form = document.getElementById('formMandalSettings');
      if (!form) return;

      form.addEventListener('submit', (e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('.btn-submit');
        const formData = new FormData(form);
        formData.append('action', 'save_settings');
        formData.set('csrf_token', this.getCSRFToken());

        this.setBtnLoading(submitBtn, true);
        this.apiPost(formData, (data) => {
          this.setBtnLoading(submitBtn, false);
          this.showNotification('success', data.message || 'Settings saved successfully!');
          this.loadSettings();
        }, (err) => {
          this.setBtnLoading(submitBtn, false);
          this.showNotification('error', err);
        });
      });

      // Logo File Preview
      const logoInput = document.getElementById('settingLogoInput');
      const logoPreview = document.getElementById('settingLogoPreview');
      if (logoInput && logoPreview) {
        logoInput.addEventListener('change', () => {
          const file = logoInput.files[0];
          if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
              logoPreview.src = e.target.result;
              logoPreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
          }
        });
      }
    },

    loadSettings() {
      this.apiGet('get_settings', {}, (data) => {
        const s = data.settings || {};
        const setVal = (id, val) => {
          const el = document.getElementById(id);
          if (el) el.value = val || '';
        };

        setVal('settingMandalName', s.mandal_name || 'Sudarshan Yuvak Mandal');
        setVal('settingFoundingYear', s.founding_year || '');
        setVal('settingContactPerson', s.contact_person || '');
        setVal('settingAddress', s.address || '');
        setVal('settingPhone', s.phone || '');
        setVal('settingWhatsapp', s.whatsapp || '');
        setVal('settingEmail', s.email || '');
        setVal('settingAboutText', s.about_text || '');
        setVal('settingFacebook', s.facebook_url || '');
        setVal('settingInstagram', s.instagram_url || '');
        setVal('settingYoutube', s.youtube_url || '');

        const preview = document.getElementById('settingLogoPreview');
        if (preview && s.logo_path) {
          preview.src = s.logo_path;
          preview.style.display = 'block';
        }
      });
    },

    // -------------------------------------------------------------
    // 2. UTSAV EVENTS
    // -------------------------------------------------------------
    initEventHandlers() {
      const btnAdd = document.getElementById('btnAddEvent');
      const modal = document.getElementById('eventModal');
      const btnClose = document.getElementById('btnCloseEventModal');
      const btnCancel = document.getElementById('btnCancelEventModal');
      const form = document.getElementById('eventForm');

      if (btnAdd) {
        btnAdd.addEventListener('click', () => {
          this.openEventModal();
        });
      }

      const closeModal = () => { if (modal) modal.classList.remove('active'); };
      if (btnClose) btnClose.addEventListener('click', closeModal);
      if (btnCancel) btnCancel.addEventListener('click', closeModal);

      if (form) {
        form.addEventListener('submit', (e) => {
          e.preventDefault();
          const submitBtn = form.querySelector('.btn-submit');
          const formData = new FormData(form);
          formData.append('action', 'save_event');
          formData.set('csrf_token', this.getCSRFToken());

          this.setBtnLoading(submitBtn, true);
          this.apiPost(formData, (data) => {
            this.setBtnLoading(submitBtn, false);
            closeModal();
            this.showNotification('success', data.message || 'Event saved!');
            this.loadEvents();
          }, (err) => {
            this.setBtnLoading(submitBtn, false);
            this.showNotification('error', err);
          });
        });
      }

      // Murtikar Photo Preview
      const photoInput = document.getElementById('murtikarPhotoInput');
      const photoPreview = document.getElementById('murtikarPhotoPreview');
      if (photoInput && photoPreview) {
        photoInput.addEventListener('change', () => {
          const file = photoInput.files[0];
          if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
              photoPreview.src = e.target.result;
              photoPreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
          }
        });
      }
    },

    openEventModal(eventData = null) {
      const modal = document.getElementById('eventModal');
      const title = document.getElementById('eventModalTitle');
      const form = document.getElementById('eventForm');
      if (!modal || !form) return;

      form.reset();
      const preview = document.getElementById('murtikarPhotoPreview');
      if (preview) preview.style.display = 'none';

      if (eventData) {
        title.textContent = `Edit Ganesh Utsav ${eventData.year}`;
        document.getElementById('eventYearInput').value = eventData.year;
        document.getElementById('eventYearInput').readOnly = true;
        document.getElementById('eventThemeInput').value = eventData.theme || '';
        document.getElementById('eventArrivalInput').value = eventData.ganesh_arrival_date || '';
        document.getElementById('eventVisarjanInput').value = eventData.ganesh_visarjan_date || '';
        document.getElementById('murtikarNameInput').value = eventData.murtikar_name || '';
        document.getElementById('murtikarInfoInput').value = eventData.murtikar_info || '';
        document.getElementById('eventIsActiveInput').checked = eventData.is_active == 1;

        if (preview && eventData.murtikar_photo) {
          preview.src = eventData.murtikar_photo;
          preview.style.display = 'block';
        }
      } else {
        title.textContent = 'Add New Ganesh Utsav Event';
        document.getElementById('eventYearInput').value = new Date().getFullYear();
        document.getElementById('eventYearInput').readOnly = false;
        document.getElementById('eventIsActiveInput').checked = true;
      }

      modal.classList.add('active');
    },

    loadEvents(callback = null) {
      const tbody = document.getElementById('tableEventsBody');
      if (!tbody) return;
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:#94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading events...</td></tr>';

      this.apiGet('get_events', {}, (data) => {
        const events = data.events || [];
        const availableYears = data.available_years || [];
        this.updateYearDropdowns(events, availableYears);

        if (callback && typeof callback === 'function') {
          callback();
        }

        if (!events.length) {
          tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:#64748B;">No Ganesh Utsav events configured yet. Click <strong>"+ Add Year Event"</strong> to create one.</td></tr>';
          return;
        }

        tbody.innerHTML = events.map(ev => {
          const activeBadge = ev.is_active == 1
            ? '<span class="badge-status approved" style="font-weight:700;"><i class="fa-solid fa-star"></i> Active Year</span>'
            : `<button type="button" class="btn-secondary-compact btn-set-active-event" data-year="${ev.year}" style="font-size:11px;"><i class="fa-regular fa-star"></i> Set Active</button>`;

          return `
            <tr>
              <td style="font-weight:800;font-size:16px;color:#DA4D12;">${ev.year}</td>
              <td style="font-weight:600;color:#1E293B;">${this.esc(ev.theme || '—')}</td>
              <td style="color:#64748B;font-size:13px;">${ev.ganesh_arrival_date || '—'}</td>
              <td style="color:#64748B;font-size:13px;">${ev.ganesh_visarjan_date || '—'}</td>
              <td>
                <div style="font-weight:600;color:#1E293B;">${this.esc(ev.murtikar_name || '—')}</div>
                ${ev.murtikar_photo ? `<img src="${this.esc(ev.murtikar_photo)}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;margin-top:4px;border:1px solid #E2E8F0;">` : ''}
              </td>
              <td>${activeBadge}</td>
              <td style="text-align:right;white-space:nowrap;">
                <button type="button" class="btn-act-edit btn-edit-event" data-event='${JSON.stringify(ev)}'><i class="fa-solid fa-pen"></i> Edit</button>
                <button type="button" class="btn-act-reject btn-delete-event" data-year="${ev.year}"><i class="fa-solid fa-trash-can"></i></button>
              </td>
            </tr>
          `;
        }).join('');

        tbody.querySelectorAll('.btn-edit-event').forEach(b => {
          b.addEventListener('click', () => {
            const ev = JSON.parse(b.getAttribute('data-event'));
            this.openEventModal(ev);
          });
        });

        tbody.querySelectorAll('.btn-delete-event').forEach(b => {
          b.addEventListener('click', () => {
            const year = b.getAttribute('data-year');
            if (confirm(`Are you sure you want to delete the festival configuration for ${year}?`)) {
              const fd = new FormData();
              fd.append('action', 'delete_event');
              fd.append('year', year);
              fd.set('csrf_token', this.getCSRFToken());
              this.apiPost(fd, (d) => {
                this.showNotification('success', d.message || 'Event deleted.');
                this.loadEvents();
              });
            }
          });
        });

        tbody.querySelectorAll('.btn-set-active-event').forEach(b => {
          b.addEventListener('click', () => {
            const year = b.getAttribute('data-year');
            const fd = new FormData();
            fd.append('action', 'set_active_event');
            fd.append('year', year);
            fd.set('csrf_token', this.getCSRFToken());
            this.apiPost(fd, (d) => {
              this.showNotification('success', d.message || 'Active year updated.');
              this.loadEvents();
            });
          });
        });
      });
    },

    updateYearDropdowns(events, availableYears = []) {
      const yearSelects = [
        document.getElementById('selectRouteYear'),
        document.getElementById('selectMemoryYear')
      ];

      const currentYear = new Date().getFullYear();
      let years = (availableYears && availableYears.length)
        ? availableYears.map(y => parseInt(y))
        : [];

      if (events && events.length) {
        events.forEach(e => {
          const y = parseInt(e.year);
          if (!isNaN(y) && !years.includes(y)) years.push(y);
        });
      }

      if (!years.length) years = [currentYear];

      years = [...new Set(years)].sort((a, b) => b - a);

      yearSelects.forEach(sel => {
        if (!sel) return;
        const currentVal = sel.value || years[0] || currentYear;
        sel.innerHTML = years.map(y => `<option value="${y}" ${y == currentVal ? 'selected' : ''}>${y}</option>`).join('');
      });
    },

    // -------------------------------------------------------------
    // 3. KARYAKARTAS (Global Mandal Committee - Independent Entity)
    // -------------------------------------------------------------
    initKaryakartaHandlers() {
      const btnAdd = document.getElementById('btnAddKaryakarta');
      const modal = document.getElementById('karyakartaModal');
      const btnClose = document.getElementById('btnCloseKaryakartaModal');
      const btnCancel = document.getElementById('btnCancelKaryakartaModal');
      const form = document.getElementById('karyakartaForm');

      if (btnAdd) {
        btnAdd.addEventListener('click', () => this.openKaryakartaModal());
      }

      const closeModal = () => { if (modal) modal.classList.remove('active'); };
      if (btnClose) btnClose.addEventListener('click', closeModal);
      if (btnCancel) btnCancel.addEventListener('click', closeModal);

      if (form) {
        form.addEventListener('submit', (e) => {
          e.preventDefault();
          const submitBtn = form.querySelector('.btn-submit');
          const formData = new FormData(form);
          formData.append('action', 'save_karyakarta');
          formData.set('csrf_token', this.getCSRFToken());

          this.setBtnLoading(submitBtn, true);
          this.apiPost(formData, (data) => {
            this.setBtnLoading(submitBtn, false);
            closeModal();
            this.showNotification('success', data.message || 'Karyakarta saved!');
            this.loadKaryakartas();
          }, (err) => {
            this.setBtnLoading(submitBtn, false);
            this.showNotification('error', err);
          });
        });
      }

      const photoInput = document.getElementById('kkPhotoInput');
      const photoPreview = document.getElementById('kkPhotoPreview');
      if (photoInput && photoPreview) {
        photoInput.addEventListener('change', () => {
          const file = photoInput.files[0];
          if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
              photoPreview.src = e.target.result;
              photoPreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
          }
        });
      }
    },

    openKaryakartaModal(kk = null) {
      const modal = document.getElementById('karyakartaModal');
      const title = document.getElementById('karyakartaModalTitle');
      const form = document.getElementById('karyakartaForm');
      if (!modal || !form) return;

      form.reset();
      const preview = document.getElementById('kkPhotoPreview');
      if (preview) preview.style.display = 'none';

      if (kk) {
        title.textContent = `Edit Karyakarta: ${kk.full_name}`;
        document.getElementById('kkIdInput').value = kk.id;
        document.getElementById('kkNameInput').value = kk.full_name;
        document.getElementById('kkRoleInput').value = kk.role;
        document.getElementById('kkEmailInput').value = kk.email || '';
        document.getElementById('kkWhatsappInput').value = kk.whatsapp || '';
        document.getElementById('kkShowEmailInput').checked = kk.show_email != 0;
        document.getElementById('kkShowWhatsappInput').checked = kk.show_whatsapp != 0;
        document.getElementById('kkOrderInput').value = kk.display_order || 0;
        document.getElementById('kkIsVisibleInput').checked = kk.is_visible == 1;

        if (preview && kk.photo_path) {
          preview.src = kk.photo_path;
          preview.style.display = 'block';
        }
      } else {
        title.textContent = 'Add Committee Member (Karyakarta)';
        document.getElementById('kkIdInput').value = '';
        document.getElementById('kkShowEmailInput').checked = true;
        document.getElementById('kkShowWhatsappInput').checked = true;
        document.getElementById('kkIsVisibleInput').checked = true;
        document.getElementById('kkOrderInput').value = 0;
      }

      modal.classList.add('active');
    },

    loadKaryakartas() {
      const tbody = document.getElementById('tableKaryakartasBody');
      if (!tbody) return;

      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:#94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading karyakartas...</td></tr>';

      this.apiGet('get_karyakartas', {}, (data) => {
        const list = data.karyakartas || [];
        if (!list.length) {
          tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:24px;color:#64748B;">No karyakartas added yet. Click <strong>"+ Add Karyakarta"</strong> to add team members.</td></tr>`;
          return;
        }

        tbody.innerHTML = list.map(kk => {
          const avatar = kk.photo_path
            ? `<img src="${this.esc(kk.photo_path)}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1px solid #E2E8F0;">`
            : `<div style="width:36px;height:36px;border-radius:50%;background:#FF9933;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;">${(kk.full_name[0]||'?').toUpperCase()}</div>`;

          const visibilityBadge = kk.is_visible == 1
            ? '<span class="badge-status approved" style="font-size:11px;">Visible</span>'
            : '<span class="badge-status pending" style="font-size:11px;">Hidden</span>';

          return `
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  ${avatar}
                  <div>
                    <strong style="color:#1E293B;">${this.esc(kk.full_name)}</strong>
                    <div style="font-size:12px;color:#64748B;">${this.esc(kk.role)}</div>
                  </div>
                </div>
              </td>
              <td>${this.esc(kk.role)}</td>
              <td>
                ${kk.email ? `<div><i class="fa-solid fa-envelope" style="font-size:11px;color:#64748B;"></i> ${this.esc(kk.email)} ${kk.show_email == 1 ? '<span style="color:#10B981;font-size:10px;">(Public)</span>' : '<span style="color:#94A3B8;font-size:10px;">(Hidden)</span>'}</div>` : '—'}
                ${kk.whatsapp ? `<div><i class="fa-brands fa-whatsapp" style="font-size:11px;color:#25D366;"></i> ${this.esc(kk.whatsapp)} ${kk.show_whatsapp == 1 ? '<span style="color:#10B981;font-size:10px;">(Public)</span>' : '<span style="color:#94A3B8;font-size:10px;">(Hidden)</span>'}</div>` : ''}
              </td>
              <td>${kk.display_order}</td>
              <td>${visibilityBadge}</td>
              <td style="text-align:right;white-space:nowrap;">
                <button type="button" class="btn-act-edit btn-edit-kk" data-kk='${JSON.stringify(kk)}'><i class="fa-solid fa-pen"></i> Edit</button>
                <button type="button" class="btn-act-reject btn-delete-kk" data-id="${kk.id}"><i class="fa-solid fa-trash-can"></i></button>
              </td>
            </tr>
          `;
        }).join('');

        tbody.querySelectorAll('.btn-edit-kk').forEach(b => {
          b.addEventListener('click', () => {
            const kk = JSON.parse(b.getAttribute('data-kk'));
            this.openKaryakartaModal(kk);
          });
        });

        tbody.querySelectorAll('.btn-delete-kk').forEach(b => {
          b.addEventListener('click', () => {
            const id = b.getAttribute('data-id');
            if (confirm('Are you sure you want to remove this karyakarta?')) {
              const fd = new FormData();
              fd.append('action', 'delete_karyakarta');
              fd.append('id', id);
              fd.set('csrf_token', this.getCSRFToken());
              this.apiPost(fd, (d) => {
                this.showNotification('success', d.message || 'Karyakarta removed.');
                this.loadKaryakartas();
              });
            }
          });
        });
      });
    },

    // -------------------------------------------------------------
    // 4. ROUTES
    // -------------------------------------------------------------
    initRouteHandlers() {
      const btnAdd = document.getElementById('btnAddRoute');
      const modal = document.getElementById('routeModal');
      const btnClose = document.getElementById('btnCloseRouteModal');
      const btnCancel = document.getElementById('btnCancelRouteModal');
      const form = document.getElementById('routeForm');
      const yearSelect = document.getElementById('selectRouteYear');

      if (yearSelect) {
        yearSelect.addEventListener('change', () => this.loadRoutes());
      }

      if (btnAdd) {
        btnAdd.addEventListener('click', () => this.openRouteModal());
      }

      const closeModal = () => { if (modal) modal.classList.remove('active'); };
      if (btnClose) btnClose.addEventListener('click', closeModal);
      if (btnCancel) btnCancel.addEventListener('click', closeModal);

      const mapInput = document.getElementById('routeMapUrlInput');
      if (mapInput) {
        const cleanMapInput = () => {
          const val = mapInput.value.trim();
          if (val.includes('<iframe') && val.includes('src=')) {
            const m = val.match(/src=["']([^"']+)["']/);
            if (m && m[1]) {
              mapInput.value = m[1];
            }
          }
        };
        mapInput.addEventListener('input', cleanMapInput);
        mapInput.addEventListener('paste', () => setTimeout(cleanMapInput, 50));
      }

      if (form) {
        form.addEventListener('submit', (e) => {
          e.preventDefault();
          const submitBtn = form.querySelector('.btn-submit');
          const formData = new FormData(form);

          // Clean map input value if it still contains iframe code
          let mapVal = (document.getElementById('routeMapUrlInput')?.value || '').trim();
          if (mapVal.includes('<iframe') && mapVal.includes('src=')) {
            const m = mapVal.match(/src=["']([^"']+)["']/);
            if (m && m[1]) {
              formData.set('map_embed_url', m[1]);
            }
          }

          formData.append('action', 'save_route');
          formData.set('csrf_token', this.getCSRFToken());

          this.setBtnLoading(submitBtn, true);
          this.apiPost(formData, (data) => {
            this.setBtnLoading(submitBtn, false);
            closeModal();
            this.showNotification('success', data.message || 'Route saved!');
            this.loadRoutes();
          }, (err) => {
            this.setBtnLoading(submitBtn, false);
            this.showNotification('error', err);
          });
        });
      }
    },

    openRouteModal(r = null) {
      const modal = document.getElementById('routeModal');
      const title = document.getElementById('routeModalTitle');
      const form = document.getElementById('routeForm');
      const yearSelect = document.getElementById('selectRouteYear');
      if (!modal || !form) return;

      form.reset();
      const pdfPreview = document.getElementById('routePdfPreview');
      if (pdfPreview) pdfPreview.style.display = 'none';

      const activeYear = (yearSelect && yearSelect.value) ? yearSelect.value : new Date().getFullYear();
      document.getElementById('routeYearInput').value = activeYear;

      if (r) {
        title.textContent = `Edit Route: ${r.title}`;
        document.getElementById('routeIdInput').value = r.id;
        document.getElementById('routeYearInput').value = r.utsav_year;
        document.getElementById('routeTypeInput').value = r.route_type;
        document.getElementById('routeTitleInput').value = r.title;
        document.getElementById('routeDescInput').value = r.description || '';
        document.getElementById('routeMapUrlInput').value = r.map_embed_url || '';
        document.getElementById('routeOrderInput').value = r.display_order || 0;

        if (pdfPreview && r.route_pdf_path) {
          pdfPreview.href = r.route_pdf_path;
          pdfPreview.style.display = 'inline-flex';
        }
      } else {
        title.textContent = `Add Procession Route for Year ${activeYear}`;
        document.getElementById('routeIdInput').value = '';
        document.getElementById('routeTypeInput').value = 'aagman';
        document.getElementById('routeOrderInput').value = 0;
      }

      modal.classList.add('active');
    },

    loadRoutes() {
      const yearSelect = document.getElementById('selectRouteYear');
      const year = (yearSelect && yearSelect.value) ? yearSelect.value : new Date().getFullYear();
      const tbody = document.getElementById('tableRoutesBody');
      if (!tbody) return;

      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:#94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading routes...</td></tr>';

      this.apiGet('get_routes', { year: year }, (data) => {
        const list = data.routes || [];
        if (!list.length) {
          tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:24px;color:#64748B;">No routes added for year ${year} yet. Click <strong>"+ Add Procession Route"</strong> to create one.</td></tr>`;
          return;
        }

        tbody.innerHTML = list.map(r => {
          const typeBadge = r.route_type === 'aagman'
            ? '<span class="badge-status approved" style="font-size:11px;">🚶 Aagman</span>'
            : '<span class="badge-status info" style="font-size:11px;">🌊 Visarjan</span>';

          const mapLink = r.map_embed_url
            ? `<a href="${this.esc(r.map_embed_url)}" target="_blank" style="color:#DA4D12;font-size:12px;font-weight:600;"><i class="fa-solid fa-map-location-dot"></i> Embed Configured</a>`
            : '<span style="color:#94A3B8;font-size:12px;">No map</span>';

          const pdfLink = r.route_pdf_path
            ? `<a href="${this.esc(r.route_pdf_path)}" target="_blank" style="color:#DC2626;font-size:12px;font-weight:600;"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>`
            : '<span style="color:#94A3B8;font-size:12px;">No PDF</span>';

          return `
            <tr>
              <td>${typeBadge}</td>
              <td>
                <strong style="color:#1E293B;">${this.esc(r.title)}</strong>
                ${r.description ? `<div style="font-size:12px;color:#64748B;margin-top:2px;">${this.esc(r.description.substring(0, 80))}${r.description.length > 80 ? '...' : ''}</div>` : ''}
              </td>
              <td>${mapLink}</td>
              <td>${pdfLink}</td>
              <td>${r.display_order}</td>
              <td style="text-align:right;white-space:nowrap;">
                <button type="button" class="btn-act-edit btn-edit-route" data-route='${JSON.stringify(r)}'><i class="fa-solid fa-pen"></i> Edit</button>
                <button type="button" class="btn-act-reject btn-delete-route" data-id="${r.id}"><i class="fa-solid fa-trash-can"></i></button>
              </td>
            </tr>
          `;
        }).join('');

        tbody.querySelectorAll('.btn-edit-route').forEach(b => {
          b.addEventListener('click', () => {
            const r = JSON.parse(b.getAttribute('data-route'));
            this.openRouteModal(r);
          });
        });

        tbody.querySelectorAll('.btn-delete-route').forEach(b => {
          b.addEventListener('click', () => {
            const id = b.getAttribute('data-id');
            if (confirm('Are you sure you want to delete this route?')) {
              const fd = new FormData();
              fd.append('action', 'delete_route');
              fd.append('id', id);
              fd.set('csrf_token', this.getCSRFToken());
              this.apiPost(fd, (d) => {
                this.showNotification('success', d.message || 'Route deleted.');
                this.loadRoutes();
              });
            }
          });
        });
      });
    },

    // -------------------------------------------------------------
    // 5. MEMORY GALLERY
    // -------------------------------------------------------------
    initMemoryHandlers() {
      const btnAdd = document.getElementById('btnAddMemory');
      const modal = document.getElementById('memoryModal');
      const btnClose = document.getElementById('btnCloseMemoryModal');
      const btnCancel = document.getElementById('btnCancelMemoryModal');
      const form = document.getElementById('memoryForm');
      const yearSelect = document.getElementById('selectMemoryYear');
      const typeSelect = document.getElementById('memoryMediaTypeInput');
      const photoGroup = document.getElementById('memPhotoGroup');
      const videoGroup = document.getElementById('memVideoGroup');

      if (yearSelect) {
        yearSelect.addEventListener('change', () => this.loadMemories());
      }

      if (typeSelect) {
        typeSelect.addEventListener('change', () => {
          const isVideo = typeSelect.value === 'video';
          if (photoGroup) photoGroup.style.display = isVideo ? 'none' : 'block';
          if (videoGroup) videoGroup.style.display = isVideo ? 'block' : 'none';
        });
      }

      const photoInput = document.getElementById('memPhotoInput');
      const photoPreview = document.getElementById('memPhotoPreview');
      if (photoInput && photoPreview) {
        photoInput.addEventListener('change', () => {
          const file = photoInput.files[0];
          if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
              photoPreview.src = e.target.result;
              photoPreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
          }
        });
      }

      if (btnAdd) {
        btnAdd.addEventListener('click', () => this.openMemoryModal());
      }

      const closeModal = () => { if (modal) modal.classList.remove('active'); };
      if (btnClose) btnClose.addEventListener('click', closeModal);
      if (btnCancel) btnCancel.addEventListener('click', closeModal);

      if (form) {
        form.addEventListener('submit', (e) => {
          e.preventDefault();
          const submitBtn = form.querySelector('.btn-submit');
          const formData = new FormData(form);
          formData.append('action', 'save_memory');
          formData.set('csrf_token', this.getCSRFToken());

          this.setBtnLoading(submitBtn, true);
          this.apiPost(formData, (data) => {
            this.setBtnLoading(submitBtn, false);
            closeModal();
            this.showNotification('success', data.message || 'Memory saved!');
            this.loadMemories();
          }, (err) => {
            this.setBtnLoading(submitBtn, false);
            this.showNotification('error', err);
          });
        });
      }
    },

    getYouTubeThumb(url) {
      if (!url) return null;
      const m = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([a-zA-Z0-9_-]{11})/i);
      return m ? `https://img.youtube.com/vi/${m[1]}/default.jpg` : null;
    },

    openMemoryModal(m = null) {
      const modal = document.getElementById('memoryModal');
      const title = document.getElementById('memoryModalTitle');
      const form = document.getElementById('memoryForm');
      const yearSelect = document.getElementById('selectMemoryYear');
      const photoGroup = document.getElementById('memPhotoGroup');
      const videoGroup = document.getElementById('memVideoGroup');
      const photoPreview = document.getElementById('memPhotoPreview');
      if (!modal || !form) return;

      form.reset();
      if (photoPreview) photoPreview.style.display = 'none';

      const activeYear = (yearSelect && yearSelect.value) ? yearSelect.value : new Date().getFullYear();
      document.getElementById('memYearInput').value = activeYear;

      if (m) {
        title.textContent = `Edit Memory: ${m.title}`;
        document.getElementById('memIdInput').value = m.id;
        document.getElementById('memYearInput').value = m.utsav_year;
        document.getElementById('memTitleInput').value = m.title;
        document.getElementById('memDescInput').value = m.description || '';
        document.getElementById('memoryMediaTypeInput').value = m.media_type;
        document.getElementById('memVideoUrlInput').value = m.video_url || '';
        document.getElementById('memOrderInput').value = m.display_order || 0;
        document.getElementById('memIsVisibleInput').checked = m.is_visible == 1;

        const isVideo = m.media_type === 'video';
        if (photoGroup) photoGroup.style.display = isVideo ? 'none' : 'block';
        if (videoGroup) videoGroup.style.display = isVideo ? 'block' : 'none';

        if (!isVideo && m.file_path && photoPreview) {
          photoPreview.src = m.file_path;
          photoPreview.style.display = 'block';
        }
      } else {
        title.textContent = `Add Memory for Year ${activeYear}`;
        document.getElementById('memIdInput').value = '';
        document.getElementById('memoryMediaTypeInput').value = 'photo';
        document.getElementById('memIsVisibleInput').checked = true;
        document.getElementById('memOrderInput').value = 0;
        if (photoGroup) photoGroup.style.display = 'block';
        if (videoGroup) videoGroup.style.display = 'none';
      }

      modal.classList.add('active');
    },

    loadMemories() {
      const yearSelect = document.getElementById('selectMemoryYear');
      const year = (yearSelect && yearSelect.value) ? yearSelect.value : new Date().getFullYear();
      const tbody = document.getElementById('tableMemoriesBody');
      if (!tbody) return;

      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:#94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading memories...</td></tr>';

      this.apiGet('get_memories', { year: year }, (data) => {
        const list = data.memories || [];
        if (!list.length) {
          tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:24px;color:#64748B;">No memories added for year ${year} yet. Click <strong>"+ Add Photo / Video Memory"</strong> to upload moments.</td></tr>`;
          return;
        }

        tbody.innerHTML = list.map(m => {
          const isVideo = m.media_type === 'video';
          let preview = '—';
          if (isVideo) {
            const ytThumb = this.getYouTubeThumb(m.video_url);
            if (ytThumb) {
              preview = `<div style="position:relative;width:48px;height:36px;border-radius:6px;overflow:hidden;border:1px solid #E2E8F0;"><img src="${ytThumb}" style="width:100%;height:100%;object-fit:cover;"><div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.4);color:#FF5500;font-size:10px;"><i class="fa-solid fa-play"></i></div></div>`;
            } else if (m.file_path) {
              preview = `<div style="width:48px;height:36px;background:#1E293B;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#FF5500;"><i class="fa-solid fa-video"></i></div>`;
            } else {
              preview = `<div style="width:48px;height:36px;background:#1E293B;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#FF5500;"><i class="fa-solid fa-link"></i></div>`;
            }
          } else if (m.file_path) {
            preview = `<img src="${this.esc(m.file_path)}" style="width:48px;height:36px;border-radius:6px;object-fit:cover;border:1px solid #E2E8F0;">`;
          }

          const typeBadge = isVideo
            ? '<span class="badge-status info" style="font-size:11px;">🎥 Video</span>'
            : '<span class="badge-status approved" style="font-size:11px;">📷 Photo</span>';

          const visibilityBadge = m.is_visible == 1
            ? '<span class="badge-status approved" style="font-size:11px;">Visible</span>'
            : '<span class="badge-status pending" style="font-size:11px;">Hidden</span>';

          return `
            <tr>
              <td>${preview}</td>
              <td>
                <strong style="color:#1E293B;">${this.esc(m.title)}</strong>
                ${m.description ? `<div style="font-size:12px;color:#64748B;margin-top:2px;">${this.esc(m.description.substring(0, 80))}${m.description.length > 80 ? '...' : ''}</div>` : ''}
              </td>
              <td>${typeBadge}</td>
              <td>${m.display_order}</td>
              <td>${visibilityBadge}</td>
              <td style="text-align:right;white-space:nowrap;">
                <button type="button" class="btn-act-edit btn-edit-mem" data-mem='${JSON.stringify(m)}'><i class="fa-solid fa-pen"></i> Edit</button>
                <button type="button" class="btn-act-reject btn-delete-mem" data-id="${m.id}"><i class="fa-solid fa-trash-can"></i></button>
              </td>
            </tr>
          `;
        }).join('');

        tbody.querySelectorAll('.btn-edit-mem').forEach(b => {
          b.addEventListener('click', () => {
            const m = JSON.parse(b.getAttribute('data-mem'));
            this.openMemoryModal(m);
          });
        });

        tbody.querySelectorAll('.btn-delete-mem').forEach(b => {
          b.addEventListener('click', () => {
            const id = b.getAttribute('data-id');
            if (confirm('Are you sure you want to delete this memory?')) {
              const fd = new FormData();
              fd.append('action', 'delete_memory');
              fd.append('id', id);
              fd.set('csrf_token', this.getCSRFToken());
              this.apiPost(fd, (d) => {
                this.showNotification('success', d.message || 'Memory deleted.');
                this.loadMemories();
              });
            }
          });
        });
      });
    },

    // -------------------------------------------------------------
    // HTTP Helpers & CSRF
    // -------------------------------------------------------------
    getCSRFToken() {
      return (window.APP_CONFIG && window.APP_CONFIG.csrfToken) ||
             document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
             document.querySelector('input[name="csrf_token"]')?.value ||
             '';
    },

    apiGet(action, params, onSuccess, onError) {
      const csrf = this.getCSRFToken();
      const q = new URLSearchParams(Object.assign({ action: action }, params));
      fetch(`api/admin_landing_handler.php?${q.toString()}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': csrf
        }
      })
      .then(async (res) => {
        let data;
        try {
          data = await res.json();
        } catch (e) {
          data = { status: 'error', message: `Server returned error (${res.status})` };
        }
        if (res.ok && data.status === 'success') {
          if (onSuccess) onSuccess(data);
        } else {
          const errMsg = data.message || `Request failed with status ${res.status}`;
          if (onError) onError(errMsg);
          else this.showNotification('error', errMsg);
        }
      })
      .catch(err => {
        if (onError) onError(err.message);
        else this.showNotification('error', 'Network error. Please try again.');
      });
    },

    apiPost(formData, onSuccess, onError) {
      const csrf = this.getCSRFToken();
      if (formData instanceof FormData) {
        if (!formData.has('csrf_token') || !formData.get('csrf_token')) {
          formData.set('csrf_token', csrf);
        }
      }
      fetch('api/admin_landing_handler.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': csrf
        }
      })
      .then(async (res) => {
        let data;
        try {
          data = await res.json();
        } catch (e) {
          data = { status: 'error', message: `Server error (${res.status})` };
        }
        if (res.ok && data.status === 'success') {
          if (onSuccess) onSuccess(data);
        } else {
          const errMsg = data.message || `Operation failed with status ${res.status}`;
          if (onError) onError(errMsg);
          else this.showNotification('error', errMsg);
        }
      })
      .catch(err => {
        if (onError) onError(err.message);
        else this.showNotification('error', 'Network error occurred.');
      });
    },

    setBtnLoading(btn, isLoading) {
      if (!btn) return;
      btn.disabled = isLoading;
      const spinner = btn.querySelector('.btn-spinner');
      const text = btn.querySelector('.btn-text-icon');
      if (spinner) spinner.style.display = isLoading ? 'inline-block' : 'none';
      if (text) text.style.opacity = isLoading ? '0.4' : '1';
    },

    showNotification(type, message) {
      if (window.showAppNotification) {
        window.showAppNotification(type, message);
        return;
      }
      alert(message);
    },

    esc(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }
  };

  document.addEventListener('DOMContentLoaded', () => {
    AdminLanding.init();
  });

})();
