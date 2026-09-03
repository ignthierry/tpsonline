/**
 * CEISA 4.0 TPS Online Dashboard — Client-Side Application
 * Handles navigation, API calls, table rendering, and export
 */

(function () {
    'use strict';

    // ===== State =====
    const state = {
        currentCategory: null,
        currentEndpoint: null,
        lastResponse: null,
        tableData: [],
        sortColumn: null,
        sortDirection: 'asc',
        currentPage: 1,
        rowsPerPage: 25,
        searchTerm: '',
    };

    // ===== Endpoint Definitions (loaded from PHP) =====
    let ENDPOINTS = {};

    // ===== DOM Elements =====
    const $ = (sel) => document.querySelector(sel);
    const $$ = (sel) => document.querySelectorAll(sel);

    // ===== Theme Management =====
    const THEME_STORAGE_KEY = 'ceisa_theme';

    function getPreferredTheme() {
        const storedTheme = localStorage.getItem(THEME_STORAGE_KEY);
        if (storedTheme) {
            return storedTheme;
        }
        return 'dark'; // default theme
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        updateThemeToggleUI(theme);
    }

    function setTheme(theme) {
        localStorage.setItem(THEME_STORAGE_KEY, theme);
        applyTheme(theme);
    }

    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || getPreferredTheme();
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
        showToast(newTheme === 'dark' ? '🌙 Mode Gelap diaktifkan' : '☀️ Mode Terang diaktifkan', 'info');
    }

    function updateThemeToggleUI(theme) {
        const isDark = theme === 'dark';
        document.querySelectorAll('.theme-toggle, .theme-toggle-floating').forEach((btn) => {
            const icon = btn.querySelector('.theme-toggle-icon');
            const text = btn.querySelector('.theme-toggle-text');
            if (icon) icon.textContent = isDark ? '🌙' : '☀️';
            if (text) text.textContent = isDark ? 'Dark' : 'Light';
            btn.setAttribute('title', isDark ? 'Ubah ke Mode Terang' : 'Ubah ke Mode Gelap');
        });
    }

    function setupThemeToggle() {
        const preferred = getPreferredTheme();
        applyTheme(preferred);

        document.querySelectorAll('.theme-toggle, .theme-toggle-floating').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                toggleTheme();
            });
        });
    }

    // ===== Initialize =====
    function init() {
        setupThemeToggle();

        // Parse endpoint definitions embedded in the page
        const defEl = document.getElementById('endpoint-definitions');
        if (defEl) {
            try {
                ENDPOINTS = JSON.parse(defEl.textContent);
            } catch (e) {
                console.error('Failed to parse endpoint definitions:', e);
            }
        }

        setupNavigation();
        setupMobileMenu();
        showHomePage();
    }

    // ===== Navigation =====
    function setupNavigation() {
        // Category nav items
        document.querySelectorAll('.nav-item[data-category]').forEach((item) => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const category = item.dataset.category;
                toggleCategory(item, category);
            });
        });

        // Sub-nav endpoint items
        document.querySelectorAll('.nav-subitem[data-endpoint]').forEach((item) => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const endpoint = item.dataset.endpoint;
                const category = item.closest('.nav-section').querySelector('.nav-item').dataset.category;
                selectEndpoint(category, endpoint);
            });
        });

        // Home link
        const homeLink = document.querySelector('.nav-item[data-page="home"]');
        if (homeLink) {
            homeLink.addEventListener('click', (e) => {
                e.preventDefault();
                clearActiveNav();
                homeLink.classList.add('active');
                showHomePage();
            });
        }
    }

    function toggleCategory(item, category) {
        const subitems = item.nextElementSibling;
        const isOpen = item.classList.contains('expanded');

        if (isOpen) {
            item.classList.remove('expanded');
            if (subitems) subitems.classList.remove('open');
        } else {
            closeOtherSidebarSections(item);
            item.classList.add('expanded');
            if (subitems) subitems.classList.add('open');
        }
    }

    function selectEndpoint(category, endpoint) {
        state.currentCategory = category;
        state.currentEndpoint = endpoint;
        state.lastResponse = null;
        state.tableData = [];
        state.currentPage = 1;
        state.searchTerm = '';

        // Update nav active state
        clearActiveNav();
        const subitem = document.querySelector(`.nav-subitem[data-endpoint="${endpoint}"]`);
        if (subitem) subitem.classList.add('active');

        // Find endpoint definition
        const catDef = ENDPOINTS[category];
        if (!catDef) return;
        const epDef = catDef.endpoints[endpoint];
        if (!epDef) return;

        // Update breadcrumb
        updateBreadcrumb(catDef.label, epDef.label);

        // Render endpoint page
        renderEndpointPage(endpoint, epDef, catDef);
    }

    function clearActiveNav() {
        document.querySelectorAll('.nav-item.active, .nav-subitem.active').forEach((el) => {
            el.classList.remove('active');
        });
    }

    function updateBreadcrumb(category, endpoint) {
        const breadcrumb = document.getElementById('header-breadcrumb');
        if (breadcrumb) {
            breadcrumb.innerHTML = `
                <span>Dashboard</span>
                <span class="sep">›</span>
                <span>${category}</span>
                <span class="sep">›</span>
                <span class="current">${endpoint}</span>
            `;
        }
    }

    // ===== Mobile Menu =====
    function setupMobileMenu() {
        const toggle = document.getElementById('menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');

        if (toggle) {
            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('visible');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                overlay.classList.remove('visible');
            });
        }
    }

    // ===== Sidebar Navigation Helpers =====
    function closeOtherSidebarSections(exceptEl) {
        const kirimToggle = document.getElementById('kirimDataToggle');
        const kirimSub = document.getElementById('kirimDataSubitems');
        if (kirimToggle && kirimToggle !== exceptEl) {
            kirimToggle.classList.remove('expanded');
            if (kirimSub) kirimSub.classList.remove('open');
        }

        const lapToggle = document.getElementById('laporanToggle');
        const lapSub = document.getElementById('laporanSubitems');
        if (lapToggle && lapToggle !== exceptEl) {
            lapToggle.classList.remove('expanded');
            if (lapSub) lapSub.classList.remove('open');
        }

        document.querySelectorAll('.dynamic-cat-toggle').forEach(t => {
            if (t !== exceptEl) {
                t.classList.remove('expanded');
                if (t.nextElementSibling) t.nextElementSibling.classList.remove('open');
            }
        });
    }

    function openSidebarMenu(toggleId, subitemsId) {
        const toggle = document.getElementById(toggleId);
        const subitems = document.getElementById(subitemsId);

        // Tutup sub-menu lain terlebih dahulu (accordion mode)
        closeOtherSidebarSections(toggle);

        // Buka mobile menu jika tertutup
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        if (sidebar && window.innerWidth <= 992) {
            sidebar.classList.add('open');
            if (overlay) overlay.classList.add('visible');
        }

        if (toggle) {
            if (!toggle.classList.contains('expanded')) {
                toggle.classList.add('expanded');
                if (subitems) subitems.classList.add('open');
            }
            toggle.scrollIntoView({ behavior: 'smooth', block: 'center' });
            toggle.classList.remove('nav-highlight-pulse');
            void toggle.offsetWidth;
            toggle.classList.add('nav-highlight-pulse');
            setTimeout(() => toggle.classList.remove('nav-highlight-pulse'), 1800);
        }
    }

    function openSidebarCategory(catKey) {
        const item = document.querySelector(`.nav-item[data-category="${catKey}"]`);

        // Tutup sub-menu lain terlebih dahulu (accordion mode)
        closeOtherSidebarSections(item);

        // Buka mobile menu jika tertutup
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        if (sidebar && window.innerWidth <= 992) {
            sidebar.classList.add('open');
            if (overlay) overlay.classList.add('visible');
        }

        if (item) {
            const subitems = item.nextElementSibling;
            if (!item.classList.contains('expanded')) {
                item.classList.add('expanded');
                if (subitems) subitems.classList.add('open');
            }
            item.scrollIntoView({ behavior: 'smooth', block: 'center' });
            item.classList.remove('nav-highlight-pulse');
            void item.offsetWidth;
            item.classList.add('nav-highlight-pulse');
            setTimeout(() => item.classList.remove('nav-highlight-pulse'), 1800);
        }
    }

    // ===== Home Page =====
    function showHomePage() {
        const content = document.getElementById('main-content');
        updateBreadcrumb('', '');
        const breadcrumb = document.getElementById('header-breadcrumb');
        if (breadcrumb) breadcrumb.innerHTML = '<span class="current">Dashboard</span>';

        let quickCardsHtml = `
            <div class="quick-card" onclick="CeisaApp.openSidebarMenu('kirimDataToggle', 'kirimDataSubitems')">
                <div class="qc-icon">📤</div>
                <h4>Kirim Data</h4>
                <p>Kirim data Container, Kemasan, Tracking & YOR ke CEISA 4.0</p>
                <div class="qc-count">5 layanan tersedia</div>
            </div>
        `;
        for (const [catKey, cat] of Object.entries(ENDPOINTS)) {
            const epCount = Object.keys(cat.endpoints).length;
            quickCardsHtml += `
                <div class="quick-card" onclick="CeisaApp.openSidebarCategory('${catKey}')">
                    <div class="qc-icon">${cat.icon}</div>
                    <h4>${cat.label}</h4>
                    <p>Akses data ${cat.label.toLowerCase()} dari API CEISA</p>
                    <div class="qc-count">${epCount} endpoint tersedia</div>
                </div>
            `;
        }
        quickCardsHtml += `
            <div class="quick-card" onclick="CeisaApp.openSidebarMenu('laporanToggle', 'laporanSubitems')">
                <div class="qc-icon">📊</div>
                <h4>Laporan</h4>
                <p>Monitoring & laporan data Container, Kemasan, Tracking & YOR</p>
                <div class="qc-count">5 laporan tersedia</div>
            </div>
        `;

        const todayStr = new Date().toLocaleDateString('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });

        content.innerHTML = `
            <div class="welcome-section hero-section">
                <div class="hero-left">
                    <div class="hero-badge">
                        <span class="hero-badge-dot"></span>
                        <span>CEISA 4.0 REST API • Host-to-Host Connected</span>
                    </div>
                    <h2 class="hero-title">Selamat Datang di TPS Online H2H 👋</h2>
                    <p class="hero-desc">
                        Sistem otomasi dan integrasi pertukaran data kepabeanan PT. Primamas Segara Utama dengan CEISA 4.0 Bea Cukai. Akses cepat pengiriman Coarri Codeco, respon dokumen pabean, dan pelaporan operasional TPS.
                    </p>
                    <div class="hero-actions">
                        <button type="button" class="btn-hero-primary" onclick="CeisaApp.openSidebarMenu('kirimDataToggle', 'kirimDataSubitems')">
                            <span>📤</span>
                            <span>Kirim Data TPS</span>
                        </button>
                        <button type="button" class="btn-hero-secondary" onclick="CeisaApp.openSidebarMenu('laporanToggle', 'laporanSubitems')">
                            <span>📊</span>
                            <span>Lihat Laporan</span>
                        </button>
                        <button type="button" class="btn-hero-tertiary" onclick="refreshAccessToken()">
                            <span>🔄</span>
                            <span>Refresh Token SSO</span>
                        </button>
                    </div>
                </div>
                <div class="hero-right">
                    <div class="hero-info-card">
                        <div class="hic-header">
                            <div class="hic-icon">🏢</div>
                            <div>
                                <div class="hic-title">PT. Primamas Segara Utama</div>
                                <div class="hic-subtitle">TPS Lini 2 • KPPBC TMP A Marunda</div>
                            </div>
                        </div>
                        <div class="hic-divider"></div>
                        <div class="hic-grid">
                            <div class="hic-item">
                                <span class="hic-label">Kode TPS</span>
                                <span class="hic-val">PSU0</span>
                            </div>
                            <div class="hic-item">
                                <span class="hic-label">Kode Gudang</span>
                                <span class="hic-val">GPSU</span>
                            </div>
                            <div class="hic-item">
                                <span class="hic-label">Environment</span>
                                <span class="hic-val badge-env">Sandbox GW</span>
                            </div>
                            <div class="hic-item">
                                <span class="hic-label">Tanggal</span>
                                <span class="hic-val date-today">${todayStr}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon">📦</div>
                    <div class="stat-label">Total Endpoint</div>
                    <div class="stat-value">${countTotalEndpoints()}</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon">✅</div>
                    <div class="stat-label">Status Koneksi</div>
                    <div class="stat-value" style="font-size:1.25rem">Terhubung</div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-icon">⏱️</div>
                    <div class="stat-label">Sesi Aktif</div>
                    <div class="stat-value" style="font-size:1.25rem" id="session-timer">—</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-icon">📊</div>
                    <div class="stat-label">Kategori</div>
                    <div class="stat-value">${Object.keys(ENDPOINTS).length}</div>
                </div>
            </div>
            <h3 style="margin-bottom:16px; font-size:1.125rem;">Akses Cepat</h3>
            <div class="quick-grid">
                ${quickCardsHtml}
            </div>
        `;

        startSessionTimer();
    }

    function countTotalEndpoints() {
        let count = 0;
        for (const cat of Object.values(ENDPOINTS)) {
            count += Object.keys(cat.endpoints).length;
        }
        return count;
    }

    function startSessionTimer() {
        const loginTime = parseInt(document.body.dataset.loginTime || '0', 10);
        if (!loginTime) return;

        function update() {
            const el = document.getElementById('session-timer');
            if (!el) return;
            const elapsed = Math.floor(Date.now() / 1000) - loginTime;
            const hours = Math.floor(elapsed / 3600);
            const mins = Math.floor((elapsed % 3600) / 60);
            el.textContent = `${hours}j ${mins}m`;
        }

        update();
        setInterval(update, 60000);
    }

    // ===== Render Endpoint Page =====
    function renderEndpointPage(endpoint, epDef, catDef) {
        const content = document.getElementById('main-content');

        // Build form fields
        let formFieldsHtml = '';
        if (epDef.params && epDef.params.length > 0) {
            formFieldsHtml = epDef.params.map((p) => {
                const inputType = p.type === 'date' ? 'date' : 'text';
                const required = p.required ? 'required' : '';
                const placeholder = p.placeholder || p.label;
                return `
                    <div class="form-group">
                        <label for="param-${p.name}">${p.label} ${p.required ? '<span style="color:var(--accent-red)">*</span>' : ''}</label>
                        <input type="${inputType}" 
                               id="param-${p.name}" 
                               name="${p.name}" 
                               class="form-input" 
                               placeholder="${placeholder}"
                               data-format="${p.format || ''}"
                               ${required}>
                    </div>
                `;
            }).join('');
        } else {
            formFieldsHtml = '<div class="form-no-params">Endpoint ini tidak memerlukan parameter input</div>';
        }

        content.innerHTML = `
            <div class="endpoint-page">
                <div class="endpoint-header">
                    <h2>${catDef.icon} ${epDef.label}</h2>
                    <p>${epDef.description}</p>
                </div>

                <div class="form-card">
                    <div class="form-card-header">
                        <h3>Parameter</h3>
                        <span class="endpoint-badge">GET /${endpoint}</span>
                    </div>
                    <form id="endpoint-form" onsubmit="return false;">
                        <div class="form-grid">
                            ${formFieldsHtml}
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-fetch" id="btn-fetch" onclick="window.CeisaApp.fetchData('${endpoint}')">
                                <span class="spinner" id="fetch-spinner"></span>
                                <span id="fetch-text">🔍 Tarik Data</span>
                            </button>
                            <button type="button" class="btn btn-clear" onclick="window.CeisaApp.clearForm()">🗑️ Reset</button>
                        </div>
                    </form>
                </div>

                <div id="results-container"></div>
            </div>
        `;
    }

    // ===== Fetch Data from API =====
    async function fetchData(endpoint) {
        const btn = document.getElementById('btn-fetch');
        const spinner = document.getElementById('fetch-spinner');
        const text = document.getElementById('fetch-text');

        // Collect form params
        const params = new URLSearchParams();
        params.set('endpoint', endpoint);

        const formInputs = document.querySelectorAll('#endpoint-form .form-input');
        formInputs.forEach((input) => {
            let value = input.value.trim();
            if (value) {
                // Convert date format from yyyy-mm-dd to dd-MM-yyyy
                if (input.type === 'date' && value) {
                    const parts = value.split('-');
                    if (parts.length === 3) {
                        value = `${parts[2]}-${parts[1]}-${parts[0]}`;
                    }
                }
                params.set(input.name, value);
            }
        });

        // Check required fields
        const requiredInputs = document.querySelectorAll('#endpoint-form .form-input[required]');
        for (const input of requiredInputs) {
            if (!input.value.trim()) {
                showToast(`Field "${input.previousElementSibling?.textContent?.replace(' *', '')}" wajib diisi`, 'warning');
                input.focus();
                return;
            }
        }

        // UI loading state
        btn.disabled = true;
        spinner.style.display = 'inline-block';
        text.textContent = 'Mengambil data...';

        try {
            const response = await fetch(`api/proxy.php?${params.toString()}`);
            const data = await response.json();

            state.lastResponse = data;

            if (data.token_expired) {
                showToast('Sesi Anda telah berakhir. Mengalihkan ke halaman login...', 'error');
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 2000);
                return;
            }

            if (data.success) {
                showToast(data.message || 'Data berhasil diambil!', 'success');
                processAndRenderResults(data, endpoint);
            } else {
                showToast(data.message || 'Gagal mengambil data', 'error');
                renderEmptyResults(data.message);
            }
        } catch (err) {
            showToast('Terjadi kesalahan koneksi: ' + err.message, 'error');
            renderEmptyResults('Terjadi kesalahan koneksi');
        } finally {
            btn.disabled = false;
            spinner.style.display = 'none';
            text.textContent = '🔍 Tarik Data';
        }
    }

    // ===== Process & Render Results =====
    function processAndRenderResults(response, endpoint) {
        const container = document.getElementById('results-container');
        let rawData = response.data;

        if (!rawData || (Array.isArray(rawData) && rawData.length === 0)) {
            renderEmptyResults('Tidak ada data yang ditemukan');
            return;
        }

        // Normalize data to flat array of objects
        let rows = normalizeData(rawData);

        if (rows.length === 0) {
            renderEmptyResults('Data tidak dapat diproses ke format tabel');
            return;
        }

        state.tableData = rows;
        state.currentPage = 1;
        state.searchTerm = '';
        state.sortColumn = null;

        renderResultsTable(rows, endpoint);
    }

    function normalizeData(data) {
        if (!data) return [];

        // If data is a single object (not array), wrap it
        if (!Array.isArray(data) && typeof data === 'object') {
            // Check if it has a known wrapper key
            const wrapperKeys = [
                'sppb', 'dokumenPabean', 'responPlp', 'responBatalPlp', 'responBatal',
                'spjm', 'npe', 'peb', 'pkbe', 'sp3b', 'items', 'dokumen', 'list', 'data', 'rows'
            ];
            for (const key of wrapperKeys) {
                if (data[key] && Array.isArray(data[key])) {
                    return flattenNestedObjects(data[key]);
                }
            }
            // Check for any array value at first level
            for (const [key, value] of Object.entries(data)) {
                if (Array.isArray(value) && value.length > 0) {
                    return flattenNestedObjects(value);
                }
            }
            // Single object
            return [flattenObject(data)];
        }

        if (Array.isArray(data)) {
            return flattenNestedObjects(data);
        }

        return [];
    }

    function flattenNestedObjects(arr) {
        return arr.map((item) => {
            if (typeof item === 'object' && item !== null) {
                return flattenObject(item);
            }
            return { value: item };
        });
    }

    function flattenObject(obj, prefix = '') {
        const result = {};
        for (const [key, value] of Object.entries(obj)) {
            // Jika ada key 'header' di level root, ratakan propertinya langsung agar kolom tabel bersih
            if (key === 'header' && !prefix && typeof value === 'object' && value !== null && !Array.isArray(value)) {
                Object.assign(result, flattenObject(value, ''));
                continue;
            }

            const newKey = prefix ? `${prefix}.${key}` : key;
            if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
                Object.assign(result, flattenObject(value, newKey));
            } else if (Array.isArray(value)) {
                if (value.length > 0 && typeof value[0] === 'object') {
                    result[newKey] = `[${value.length} items]`;
                } else if (value.length === 0) {
                    result[newKey] = '-';
                } else {
                    result[newKey] = value.join(', ');
                }
            } else {
                result[newKey] = value !== null && value !== undefined && value !== '' ? value : '-';
            }
        }
        return result;
    }

    // ===== Render Table =====
    function renderResultsTable(rows, endpoint) {
        const container = document.getElementById('results-container');

        // Get filtered rows
        const filtered = getFilteredRows(rows);
        const totalRows = filtered.length;
        const totalPages = Math.ceil(totalRows / state.rowsPerPage);
        const start = (state.currentPage - 1) * state.rowsPerPage;
        const pageRows = filtered.slice(start, start + state.rowsPerPage);

        // Get all column headers
        const columns = [];
        const columnSet = new Set();
        rows.forEach((row) => {
            Object.keys(row).forEach((k) => columnSet.add(k));
        });
        columnSet.forEach((c) => columns.push(c));

        // Build table headers
        const thHtml = columns.map((col) => {
            const sortIcon = state.sortColumn === col 
                ? (state.sortDirection === 'asc' ? '▲' : '▼') 
                : '⇅';
            const sortedClass = state.sortColumn === col ? 'sorted' : '';
            return `<th class="${sortedClass}" onclick="window.CeisaApp.sortTable('${col}')">${col} <span class="sort-icon">${sortIcon}</span></th>`;
        }).join('');

        // Build table body
        const tbodyHtml = pageRows.map((row) => {
            const tds = columns.map((col) => {
                let val = row[col];
                if (val === null || val === undefined) val = '';
                if (typeof val === 'boolean') val = val ? 'Ya' : 'Tidak';
                return `<td title="${String(val).replace(/"/g, '&quot;')}">${val}</td>`;
            }).join('');
            return `<tr>${tds}</tr>`;
        }).join('');

        // Pagination
        let paginationHtml = '';
        if (totalPages > 1) {
            let buttons = '';
            buttons += `<button class="pagination-btn" onclick="window.CeisaApp.goToPage(1)" ${state.currentPage === 1 ? 'disabled' : ''}>«</button>`;
            buttons += `<button class="pagination-btn" onclick="window.CeisaApp.goToPage(${state.currentPage - 1})" ${state.currentPage === 1 ? 'disabled' : ''}>‹</button>`;

            const maxButtons = 5;
            let startPage = Math.max(1, state.currentPage - Math.floor(maxButtons / 2));
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);
            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                buttons += `<button class="pagination-btn ${i === state.currentPage ? 'active' : ''}" onclick="window.CeisaApp.goToPage(${i})">${i}</button>`;
            }

            buttons += `<button class="pagination-btn" onclick="window.CeisaApp.goToPage(${state.currentPage + 1})" ${state.currentPage === totalPages ? 'disabled' : ''}>›</button>`;
            buttons += `<button class="pagination-btn" onclick="window.CeisaApp.goToPage(${totalPages})" ${state.currentPage === totalPages ? 'disabled' : ''}>»</button>`;

            paginationHtml = `
                <div class="pagination">
                    <div class="pagination-info">Menampilkan ${start + 1}–${Math.min(start + state.rowsPerPage, totalRows)} dari ${totalRows} data</div>
                    <div class="pagination-controls">${buttons}</div>
                </div>
            `;
        }

        container.innerHTML = `
            <div class="results-section">
                <div class="results-card">
                    <div class="results-header">
                        <div class="results-info">
                            <h4>Hasil Data</h4>
                            <span class="results-count">${totalRows} record</span>
                        </div>
                        <div class="results-actions">
                            <button class="btn btn-sm btn-export" onclick="window.CeisaApp.exportCSV('${endpoint}')">📥 Export CSV</button>
                            <button class="btn btn-sm btn-json" onclick="window.CeisaApp.showJSON()">{ } Raw JSON</button>
                        </div>
                    </div>
                    <div class="results-search">
                        <input type="text" placeholder="Cari di semua kolom..." id="search-input" value="${state.searchTerm}" oninput="window.CeisaApp.onSearch(this.value)">
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead><tr>${thHtml}</tr></thead>
                            <tbody>${tbodyHtml || '<tr><td colspan="' + columns.length + '"><div class="empty-state"><p>Tidak ada data cocok</p></div></td></tr>'}</tbody>
                        </table>
                    </div>
                    ${paginationHtml}
                </div>
            </div>
        `;
    }

    function getFilteredRows(rows) {
        if (!state.searchTerm) return rows;
        const term = state.searchTerm.toLowerCase();
        return rows.filter((row) => {
            return Object.values(row).some((val) => {
                return String(val).toLowerCase().includes(term);
            });
        });
    }

    function renderEmptyResults(message) {
        const container = document.getElementById('results-container');
        container.innerHTML = `
            <div class="results-card" style="padding: 0;">
                <div class="results-header" style="border-bottom: 1px solid var(--border-color); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
                    <div class="results-info">
                        <h4 style="margin: 0;">Hasil Data</h4>
                    </div>
                    <div class="results-actions">
                        <button class="btn btn-sm btn-json" style="padding: 6px 12px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 6px;" onclick="window.CeisaApp.showJSON()">
                            <span style="font-family: monospace; font-weight: bold;">{ }</span> Raw JSON
                        </button>
                    </div>
                </div>
                <div class="empty-state" style="padding: 40px 20px; text-align: center;">
                    <div class="empty-icon" style="font-size: 3rem; margin-bottom: 15px;">📭</div>
                    <h4 style="margin: 0 0 10px 0; color: var(--text-primary);">Tidak Ada Data</h4>
                    <p style="margin: 0; color: var(--text-secondary);">${message || 'Silakan ubah parameter pencarian dan coba lagi'}</p>
                </div>
            </div>
        `;
    }

    // ===== Sorting =====
    function sortTable(column) {
        if (state.sortColumn === column) {
            state.sortDirection = state.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            state.sortColumn = column;
            state.sortDirection = 'asc';
        }

        state.tableData.sort((a, b) => {
            let valA = a[column] ?? '';
            let valB = b[column] ?? '';

            // Try numeric comparison
            const numA = Number(valA);
            const numB = Number(valB);
            if (!isNaN(numA) && !isNaN(numB)) {
                return state.sortDirection === 'asc' ? numA - numB : numB - numA;
            }

            // String comparison
            valA = String(valA).toLowerCase();
            valB = String(valB).toLowerCase();
            if (valA < valB) return state.sortDirection === 'asc' ? -1 : 1;
            if (valA > valB) return state.sortDirection === 'asc' ? 1 : -1;
            return 0;
        });

        renderResultsTable(state.tableData, state.currentEndpoint);
    }

    // ===== Pagination =====
    function goToPage(page) {
        const totalPages = Math.ceil(getFilteredRows(state.tableData).length / state.rowsPerPage);
        if (page < 1 || page > totalPages) return;
        state.currentPage = page;
        renderResultsTable(state.tableData, state.currentEndpoint);
        // Scroll to results
        document.querySelector('.results-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ===== Search =====
    function onSearch(term) {
        state.searchTerm = term;
        state.currentPage = 1;
        renderResultsTable(state.tableData, state.currentEndpoint);
    }

    // ===== Export CSV =====
    async function exportCSV(endpoint) {
        const rows = getFilteredRows(state.tableData);
        if (!rows || rows.length === 0) {
            showToast('Tidak ada data untuk di-export', 'warning');
            return;
        }

        try {
            const response = await fetch('api/export.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    rows: rows,
                    filename: endpoint || 'export_ceisa',
                }),
            });

            if (!response.ok) throw new Error('Export gagal');

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${endpoint}_${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();

            showToast(`Berhasil export ${rows.length} baris data`, 'success');
        } catch (err) {
            showToast('Gagal export: ' + err.message, 'error');
        }
    }

    // ===== Show JSON Modal =====
    function showJSON() {
        if (!state.lastResponse) {
            showToast('Belum ada data response', 'warning');
            return;
        }

        const overlay = document.getElementById('json-modal');
        const body = document.getElementById('json-content');
        body.textContent = JSON.stringify(state.lastResponse, null, 2);
        overlay.classList.add('visible');
    }

    function closeJSON() {
        const overlay = document.getElementById('json-modal');
        overlay.classList.remove('visible');
    }

    // ===== Clear Form =====
    function clearForm() {
        document.querySelectorAll('#endpoint-form .form-input').forEach((input) => {
            input.value = '';
        });
        document.getElementById('results-container').innerHTML = '';
        state.tableData = [];
        state.lastResponse = null;
    }

    // ===== Toast Notifications =====
    function showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️',
        };

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;

        container.appendChild(toast);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100px)';
                toast.style.transition = '0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    }

    // ===== Public API =====
    window.CeisaApp = {
        init,
        fetchData,
        sortTable,
        goToPage,
        onSearch,
        exportCSV,
        showJSON,
        closeJSON,
        clearForm,
        showToast,
        toggleTheme,
        setTheme,
        getPreferredTheme,
        openSidebarMenu,
        openSidebarCategory,
    };
    window.openSidebarMenu = openSidebarMenu;
    window.openSidebarCategory = openSidebarCategory;

    // Auto-init when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
