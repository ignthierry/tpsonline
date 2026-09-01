<?php
/**
 * Laporan Data Terkirim Coarri Codeco Container (CoCoCont) CEISA 4.0
 * Endpoint: /cek-data-terkirim
 * Terintegrasi dengan jQuery DataTables, Auto-Sync AJAX, Modal Detail Rincian Kontainer, dan Desain Modern Enterprise
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';
$config = require __DIR__ . '/config.php';
$endpoints = getEndpointDefinitions();

$username = $_SESSION['name'] ?? $_SESSION['username'] ?? $config['username'] ?? 'User';
$userInitial = strtoupper(substr($username, 0, 2));
$todayDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Coarri Codeco (CoCoCont) CEISA 4.0 — <?= e($config['app_name']) ?></title>
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
                        <span>Laporan Coarri Codeco</span>
                        <span class="separator">/</span>
                        <span class="current">Container (CoCoCont)</span>
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
                                    📊 Laporan Coarri Codeco Container (CoCoCont)
                                </h2>
                                <p style="margin:4px 0 0; color:var(--text-secondary); font-size:0.88rem;">
                                    Monitoring & verifikasi data pergerakan kontainer (Gate-In / Gate-Out) yang telah resmi tersimpan di server Bea Cukai CEISA 4.0.
                                </p>
                            </div>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <a href="cococont.php" class="btn-action-sm" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                    <span>📦</span> Menu CoCoCont
                                </a>
                                <a href="report_kms.php" class="btn-action-sm" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px; color:#c4b5fd; border-color:rgba(139,92,246,0.3); background:rgba(139,92,246,0.1);">
                                    <span>📊</span> Laporan Kemasan
                                </a>
                                <span class="badge-pill badge-ceisa">coarri-codeco-container</span>
                            </div>
                        </div>

                        <form id="filter-form" onsubmit="event.preventDefault(); loadReportData(true);">
                            <div class="filter-grid">
                                <div class="input-group">
                                    <label for="tgl-awal">Tanggal Awal</label>
                                    <input type="date" id="tgl-awal" class="input-control" value="<?= $todayDate ?>" required>
                                </div>

                                <div class="input-group">
                                    <label for="tgl-akhir">Tanggal Akhir</label>
                                    <input type="date" id="tgl-akhir" class="input-control" value="<?= $todayDate ?>" required>
                                </div>

                                <div style="display:flex; align-items:center; height:42px; margin-bottom:2px;">
                                    <div id="auto-sync-status" style="display:inline-flex; align-items:center; gap:8px; font-size:0.85rem; font-weight:600; color:#10b981; padding:8px 16px; border-radius:20px; background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.25);">
                                        <span class="pulse-dot"></span> <span id="auto-sync-text">Auto-Sync AJAX Aktif</span>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Stats Row -->
                    <div class="stats-bar" id="stats-bar" style="display:none;">
                        <div class="stat-item">
                            <span class="stat-label">Total Referensi Terkirim</span>
                            <span class="stat-value" id="stat-count" style="color:var(--accent-blue);">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Layanan / Service</span>
                            <span class="stat-value" id="stat-service" style="font-size:1.15rem; color:var(--accent-purple);">coarri-codeco-container</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Rentang Tanggal</span>
                            <span class="stat-value" id="stat-range" style="font-size:1.1rem; color:var(--accent-amber);">-</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Verifikasi Bea Cukai</span>
                            <span class="stat-value" style="font-size:1.1rem; color:#10b981; display:flex; align-items:center; gap:6px;">
                                <span>✅</span> Terdaftar di Gateway
                            </span>
                        </div>
                    </div>

                    <!-- Result Panel -->
                    <div class="report-card" id="result-card" style="display:none;">
                        <!-- Tabs -->
                        <div class="tabs-nav">
                            <button class="tab-btn active" onclick="switchTab('tab-table', this)">
                                <span>📋</span> Data Terkirim CoCoCont (<span id="tab-count">0</span>)
                            </button>
                            <button class="tab-btn" onclick="switchTab('tab-json', this)">
                                <span>📦</span> Respon JSON CEISA 4.0
                            </button>
                        </div>

                        <!-- Tab 1: Table -->
                        <div class="tab-content active" id="tab-table">
                            <div style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <div style="font-size:0.88rem; color:var(--text-secondary);">
                                    <span>Tabel Interaktif Pengiriman Coarri Codeco Container (Sorting, Filter & Pagination DataTables aktif)</span>
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
                                            <th>Reference Number</th>
                                            <th>Layanan / Dokumen</th>
                                            <th>Rentang Tanggal</th>
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
                                    Raw JSON Response dari Gateway CEISA 4.0 Bea Cukai:
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
                        <span>Ref Number:</span>
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
                    <p style="margin-top:12px; color:var(--text-secondary); font-size:0.9rem;">Memuat rincian data kontainer dari database & log CEISA...</p>
                </div>

                <!-- Main Content (when loaded) -->
                <div id="modal-content" style="display:none;">
                    <!-- Grid Header Details -->
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:20px; background:var(--bg-surface); padding:16px; border-radius:10px; border:1px solid var(--border-subtle);">
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-secondary); font-weight:600; text-transform:uppercase;">No & Tgl BC 1.1</div>
                            <div id="modal-bc11" style="font-size:0.92rem; font-weight:600; color:var(--text-primary); margin-top:2px;">-</div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-secondary); font-weight:600; text-transform:uppercase;">Sarana Pengangkut</div>
                            <div id="modal-sarana" style="font-size:0.92rem; font-weight:600; color:var(--text-primary); margin-top:2px;">-</div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-secondary); font-weight:600; text-transform:uppercase;">Gudang / TPS</div>
                            <div id="modal-gudang" style="font-size:0.92rem; font-weight:600; color:var(--text-primary); margin-top:2px;">-</div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-secondary); font-weight:600; text-transform:uppercase;">Waktu Pengiriman</div>
                            <div id="modal-waktu-kirim" style="font-size:0.92rem; font-weight:600; color:var(--text-primary); margin-top:2px;">-</div>
                        </div>
                    </div>

                    <!-- Subtabs -->
                    <div style="display:flex; border-bottom:1px solid var(--border-medium); margin-bottom:16px; gap:8px;">
                        <button type="button" class="btn-subtab active" id="btn-subtab-table" onclick="switchModalTab('table')">
                            📦 Daftar Kontainer (<span id="modal-cont-count">0</span>)
                        </button>
                        <button type="button" class="btn-subtab" id="btn-subtab-raw" onclick="switchModalTab('raw')">
                            ⚡ Respon & Payload Gateway
                        </button>
                    </div>

                    <!-- Subtab 1: Table -->
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
                                        <th>Pos BC 1.1</th>
                                        <th>Consignee / Pemilik</th>
                                        <th>No Polisi & Gate</th>
                                        <th>No Segel</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-table-body">
                                    <!-- Dynamic rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Subtab 2: Raw JSON -->
                    <div id="modal-subtab-raw" style="display:none;">
                        <div style="margin-bottom:8px; font-size:0.82rem; color:var(--text-secondary);">
                            Payload Pengiriman dan Log Respon Resmi CEISA 4.0:
                        </div>
                        <pre id="modal-raw-viewer" style="background:#0d131f; color:#a5f3fc; padding:16px; border-radius:8px; font-family:'JetBrains Mono',monospace; font-size:12px; max-height:360px; overflow:auto;"></pre>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div style="padding:14px 24px; border-top:1px solid var(--border-medium); display:flex; justify-content:space-between; align-items:center; background:var(--bg-surface);">
                <button type="button" class="btn-action-sm" onclick="copyText($('#modal-ref-number').text())">
                    <span>📋</span> Salin Ref Number
                </button>
                <button type="button" class="btn-action-sm" style="background:var(--bg-base); color:var(--text-primary); border:1px solid var(--border-medium);" onclick="closeDetailModal()">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toast-container" style="position:fixed; bottom:24px; right:24px; z-index:999999; display:flex; flex-direction:column; gap:10px;"></div>

    <script>
        let rawApiResponse = null;
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

            if (!tglAwal || !tglAkhir) return;

            // Batalkan request sebelumnya jika user mengganti tanggal dengan cepat
            if (activeAjaxRequest && activeAjaxRequest.readyState !== 4) {
                activeAjaxRequest.abort();
            }

            $('#auto-sync-status').html('<span class="pulse-dot" style="background:#3b82f6;"></span> <span style="color:#60a5fa;">Memeriksa server Bea Cukai...</span>');

            activeAjaxRequest = $.ajax({
                url: 'api/report.php',
                type: 'GET',
                data: {
                    action: 'cek_terkirim',
                    category: 'container',
                    tanggalAwal: tglAwal,
                    tanggalAkhir: tglAkhir
                },
                dataType: 'json',
                success: function(result) {
                    rawApiResponse = result.raw || result;

                    if (!result.success && result.count === 0) {
                        $('#auto-sync-status').html('ℹ️ <span style="color:var(--text-secondary);">Tidak ada data</span>');
                        if (dataTableInstance) {
                            dataTableInstance.destroy();
                            dataTableInstance = null;
                        }
                        $('#table-body').empty();
                        $('#stats-bar').hide();
                        $('#result-card').hide();

                        if (showNotification) {
                            showToast(result.message || 'Tidak ada data terkirim pada rentang tanggal tersebut', 'info');
                        }
                        return;
                    }

                    if (result.count === 0) {
                        $('#auto-sync-status').html('ℹ️ <span style="color:var(--text-secondary);">Tidak ada data</span>');
                        if (dataTableInstance) {
                            dataTableInstance.destroy();
                            dataTableInstance = null;
                        }
                        $('#table-body').empty();
                        $('#stats-bar').hide();
                        $('#result-card').hide();

                        if (showNotification) {
                            showToast(`Tidak ada referensi terkirim pada ${result.tglAwal} s/d ${result.tglAkhir}`, 'info');
                        }
                        return;
                    }

                    // Tampilkan Stats
                    $('#stats-bar').css('display', 'grid');
                    $('#result-card').show();

                    $('#stat-count').text(result.count + ' Ref Batch');
                    $('#tab-count').text(result.count);
                    $('#stat-service').text(result.services.join(', ') || 'coarri-codeco-container');
                    $('#stat-range').text(`${result.tglAwal} s/d ${result.tglAkhir}`);

                    // Render DataTables
                    renderReportTable(result.rows);

                    // Render JSON Box
                    $('#json-viewer').val(JSON.stringify(rawApiResponse, null, 4));

                    $('#auto-sync-status').html('<span class="pulse-dot"></span> <span style="color:#10b981;">Terkonfirmasi (' + result.count + ' Ref Terkirim)</span>');

                    if (showNotification) {
                        showToast(`Ditemukan ${result.count} referensi pengiriman resmi di CEISA 4.0!`, 'success');
                    }
                },
                error: function(xhr, status, error) {
                    if (status === 'abort') return;
                    console.error('AJAX Error:', error);
                    showToast('Gagal terhubung ke API Gateway: ' + error, 'error');
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
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="text-align:center;">${idx + 1}</td>
                    <td>
                        <span class="ref-badge">
                            <span>📄</span>
                            <span>${r.referenceNumber}</span>
                            <button type="button" class="btn-copy-ref" onclick="copyText('${r.referenceNumber}')" title="Salin Reference Number">📋</button>
                        </span>
                    </td>
                    <td><span class="badge-pill" style="background:rgba(139,92,246,0.12); color:#c4b5fd; border:1px solid rgba(139,92,246,0.3);">${r.serviceLabel || r.service}</span></td>
                    <td><span style="font-family:'JetBrains Mono',monospace; font-size:0.85rem;">${r.tglAwal} s/d ${r.tglAkhir}</span></td>
                    <td><span class="badge-pill badge-in">✅ ${r.status}</span></td>
                    <td style="text-align:center; white-space:nowrap;">
                        <div class="btn-action-group">
                            <button type="button" class="btn-table-detail" onclick="openDetailModal('${r.referenceNumber}')" title="Lihat rincian lengkap data kontainer yang terkirim">
                                <span>🔍</span>
                                <span>Lihat Data</span>
                            </button>
                            <button type="button" class="btn-table-copy" onclick="copyText('${r.referenceNumber}')" title="Salin Nomor Referensi">
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
                    searchPlaceholder: "Nomor referensi / dokumen...",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data referensi",
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
        function openDetailModal(refNumber) {
            $('#modal-detail-cont').css('display', 'flex');
            $('#modal-ref-number').text(refNumber);
            $('#modal-loading').show();
            $('#modal-content').hide();
            switchModalTab('table');

            $.ajax({
                url: 'api/report.php',
                type: 'GET',
                data: {
                    action: 'detail_cont_ref',
                    refNumber: refNumber
                },
                dataType: 'json',
                success: function(res) {
                    $('#modal-loading').hide();
                    $('#modal-content').show();

                    if (!res.success) {
                        showToast(res.message || 'Gagal memuat detail', 'error');
                        return;
                    }

                    const header = res.header || {};
                    const log = res.log || {};
                    const containers = res.containers || [];

                    $('#modal-bc11').text((header.noBc11 || '-') + (header.tanggalBc11 ? ' (' + header.tanggalBc11 + ')' : ''));
                    $('#modal-sarana').text((header.namaAngkut || '-') + (header.nomorVoyFlight ? ' (' + header.nomorVoyFlight + ')' : ''));
                    $('#modal-gudang').text((header.kodeGudang || 'CPSU') + ' / ' + (header.kodeTps || 'PSU0'));
                    $('#modal-waktu-kirim').text(log.created_at || '-');
                    $('#modal-cont-count').text(containers.length);

                    // Render Tabel Kontainer di Modal
                    const tbody = document.getElementById('modal-table-body');
                    tbody.innerHTML = '';

                    if (containers.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:24px; color:var(--text-secondary);">Tidak ada rincian kontainer yang tersimpan untuk referensi ini di database lokal.</td></tr>';
                    } else {
                        containers.forEach((c, i) => {
                            const tr = document.createElement('tr');
                            const isKosong = (c.jenisMuat && c.jenisMuat.toLowerCase().includes('empty'));
                            tr.innerHTML = `
                                <td style="text-align:center;">${i + 1}</td>
                                <td><strong style="color:var(--text-primary); font-family:'JetBrains Mono',monospace;">${c.noCont}</strong></td>
                                <td>${c.ukuran} <span class="badge-pill" style="font-size:10px; margin-left:4px;">${c.jenisCont}</span></td>
                                <td><span class="badge-pill ${isKosong ? 'badge-out' : 'badge-in'}" style="font-size:10px;">${c.jenisMuat}</span></td>
                                <td>
                                    <div>${c.noBlAwb}</div>
                                    <small style="color:var(--text-secondary); font-size:11px;">${c.tanggalBlAwb}</small>
                                </td>
                                <td><code style="font-size:11.5px; color:#38bdf8;">${c.nomorPosBc11}</code></td>
                                <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${c.consignee}">${c.consignee}</td>
                                <td>
                                    <div>${c.nomorPolisi || '-'}</div>
                                    <small style="color:var(--text-secondary); font-size:11px;">${c.waktuInOut || '-'}</small>
                                </td>
                                <td><span style="font-family:'JetBrains Mono',monospace; font-size:11px;">${c.noSegel}</span></td>
                            `;
                            tbody.appendChild(tr);
                        });
                    }

                    // Tampilkan Raw Log & Payload
                    $('#modal-raw-viewer').text(JSON.stringify({
                        referenceNumber: refNumber,
                        header: header,
                        log: log,
                        containers: containers
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
                $('#btn-subtab-raw').addClass('active');
                $('#btn-subtab-table').removeClass('active');
                $('#modal-subtab-table').hide();
                $('#modal-subtab-raw').show();
            }
        }

        function copyText(txt) {
            navigator.clipboard.writeText(txt).then(() => {
                showToast(`Reference number ${txt} disalin ke clipboard!`, 'success');
            }).catch(e => {
                showToast('Gagal menyalin: ' + e, 'error');
            });
        }

        function copyJson() {
            const viewer = document.getElementById('json-viewer');
            viewer.select();
            navigator.clipboard.writeText(viewer.value).then(() => {
                showToast('Respon JSON berhasil disalin!', 'success');
            }).catch(e => {
                showToast('Gagal menyalin: ' + e, 'error');
            });
        }

        function downloadJson() {
            const viewer = document.getElementById('json-viewer');
            const tgl = $('#tgl-awal').val();
            const blob = new Blob([viewer.value], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `CEISA4_Laporan_CoCoCont_${tgl}.json`;
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
