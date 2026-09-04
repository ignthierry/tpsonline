<?php
/**
 * Laporan Data Terkirim TPS Tracking Kontainer CEISA 4.0
 * Endpoint: /kirim-tps-tracking
 * Diselaraskan sepenuhnya dengan report_cont.php & report_kms.php
 * Terintegrasi dengan jQuery DataTables, Auto-Sync AJAX, Modal Detail Rincian Kontainer, dan Desain Modern Enterprise
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';
$config = require __DIR__ . '/config.php';
$endpoints = getEndpointDefinitions();

$username = $_SESSION['name'] ?? $_SESSION['username'] ?? $config['username'] ?? 'User';
$userInitial = strtoupper(substr($username, 0, 2));
$todayDate = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan TPS Tracking (Kontainer) CEISA 4.0 — <?= e($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <!-- jQuery & DataTables CDN -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
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
        .report-container {
            padding: 24px 24px 80px 24px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .report-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid var(--border-subtle);
            margin-bottom: 24px;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 16px;
            align-items: flex-end;
        }
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
        .input-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .input-control {
            width: 100%;
            padding: 10px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border-medium);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .input-control:focus {
            outline: none;
            border-color: var(--accent-blue);
        }
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-item {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
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
            font-size: 0.95rem;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: rgba(59, 130, 246, 0.15);
            color: var(--accent-blue);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .json-box {
            width: 100%;
            height: 440px;
            background: #0d131f;
            color: #7dd3fc;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13.5px;
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
        .ref-badge {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            color: #60a5fa;
            background: rgba(59, 130, 246, 0.12);
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid rgba(59, 130, 246, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-copy-ref {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.85rem;
            padding: 2px 4px;
            border-radius: 4px;
            transition: color 0.15s;
        }
        .btn-copy-ref:hover {
            color: #ffffff;
        }

        /* Action Buttons & View Raw JSON Styling */
        .btn-action-group {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }
        .btn-table-detail {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.18) 0%, rgba(37, 99, 235, 0.28) 100%);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.45);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.15);
            transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
        }
        .btn-table-detail:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.3) 0%, rgba(37, 99, 235, 0.42) 100%);
            color: #93c5fd;
            border-color: #60a5fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35);
        }
        .btn-table-detail:active {
            transform: translateY(0);
        }
        .btn-table-copy {
            background: var(--bg-surface);
            color: var(--text-secondary);
            border: 1px solid var(--border-medium);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .btn-table-copy:hover {
            background: var(--border-medium);
            color: var(--text-primary);
            border-color: var(--text-secondary);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .btn-view-raw-json {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.16) 0%, rgba(99, 102, 241, 0.24) 100%);
            color: #c4b5fd;
            border: 1px solid rgba(139, 92, 246, 0.42);
            padding: 8px 18px;
            border-radius: 24px;
            font-size: 0.86rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.15);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-view-raw-json:hover {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.3) 0%, rgba(99, 102, 241, 0.4) 100%);
            color: #ffffff;
            border-color: #a78bfa;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(139, 92, 246, 0.38);
        }
        .btn-view-raw-json:active {
            transform: translateY(0);
        }
        .btn-view-raw-json .icon-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            background: rgba(139, 92, 246, 0.3);
            border-radius: 50%;
            font-size: 0.78rem;
        }

        /* Modal Styles */
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96) translateY(-10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.75);
            z-index: 99999;
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
        }
        .modal-card {
            background: var(--bg-card);
            border: 1px solid var(--border-medium);
            border-radius: 14px;
            width: 100%;
            max-width: 1100px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            animation: modalFadeIn 0.25s ease-out;
        }
        .btn-subtab {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.88rem;
            padding: 8px 16px;
            border-radius: 6px 6px 0 0;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-subtab.active {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border-bottom: 2px solid #3b82f6;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar Overlay (Mobile) -->
        <div class="sidebar-overlay"></div>

        <!-- Sidebar -->
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
                        <span>Laporan</span>
                        <span class="separator">/</span>
                        <span class="current">TPS Tracking (Kontainer)</span>
                    </div>
                </div>

                <div class="header-right">
                    <button class="theme-toggle-btn" id="theme-toggle" title="Ganti Tema">
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
                <div class="report-container">

                    <!-- Filter Card -->
                    <div class="report-card">
                        <div style="margin-bottom: 18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                            <div>
                                <h2 style="margin:0; font-size:1.25rem; color:var(--text-primary); font-weight:700;">
                                    📊 Laporan TPS Tracking Kontainer
                                </h2>
                                <p style="margin:4px 0 0; color:var(--text-secondary); font-size:0.88rem;">
                                    Monitoring & verifikasi data pergerakan fisik kontainer (Gate-In / Gate-Out / Stacking) yang telah resmi tersimpan di server Bea Cukai CEISA 4.0.
                                </p>
                            </div>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <a href="tps_tracking.php" class="btn-action-sm" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                    <span>📍</span> Menu TPS Tracking
                                </a>
                                <a href="report_cont.php" class="btn-action-sm" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px; color:#60a5fa; border-color:rgba(59,130,246,0.3); background:rgba(59,130,246,0.1);">
                                    <span>📦</span> Laporan Kontainer
                                </a>
                                <a href="report_kms.php" class="btn-action-sm" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px; color:#c4b5fd; border-color:rgba(139,92,246,0.3); background:rgba(139,92,246,0.1);">
                                    <span>📦</span> Laporan Kemasan
                                </a>
                                <span class="badge-pill badge-ceisa">kirim-tps-tracking</span>
                            </div>
                        </div>

                        <form id="filter-form" onsubmit="event.preventDefault(); loadReportData(true);">
                            <div class="filter-grid" style="grid-template-columns: 1fr 1fr 1.2fr auto;">
                                <div class="input-group">
                                    <label for="tgl-awal">Tanggal Awal</label>
                                    <input type="date" id="tgl-awal" class="input-control" value="<?= e($firstDayOfMonth) ?>" required onchange="loadReportData(false)">
                                </div>

                                <div class="input-group">
                                    <label for="tgl-akhir">Tanggal Akhir</label>
                                    <input type="date" id="tgl-akhir" class="input-control" value="<?= e($todayDate) ?>" required onchange="loadReportData(false)">
                                </div>

                                <div class="input-group">
                                    <label for="filter-dept">Departemen Operasional</label>
                                    <select id="filter-dept" class="input-control" onchange="loadReportData(false)">
                                        <option value="">Semua Departemen (TPP & Gudang)</option>
                                        <option value="tpp">🏢 TPP (CPSU - PLP FCL)</option>
                                        <option value="gudang">🏬 Gudang (GPSU - CFS LCL)</option>
                                    </select>
                                </div>

                                <div style="display:flex; align-items:center; height:42px; margin-bottom:2px;">
                                    <div id="auto-sync-status" style="display:inline-flex; align-items:center; gap:8px; font-size:0.85rem; font-weight:600; color:#10b981; padding:8px 16px; border-radius:20px; background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.25);">
                                        <span class="pulse-dot"></span> <span id="auto-sync-text">Auto-Sync AJAX Aktif</span>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Stats Bar -->
                    <div class="stats-bar" id="stats-bar" style="display:none; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                        <div class="stat-item">
                            <span class="stat-label">Total Data Terkirim</span>
                            <span class="stat-value" id="stat-count" style="color:#10b981;">0 Kontainer</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">🏢 TPP (CPSU)</span>
                            <span class="stat-value" id="stat-tpp" style="color:#60a5fa; font-size:1.4rem;">0 Box</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">🏬 Gudang (GPSU)</span>
                            <span class="stat-value" id="stat-gudang" style="color:#10b981; font-size:1.4rem;">0 Box</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Layanan / Endpoint</span>
                            <span class="stat-value" id="stat-service" style="color:#38bdf8; font-size:1.1rem; font-family:'JetBrains Mono',monospace;">kirim-tps-tracking</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Rentang Waktu</span>
                            <span class="stat-value" id="stat-range" style="color:#a78bfa; font-size:1.1rem; font-family:'JetBrains Mono',monospace;">-</span>
                        </div>
                    </div>

                    <!-- Result Panel -->
                    <div class="report-card" id="result-card" style="display:none;">
                        <!-- Tabs -->
                        <div class="tabs-nav">
                            <button class="tab-btn active" onclick="switchTab('tab-table', this)">
                                <span>📋</span> Data Terkirim TPS Tracking (<span id="tab-count">0</span>)
                            </button>
                            <button class="tab-btn" onclick="switchTab('tab-json', this)">
                                <span>📦</span> Respon JSON CEISA 4.0
                            </button>
                        </div>

                        <!-- Tab 1: Table -->
                        <div class="tab-content active" id="tab-table">
                            <div style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <div style="font-size:0.88rem; color:var(--text-secondary);">
                                    <span>Tabel Interaktif Pengiriman TPS Tracking Kontainer (Sorting, Filter & Pagination DataTables aktif)</span>
                                </div>
                                <button type="button" class="btn-view-raw-json" onclick="switchTab('tab-json', document.querySelectorAll('.tab-btn')[1])" title="Buka respon lengkap JSON dari Gateway CEISA 4.0">
                                    <span class="icon-badge">⚡</span>
                                    <span>Lihat Raw JSON Response</span>
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="data-table display responsive nowrap" id="table-report" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th style="width:50px; text-align:center;">No</th>
                                            <th>Nomor Kontainer</th>
                                            <th>Departemen</th>
                                            <th>Kegiatan & Status CEISA</th>
                                            <th>Waktu Kegiatan</th>
                                            <th>No. B/L / AWB</th>
                                            <th>Posisi Yard & Nopol</th>
                                            <th>Status Gateway</th>
                                            <th style="text-align:center; width:170px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="table-body">
                                        <!-- Rendered via DataTables -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab 2: JSON Response -->
                        <div class="tab-content" id="tab-json">
                            <div style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <span style="font-size:0.88rem; color:var(--text-secondary);">
                                    Raw JSON Log Pengiriman & Respon Gateway CEISA 4.0 Bea Cukai:
                                </span>
                                <div style="display:flex; gap:10px;">
                                    <button type="button" class="btn-action-sm" onclick="copyJson()">
                                        <span>📋</span> Salin JSON
                                    </button>
                                    <button type="button" class="btn-action-sm" onclick="downloadJson()">
                                        <span>💾</span> Unduh .json
                                    </button>
                                </div>
                            </div>
                            <textarea id="json-viewer" class="json-box" spellcheck="false" readonly></textarea>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- ===== MODAL DETAIL RINCIAN KONTAINER TERKIRIM ===== -->
    <div id="modal-detail-cont" class="modal-overlay" onclick="handleModalOverlayClick(event)">
        <div class="modal-card">
            <!-- Modal Header -->
            <div style="padding:18px 24px; border-bottom:1px solid var(--border-medium); display:flex; justify-content:space-between; align-items:center; background:var(--bg-surface);">
                <div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.35rem;">📦</span>
                        <h3 style="margin:0; font-size:1.15rem; color:var(--text-primary); font-weight:700;">
                            Rincian Data Kontainer Terkirim ke CEISA 4.0
                        </h3>
                    </div>
                    <div style="margin-top:5px; font-size:0.85rem; color:var(--text-secondary); display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <span>Nomor Kontainer:</span>
                        <code id="modal-ref-number" style="font-family:'JetBrains Mono',monospace; color:var(--accent-blue); font-weight:700; font-size:0.92rem; background:rgba(59,130,246,0.12); padding:2px 8px; border-radius:4px;">-</code>
                        <span id="modal-status-pill" class="badge-pill badge-in" style="font-size:0.75rem;">✅ TERKIRIM & TERCATAT</span>
                    </div>
                </div>
                <button type="button" onclick="closeDetailModal()" style="background:none; border:none; color:var(--text-secondary); font-size:1.8rem; cursor:pointer; padding:2px 8px; line-height:1; border-radius:6px;" title="Tutup">&times;</button>
            </div>

            <!-- Modal Body -->
            <div style="padding:20px 24px; overflow-y:auto; flex-grow:1;">
                <!-- Loading State -->
                <div id="modal-loading" style="text-align:center; padding:40px 20px;">
                    <span class="pulse-dot" style="width:12px; height:12px; background:#3b82f6;"></span>
                    <p style="margin-top:12px; color:var(--text-secondary); font-size:0.9rem;">Memuat rincian data tracking dari database & log CEISA...</p>
                </div>

                <!-- Main Content (when loaded) -->
                <div id="modal-content" style="display:none;">
                    <!-- Grid Header Details -->
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:20px; background:var(--bg-surface); padding:16px; border-radius:10px; border:1px solid var(--border-subtle);">
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-secondary); font-weight:600; text-transform:uppercase;">No & Tgl BC 1.1 / Dokumen</div>
                            <div id="modal-bc11" style="font-size:0.92rem; font-weight:600; color:var(--text-primary); margin-top:2px;">-</div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-secondary); font-weight:600; text-transform:uppercase;">Gudang / TPS</div>
                            <div id="modal-gudang" style="font-size:0.92rem; font-weight:600; color:var(--text-primary); margin-top:2px;">-</div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-secondary); font-weight:600; text-transform:uppercase;">Waktu Kegiatan Fisik</div>
                            <div id="modal-waktu-kegiatan" style="font-size:0.92rem; font-weight:600; color:var(--text-primary); margin-top:2px;">-</div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-secondary); font-weight:600; text-transform:uppercase;">Waktu Rekam CEISA</div>
                            <div id="modal-waktu-kirim" style="font-size:0.92rem; font-weight:600; color:var(--text-primary); margin-top:2px;">-</div>
                        </div>
                    </div>

                    <!-- Subtabs -->
                    <div style="display:flex; border-bottom:1px solid var(--border-medium); margin-bottom:16px; gap:8px;">
                        <button type="button" class="btn-subtab active" id="btn-subtab-table" onclick="switchModalTab('table')">
                            📦 Rincian Data Kontainer
                        </button>
                        <button type="button" class="btn-subtab" id="btn-subtab-raw" onclick="switchModalTab('raw')">
                            ⚡ Respon & Payload Gateway
                        </button>
                    </div>

                    <!-- Subtab 1: Table Details -->
                    <div id="modal-subtab-table">
                        <div style="overflow-x:auto; border-radius:8px; border:1px solid var(--border-subtle);">
                            <table class="data-table" style="width:100%; font-size:0.85rem;">
                                <thead>
                                    <tr>
                                        <th style="width:40px; text-align:center;">No</th>
                                        <th>Nomor Kontainer</th>
                                        <th>Ukuran / Tipe</th>
                                        <th>Status Muat</th>
                                        <th>Nomor B/L & Tgl</th>
                                        <th>Kegiatan CEISA</th>
                                        <th>Posisi Yard</th>
                                        <th>No Polisi & STID</th>
                                        <th>ID Tracking CEISA</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-table-body">
                                    <!-- Dynamic rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Subtab 2: Raw Response & Payload JSON -->
                    <div id="modal-subtab-raw" style="display:none;">
                        <div style="margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.82rem; color:var(--text-secondary); text-transform:uppercase; font-weight:600;">Data Raw JSON:</span>
                            <button type="button" class="btn-action-sm" onclick="copyModalRaw()">
                                <span>📋</span> Salin Raw JSON
                            </button>
                        </div>
                        <pre id="modal-raw-viewer" style="background:#0d131f; color:#a5f3fc; padding:16px; border-radius:8px; font-family:'JetBrains Mono',monospace; font-size:12.5px; line-height:1.5; max-height:360px; overflow:auto; margin:0; border:1px solid var(--border-medium);"></pre>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div style="padding:14px 24px; border-top:1px solid var(--border-medium); display:flex; justify-content:flex-end; background:var(--bg-surface);">
                <button type="button" class="btn-action-sm" onclick="closeDetailModal()">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" style="position:fixed; bottom:20px; right:20px; z-index:999999; display:flex; flex-direction:column; gap:10px;"></div>

    <script>
        let rawApiResponse = null;
        let cachedRows = [];
        let dataTableInstance = null;
        let activeAjaxRequest = null;

        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.style.padding = '12px 20px';
            toast.style.borderRadius = '8px';
            toast.style.fontSize = '0.9rem';
            toast.style.fontWeight = '500';
            toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.25)';
            toast.style.display = 'flex';
            toast.style.alignItems = 'center';
            toast.style.gap = '10px';
            toast.style.transition = 'all 0.3s ease';
            toast.style.zIndex = '999999';

            if (type === 'success') {
                toast.style.background = '#065f46';
                toast.style.color = '#34d399';
                toast.style.border = '1px solid #059669';
                toast.innerHTML = '<span>✅</span> ' + message;
            } else if (type === 'error') {
                toast.style.background = '#7f1d1d';
                toast.style.color = '#f87171';
                toast.style.border = '1px solid #dc2626';
                toast.innerHTML = '<span>❌</span> ' + message;
            } else {
                toast.style.background = '#1e3a8a';
                toast.style.color = '#93c5fd';
                toast.style.border = '1px solid #2563eb';
                toast.innerHTML = '<span>ℹ️</span> ' + message;
            }

            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        function switchTab(tabId, btn) {
            $('.tab-btn').removeClass('active');
            $('.tab-content').removeClass('active');
            if (btn) $(btn).addClass('active');
            $('#' + tabId).addClass('active');
        }

        function loadReportData(showNotification = true) {
            const tglAwal = $('#tgl-awal').val();
            const tglAkhir = $('#tgl-akhir').val();
            const dept = $('#filter-dept').val() || '';

            if (!tglAwal || !tglAkhir) return;

            // Batalkan request sebelumnya jika user mengganti tanggal dengan cepat
            if (activeAjaxRequest && activeAjaxRequest.readyState !== 4) {
                activeAjaxRequest.abort();
            }

            $('#auto-sync-status').html('<span class="pulse-dot" style="background:#3b82f6;"></span> <span style="color:#60a5fa;">Memeriksa server Bea Cukai...</span>');

            activeAjaxRequest = $.ajax({
                url: 'api/tps_tracking.php',
                type: 'GET',
                data: {
                    action: 'report',
                    tanggalAwal: tglAwal,
                    tanggalAkhir: tglAkhir,
                    dept: dept
                },
                dataType: 'json',
                success: function(result) {
                    rawApiResponse = result;
                    cachedRows = result.rows || [];
                    const count = cachedRows.length;
                    const summary = result.summary || {};

                    if (!result.success || count === 0) {
                        $('#auto-sync-status').html('ℹ️ <span style="color:var(--text-secondary);">Tidak ada data</span>');
                        if (dataTableInstance) {
                            dataTableInstance.destroy();
                            dataTableInstance = null;
                        }
                        $('#table-body').empty();
                        $('#stats-bar').hide();
                        $('#result-card').hide();

                        if (showNotification) {
                            showToast('Tidak ada data tracking terkirim pada rentang tanggal tersebut', 'info');
                        }
                        return;
                    }

                    // Tampilkan Stats
                    $('#stats-bar').css('display', 'grid');
                    $('#result-card').show();

                    $('#stat-count').text(count + ' Kontainer');
                    $('#stat-tpp').text((summary.tpp || 0) + ' Box');
                    $('#stat-gudang').text((summary.gudang || 0) + ' Box');
                    $('#tab-count').text(count);
                    $('#stat-service').text('kirim-tps-tracking');
                    $('#stat-range').text(`${tglAwal} s/d ${tglAkhir}`);

                    // Render DataTables
                    renderReportTable(cachedRows);

                    // Render JSON Box
                    $('#json-viewer').val(JSON.stringify(rawApiResponse, null, 4));

                    $('#auto-sync-status').html('<span class="pulse-dot"></span> <span style="color:#10b981;">Terkonfirmasi (' + count + ' Kontainer Terkirim)</span>');

                    if (showNotification) {
                        showToast(`Ditemukan ${count} data pengiriman tracking di CEISA 4.0!`, 'success');
                    }
                },
                error: function(xhr, status, error) {
                    if (status === 'abort') return;
                    console.error('AJAX Error:', error);
                    showToast('Gagal terhubung ke API Tracking: ' + error, 'error');
                    $('#auto-sync-status').html('❌ <span style="color:#ef4444;">Error koneksi API</span>');
                }
            });
        }

        function renderReportTable(rows) {
            if ($.fn.DataTable.isDataTable('#table-report')) {
                $('#table-report').DataTable().destroy();
                dataTableInstance = null;
            }

            const tbody = document.getElementById('table-body');
            tbody.innerHTML = '';

            rows.forEach((r, idx) => {
                const isGudang = (r.dept === 'GUDANG' || r.kode_gudang === 'GPSU');
                const deptBadge = isGudang
                    ? `<span class="badge-pill" style="background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.3); font-weight:700; font-size:11px;">🏬 GUDANG (GPSU)</span>`
                    : `<span class="badge-pill" style="background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.3); font-weight:700; font-size:11px;">🏢 TPP (CPSU)</span>`;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="text-align:center;">${idx + 1}</td>
                    <td>
                        <span class="ref-badge">
                            <span>📦</span>
                            <span>${r.no_cont}</span>
                            <button type="button" class="btn-copy-ref" onclick="copyText('${r.no_cont}')" title="Salin No Kontainer">📋</button>
                        </span>
                    </td>
                    <td>${deptBadge}</td>
                    <td><span class="badge-pill" style="background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.3); font-size:11px;">${r.status_tracking || '-'}</span></td>
                    <td><span style="font-family:'JetBrains Mono',monospace; font-size:0.85rem;">${r.waktu_status || '-'}</span></td>
                    <td>
                        <div>${r.no_bl_awb || '-'}</div>
                        ${r.tgl_bl_awb && r.tgl_bl_awb !== '-' ? `<small style="color:var(--text-secondary); font-size:11px;">${r.tgl_bl_awb}</small>` : ''}
                    </td>
                    <td>
                        <div>📍 ${r.yard_pos || '-'}</div>
                        ${r.nopol && r.nopol !== '-' ? `<small style="color:var(--text-secondary); font-size:11px;">🚛 ${r.nopol}</small>` : ''}
                    </td>
                    <td><span class="badge-pill badge-in">✅ TERKIRIM DI CEISA 4.0</span></td>
                    <td style="text-align:center; white-space:nowrap;">
                        <div class="btn-action-group">
                            <button type="button" class="btn-table-detail" onclick="openDetailModal(${r.id})" title="Lihat rincian lengkap data kontainer yang terkirim">
                                <span>🔍</span>
                                <span>Lihat Data</span>
                            </button>
                            <button type="button" class="btn-table-copy" onclick="copyText('${r.no_cont}')" title="Salin Nomor Kontainer">
                                <span>📋</span>
                                <span>Salin</span>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            dataTableInstance = $('#table-report').DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
                language: {
                    search: "Cari:",
                    searchPlaceholder: "Nomor kontainer / B/L / nopol...",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data tracking",
                    infoEmpty: "Tidak ada data pengiriman",
                    infoFiltered: "(difilter dari _MAX_ total data)",
                    zeroRecords: "Tidak ada data yang cocok dengan pencarian",
                    paginate: {
                        first: "«",
                        previous: "‹",
                        next: "›",
                        last: "»"
                    }
                },
                order: [[0, 'asc']],
                dom: '<"dt-top-controls"lf>rt<"dt-bottom-controls"ip>'
            });
        }

        // ===== MODAL DETAIL RINCIAN KONTAINER =====
        function openDetailModal(trackingId) {
            $('#modal-detail-cont').css('display', 'flex');
            $('#modal-loading').show();
            $('#modal-content').hide();
            switchModalTab('table');

            $.ajax({
                url: 'api/tps_tracking.php',
                type: 'GET',
                data: {
                    action: 'detail',
                    id: trackingId
                },
                dataType: 'json',
                success: function(res) {
                    $('#modal-loading').hide();
                    $('#modal-content').show();

                    if (!res.success) {
                        showToast(res.message || 'Gagal memuat detail', 'error');
                        return;
                    }

                    const data = res.data || {};
                    const payload = res.payload || {};
                    const response = res.response || {};

                    $('#modal-ref-number').text(data.no_cont || payload.nomorKontainer || '-');
                    $('#modal-bc11').text(payload.nomorDokumen ? (payload.kodeDokumen || '20') + ' / ' + payload.nomorDokumen + (payload.tanggalDokumen ? ' (' + payload.tanggalDokumen + ')' : '') : '-');

                    const kg = (payload.kodeGudang || (data.keterangan && data.keterangan.includes('[GUDANG]') ? 'GPSU' : 'CPSU')).toUpperCase();
                    const deptDesc = (kg === 'GPSU') ? '🏬 Gudang CFS (GPSU)' : '🏢 TPP Lapangan (CPSU)';
                    $('#modal-gudang').html(`<b>${deptDesc}</b> <small style="color:var(--text-secondary);">/ ${payload.kodeTps || 'PSU0'}</small>`);
                    $('#modal-waktu-kegiatan').text(payload.waktuKegiatan || data.waktu_status || '-');
                    $('#modal-waktu-kirim').text(response.waktuRekam || data.created_at || '-');

                    // Render Tabel Kontainer di Modal
                    const tbody = document.getElementById('modal-table-body');
                    tbody.innerHTML = '';

                    const isKosong = (payload.jenisKontainer == '4');
                    const jnsLabel = (payload.jenisKontainer == '4') ? 'EMPTY' : ((payload.jenisKontainer == '7') ? 'LCL' : 'FCL');
                    const loc = [];
                    if (payload.block) loc.push(payload.block);
                    if (payload.slot) loc.push('S:' + payload.slot);
                    if (payload.tier) loc.push('T:' + payload.tier);
                    const yard = loc.length > 0 ? loc.join(' ') : '-';

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="text-align:center;">1</td>
                        <td><strong style="color:var(--text-primary); font-family:'JetBrains Mono',monospace;">${data.no_cont || payload.nomorKontainer}</strong></td>
                        <td>${payload.ukuranKontainer || '40'} ft <span class="badge-pill" style="font-size:10px; margin-left:4px;">${jnsLabel}</span></td>
                        <td><span class="badge-pill ${isKosong ? 'badge-out' : 'badge-in'}" style="font-size:10px;">${isKosong ? 'Empty' : (payload.jenisKontainer == '7' ? 'LCL' : 'Full')}</span></td>
                        <td>
                            <div>${payload.nomorBlAwb || data.no_bl_awb || '-'}</div>
                            <small style="color:var(--text-secondary); font-size:11px;">${payload.tanggalBlAwb || data.tgl_bl_awb || '-'}</small>
                        </td>
                        <td><span class="badge-pill" style="font-size:10px; background:rgba(16,185,129,0.15); color:#10b981;">${data.status_tracking || 'Kegiatan ' + (payload.kodeKegiatan || 5)}</span></td>
                        <td><span style="font-family:'JetBrains Mono',monospace; font-size:11px;">${yard}</span></td>
                        <td>
                            <div>${payload.nomorPolisi || '-'}</div>
                            <small style="color:var(--text-secondary); font-size:11px;">STID: ${payload.stid || '-'}</small>
                        </td>
                        <td><code style="font-size:11.5px; color:#10b981; font-weight:700;">#${response.id || data.id}</code></td>
                    `;
                    tbody.appendChild(tr);

                    // Tampilkan Raw Log & Payload
                    $('#modal-raw-viewer').text(JSON.stringify({
                        idTracking: data.id,
                        nomorKontainer: data.no_cont,
                        payloadSent: payload,
                        ceisaResponse: response
                    }, null, 4));
                },
                error: function(xhr, status, error) {
                    $('#modal-loading').hide();
                    showToast('Gagal terhubung untuk mengambil rincian: ' + error, 'error');
                }
            });
        }

        function closeDetailModal() {
            $('#modal-detail-cont').hide();
        }

        function handleModalOverlayClick(e) {
            if (e.target.id === 'modal-detail-cont') {
                closeDetailModal();
            }
        }

        function switchModalTab(tab) {
            if (tab === 'table') {
                $('#btn-subtab-table').addClass('active');
                $('#btn-subtab-raw').removeClass('active');
                $('#modal-subtab-table').show();
                $('#modal-subtab-raw').hide();
            } else {
                $('#btn-subtab-table').removeClass('active');
                $('#btn-subtab-raw').addClass('active');
                $('#modal-subtab-table').hide();
                $('#modal-subtab-raw').show();
            }
        }

        function copyModalRaw() {
            const raw = $('#modal-raw-viewer').text();
            copyText(raw, 'Raw JSON berhasil disalin!');
        }

        function copyText(text, successMsg = 'Teks berhasil disalin ke clipboard!') {
            navigator.clipboard.writeText(text).then(() => {
                showToast(successMsg, 'success');
            }).catch(() => {
                showToast('Gagal menyalin teks', 'error');
            });
        }

        function copyJson() {
            const val = $('#json-viewer').val();
            if (!val) return;
            copyText(val, 'Respon JSON berhasil disalin!');
        }

        function downloadJson() {
            const val = $('#json-viewer').val();
            if (!val) return;
            const tgl = new Date().toISOString().slice(0, 10);
            const blob = new Blob([val], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `CEISA4_Laporan_TPS_Tracking_${tgl}.json`;
            a.click();
            URL.revokeObjectURL(url);
            showToast('JSON berhasil diunduh!', 'success');
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
                    showToast('Token JWT berhasil diperbarui!', 'success');
                    loadReportData(true);
                } else {
                    showToast(data.message || 'Gagal memperbarui token', 'error');
                }
            } catch (e) {
                showToast('Gagal terhubung ke server auth', 'error');
            }
        }

        // Theme Toggle
        document.getElementById('theme-toggle').addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('ceisa_theme', newTheme);
            this.querySelector('.theme-toggle-icon').textContent = newTheme === 'dark' ? '🌙' : '☀️';
            this.querySelector('.theme-toggle-text').textContent = newTheme === 'dark' ? 'Dark' : 'Light';
        });

        // Mobile Menu Toggle
        document.getElementById('menu-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('open');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        });

        document.querySelector('.sidebar-overlay')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.remove('open');
            this.classList.remove('active');
        });

        // jQuery Auto-Sync Listeners
        $(document).ready(function() {
            // Otomatis proses saat berpindah tanggal awal atau akhir
            $('#tgl-awal, #tgl-akhir').on('change', function() {
                loadReportData(true);
            });

            // Otomatis proses saat halaman pertama kali dibuka
            loadReportData(false);
        });
    </script>
</body>
</html>
