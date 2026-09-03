<?php
/**
 * TPS Tracking (Pergerakan Kontainer) CEISA 4.0 — TPS Online Dashboard
 * Halaman perekaman data tracking pergerakan kontainer di TPS (Gate In, Gate Out, Stacking, Truck In, Pickup, dll.)
 * Target Endpoint: POST /kirim-tps-tracking
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

requireAuth();

$config = require __DIR__ . '/config.php';
$username = $_SESSION['name'] ?? $_SESSION['username'] ?? $config['username'] ?? 'User';
$loginTime = $_SESSION['login_time'] ?? time();
$userInitial = strtoupper(substr($username, 0, 2));

$defaultEndpoint = 'kirim-tps-tracking';
$nowDmyHis = date('d-m-Y H:i:s');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TPS Tracking Kontainer — <?= e($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('ceisa_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.35; transform: scale(0.75); }
        }
        .pulse-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            animation: pulseDot 1.4s infinite ease-in-out;
        }
        .content-area {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            height: calc(100vh - var(--header-height));
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        .content-area::-webkit-scrollbar {
            width: 8px;
        }
        .content-area::-webkit-scrollbar-track {
            background: var(--bg-base);
        }
        .content-area::-webkit-scrollbar-thumb {
            background: var(--border-medium);
            border-radius: 4px;
        }
        .content-area::-webkit-scrollbar-thumb:hover {
            background: var(--accent-blue);
        }
        .coco-container {
            padding: 24px 24px 80px 24px;
            max-width: 1440px;
            margin: 0 auto;
        }
        .coco-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid var(--border-subtle);
            margin-bottom: 24px;
        }
        .section-header-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            padding-bottom: 8px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border-subtle);
        }
        .field-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .field-group .label-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2px;
        }
        .field-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin: 0;
        }
        .field-group label .req-star {
            color: var(--accent-red);
            font-weight: 700;
            margin-left: 2px;
        }
        .field-action-btn {
            font-size: 0.78rem;
            color: var(--accent-blue);
            background: transparent;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
            padding: 0;
            text-transform: none;
            transition: color 0.15s;
        }
        .field-action-btn:hover {
            color: #60a5fa;
            text-decoration: underline;
        }
        .input-control {
            width: 100%;
            padding: 10px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border-medium);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.92rem;
            transition: all 0.2s ease;
        }
        .input-control:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .type-toggle-group {
            display: flex;
            background: var(--bg-input);
            padding: 4px;
            border-radius: 8px;
            border: 1px solid var(--border-medium);
            gap: 4px;
            height: 44px;
            align-items: center;
        }
        .type-btn {
            flex: 1;
            height: 36px;
            padding: 0 12px;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .type-btn.active {
            background: var(--accent-blue);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4);
        }
        .dept-toggle-card {
            background: var(--bg-card);
            border: 1px solid var(--border-medium);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 16px;
        }
        .dept-toggle-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .dept-toggle-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--bg-input);
            padding: 4px;
            border-radius: 8px;
            border: 1px solid var(--border-medium);
            gap: 6px;
        }
        .dept-btn {
            height: 40px;
            border-radius: 6px;
            border: 1px solid transparent;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .dept-btn:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.05);
        }
        .dept-btn.active.dept-tpp {
            background: var(--accent-blue);
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 2px 10px rgba(59, 130, 246, 0.35);
        }
        .dept-btn.active.dept-gudang {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 2px 10px rgba(16, 185, 129, 0.35);
        }
        .tabs-nav {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border-subtle);
            margin-bottom: 20px;
            padding-bottom: 8px;
        }
        .tab-btn {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 0.92rem;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
        }
        .tab-btn:hover {
            color: var(--text-primary);
            background: var(--bg-card-hover);
        }
        .tab-btn.active {
            background: rgba(59, 130, 246, 0.12);
            color: var(--accent-blue);
            border-bottom: 2px solid var(--accent-blue);
        }
        .json-box {
            width: 100%;
            height: 520px;
            background: #0d131f;
            color: #7dd3fc;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            padding: 18px;
            border-radius: 8px;
            border: 1px solid var(--border-medium);
            resize: vertical;
            line-height: 1.5;
        }
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border-subtle);
        }
        .badge-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .badge-in { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-out { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-ceisa { background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .action-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-subtle);
        }
        .btn-action-sm {
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid var(--border-medium);
            background: var(--bg-surface);
            color: var(--text-primary);
            font-size: 0.88rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-action-sm:hover {
            background: var(--border-medium);
        }
        .btn-send-prod {
            background: var(--accent-green-gradient);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transition: transform 0.15s, opacity 0.15s;
            width: 100%;
            justify-content: center;
        }
        .btn-send-prod:hover {
            transform: translateY(-1px);
        }
        .btn-send-prod:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        /* Select2 Theme Customization */
        .select2-container--default .select2-selection--single {
            background-color: var(--bg-input) !important;
            border: 1px solid var(--border-medium) !important;
            border-radius: 8px !important;
            height: 42px !important;
            display: flex !important;
            align-items: center !important;
            transition: all 0.2s ease !important;
        }
        .select2-container--default .select2-selection--single:focus,
        .select2-container--default.select2-container--open .select2-selection--single {
            border-color: var(--accent-blue) !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
            outline: none !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--text-primary) !important;
            line-height: 40px !important;
            padding-left: 14px !important;
            padding-right: 36px !important;
            font-size: 0.92rem !important;
            font-family: 'JetBrains Mono', monospace !important;
            font-weight: 600 !important;
            letter-spacing: 0.5px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: var(--text-secondary) !important;
            font-weight: 400 !important;
            font-family: inherit !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
            right: 10px !important;
            display: flex !important;
            align-items: center !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: var(--text-secondary) transparent transparent transparent !important;
        }
        .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
            border-color: transparent transparent var(--text-secondary) transparent !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__clear {
            color: var(--text-secondary) !important;
            font-size: 1.2rem !important;
            line-height: 40px !important;
            margin-right: 12px !important;
        }
        .select2-dropdown {
            background-color: var(--bg-surface) !important;
            border: 1px solid var(--border-medium) !important;
            border-radius: 8px !important;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45) !important;
            z-index: 9999 !important;
        }
        .select2-search--dropdown {
            padding: 8px !important;
        }
        .select2-search--dropdown .select2-search__field {
            background-color: var(--bg-input) !important;
            border: 1px solid var(--border-medium) !important;
            border-radius: 6px !important;
            color: var(--text-primary) !important;
            padding: 8px 12px !important;
            font-family: 'JetBrains Mono', monospace !important;
            font-size: 0.9rem !important;
            outline: none !important;
        }
        .select2-search--dropdown .select2-search__field:focus {
            border-color: var(--accent-blue) !important;
        }
        .select2-container--default .select2-results__option {
            padding: 10px 14px !important;
            font-size: 0.88rem !important;
            color: var(--text-primary) !important;
            border-bottom: 1px solid var(--border-subtle) !important;
            transition: background 0.15s !important;
        }
        .select2-container--default .select2-results__option:last-child {
            border-bottom: none !important;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: rgba(59, 130, 246, 0.18) !important;
            color: var(--text-primary) !important;
        }
        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: rgba(59, 130, 246, 0.25) !important;
            color: var(--accent-blue) !important;
            font-weight: 600 !important;
        }
        .select2-container--default .select2-results__message {
            color: var(--text-secondary) !important;
            font-size: 0.85rem !important;
            padding: 12px !important;
            text-align: center !important;
        }
    </style>
</head>
<body data-login-time="<?= $loginTime ?>">
    <div class="dashboard">
        <!-- Sidebar Overlay (Mobile) -->
        <div class="sidebar-overlay"></div>

        <!-- ===== SIDEBAR ===== -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- ===== MAIN CONTENT ===== -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="menu-toggle" id="menu-toggle">☰</button>
                    <div class="header-breadcrumb">
                        <span>CEISA 4.0</span>
                        <span class="separator">/</span>
                        <span>Kirim Dokumen</span>
                        <span class="separator">/</span>
                        <span class="current">TPS Tracking Kontainer</span>
                    </div>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="theme-toggle" title="Ubah Mode (Gelap / Terang)">
                        <span class="theme-toggle-icon">🌙</span>
                        <span class="theme-toggle-text">Dark</span>
                    </button>
                    <button class="btn-refresh-token" onclick="refreshAccessToken()" title="Perbarui token JWT dari server CEISA">
                        <span>🔄</span>
                        <span>Refresh Token</span>
                    </button>
                    <div class="header-user">
                        <div class="user-avatar"><?= e($userInitial) ?></div>
                        <span><?= e($username) ?></span>
                        <a href="logout.php" title="Keluar" style="color:var(--accent-red); margin-left:6px; text-decoration:none;">🚪</a>
                    </div>
                </div>
            </header>

            <!-- Main Body -->
            <main class="content-area">
                <div class="coco-container">

                    <!-- Header & Action Card -->
                    <div class="coco-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                            <div>
                                <h2 style="margin: 0; font-size: 1.3rem; color: var(--text-primary); font-weight: 700; display: flex; align-items: center; gap: 10px;">
                                    <span>📍</span> TPS Tracking Pergerakan Kontainer
                                </h2>
                                <p style="margin: 6px 0 0; color: var(--text-secondary); font-size: 0.88rem;">
                                    Rekam data tracking pergerakan fisik kontainer di TPS secara real-time ke sistem Bea Cukai via REST API CEISA 4.0.
                                </p>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <a href="report_tracking.php" class="btn-action-sm" style="text-decoration:none; padding:8px 16px; border-radius:8px; font-weight:600; display:inline-flex; align-items:center; gap:6px; background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.35);">
                                    <span>📊</span> Laporan Tracking Terkirim
                                </a>
                                <span class="badge-pill badge-ceisa">POST /kirim-tps-tracking</span>
                                <span class="badge-pill" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                                    <span class="pulse-dot"></span> CEISA 4.0 OpenAPI
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- FORMULIR PENGIRIMAN TRACKING -->
                    <div id="tab-form-content">
                        <div style="display: grid; grid-template-columns: 1.25fr 1fr; gap: 24px; align-items: start;">

                            <!-- KOLOM KIRI: FORMULIR INPUT -->
                            <div class="coco-card" style="margin-bottom: 0;">
                                <form id="tracking-form" onsubmit="event.preventDefault(); sendTracking();">
                                    
                                    <!-- 1. Identitas Kontainer & TPS -->
                                    <div style="margin-bottom: 24px;">
                                        <div class="section-header-row">
                                            <span>📦</span> 1. Identitas Kontainer & Lokasi TPS
                                        </div>

                                        <!-- Pemilihan Departemen Operasional -->
                                        <div class="dept-toggle-card">
                                            <div class="dept-toggle-label">
                                                <span>🏢 Departemen Operasional Lini 2</span>
                                                <span id="dept-database-badge" style="font-size:0.72rem; text-transform:none; padding:2px 8px; border-radius:6px; background:rgba(59,130,246,0.15); color:var(--accent-blue); border:1px solid rgba(59,130,246,0.3);">
                                                    DB: tpp_primamas (PLP FCL)
                                                </span>
                                            </div>
                                            <div class="dept-toggle-group">
                                                <button type="button" class="dept-btn active dept-tpp" id="btn-dept-tpp" onclick="setDepartment('tpp')">
                                                    <span>🏢</span> TPP (PLP / Lapangan)
                                                </button>
                                                <button type="button" class="dept-btn" id="btn-dept-gudang" onclick="setDepartment('gudang')">
                                                    <span>🏬</span> Gudang (LCL / Stripping)
                                                </button>
                                            </div>
                                            <input type="hidden" id="departemen-val" value="tpp">
                                        </div>

                                        <div style="display: grid; grid-template-columns: 1.35fr 1fr; gap: 16px; margin-bottom: 14px;">
                                            <div class="field-group">
                                                <div class="label-header">
                                                    <label for="no-cont">Nomor Kontainer <span class="req-star">*</span></label>
                                                    <span id="dept-hint-badge" style="font-size:0.75rem; color:var(--accent-blue); font-weight:600;">⚡ Auto-Fill tpp_primamas</span>
                                                </div>
                                                <select id="no-cont" style="width: 100%;" required>
                                                    <option value=""></option>
                                                </select>
                                            </div>

                                            <div class="field-group">
                                                <label for="ukuran-cont-display">Ukuran Kontainer <span class="req-star">*</span></label>
                                                <div style="position: relative;">
                                                    <input type="text" id="ukuran-cont-display" class="input-control" value="40 ft" readonly style="background:var(--bg-card); cursor:not-allowed; opacity:0.9; font-weight:600; font-family:'JetBrains Mono',monospace;" title="Ukuran kontainer otomatis terisi dari kontainer terpilih (Readonly)">
                                                    <input type="hidden" id="ukuran-cont" value="40">
                                                </div>
                                            </div>
                                        </div>

                                        <div style="display: grid; grid-template-columns: 1.3fr 1fr 1fr; gap: 14px;">
                                            <div class="field-group">
                                                <label for="jenis-cont">Jenis Kontainer <span class="req-star">*</span></label>
                                                <select id="jenis-cont" class="input-control" onchange="updateJsonPreview()" required>
                                                    <option value="8" selected>8 — FCL (Full Container)</option>
                                                    <option value="7">7 — LCL (Less Container)</option>
                                                    <option value="4">4 — EMPTY (Kontainer Kosong)</option>
                                                </select>
                                            </div>

                                            <div class="field-group">
                                                <label for="kode-tps">Kode TPS <span class="req-star">*</span></label>
                                                <input type="text" id="kode-tps" class="input-control" value="PSU0" readonly style="background:var(--bg-card); cursor:not-allowed; opacity:0.9; font-family:'JetBrains Mono',monospace; text-transform:uppercase; font-weight:600;" title="Kode TPS Baku (PSU0) - Readonly">
                                            </div>

                                            <div class="field-group">
                                                <label for="kode-gudang">Kode Gudang <span class="req-star">*</span></label>
                                                <input type="text" id="kode-gudang" class="input-control" value="CPSU" readonly style="background:var(--bg-card); cursor:not-allowed; opacity:0.9; font-family:'JetBrains Mono',monospace; text-transform:uppercase; font-weight:600;" title="Kode Gudang Baku Kontainer (CPSU) - Readonly">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 2. Kegiatan & Waktu Pergerakan -->
                                    <div style="margin-bottom: 24px;">
                                        <div class="section-header-row">
                                            <span>⏱️</span> 2. Kegiatan & Waktu Pergerakan Kontainer
                                        </div>

                                        <div class="field-group" style="margin-bottom: 14px;">
                                            <label for="kode-kegiatan">Kode Kegiatan CEISA 4.0 <span class="req-star">*</span></label>
                                            <select id="kode-kegiatan" class="input-control" style="font-weight: 500;" onchange="onKegiatanChange(this.value); updateJsonPreview();" required>
                                                 <optgroup label="🏢 Kegiatan Utama TPP (PLP / Lapangan Penumpukan FCL)">
                                                     <option value="5" selected>5 — GATE IN PLP (Pemasukan Kontainer FCL)</option>
                                                     <option value="17">17 — STACKING DISCHARGE LINI 2 (Posisi Yard Wajib)</option>
                                                     <option value="21">21 — BEHANDLE LINI 2 (Pemeriksaan Fisik Behandle Yard)</option>
                                                     <option value="22">22 — SHIFTING LINI 2 (Pergeseran Posisi Yard)</option>
                                                     <option value="20">20 — PICKUP LINI 2 (Pengangkatan Kontainer ke Truk)</option>
                                                     <option value="6">6 — GATE OUT LINI 2 (Pengeluaran Kontainer FCL Lini 2)</option>
                                                 </optgroup>
                                                 <optgroup label="🚢 Alur Ekspor Lini 2">
                                                     <option value="7">7 — GATE IN EKSPOR LINI 2</option>
                                                     <option value="18">18 — STACKING EKSPOR LINI 2 (Posisi Yard Wajib)</option>
                                                     <option value="8">8 — GATE OUT EKSPOR LINI 2</option>
                                                 </optgroup>
                                                 <optgroup label="⚓ Kegiatan Lainnya (Gudang & Dermaga Lini 1)">
                                                     <option value="23">23 — STRIPPING STUFFING LINI 2</option>
                                                     <option value="24">24 — STUFFING KE GUDANG LINI 2 (Posisi Yard Wajib)</option>
                                                     <option value="19">19 — TRUCK IN LINI 2</option>
                                                     <option value="1">1 — DISCHARGE</option>
                                                     <option value="2">2 — LOADING</option>
                                                     <option value="3">3 — GATE OUT (Codeco Impor Lini 1)</option>
                                                     <option value="4">4 — GATE IN RECEIVING (Codeco Ekspor)</option>
                                                     <option value="9">9 — GATE OUT BATAL EKSPOR</option>
                                                     <option value="10">10 — STACKING DISCHARGE Lini 1</option>
                                                     <option value="11">11 — STACKING EKSPOR Lini 1</option>
                                                     <option value="12">12 — TRUCK IN</option>
                                                     <option value="13">13 — PICKUP</option>
                                                     <option value="14">14 — BEHANDLE Lini 1</option>
                                                     <option value="15">15 — SHIFTING Lini 1</option>
                                                     <option value="16">16 — STRIPPING STUFFING Lini 1</option>
                                                 </optgroup>
                                             </select>
                                        </div>

                                        <div class="field-group">
                                            <div class="label-header">
                                                <label for="waktu-kegiatan">Waktu Kegiatan (dd-MM-yyyy HH:mm:ss) <span class="req-star">*</span></label>
                                                <button type="button" class="field-action-btn" onclick="setNowTime();">⏱️ Gunakan Waktu Sekarang</button>
                                            </div>
                                            <input type="text" id="waktu-kegiatan" class="input-control" value="<?= e($nowDmyHis) ?>" style="font-family:'JetBrains Mono',monospace;" required oninput="updateJsonPreview()">
                                        </div>
                                    </div>

                                    <!-- 3. Posisi Yard & Armada Truk (Opsional) -->
                                    <div style="margin-bottom: 24px;">
                                        <div class="section-header-row">
                                            <span>🚜</span> 3. Posisi Yard & Identitas Armada Truk (Opsional)
                                        </div>

                                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                                            <div class="field-group">
                                                <label for="block-loc">Block</label>
                                                <input type="text" id="block-loc" class="input-control" placeholder="Contoh: A1" maxlength="10" oninput="updateJsonPreview()">
                                            </div>
                                            <div class="field-group">
                                                <label for="slot-loc">Slot</label>
                                                <input type="text" id="slot-loc" class="input-control" placeholder="Contoh: 01" maxlength="10" oninput="updateJsonPreview()">
                                            </div>
                                            <div class="field-group">
                                                <label for="tier-loc">Tier</label>
                                                <input type="text" id="tier-loc" class="input-control" placeholder="Contoh: 01" maxlength="10" oninput="updateJsonPreview()">
                                            </div>
                                        </div>

                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                            <div class="field-group">
                                                <label for="nopol">Nomor Polisi Truk</label>
                                                <input type="text" id="nopol" class="input-control" placeholder="Contoh: L 8415 UAD" maxlength="15" style="text-transform:uppercase;" oninput="updateJsonPreview()">
                                            </div>
                                            <div class="field-group">
                                                <label for="stid">STID (Single Truck ID)</label>
                                                <input type="text" id="stid" class="input-control" placeholder="Contoh: STID-0012345" maxlength="50" oninput="updateJsonPreview()">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 4. Dokumen Pabean & Pengangkutan (Opsional) -->
                                    <div>
                                        <div class="section-header-row">
                                            <span>📑</span> 4. Dokumen Pabean & Pengangkutan (Opsional)
                                        </div>

                                        <div style="display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                                            <div class="field-group">
                                                <label for="kode-dok">Kode Dokumen</label>
                                                <select id="kode-dok" class="input-control" onchange="updateJsonPreview()">
                                                    <option value="">-- Tanpa Dokumen / Sesuai Fisik --</option>
                                                    
                                                    <optgroup label="🏢 Khusus TPP (PLP / Lapangan FCL)">
                                                        <option value="3" selected>3 — Persetujuan PLP (Surat Pindah Lokasi Penimbunan)</option>
                                                    </optgroup>

                                                    <optgroup label="🏬 Khusus Gudang (LCL / CFS Warehouse)">
                                                        <option value="704">704 — MASTER B/L (Master Bill of Lading)</option>
                                                        <option value="705">705 — B/L (House Bill of Lading)</option>
                                                        <option value="640">640 — Delivery Order (D/O)</option>
                                                        <option value="65">65 — BC 1.1 Konsolidasi PJT</option>
                                                    </optgroup>

                                                    <optgroup label="📋 Dokumen Manifes & Kedatangan (Bersama)">
                                                        <option value="11">11 — MANIFES (BC 1.1 Inward Manifest)</option>
                                                        <option value="10">10 — RKSP (Rencana Kedatangan Sarana Pengangkut)</option>
                                                    </optgroup>

                                                    <optgroup label="📥 Dokumen Pabean Impor & Pengeluaran SPPB (Bersama)">
                                                        <option value="20">20 — BC 2.0 (PIB - Pemberitahuan Impor Barang)</option>
                                                        <option value="23">23 — BC 2.3 (Impor Tempat Penimbunan Berikat / TPB)</option>
                                                        <option value="21">21 — PIBK (Pemberitahuan Impor Barang Khusus)</option>
                                                        <option value="16">16 — BC 1.6 (Pengeluaran Kawasan Pabean ke PLB)</option>
                                                        <option value="28">28 — BC 2.8 (Impor dari Pusat Logistik Berikat)</option>
                                                        <option value="25">25 — BC 2.5 (Pengeluaran dari TPB ke TLDDP)</option>
                                                        <option value="27">27 — BC 2.7 (Pengeluaran Antar TPB)</option>
                                                        <option value="40">40 — BC 4.0 (Pemasukan TLDDP ke TPB)</option>
                                                        <option value="41">41 — BC 4.1 (Pengeluaran Kembali ke TLDDP)</option>
                                                    </optgroup>

                                                    <optgroup label="🚢 Dokumen Ekspor">
                                                        <option value="30">30 — BC 3.0 (PEB - Pemberitahuan Ekspor Barang)</option>
                                                        <option value="33">33 — BC 3.3 (Ekspor melalui PLB)</option>
                                                    </optgroup>
                                                </select>
                                            </div>
                                            <div class="field-group">
                                                <label for="no-dok">Nomor Dokumen</label>
                                                <input type="text" id="no-dok" class="input-control" placeholder="Contoh: 016547" maxlength="50" oninput="updateJsonPreview()">
                                            </div>
                                            <div class="field-group">
                                                <label for="tgl-dok">Tanggal Dokumen</label>
                                                <input type="text" id="tgl-dok" class="input-control" placeholder="dd-MM-yyyy" maxlength="10" oninput="updateJsonPreview()">
                                            </div>
                                        </div>

                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                            <div class="field-group">
                                                <label for="no-bl">Nomor B/L atau AWB</label>
                                                <input type="text" id="no-bl" class="input-control" placeholder="Contoh: SSLSGSUBCAE6364" maxlength="50" oninput="updateJsonPreview()">
                                            </div>
                                            <div class="field-group">
                                                <label for="tgl-bl">Tanggal B/L</label>
                                                <input type="text" id="tgl-bl" class="input-control" placeholder="dd-MM-yyyy" maxlength="10" oninput="updateJsonPreview()">
                                            </div>
                                        </div>
                                    </div>

                                </form>
                            </div>

                            <!-- KOLOM KANAN: LIVE JSON PREVIEW & AKSI KIRIM -->
                            <div style="position: sticky; top: 80px;">
                                <div class="coco-card" style="margin-bottom: 0;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="font-size: 1.15rem;">⚡</span>
                                            <strong style="color: var(--text-primary); font-size: 1rem;">Live JSON Payload</strong>
                                        </div>
                                        <button type="button" class="btn-action-sm" onclick="copyJsonPayload()">
                                            <span>📋</span> Salin JSON
                                        </button>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.8rem;">
                                        <span style="color: var(--text-secondary);">Target: <code>/kirim-tps-tracking</code></span>
                                        <span id="schema-status" style="color: #10b981; font-weight: 600;">✓ Parameter Wajib Terisi</span>
                                    </div>

                                    <textarea id="json-tracking-preview" class="json-box" readonly></textarea>

                                    <!-- Action Send Row -->
                                    <div style="margin-top: 20px;">
                                        <button type="button" id="btn-submit-tracking" class="btn-send-prod" onclick="sendTracking()">
                                            <span id="send-spinner" style="display: none;">⏳</span>
                                            <span id="send-icon">🚀</span>
                                            <span id="send-text">Kirim Tracking ke CEISA 4.0</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Card Respon Pengiriman -->
                                <div id="send-result-card" class="coco-card" style="display: none; margin-top: 20px; padding: 20px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span id="send-status-badge" class="badge-pill"></span>
                                            <span id="send-timestamp" style="font-size: 0.85rem; color: var(--text-secondary);"></span>
                                        </div>
                                        <button type="button" class="btn-action-sm" onclick="$('#send-raw-response').slideToggle(200)">
                                            <span>📋</span> Toggle Raw Response
                                        </button>
                                    </div>
                                    <div id="send-result-msg" style="font-size: 0.92rem; color: var(--text-primary); margin-bottom: 12px;"></div>
                                    <pre id="send-raw-response" style="display: none; background: #0d131f; color: #a5f3fc; padding: 16px; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 13px; max-height: 260px; overflow: auto; margin: 0;"></pre>
                                </div>
                            </div>

                </div>
            </main>
        </div>
    </div>

    <div id="toast-container" style="position:fixed; bottom:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:10px;"></div>

    <script>
        let currentPayload = {};
        let searchTimeout = null;
        let historyTableInstance = null;
        let currentDept = 'tpp';
        window.selectedContainerData = null;

        function renderKegiatanOptions(dept) {
            const selectEl = document.getElementById('kode-kegiatan');
            if (!selectEl) return;
            const currentVal = selectEl.value;

            if (dept === 'gudang') {
                selectEl.innerHTML = `
                    <optgroup label="🏬 Kegiatan Utama Gudang (LCL / CFS Warehouse)">
                        <option value="5" ${currentVal==='5'?'selected':''}>5 — GATE IN PLP (Pemasukan Kontainer LCL)</option>
                        <option value="23" ${currentVal==='23' || !currentVal || currentVal==='17'?'selected':''}>23 — STRIPPING STUFFING LINI 2 (Pembongkaran Kargo LCL)</option>
                        <option value="21" ${currentVal==='21'?'selected':''}>21 — BEHANDLE LINI 2 (Pemeriksaan Fisik Kargo / Gudang)</option>
                        <option value="6" ${currentVal==='6'?'selected':''}>6 — GATE OUT LINI 2 (Kontainer Kosong / Empty Return)</option>
                        <option value="24" ${currentVal==='24'?'selected':''}>24 — STUFFING KE GUDANG LINI 2 (Pemuatan Kargo Ekspor)</option>
                    </optgroup>
                    <optgroup label="🚢 Alur Ekspor Lini 2">
                        <option value="7" ${currentVal==='7'?'selected':''}>7 — GATE IN EKSPOR LINI 2</option>
                        <option value="18" ${currentVal==='18'?'selected':''}>18 — STACKING EKSPOR LINI 2 (Posisi Yard Wajib)</option>
                        <option value="8" ${currentVal==='8'?'selected':''}>8 — GATE OUT EKSPOR LINI 2</option>
                    </optgroup>
                    <optgroup label="⚓ Kegiatan Lainnya (Lini 1 / Lapangan TPP)">
                        <option value="17">17 — STACKING DISCHARGE LINI 2 (Posisi Yard Wajib)</option>
                        <option value="19">19 — TRUCK IN LINI 2</option>
                        <option value="20">20 — PICKUP LINI 2</option>
                        <option value="22">22 — SHIFTING LINI 2 (Posisi Yard Wajib)</option>
                        <option value="1">1 — DISCHARGE</option>
                        <option value="2">2 — LOADING</option>
                        <option value="3">3 — GATE OUT (Codeco Impor Lini 1)</option>
                        <option value="4">4 — GATE IN RECEIVING (Codeco Ekspor)</option>
                        <option value="9">9 — GATE OUT BATAL EKSPOR</option>
                        <option value="10">10 — STACKING DISCHARGE Lini 1</option>
                        <option value="11">11 — STACKING EKSPOR Lini 1</option>
                        <option value="12">12 — TRUCK IN</option>
                        <option value="13">13 — PICKUP</option>
                        <option value="14">14 — BEHANDLE Lini 1</option>
                        <option value="15">15 — SHIFTING Lini 1</option>
                        <option value="16">16 — STRIPPING STUFFING Lini 1</option>
                    </optgroup>
                `;
            } else {
                selectEl.innerHTML = `
                    <optgroup label="🏢 Kegiatan Utama TPP (PLP / Lapangan Penumpukan FCL)">
                        <option value="5" ${currentVal==='5' || !currentVal || currentVal==='23'?'selected':''}>5 — GATE IN PLP (Pemasukan Kontainer FCL)</option>
                        <option value="17" ${currentVal==='17'?'selected':''}>17 — STACKING DISCHARGE LINI 2 (Posisi Yard Wajib)</option>
                        <option value="21" ${currentVal==='21'?'selected':''}>21 — BEHANDLE LINI 2 (Pemeriksaan Fisik Behandle Yard)</option>
                        <option value="22" ${currentVal==='22'?'selected':''}>22 — SHIFTING LINI 2 (Pergeseran Posisi Yard)</option>
                        <option value="20" ${currentVal==='20'?'selected':''}>20 — PICKUP LINI 2 (Pengangkatan Kontainer ke Truk)</option>
                        <option value="6" ${currentVal==='6'?'selected':''}>6 — GATE OUT LINI 2 (Pengeluaran Kontainer FCL Lini 2)</option>
                    </optgroup>
                    <optgroup label="🚢 Alur Ekspor Lini 2">
                        <option value="7" ${currentVal==='7'?'selected':''}>7 — GATE IN EKSPOR LINI 2</option>
                        <option value="18" ${currentVal==='18'?'selected':''}>18 — STACKING EKSPOR LINI 2 (Posisi Yard Wajib)</option>
                        <option value="8" ${currentVal==='8'?'selected':''}>8 — GATE OUT EKSPOR LINI 2</option>
                    </optgroup>
                    <optgroup label="⚓ Kegiatan Lainnya (Gudang & Dermaga Lini 1)">
                        <option value="23">23 — STRIPPING STUFFING LINI 2</option>
                        <option value="24">24 — STUFFING KE GUDANG LINI 2 (Posisi Yard Wajib)</option>
                        <option value="19">19 — TRUCK IN LINI 2</option>
                        <option value="1">1 — DISCHARGE</option>
                        <option value="2">2 — LOADING</option>
                        <option value="3">3 — GATE OUT (Codeco Impor Lini 1)</option>
                        <option value="4">4 — GATE IN RECEIVING (Codeco Ekspor)</option>
                        <option value="9">9 — GATE OUT BATAL EKSPOR</option>
                        <option value="10">10 — STACKING DISCHARGE Lini 1</option>
                        <option value="11">11 — STACKING EKSPOR Lini 1</option>
                        <option value="12">12 — TRUCK IN</option>
                        <option value="13">13 — PICKUP</option>
                        <option value="14">14 — BEHANDLE Lini 1</option>
                        <option value="15">15 — SHIFTING Lini 1</option>
                        <option value="16">16 — STRIPPING STUFFING Lini 1</option>
                    </optgroup>
                `;
            }
        }

        function setDepartment(dept) {
            currentDept = dept;
            $('#departemen-val').val(dept);

            renderKegiatanOptions(dept);

            if (dept === 'gudang') {
                $('#btn-dept-gudang').addClass('active dept-gudang');
                $('#btn-dept-tpp').removeClass('active dept-tpp');
                $('#dept-hint-badge').html('⚡ Auto-Fill primamas (Gudang LCL)').css('color', '#10b981');
                $('#dept-database-badge').html('DB: primamas (Gudang LCL)').css({
                    'background': 'rgba(16, 185, 129, 0.15)',
                    'color': '#10b981',
                    'border-color': 'rgba(16, 185, 129, 0.3)'
                });
                $('#kode-gudang').val('GPSU');
                $('#jenis-cont').val('7'); // 7 = LCL
                $('#kode-dok').val('704'); // 704 = MASTER B/L (Standar LCL)
                
                showToast('Departemen Gudang (LCL / database primamas) aktif — Dokumen default: 704 (MASTER B/L)', 'info');
            } else {
                $('#btn-dept-tpp').addClass('active dept-tpp');
                $('#btn-dept-gudang').removeClass('active dept-gudang');
                $('#dept-hint-badge').html('⚡ Auto-Fill tpp_primamas (TPP PLP)').css('color', 'var(--accent-blue)');
                $('#dept-database-badge').html('DB: tpp_primamas (PLP FCL)').css({
                    'background': 'rgba(59, 130, 246, 0.15)',
                    'color': 'var(--accent-blue)',
                    'border-color': 'rgba(59, 130, 246, 0.3)'
                });
                $('#kode-gudang').val('CPSU');
                $('#jenis-cont').val('8'); // 8 = FCL
                $('#kode-dok').val('3'); // 3 = Persetujuan PLP (Standar PLP FCL)
                showToast('Departemen TPP (PLP / database tpp_primamas) aktif — Dokumen default: 3 (Persetujuan PLP)', 'info');
            }

            // Reset kontainer yang terpilih agar operator mencari di database yang aktif
            window.selectedContainerData = null;
            $('#no-cont').val(null).trigger('change');
            updateJsonPreview();
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.style.padding = '12px 20px';
            toast.style.borderRadius = '8px';
            toast.style.fontSize = '0.9rem';
            toast.style.fontWeight = '500';
            toast.style.boxShadow = '0 4px 14px rgba(0,0,0,0.3)';
            toast.style.transition = 'all 0.3s ease';
            toast.style.display = 'flex';
            toast.style.alignItems = 'center';
            toast.style.gap = '10px';

            if (type === 'success') {
                toast.style.background = '#065f46';
                toast.style.color = '#a7f3d0';
                toast.style.border = '1px solid #10b981';
                toast.innerHTML = '<span>✅</span> ' + message;
            } else if (type === 'error') {
                toast.style.background = '#7f1d1d';
                toast.style.color = '#fecaca';
                toast.style.border = '1px solid #ef4444';
                toast.innerHTML = '<span>❌</span> ' + message;
            } else {
                toast.style.background = '#1e293b';
                toast.style.color = '#e2e8f0';
                toast.style.border = '1px solid #475569';
                toast.innerHTML = '<span>ℹ️</span> ' + message;
            }

            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        async function refreshAccessToken() {
            try {
                showToast('Memperbarui token dari SSO Bea Cukai...', 'info');
                const res = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ refresh: true })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Token berhasil diperbarui!', 'success');
                } else {
                    showToast('Gagal refresh token: ' + (data.message || 'Error'), 'error');
                }
            } catch (e) {
                showToast('Koneksi auth error: ' + e.message, 'error');
            }
        }

        function setSize(size, btn) {
            $('.type-btn').removeClass('active');
            $(btn).addClass('active');
            $('#ukuran-cont').val(size);
            updateJsonPreview();
        }

        function setNowTime() {
            const now = new Date();
            const d = String(now.getDate()).padStart(2, '0');
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const y = now.getFullYear();
            const H = String(now.getHours()).padStart(2, '0');
            const i = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            $('#waktu-kegiatan').val(`${d}-${m}-${y} ${H}:${i}:${s}`);
            updateJsonPreview();
            showToast('Waktu kegiatan diset ke waktu sekarang', 'info');
        }

        function applySmartTimestamp(item) {
            item = item || window.selectedContainerData;
            if (!item) return;

            const kdKeg = $('#kode-kegiatan').val();
            // 5 = Gate In PLP
            if (kdKeg === '5' && item.waktu_masuk) {
                $('#waktu-kegiatan').val(item.waktu_masuk);
            } 
            // 16 / 23 = Stripping Stuffing
            else if ((kdKeg === '16' || kdKeg === '23') && item.waktu_stripping) {
                $('#waktu-kegiatan').val(item.waktu_stripping);
            } 
            // 6 = Gate Out Lini 2
            else if (kdKeg === '6' && item.waktu_keluar) {
                $('#waktu-kegiatan').val(item.waktu_keluar);
                if (item.departemen === 'GUDANG') {
                    $('#jenis-cont').val('4'); // Gate Out Gudang kontainer kosong (EMPTY)
                }
            } 
            // Fallback ke waktu_masuk jika ada
            else if (item.waktu_masuk && !$('#waktu-kegiatan').val()) {
                $('#waktu-kegiatan').val(item.waktu_masuk);
            }
        }

        function onKegiatanChange(val) {
            // Bab 8.2: Kosongkan posisi jika kegiatan keluar/loading (Kosongkan Posisi = Ya)
            const kosongkanPosisi = ['2', '3', '6', '8', '9', '13', '20'];
            if (kosongkanPosisi.includes(val)) {
                $('#block-loc').val('');
                $('#slot-loc').val('');
                $('#tier-loc').val('');
            }

            // Set otomatis dokumen berdasarkan alur kegiatan & departemen
            if (val === '5') {
                // Gate In PLP -> TPP: 3 (Persetujuan PLP), Gudang: 704 (Master B/L) atau 11 (Manifes)
                if (currentDept === 'gudang') {
                    $('#kode-dok').val('704');
                } else {
                    $('#kode-dok').val('3');
                }
            } else if (val === '23') {
                // 23 = Stripping Stuffing Lini 2 -> Khusus Gudang LCL
                if (currentDept === 'gudang') {
                    if (!$('#kode-dok').val() || $('#kode-dok').val() === '3') {
                        $('#kode-dok').val('704');
                    }
                }
            } else if (val === '6') {
                // Gate Out Lini 2
                if (currentDept === 'gudang') {
                    $('#jenis-cont').val('4'); // Empty container
                } else {
                    if (!$('#kode-dok').val() || $('#kode-dok').val() === '3') {
                        $('#kode-dok').val('20'); // BC 2.0 (SPPB PIB)
                    }
                }
            } else if (val === '7' || val === '8' || val === '18' || val === '24') {
                // Ekspor Lini 2 -> Default PEB (30)
                if (!$('#kode-dok').val()) {
                    $('#kode-dok').val('30');
                }
            }

            // Terapkan timestamp otomatis bila data kontainer sudah dimuat
            if (window.selectedContainerData) {
                applySmartTimestamp(window.selectedContainerData);
            }
        }

        function initSelect2Container() {
            $('#no-cont').select2({
                placeholder: '-- Ketik atau Pilih Nomor Kontainer --',
                allowClear: true,
                tags: true,
                ajax: {
                    url: 'api/tps_tracking.php?action=search_container',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            dept: currentDept
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results || []
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0,
                templateResult: formatContainerOption,
                templateSelection: formatContainerSelection
            });

            // Event saat kontainer dipilih dari dropdown
            $('#no-cont').on('select2:select', function (e) {
                const item = e.params.data;
                autoFillContainerData(item);
            });

            // Event saat pilihan dihapus
            $('#no-cont').on('select2:clear select2:unselect', function () {
                window.selectedContainerData = null;
                setTimeout(updateJsonPreview, 50);
            });

            // Event saat nilai berubah
            $('#no-cont').on('change', function () {
                updateJsonPreview();
            });
        }

        function formatContainerOption(item) {
            if (item.loading) return item.text;
            if (!item.container_no) return item.text;

            const isEmp = item.status === 'EMPTY';
            const isLcl = item.status === 'LCL';
            const badgeStatus = isEmp 
                ? `<span class="badge-pill badge-out" style="font-size:0.72rem; padding:2px 6px;">EMPTY</span>` 
                : (isLcl 
                    ? `<span class="badge-pill" style="background:rgba(245, 158, 11, 0.15); color:#f59e0b; border:1px solid rgba(245, 158, 11, 0.3); font-size:0.72rem; padding:2px 6px;">LCL</span>`
                    : `<span class="badge-pill badge-in" style="font-size:0.72rem; padding:2px 6px;">FCL</span>`);

            const badgeDept = item.departemen === 'GUDANG'
                ? `<span class="badge-pill" style="background:rgba(16, 185, 129, 0.15); color:#10b981; border:1px solid rgba(16, 185, 129, 0.3); font-size:0.7rem; padding:2px 6px;">🏬 GUDANG</span>`
                : `<span class="badge-pill" style="background:rgba(59, 130, 246, 0.15); color:#60a5fa; border:1px solid rgba(59, 130, 246, 0.3); font-size:0.7rem; padding:2px 6px;">🏢 TPP</span>`;

            const badgeSent = item.already_sent 
                ? `<span class="badge-pill" style="background:rgba(245, 158, 11, 0.15); color:#f59e0b; border:1px solid rgba(245, 158, 11, 0.35); font-size:0.7rem; padding:2px 6px;">Pernah Terkirim</span>` 
                : '';

            let html = `
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong style="font-family:'JetBrains Mono',monospace; color:var(--text-primary); font-size:0.92rem;">${item.container_no}</strong>
                        ${badgeDept}
                        ${badgeStatus}
                        <span class="badge-pill badge-ceisa" style="font-size:0.72rem; padding:2px 6px;">${item.size_type || '40'} ft</span>
                        ${badgeSent}
                    </div>
                    ${item.yard_block ? `<span style="font-size:0.78rem; color:var(--text-secondary);">📍 ${item.yard_block}${item.slot ? ' S:'+item.slot : ''}${item.tier ? ' T:'+item.tier : ''}</span>` : ''}
                </div>
            `;

            const subInfo = [];
            if (item.no_bl) subInfo.push(`B/L: <span style="color:var(--accent-blue);">${item.no_bl}</span>`);
            if (item.nopol) subInfo.push(`Nopol: <b>${item.nopol}</b>`);
            if (item.no_dokumen) subInfo.push(`Dok: ${item.no_dokumen}`);
            if (item.waktu_stripping) subInfo.push(`Stripping: ${item.waktu_stripping.split(' ')[0]}`);

            if (subInfo.length > 0) {
                html += `<div style="font-size:0.75rem; color:var(--text-secondary); margin-top:2px;">${subInfo.join(' &bull; ')}</div>`;
            }

            return $(html);
        }

        function formatContainerSelection(item) {
            const val = item.container_no || item.id || item.text || '';
            return val ? val.replace(/[\s\-]/g, '').toUpperCase() : '-- Ketik atau Pilih Nomor Kontainer --';
        }

        function autoFillContainerData(item) {
            if (!item) return;

            window.selectedContainerData = item;

            // Pastikan nomor kontainer bersih tanpa spasi atau dash
            const contClean = (item.container_no || item.id || '').replace(/[\s\-]/g, '').toUpperCase();
            if ($('#no-cont').val() !== contClean) {
                if ($('#no-cont').find("option[value='" + contClean + "']").length) {
                    $('#no-cont').val(contClean);
                } else {
                    const newOption = new Option(contClean, contClean, true, true);
                    $('#no-cont').append(newOption);
                }
            }

            // 1. Ukuran Kontainer (Readonly)
            const sz = item.size || (item.size_type && item.size_type.toString().includes('20') ? '20' : (item.size_type && item.size_type.toString().includes('45') ? '45' : '40'));
            $('#ukuran-cont').val(sz);
            $('#ukuran-cont-display').val(sz + ' ft');

            // 2. Jenis Kontainer (4 = EMPTY, 7 = LCL, 8 = FCL sesuai OpenAPI CEISA 4.0)
            if (item.status === 'EMPTY') {
                $('#jenis-cont').val('4');
            } else if (item.status === 'LCL') {
                $('#jenis-cont').val('7');
            } else {
                $('#jenis-cont').val('8'); // FCL
            }

            // 3. Posisi Yard Lapangan
            $('#block-loc').val(item.yard_block || '');
            $('#slot-loc').val(item.slot || '');
            $('#tier-loc').val(item.tier || '');

            // 4. Armada Truk Nopol
            $('#nopol').val(item.nopol || '');

            // 5. Dokumen B/L
            $('#no-bl').val(item.no_bl || '');
            $('#tgl-bl').val(item.tgl_bl || '');

            // 6. Dokumen Pabean & Pengangkutan Berdasarkan Departemen
            if (item.departemen === 'GUDANG') {
                // Untuk Gudang (LCL): dokumen kedatangan kontainer adalah Master B/L (704) atau Manifes (11)
                if (item.no_bl) {
                    $('#no-dok').val(item.no_bl);
                    $('#tgl-dok').val(item.tgl_bl || '');
                    $('#kode-dok').val('704'); // 704 = MASTER B/L
                } else if (item.no_dokumen) {
                    $('#no-dok').val(item.no_dokumen);
                    $('#tgl-dok').val(item.tgl_dokumen || '');
                    $('#kode-dok').val('11'); // 11 = MANIFES (BC 1.1)
                }
            } else {
                // Untuk TPP (PLP): dokumen kedatangan kontainer adalah Persetujuan PLP (3)
                if (item.no_dokumen || item.no_plp) {
                    $('#no-dok').val(item.no_dokumen || item.no_plp);
                    $('#tgl-dok').val(item.tgl_dokumen || item.tgl_plp || '');
                    $('#kode-dok').val('3'); // 3 = Persetujuan PLP
                }
            }

            // 7. Waktu Kegiatan Cerdas
            applySmartTimestamp(item);

            // Update live preview & beri notifikasi toast
            updateJsonPreview();
            
            const deptLabel = item.departemen || (currentDept === 'gudang' ? 'Gudang' : 'TPP');
            if (item.already_sent) {
                showToast(`ℹ️ Kontainer [${deptLabel}] ${contClean} sudah pernah dikirim sebelumnya (${item.last_tracked_status})`, 'info');
            } else {
                showToast(`Data kontainer [${deptLabel}] ${contClean} berhasil dimuat otomatis!`, 'success');
            }
        }

        function buildPayload() {
            const rawCont = ($('#no-cont').val() || '').trim().toUpperCase();
            const contVal = rawCont.replace(/[\s\-]/g, '');
            const payload = {
                departemen: currentDept.toUpperCase(),
                nomorKontainer: contVal,
                ukuranKontainer: $('#ukuran-cont').val(),
                jenisKontainer: $('#jenis-cont').val(),
                kodeTps: $('#kode-tps').val().trim().toUpperCase(),
                kodeGudang: $('#kode-gudang').val().trim().toUpperCase(),
                kodeKegiatan: parseInt($('#kode-kegiatan').val(), 10),
                waktuKegiatan: $('#waktu-kegiatan').val().trim()
            };

            const block = $('#block-loc').val().trim();
            if (block) payload.block = block;

            const slot = $('#slot-loc').val().trim();
            if (slot) payload.slot = slot;

            const tier = $('#tier-loc').val().trim();
            if (tier) payload.tier = tier;

            const nopol = $('#nopol').val().trim().toUpperCase();
            if (nopol) payload.nomorPolisi = nopol;

            const stid = $('#stid').val().trim();
            if (stid) payload.stid = stid;

            const kdDok = $('#kode-dok').val();
            if (kdDok) payload.kodeDokumen = kdDok;

            const noDok = $('#no-dok').val().trim();
            if (noDok) payload.nomorDokumen = noDok;

            const tglDok = $('#tgl-dok').val().trim();
            if (tglDok) payload.tanggalDokumen = tglDok;

            const noBl = $('#no-bl').val().trim();
            if (noBl) payload.nomorBlAwb = noBl;

            const tglBl = $('#tgl-bl').val().trim();
            if (tglBl) payload.tanggalBlAwb = tglBl;

            return payload;
        }

        function updateJsonPreview() {
            currentPayload = buildPayload();
            const btnSend = document.getElementById('btn-submit-tracking');
            const contVal = currentPayload.nomorKontainer;

            // Jika belum ada nomor kontainer, kosongkan JSON preview dan nonaktifkan tombol kirim
            if (!contVal || contVal.length < 4) {
                $('#json-tracking-preview').val('{\n    // Silakan ketik atau pilih nomor kontainer terlebih dahulu\n}');
                $('#schema-status').html('<span style="color:var(--text-secondary);">⚠️ Nomor Kontainer Belum Diisi</span>');
                if (btnSend) {
                    btnSend.disabled = true;
                    btnSend.style.opacity = '0.45';
                    btnSend.style.cursor = 'not-allowed';
                    btnSend.style.filter = 'grayscale(0.7)';
                    btnSend.title = 'Silakan pilih atau ketik nomor kontainer terlebih dahulu';
                }
                return;
            }

            $('#json-tracking-preview').val(JSON.stringify(currentPayload, null, 4));

            const isValid = !!(currentPayload.nomorKontainer && currentPayload.ukuranKontainer && currentPayload.jenisKontainer && currentPayload.kodeTps && currentPayload.kodeGudang && currentPayload.kodeKegiatan && currentPayload.waktuKegiatan);
            if (isValid) {
                $('#schema-status').html('<span style="color:#10b981;">✓ Parameter Wajib Terisi</span>');
                if (btnSend) {
                    btnSend.disabled = false;
                    btnSend.style.opacity = '1';
                    btnSend.style.cursor = 'pointer';
                    btnSend.style.filter = 'none';
                    btnSend.title = 'Kirim data tracking ke gateway CEISA 4.0';
                }
            } else {
                $('#schema-status').html('<span style="color:#f59e0b;">⚠️ Field Bertanda * Wajib Diisi</span>');
                if (btnSend) {
                    btnSend.disabled = true;
                    btnSend.style.opacity = '0.45';
                    btnSend.style.cursor = 'not-allowed';
                    btnSend.style.filter = 'grayscale(0.7)';
                    btnSend.title = 'Lengkapi field bertanda * terlebih dahulu';
                }
            }
        }

        function copyJsonPayload() {
            const text = $('#json-tracking-preview').val();
            navigator.clipboard.writeText(text).then(() => {
                showToast('JSON payload berhasil disalin ke clipboard!', 'success');
            }).catch(err => {
                showToast('Gagal menyalin: ' + err, 'error');
            });
        }

        async function sendTracking() {
            const payload = buildPayload();

            if (!payload.nomorKontainer) {
                Swal.fire({
                    title: 'Nomor Kontainer Kosong',
                    text: 'Silakan isi nomor kontainer terlebih dahulu!',
                    icon: 'warning',
                    confirmButtonColor: '#10b981'
                });
                $('#no-cont').focus();
                return;
            }

            if (!payload.waktuKegiatan) {
                Swal.fire({
                    title: 'Waktu Kegiatan Kosong',
                    text: 'Silakan isi waktu kegiatan!',
                    icon: 'warning',
                    confirmButtonColor: '#10b981'
                });
                return;
            }

            const kegiatanLabel = $('#kode-kegiatan option:selected').text();

            // 1. Konfirmasi SweetAlert2
            const confirmRes = await Swal.fire({
                title: 'Konfirmasi Pengiriman Tracking',
                html: `
                    <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                        <p style="margin-bottom:8px;">
                            Kirim data tracking pergerakan kontainer ke <b>CEISA 4.0</b>?
                        </p>
                        <div style="background:rgba(0,0,0,0.2); border:1px solid var(--border-medium); border-radius:8px; padding:12px; margin-bottom:10px;">
                            <div>📦 <b>Kontainer:</b> <code style="color:#38bdf8;">${payload.nomorKontainer}</code> (${payload.ukuranKontainer} ft)</div>
                            <div>⚡ <b>Kegiatan:</b> ${kegiatanLabel}</div>
                            <div>⏱️ <b>Waktu:</b> ${payload.waktuKegiatan}</div>
                            <div>🏢 <b>TPS / Gudang:</b> ${payload.kodeTps} / ${payload.kodeGudang}</div>
                            ${payload.nomorPolisi ? `<div>🚚 <b>Nopol:</b> ${payload.nomorPolisi}</div>` : ''}
                            ${payload.nomorDokumen ? `<div>📑 <b>Dokumen:</b> ${payload.nomorDokumen}</div>` : ''}
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '🚀 Ya, Kirim Tracking',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                reverseButtons: true
            });

            if (!confirmRes.isConfirmed) return;

            const btnSend = document.getElementById('btn-submit-tracking');
            const spinner = document.getElementById('send-spinner');
            const icon = document.getElementById('send-icon');

            btnSend.disabled = true;
            spinner.style.display = 'inline-block';
            icon.style.display = 'none';

            // SweetAlert Loading
            Swal.fire({
                title: 'Merekam ke CEISA 4.0...',
                html: `Sedang mengirim tracking kontainer <b>${payload.nomorKontainer}</b> ke gateway Bea Cukai...`,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const res = await fetch('api/tps_tracking.php?action=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ payload: payload })
                });

                const result = await res.json();
                const ceisaRaw = result.raw || result;
                const isConflict = (result.code === 409 || ceisaRaw.code === 409 || ceisaRaw.result === 'Data already exists.' || (ceisaRaw.detail && ceisaRaw.detail.includes('sudah pernah')));

                // Tampilkan Response Card di UI
                $('#send-result-card').show();
                const badge = $('#send-status-badge');
                badge.removeClass('badge-in badge-out').removeAttr('style');

                if (result.success) {
                    badge.addClass('badge-in').text(`HTTP ${result.code || 201} CREATED`);
                    $('#send-result-msg').html(`✅ <b>Berhasil Terkirim:</b> ${result.message || 'Data tracking pergerakan kontainer berhasil direkam di CEISA 4.0.'}`);
                } else if (isConflict) {
                    badge.css({
                        background: 'rgba(245, 158, 11, 0.18)',
                        color: '#f59e0b',
                        border: '1px solid #f59e0b'
                    }).text(`HTTP 409 CONFLICT — DATA ALREADY EXISTS`);
                    $('#send-result-msg').html(`⚠️ <b>Data already exists:</b> ${ceisaRaw.detail || 'Data sudah pernah dikirim sebelumnya.'} ${ceisaRaw.data?.alasan ? '<br><small style="color:var(--text-secondary);">' + ceisaRaw.data.alasan + '</small>' : ''}`);
                } else {
                    badge.addClass('badge-out').text(`HTTP ${result.code || 400} FAILED`);
                    $('#send-result-msg').html(`❌ <b>Gagal:</b> ${result.message || 'Pengiriman ditolak oleh gateway CEISA 4.0'}`);
                }

                $('#send-timestamp').text('Respon: ' + new Date().toLocaleTimeString('id-ID'));
                
                // Tampilkan format resmi CEISA persis seperti yang diminta
                const displayJson = (isConflict || ceisaRaw.result) ? ceisaRaw : result;
                $('#send-raw-response').text(JSON.stringify(displayJson, null, 4)).show();
                document.getElementById('send-result-card').scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                if (result.success) {
                    const trackingId = result.data?.id || '-';
                    Swal.fire({
                        title: '🎉 Tracking Berhasil Direkam!',
                        html: `
                            <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                                Data tracking pergerakan kontainer <b>${payload.nomorKontainer}</b> telah berhasil diterima oleh <b>CEISA 4.0</b>.<br><br>
                                <div style="background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); border-radius:6px; padding:10px;">
                                    <span>🆔 <b>ID Tracking CEISA:</b> <code style="color:#10b981; font-weight:700;">#${trackingId}</code></span><br>
                                    <span>📦 <b>Kontainer:</b> ${payload.nomorKontainer}</span><br>
                                    <span>⏱️ <b>Waktu Rekam:</b> ${result.data?.waktuRekam || new Date().toLocaleString('id-ID')}</span>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: '📜 Buka Laporan Tracking',
                        cancelButtonText: 'Tutup',
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#64748b'
                    }).then((r) => {
                        if (r.isConfirmed) {
                            window.location.href = 'report_tracking.php';
                        }
                    });
                    showToast('Tracking kontainer sukses dikirim!', 'success');
                } else if (isConflict) {
                    const dupData = ceisaRaw.data || {};
                    Swal.fire({
                        title: '⚠️ Data Sudah Pernah Dikirim!',
                        html: `
                            <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                                <div style="background:rgba(245, 158, 11, 0.12); border:1px solid rgba(245, 158, 11, 0.35); border-radius:8px; padding:12px; margin-bottom:12px;">
                                    <div style="font-weight:700; color:#d97706; font-size:14px; margin-bottom:4px;">
                                        HTTP 409 — ${ceisaRaw.result || 'Data already exists.'}
                                    </div>
                                    <div style="color:var(--text-primary); margin-bottom:6px; font-weight:500;">
                                        ${ceisaRaw.detail || 'Data sudah pernah dikirim sebelumnya.'}
                                    </div>
                                    ${dupData.alasan ? `<div style="font-size:12.5px; color:#d97706; margin-bottom:6px;">ℹ️ ${dupData.alasan}</div>` : ''}
                                    <div style="font-size:12px; color:var(--text-secondary);">
                                        <b>Kontainer:</b> <code>${dupData.nomorKontainer || payload.nomorKontainer}</code> &bull; 
                                        <b>Kegiatan:</b> ${dupData.kodeKegiatan || payload.kodeKegiatan} &bull; 
                                        <b>Waktu:</b> ${dupData.waktuKegiatan || payload.waktuKegiatan}
                                    </div>
                                </div>
                                <div style="font-size:12.5px; color:var(--text-secondary); margin-bottom:10px;">
                                    💡 <b>Solusi:</b> Jika pergerakan ini ingin dicatat dengan waktu baru, klik tombol <b>Gunakan Waktu Sekarang & Kirim Ulang</b> di bawah.
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                    <span style="font-weight:600; font-size:12px; color:var(--text-secondary);">RAW RESPONSE CEISA 4.0 (HTTP 409):</span>
                                    <button type="button" class="field-action-btn" onclick="navigator.clipboard.writeText($('#modal-dup-json').text()); showToast('JSON disalin ke clipboard!','success');">📋 Salin JSON</button>
                                </div>
                                <pre id="modal-dup-json" style="background:#0d131f; color:#38bdf8; padding:12px; border-radius:8px; font-family:'JetBrains Mono',monospace; font-size:11.5px; line-height:1.5; max-height:180px; overflow:auto; margin:0; border:1px solid #1e293b;">${JSON.stringify(ceisaRaw, null, 4)}</pre>
                            </div>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '⏱️ Gunakan Waktu Sekarang & Kirim Ulang',
                        cancelButtonText: 'Tutup & Edit Manual',
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#64748b'
                    }).then(r => {
                        if (r.isConfirmed) {
                            setNowTime();
                            showToast('Waktu kegiatan diset ke sekarang. Mengirim ulang tracking...', 'info');
                            setTimeout(() => submitTracking(), 400);
                        }
                    });
                    showToast('Kontainer sudah pernah dikirim sebelumnya (HTTP 409)', 'info');
                } else {
                    Swal.fire({
                        title: 'Pengiriman Tracking Ditolak',
                        html: `
                            <div style="text-align:left; font-size:13.5px;">
                                <p style="color:#ef4444; font-weight:600;">${result.message || 'Gateway mengembalikan error'}</p>
                                <pre style="background:#0d131f; color:#fca5a5; padding:10px; border-radius:6px; font-size:12px; max-height:150px; overflow:auto;">${JSON.stringify(ceisaRaw, null, 2)}</pre>
                            </div>
                        `,
                        icon: 'error',
                        confirmButtonColor: '#ef4444'
                    });
                    showToast('Pengiriman tracking gagal: ' + (result.message || ''), 'error');
                }

            } catch (err) {
                console.error(err);
                Swal.fire({
                    title: 'Kesalahan Sistem',
                    text: err.message,
                    icon: 'error'
                });
                showToast('Terjadi kesalahan jaringan: ' + err.message, 'error');
            } finally {
                btnSend.disabled = false;
                spinner.style.display = 'none';
                icon.style.display = 'inline-block';
            }
        }



        // Inisialisasi awal
        $(document).ready(function() {
            initSelect2Container();
            updateJsonPreview();

            // Theme management
            const themeBtn = document.getElementById('theme-toggle');
            if (themeBtn) {
                themeBtn.addEventListener('click', () => {
                    const cur = document.documentElement.getAttribute('data-theme') || 'dark';
                    const next = cur === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', next);
                    localStorage.setItem('ceisa_theme', next);
                    const icon = document.querySelector('.theme-toggle-icon');
                    const txt = document.querySelector('.theme-toggle-text');
                    if (icon) icon.textContent = next === 'dark' ? '🌙' : '☀️';
                    if (txt) txt.textContent = next === 'dark' ? 'Dark' : 'Light';
                });
            }

            // Mobile menu toggle
            const menuToggle = document.getElementById('menu-toggle');
            if (menuToggle) {
                menuToggle.addEventListener('click', () => {
                    document.querySelector('.sidebar').classList.toggle('open');
                    document.querySelector('.sidebar-overlay').classList.toggle('active');
                });
            }
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                overlay.addEventListener('click', () => {
                    document.querySelector('.sidebar').classList.remove('open');
                    overlay.classList.remove('active');
                });
            }
        });
    </script>
</body>
</html>
