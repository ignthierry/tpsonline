<?php
/**
 * Coarri Codeco (CoCoCont) CEISA 4.0 — TPS Online Dashboard
 * Halaman penarikan data Gate-In & Gate-Out dari database TPP, pembentukan JSON CEISA 4.0, dan pengiriman ke REST API Bea Cukai.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

requireAuth();

$config = require __DIR__ . '/config.php';
$endpoints = getEndpointDefinitions();
$username = $_SESSION['name'] ?? $_SESSION['username'] ?? $config['username'] ?? 'User';
$loginTime = $_SESSION['login_time'] ?? time();
$userInitial = strtoupper(substr($username, 0, 2));

$defaultEndpoint = 'coarri-codeco-container';
$todayDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coarri Codeco (CoCoCont) CEISA 4.0 — <?= e($config['app_name']) ?></title>
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
            max-width: 1400px;
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
        .filter-grid {
            display: grid;
            grid-template-columns: auto 1fr 1fr auto;
            gap: 16px;
            align-items: flex-end;
        }
        @media (max-width: 992px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
        .type-toggle-group {
            display: flex;
            background: var(--bg-input);
            padding: 4px;
            border-radius: 8px;
            border: 1px solid var(--border-subtle);
            gap: 4px;
        }
        .type-btn {
            padding: 9px 18px;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .type-btn.active {
            background: var(--accent-blue);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4);
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
        .btn-fetch {
            padding: 10px 24px;
            background: var(--accent-blue-gradient);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.15s, opacity 0.15s;
            height: 44px;
            white-space: nowrap;
        }
        .btn-fetch:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }
        .btn-fetch:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .btn-generate-json {
            padding: 10px 22px;
            background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            height: 44px;
            white-space: nowrap;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.35);
        }
        .btn-generate-json:hover {
            opacity: 0.92;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(139, 92, 246, 0.45);
        }
        .btn-generate-json:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            height: 480px;
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
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        .data-table th {
            background: var(--bg-table-th);
            color: var(--text-secondary);
            font-weight: 600;
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border-subtle);
            white-space: nowrap;
        }
        .data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-subtle);
            color: var(--text-primary);
        }
        .data-table tr:hover td {
            background: var(--bg-table-hover);
        }
        .badge-pill {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-in { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-out { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
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
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transition: transform 0.15s;
        }
        .btn-send-prod:hover {
            transform: translateY(-1px);
        }
        .header-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        .header-tag {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .header-tag strong {
            color: var(--text-primary);
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
                        <span>Coarri Codeco</span>
                        <span class="separator">/</span>
                        <span class="current" id="breadcrumb-title">Container In & Out</span>
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
                    
                    <!-- Form Filter Panel -->
                    <div class="coco-card">
                        <div style="margin-bottom: 18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                            <div>
                                <h2 style="margin:0; font-size:1.25rem; color:var(--text-primary); font-weight:700;">
                                    📦 Coarri Codeco Container (CoCoCont)
                                </h2>
                                <p style="margin:4px 0 0; color:var(--text-secondary); font-size:0.88rem;">
                                    Tarik data Gate-In / Gate-Out dari database operasional TPP & bentuk payload sesuai standar REST API CEISA 4.0.
                                </p>
                            </div>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <a href="report_cont.php" class="btn-action-sm" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                    <span>📊</span> Laporan CoCoCont
                                </a>
                                <span class="badge-pill badge-ceisa">POST /coarri-codeco-container</span>
                            </div>
                        </div>

                        <form id="filter-form" onsubmit="event.preventDefault(); fetchData();">
                            <div class="filter-grid">
                                <div>
                                    <label style="display:block; font-size:0.82rem; font-weight:600; color:var(--text-secondary); margin-bottom:6px; text-transform:uppercase;">Tipe Pergerakan</label>
                                    <div class="type-toggle-group">
                                        <button type="button" class="type-btn active" id="btn-type-in" onclick="setType('In')">
                                            <span>📥</span> Gate-In (Masuk)
                                        </button>
                                        <button type="button" class="type-btn" id="btn-type-out" onclick="setType('Out')">
                                            <span>📤</span> Gate-Out (Keluar)
                                        </button>
                                    </div>
                                    <input type="hidden" id="type-input" value="In">
                                </div>

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
                            <span class="stat-label">Total Kontainer</span>
                            <span class="stat-value" id="stat-count" style="color:var(--accent-blue);">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Berat Bruto</span>
                            <span class="stat-value" id="stat-bruto" style="color:var(--accent-green);">0 KG</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Grup Header (BC 1.1)</span>
                            <span class="stat-value" id="stat-groups" style="color:var(--accent-amber);">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Status Struktur</span>
                            <span class="stat-value" style="font-size:1.1rem; color:#10b981; display:flex; align-items:center; gap:6px;">
                                <span>✅</span> Siap Kirim
                            </span>
                        </div>
                    </div>

                    <!-- Result Panel -->
                    <div class="coco-card" id="result-card" style="display:none;">
                        <!-- Tabs -->
                        <div class="tabs-nav">
                            <button class="tab-btn active" onclick="switchTab('tab-table', this)">
                                <span>📋</span> Pratinjau Tabel Kontainer (<span id="tab-count">0</span>)
                            </button>
                            <button class="tab-btn" onclick="switchTab('tab-json', this)">
                                <span>📦</span> JSON Payload CEISA 4.0
                            </button>
                        </div>

                        <!-- Tab 1: Table -->
                        <div class="tab-content active" id="tab-table">
                            <div style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <div style="font-size:0.88rem; color:var(--text-secondary);">
                                    <span>Tabel Interaktif Kontainer (Sorting, Filter & Pagination DataTables aktif)</span>
                                </div>
                                <button type="button" class="btn-action-sm" style="color:#c4b5fd; border-color:rgba(139,92,246,0.4); background:rgba(139,92,246,0.12);" onclick="switchTab('tab-json', document.querySelectorAll('.tab-btn')[1])">
                                    <span>⚡</span> Buka JSON Payload
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table display responsive nowrap" id="table-kontainer" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th style="width:50px; text-align:center;">No</th>
                                            <th>No. Kontainer</th>
                                            <th>Ukuran</th>
                                            <th>Jenis</th>
                                            <th>Status</th>
                                            <th>No. Polisi</th>
                                            <th>Dokumen In/Out</th>
                                            <th>No. BC 1.1</th>
                                            <th>Consignee</th>
                                            <th>Waktu In/Out</th>
                                        </tr>
                                    </thead>
                                    <tbody id="table-body">
                                        <!-- Rendered via DataTables -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab 2: JSON Payload -->
                        <div class="tab-content" id="tab-json">
                            <!-- Header Info Preview -->
                            <div class="header-tags" id="header-tags-container">
                                <!-- Rendered via JS -->
                            </div>

                            <textarea id="json-viewer" class="json-box" spellcheck="false"></textarea>

                            <div class="action-row">
                                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                    <button type="button" class="btn-action-sm" style="background:rgba(139,92,246,0.15); color:#c4b5fd; border-color:rgba(139,92,246,0.4);" onclick="fetchData('json')">
                                        <span>⚡</span> Re-generate JSON
                                    </button>
                                    <button type="button" class="btn-action-sm" onclick="copyJson()">
                                        <span>📋</span> Salin JSON
                                    </button>
                                    <button type="button" class="btn-action-sm" onclick="downloadJson()">
                                        <span>💾</span> Unduh .json
                                    </button>
                                    <button type="button" class="btn-action-sm" onclick="validateJsonStructure()">
                                        <span>🔍</span> Validasi Skema
                                    </button>
                                </div>
                                <span id="json-validity-badge" class="badge-pill badge-ceisa">Format Valid</span>
                            </div>
                        </div>

                        <!-- Multi-Batch Notice (Muncul jika ada kontainer multiple B/L) -->
                        <div id="batch-notice-card" style="display:none; margin-top:20px; background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.3); border-radius:10px; padding:14px 18px;">
                            <div style="display:flex; align-items:flex-start; gap:12px;">
                                <span style="font-size:1.3rem;">⚠️</span>
                                <div style="flex-grow:1;">
                                    <div style="font-weight:600; color:#f59e0b; font-size:0.92rem; margin-bottom:4px;">
                                        Deteksi Pengiriman Bertahap (<span id="batch-count-badge">0</span> Batch Diperlukan)
                                    </div>
                                    <div style="font-size:0.85rem; color:var(--text-secondary); line-height:1.5;">
                                        Terdeteksi nomor kontainer dengan multiple B/L / Pos BC 1.1 (<code id="batch-dup-containers" style="color:#f59e0b; background:rgba(245,158,11,0.15); padding:1px 6px; border-radius:4px;"></code>). 
                                        Untuk mencegah error <em>"Duplikat Kontainer"</em> di CEISA 4.0 dan memastikan <strong>seluruh data terkirim 100% tanpa ada yang dikurangi</strong>, sistem membagi pengiriman menjadi <strong id="batch-count-text">2</strong> tahap secara otomatis.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Target Endpoint & Send Action Row -->
                        <div class="action-row" style="background:var(--bg-surface); padding:16px 20px; border-radius:10px; margin-top:20px;">
                            <div style="flex-grow:1; max-width:650px;">
                                <label style="font-size:0.8rem; font-weight:600; color:var(--text-secondary); display:block; margin-bottom:4px;">
                                    TARGET ENDPOINT CEISA 4.0 (OPENAPI):
                                </label>
                                <input type="text" id="target-endpoint" class="input-control" value="<?= e($defaultEndpoint) ?>" style="font-family:'JetBrains Mono',monospace; font-size:0.88rem;">
                            </div>
                            <div>
                                <button type="button" class="btn-send-prod" id="btn-send" onclick="sendToCeisa()">
                                    <span id="send-spinner" style="display:none;">⏳</span>
                                    <span id="send-icon">🚀</span>
                                    <span id="btn-send-text">Kirim ke CEISA 4.0</span>
                                </button>
                            </div>
                        </div>

                        <!-- Card Respon Pengiriman (Muncul jika user telah menekan Kirim) -->
                        <div id="send-result-card" style="display:none; margin-top:20px; background:var(--bg-surface); border:1px solid var(--border-medium); border-radius:10px; padding:20px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span id="send-status-badge" class="badge-pill"></span>
                                    <span id="send-timestamp" style="font-size:0.85rem; color:var(--text-secondary);"></span>
                                </div>
                                <div style="display:flex; gap:10px;">
                                    <a href="report_cont.php" class="btn-action-sm" style="background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.3); text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                        <span>📊</span> Buka Laporan Coarri Codeco
                                    </a>
                                    <button type="button" class="btn-action-sm" onclick="$('#send-raw-response').slideToggle(200)">
                                        <span>📋</span> Toggle Raw Response
                                    </button>
                                </div>
                            </div>
                            <div id="send-result-msg" style="font-size:0.92rem; color:var(--text-primary); margin-bottom:12px;"></div>
                            <pre id="send-raw-response" style="display:none; background:#0d131f; color:#a5f3fc; padding:16px; border-radius:8px; font-family:'JetBrains Mono',monospace; font-size:13px; max-height:300px; overflow:auto;"></pre>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toast-container" style="position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:10px;"></div>

    <script>
        let currentPayload = null;
        let currentBatches = [];
        let duplicateContainersList = [];
        let hasDuplicates = false;
        let tableRowsData = [];
        let loadedParams = {
            type: null,
            tglAwal: null,
            tglAkhir: null
        };

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

        let activeAjaxRequest = null;

        function setType(type) {
            const prevType = $('#type-input').val();
            $('#type-input').val(type);

            if (type === 'In') {
                $('#btn-type-in').addClass('active');
                $('#btn-type-out').removeClass('active');
            } else {
                $('#btn-type-out').addClass('active');
                $('#btn-type-in').removeClass('active');
            }

            // Otomatis proses data baru via AJAX saat tipe pergerakan berpindah
            if (prevType !== type) {
                autoFetchData('current', true);
            }
        }

        function switchTab(tabId, btn) {
            $('.tab-btn').removeClass('active');
            $('.tab-content').removeClass('active');
            if (btn) $(btn).addClass('active');
            $('#' + tabId).addClass('active');
        }

        function autoFetchData(targetTab = 'current', showNotification = true) {
            const type = $('#type-input').val();
            const tglAwal = $('#tgl-awal').val();
            const tglAkhir = $('#tgl-akhir').val();

            if (!tglAwal || !tglAkhir) return;

            // Batalkan request AJAX sebelumnya jika user cepat mengganti tanggal (prevent race-condition)
            if (activeAjaxRequest && activeAjaxRequest.readyState !== 4) {
                activeAjaxRequest.abort();
            }

            // Indikator UI loading
            $('#auto-sync-status').html('<span class="pulse-dot" style="background:#3b82f6;"></span> <span style="color:#60a5fa;">Memproses AJAX...</span>');

            activeAjaxRequest = $.ajax({
                url: 'api/cococont.php',
                type: 'GET',
                data: {
                    action: 'fetch',
                    type: type,
                    tglAwal: tglAwal,
                    tglAkhir: tglAkhir
                },
                dataType: 'json',
                success: function(result) {
                    if (!result.success) {
                        showToast(result.message || 'Gagal memproses data database', 'error');
                        $('#auto-sync-status').html('❌ <span style="color:#ef4444;">Gagal memuat</span>');
                        return;
                    }

                    loadedParams = { type, tglAwal, tglAkhir };

                    if (result.count === 0) {
                        currentPayload = null;
                        tableRowsData = [];
                        if ($.fn.DataTable.isDataTable('#table-kontainer')) {
                            $('#table-kontainer').DataTable().destroy();
                            dataTableInstance = null;
                        }
                        $('#table-body').empty();
                        $('#stats-bar').hide();
                        $('#result-card').hide();
                        $('#auto-sync-status').html('ℹ️ <span style="color:var(--text-secondary);">Tidak ada kontainer</span>');
                        if (showNotification) {
                            showToast(`Tidak ada kontainer ${type === 'In' ? 'Gate-In' : 'Gate-Out'} pada rentang tanggal tersebut`, 'info');
                        }
                        return;
                    }

                    // Sukses mengambil data
                    currentPayload = result.payload;
                    currentBatches = result.batches || [];
                    duplicateContainersList = result.duplicate_containers || [];
                    hasDuplicates = !!result.has_duplicates;
                    tableRowsData = result.table_data || [];

                    // Update Notice jika terdeteksi pengiriman multi-batch
                    if (hasDuplicates && currentBatches.length > 1) {
                        $('#batch-notice-card').slideDown(200);
                        $('#batch-count-badge').text(result.batches_count);
                        $('#batch-count-text').text(result.batches_count);
                        $('#batch-dup-containers').text(duplicateContainersList.join(', '));
                        $('#btn-send-text').text(`Kirim ke CEISA 4.0 (${result.batches_count} Batch)`);
                    } else {
                        $('#batch-notice-card').slideUp(200);
                        $('#btn-send-text').text('Kirim ke CEISA 4.0');
                    }

                    // Update UI statistik
                    $('#stats-bar').css('display', 'grid');
                    $('#result-card').show();

                    $('#stat-count').text(result.count);
                    $('#tab-count').text(result.count);
                    $('#stat-groups').text(result.groups_count);

                    const totalBruto = tableRowsData.reduce((sum, r) => sum + (r.bruto || 0), 0);
                    $('#stat-bruto').text(new Intl.NumberFormat('id-ID').format(Math.round(totalBruto)) + ' KG');

                    // Render Tabel Kontainer
                    renderTable(tableRowsData);

                    // Render Header Tags & Isi JSON Viewer secara otomatis
                    renderHeaderTags(result.payload.header);
                    $('#json-viewer').val(JSON.stringify(result.payload, null, 4));

                    $('#auto-sync-status').html('<span class="pulse-dot"></span> <span style="color:#10b981;">Tersinkron (' + result.count + ' kontainer' + (hasDuplicates ? ', ' + currentBatches.length + ' batch' : '') + ')</span>');

                    // Arahkan ke tab jika dipanggil secara spesifik
                    if (targetTab === 'json') {
                        const tabJsonBtn = $('.tab-btn').eq(1);
                        if (tabJsonBtn.length) switchTab('tab-json', tabJsonBtn[0]);
                        if (showNotification) showToast(`⚡ JSON CEISA 4.0 (${type === 'In' ? 'Gate-In' : 'Gate-Out'}, ${result.count} kontainer) siap!`, 'success');
                    } else if (targetTab === 'table') {
                        const tabTableBtn = $('.tab-btn').eq(0);
                        if (tabTableBtn.length) switchTab('tab-table', tabTableBtn[0]);
                        if (showNotification) showToast(`Berhasil memuat ${result.count} kontainer ${type === 'In' ? 'Gate-In' : 'Gate-Out'}!`, 'success');
                    } else if (showNotification) {
                        showToast(`Data ${type === 'In' ? 'Gate-In' : 'Gate-Out'} otomatis dimuat (${result.count} kontainer)`, 'info');
                    }
                },
                error: function(xhr, status, error) {
                    if (status === 'abort') return;
                    console.error('AJAX Error:', error);
                    showToast('Terjadi kesalahan jaringan atau server: ' + error, 'error');
                    $('#auto-sync-status').html('❌ <span style="color:#ef4444;">Error koneksi</span>');
                }
            });
        }

        // Aliases untuk fungsi manual & pintasan
        function fetchData(targetTab = 'table') {
            autoFetchData(targetTab, true);
        }

        function generateJson() {
            autoFetchData('json', true);
        }

        // jQuery Event Listeners Otomatis
        $(document).ready(function() {
            // 1. Ketika user berpindah tanggal awal atau tanggal akhir, otomatis proses via AJAX
            $('#tgl-awal, #tgl-akhir').on('change', function() {
                autoFetchData('current', true);
            });

            // 2. Otomatis proses dan tampilkan data saat halaman pertama kali dibuka
            autoFetchData('current', false);
        });

        let dataTableInstance = null;

        function renderTable(rows) {
            // Hancurkan DataTable lama jika ada sebelum mengisi ulang
            if ($.fn.DataTable.isDataTable('#table-kontainer')) {
                $('#table-kontainer').DataTable().destroy();
                dataTableInstance = null;
            }

            const tbody = document.getElementById('table-body');
            tbody.innerHTML = '';

            rows.forEach((r, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="text-align:center;">${idx + 1}</td>
                    <td><strong>${r.noCont || '-'}</strong></td>
                    <td>${r.size || '-'} ft</td>
                    <td><span class="badge-pill" style="background:rgba(59,130,246,0.12); color:var(--accent-blue);">${r.jnsCont || 'FCL'}</span></td>
                    <td><span class="badge-pill ${r.isKosong === 'Kosong' ? 'badge-out' : 'badge-in'}">${r.isKosong || 'Isi'}</span></td>
                    <td>${r.noPol || '-'}</td>
                    <td>${r.noDokInOut || '-'}</td>
                    <td>${r.noBc11 || '-'}</td>
                    <td>${r.consignee || '-'}</td>
                    <td><small style="font-family:'JetBrains Mono',monospace;">${formatDateTime(r.wkInOut)}</small></td>
                `;
                tbody.appendChild(tr);
            });

            // Inisialisasi DataTables dengan sorting, pagination, search, dan responsive
            dataTableInstance = $('#table-kontainer').DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
                language: {
                    search: "Cari:",
                    searchPlaceholder: "No kontainer, consignee...",
                    lengthMenu: "Tampilkan _MENU_ baris",
                    info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ kontainer",
                    infoEmpty: "Tidak ada data kontainer",
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

        function formatDateTime(str) {
            if (!str || str.length < 8) return str || '-';
            // Format input YYYYMMDDHHiiss ke format ramah pembaca
            const y = str.substr(0, 4);
            const m = str.substr(4, 2);
            const d = str.substr(6, 2);
            let time = '';
            if (str.length >= 12) {
                time = ' ' + str.substr(8, 2) + ':' + str.substr(10, 2);
            }
            return `${d}-${m}-${y}${time}`;
        }

        function renderHeaderTags(h) {
            const container = document.getElementById('header-tags-container');
            container.innerHTML = `
                <div class="header-tag">Dokumen: <strong>${h.kodeDokumen === '5' ? 'Gate-In (5)' : 'Gate-Out (6)'}</strong></div>
                <div class="header-tag">TPS: <strong>${h.kodeTps}</strong></div>
                <div class="header-tag">Gudang: <strong>${h.kodeGudang}</strong></div>
                <div class="header-tag">Kapal/Voyage: <strong>${h.namaAngkut || '-'} (${h.nomorVoyFlight || '-'})</strong></div>
                <div class="header-tag">Call Sign: <strong>${h.callSign || '-'}</strong></div>
                <div class="header-tag">BC 1.1: <strong>${h.noBc11 || '-'}</strong></div>
                <div class="header-tag">Tgl Tiba: <strong>${h.tanggalTiba || '-'}</strong></div>
            `;
        }

        function copyJson() {
            const viewer = document.getElementById('json-viewer');
            viewer.select();
            navigator.clipboard.writeText(viewer.value).then(() => {
                showToast('JSON berhasil disalin ke clipboard!', 'success');
            }).catch(err => {
                showToast('Gagal menyalin: ' + err, 'error');
            });
        }

        function downloadJson() {
            const viewer = document.getElementById('json-viewer');
            const type = document.getElementById('type-input').value;
            const tgl = document.getElementById('tgl-awal').value;
            const blob = new Blob([viewer.value], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `CEISA4_CoCoCont_${type}_${tgl}.json`;
            a.click();
            URL.revokeObjectURL(url);
            showToast('File JSON berhasil diunduh!', 'success');
        }

        function validateJsonStructure() {
            try {
                const text = document.getElementById('json-viewer').value;
                const parsed = JSON.parse(text);
                if (!parsed.header || !parsed.kontainer || !Array.isArray(parsed.kontainer)) {
                    throw new Error('Objek harus memiliki key "header" dan array "kontainer"');
                }
                
                const dmyRegex = /^\d{2}-\d{2}-\d{4}$/;
                const dtRegex = /^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}$/;
                const validJnsCont = ['FCL', 'LCL', '4', '7', '8'];

                // Validate Header
                const h = parsed.header;
                if (!h.tanggalBc11 || !dmyRegex.test(h.tanggalBc11)) {
                    throw new Error(`header.tanggalBc11 ("${h.tanggalBc11 || ''}") harus berformat dd-MM-yyyy`);
                }
                if (!h.tanggalTiba || !dmyRegex.test(h.tanggalTiba)) {
                    throw new Error(`header.tanggalTiba ("${h.tanggalTiba || ''}") harus berformat dd-MM-yyyy`);
                }

                // Validate Containers
                for (let i = 0; i < parsed.kontainer.length; i++) {
                    const k = parsed.kontainer[i];
                    const label = `Kontainer #${i + 1} (${k.nomorKontainer || 'No Cont Kosong'}):`;
                    
                    if (!validJnsCont.includes(k.jenisKontainer)) {
                        throw new Error(`${label} jenisKontainer ("${k.jenisKontainer}") harus salah satu dari: FCL, LCL, 4, 7, 8`);
                    }
                    if (!k.gudangTujuan || !k.gudangTujuan.trim()) {
                        throw new Error(`${label} gudangTujuan tidak boleh kosong`);
                    }
                    if (!k.nomorSegelBc || !k.nomorSegelBc.trim()) {
                        throw new Error(`${label} nomorSegelBc tidak boleh kosong`);
                    }
                    if (!k.tanggalSegelBc || !dmyRegex.test(k.tanggalSegelBc)) {
                        throw new Error(`${label} tanggalSegelBc ("${k.tanggalSegelBc || ''}") harus berformat dd-MM-yyyy`);
                    }
                    if (!k.nomorSegel || !k.nomorSegel.trim()) {
                        throw new Error(`${label} nomorSegel tidak boleh kosong`);
                    }
                    if (!k.idConsignee || !k.idConsignee.trim()) {
                        throw new Error(`${label} idConsignee tidak boleh kosong`);
                    }
                    if (!k.nomorDaftarPabean || !k.nomorDaftarPabean.trim()) {
                        throw new Error(`${label} nomorDaftarPabean tidak boleh kosong`);
                    }
                    if (k.nomorDaftarPabean.trim().length > 10) {
                        throw new Error(`${label} nomorDaftarPabean ("${k.nomorDaftarPabean}") maksimal 10 karakter`);
                    }
                    if (!k.tanggalDaftarPabean || !dmyRegex.test(k.tanggalDaftarPabean)) {
                        throw new Error(`${label} tanggalDaftarPabean ("${k.tanggalDaftarPabean || ''}") harus berformat dd-MM-yyyy`);
                    }
                    if (!k.waktuInOut || !dtRegex.test(k.waktuInOut)) {
                        throw new Error(`${label} waktuInOut ("${k.waktuInOut || ''}") harus berformat dd-MM-yyyy HH:mm:ss`);
                    }
                    if (!k.tanggalDokumenInOut || !dmyRegex.test(k.tanggalDokumenInOut)) {
                        throw new Error(`${label} tanggalDokumenInOut ("${k.tanggalDokumenInOut || ''}") harus berformat dd-MM-yyyy`);
                    }
                    if (!k.tanggalBlAwb || !dmyRegex.test(k.tanggalBlAwb)) {
                        throw new Error(`${label} tanggalBlAwb ("${k.tanggalBlAwb || ''}") harus berformat dd-MM-yyyy`);
                    }
                    if (!k.tanggalMasterBlAwb || !dmyRegex.test(k.tanggalMasterBlAwb)) {
                        throw new Error(`${label} tanggalMasterBlAwb ("${k.tanggalMasterBlAwb || ''}") harus berformat dd-MM-yyyy`);
                    }
                    if (!k.tanggalIjinTps || !dmyRegex.test(k.tanggalIjinTps)) {
                        throw new Error(`${label} tanggalIjinTps ("${k.tanggalIjinTps || ''}") harus berformat dd-MM-yyyy`);
                    }
                }

                showToast(`✅ Struktur & parameter JSON 100% valid! (Header & ${parsed.kontainer.length} kontainer sesuai standar CEISA 4.0)`, 'success');
            } catch (e) {
                showToast('Validasi Gagal: ' + e.message, 'error');
            }
        }

        async function sendToCeisa() {
            if (!currentPayload || !currentPayload.kontainer || currentPayload.kontainer.length === 0) {
                Swal.fire({
                    title: 'Belum Ada Data Kontainer',
                    text: 'Tidak ada data kontainer yang terpilih. Silakan ubah filter tanggal atau klik Gate-In / Gate-Out untuk memuat data terlebih dahulu.',
                    icon: 'info',
                    confirmButtonColor: '#10b981',
                    confirmButtonText: 'Mengerti'
                });
                return;
            }

            const targetEndpoint = document.getElementById('target-endpoint').value.trim();
            if (!targetEndpoint) {
                showToast('Target endpoint tidak boleh kosong!', 'error');
                return;
            }

            const btnSend = document.getElementById('btn-send');
            const spinner = document.getElementById('send-spinner');
            const icon = document.getElementById('send-icon');
            const type = $('#type-input').val() || 'In';

            // 1. JIKA TERDETEKSI MULTI-BATCH KARENA KONTAINER DUPLIKAT (MULTIPLE B/L)
            if (currentBatches && currentBatches.length > 1) {
                const dupNames = duplicateContainersList.join(', ');
                const totalAllCont = currentBatches.reduce((sum, b) => sum + b.kontainer_count, 0);

                const confirmRes = await Swal.fire({
                    title: 'Konfirmasi Pengiriman Bertahap',
                    html: `
                        <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                            <p style="margin-bottom:8px;">
                                Terdeteksi <strong>${duplicateContainersList.length} nomor kontainer</strong> dengan multiple B/L / data pabean ganda:<br>
                                <span style="display:inline-block; margin-top:4px; padding:3px 8px; background:rgba(245,158,11,0.15); color:#f59e0b; border-radius:4px; font-family:monospace; font-weight:600;">
                                    ${dupNames}
                                </span>
                            </p>
                            <p style="margin-bottom:8px;">
                                Portal CEISA 4.0 membatasi agar tidak ada nomor kontainer yang sama dalam 1 pengiriman. 
                                Agar <strong>seluruh ${totalAllCont} data kontainer tetap terkirim 100% tanpa ada yang dikurangi</strong>, pengiriman akan dijalankan dalam <strong>${currentBatches.length} kali pengiriman bertahap</strong>:
                            </p>
                            <div style="background:rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.1); border-radius:6px; padding:10px; margin-bottom:10px;">
                                ${currentBatches.map(b => `
                                    <div style="display:flex; justify-content:space-between; align-items:center; padding:5px 0; border-bottom:1px dashed rgba(255,255,255,0.1);">
                                        <span>📦 <strong>Batch ${b.batch_number}</strong> (${b.kontainer_count} Kontainer)</span>
                                        <code style="font-size:12px; color:#38bdf8;">${b.payload.header.refNumber}</code>
                                    </div>
                                `).join('')}
                            </div>
                            <p style="margin:0; font-size:12px; color:#94a3b8;">
                                <em>Sistem akan mengirimkan Batch 1 terlebih dahulu, lalu otomatis melanjutkan Batch berikutnya hingga tuntas.</em>
                            </p>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: `🚀 Ya, Kirim ${currentBatches.length} Batch Sekarang`,
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#64748b',
                    reverseButtons: true
                });

                if (!confirmRes.isConfirmed) {
                    return;
                }

                btnSend.disabled = true;
                spinner.style.display = 'inline-block';
                icon.style.display = 'none';

                let batchResults = [];
                let allSuccess = true;

                for (let i = 0; i < currentBatches.length; i++) {
                    const b = currentBatches[i];
                    Swal.fire({
                        title: `Mengirim Batch ${b.batch_number} dari ${currentBatches.length}...`,
                        html: `Sedang mengirim <b>${b.kontainer_count} kontainer</b> ke CEISA 4.0...<br><span style="font-size:12px; font-family:monospace; color:#38bdf8;">Ref: ${b.payload.header.refNumber}</span>`,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    try {
                        const res = await fetch('api/cococont.php?action=send', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                endpoint: targetEndpoint,
                                payload: b.payload
                            })
                        });
                        const result = await res.json();
                        batchResults.push({
                            batch: b.batch_number,
                            ref: b.payload.header.refNumber,
                            count: b.kontainer_count,
                            success: result.success,
                            code: result.code,
                            message: result.message,
                            raw: result
                        });

                        if (!result.success && result.code >= 400) {
                            allSuccess = false;
                            break;
                        }
                    } catch (err) {
                        allSuccess = false;
                        batchResults.push({
                            batch: b.batch_number,
                            ref: b.payload.header.refNumber,
                            count: b.kontainer_count,
                            success: false,
                            code: 500,
                            message: err.message,
                            raw: { error: err.message }
                        });
                        break;
                    }
                }

                btnSend.disabled = false;
                spinner.style.display = 'none';
                icon.style.display = 'inline-block';

                // Tampilkan hasil di Card Respon Pengiriman
                const resultCard = document.getElementById('send-result-card');
                if (resultCard) resultCard.style.display = 'block';

                const badge = document.getElementById('send-status-badge');
                if (badge) {
                    badge.className = 'badge-pill ' + (allSuccess ? 'badge-in' : 'badge-out');
                    badge.textContent = allSuccess ? `SEMUA BATCH BERHASIL (${batchResults.length}/${currentBatches.length})` : `BATCH SEBAGIAN GAGAL`;
                }

                const timestampEl = document.getElementById('send-timestamp');
                if (timestampEl) timestampEl.textContent = 'Waktu respon: ' + new Date().toLocaleString('id-ID');

                const msgEl = document.getElementById('send-result-msg');
                if (msgEl) {
                    msgEl.innerHTML = `
                        <div style="margin-bottom:8px;">
                            ${allSuccess ? '✅ <strong>Seluruh Pengiriman Bertahap Selesai:</strong>' : '⚠️ <strong>Hasil Pengiriman Bertahap:</strong>'}
                        </div>
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            ${batchResults.map(r => `
                                <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.15); padding:6px 12px; border-radius:6px; font-size:0.85rem;">
                                    <span>${r.success ? '✅' : '❌'} <strong>Batch ${r.batch}</strong> (${r.count} kontainer, Ref: <code>${r.ref}</code>)</span>
                                    <span style="font-weight:600; color:${r.success ? '#10b981' : '#ef4444'};">${r.message || (r.success ? 'BERHASIL' : 'GAGAL')}</span>
                                </div>
                            `).join('')}
                        </div>
                    `;
                }

                const rawEl = document.getElementById('send-raw-response');
                if (rawEl) {
                    rawEl.textContent = JSON.stringify(batchResults, null, 4);
                    rawEl.style.display = 'block';
                }

                if (allSuccess) {
                    Swal.fire({
                        title: '🎉 Pengiriman Sukses!',
                        html: `
                            <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                                Seluruh <b>${batchResults.length} Batch (${totalAllCont} data kontainer)</b> telah <b>BERHASIL</b> terkirim ke CEISA 4.0 tanpa ada data yang terlewat!<br><br>
                                Data telah dicatat ke database lokal dan dapat langsung dipantau di Laporan Coarri Codeco.
                            </div>
                        `,
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: '📊 Buka Laporan Coarri Codeco',
                        cancelButtonText: 'Tutup',
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#64748b'
                    }).then((r) => {
                        if (r.isConfirmed) {
                            window.location.href = 'report_cont.php';
                        }
                    });
                    showToast('Semua batch kontainer berhasil dikirim!', 'success');
                } else {
                    Swal.fire({
                        title: 'Pengiriman Terhenti',
                        html: `
                            <div style="text-align:left; font-size:13.5px;">
                                <p>Terdapat kendala saat mengirim salah satu batch:</p>
                                ${batchResults.map(r => `
                                    <div style="padding:4px 0; color:${r.success ? '#10b981' : '#ef4444'};">
                                        Batch ${r.batch}: ${r.message || (r.success ? 'Berhasil' : 'Gagal')}
                                    </div>
                                `).join('')}
                            </div>
                        `,
                        icon: 'error',
                        confirmButtonText: 'Tutup',
                        confirmButtonColor: '#ef4444'
                    });
                    showToast('Pengiriman bertahap mengalami kendala', 'error');
                }

                return;
            }

            // 2. JIKA BATCH TUNGGAL NORMAL (TIDAK ADA KONTAINER DUPLIKAT)
            try {
                const viewerText = document.getElementById('json-viewer').value;
                if (viewerText && viewerText.trim().startsWith('{')) {
                    currentPayload = JSON.parse(viewerText);
                }
            } catch (e) {
                console.warn('Memakai currentPayload dari memory:', e);
            }

            const swalSingle = await Swal.fire({
                title: 'Konfirmasi Pengiriman',
                html: `Kirim <strong>${currentPayload.kontainer.length} kontainer</strong> (${type === 'In' ? 'Gate-In' : 'Gate-Out'}) ke CEISA 4.0?<br><code style="font-size:12px; color:#38bdf8;">Ref: ${currentPayload.header.refNumber}</code>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '🚀 Ya, Kirim ke CEISA',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                reverseButtons: true
            });

            if (!swalSingle.isConfirmed) return;

            btnSend.disabled = true;
            spinner.style.display = 'inline-block';
            icon.style.display = 'none';

            Swal.fire({
                title: 'Mengirim ke CEISA 4.0...',
                html: `Sedang mengirim <b>${currentPayload.kontainer.length} kontainer</b>...<br><span style="font-size:12px; font-family:monospace; color:#38bdf8;">Ref: ${currentPayload.header.refNumber}</span>`,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                showToast('Mengirim payload ke CEISA 4.0...', 'info');
                const res = await fetch('api/cococont.php?action=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        endpoint: targetEndpoint,
                        payload: currentPayload
                    })
                });

                const result = await res.json();

                // Tampilkan di Send Result Card
                const resultCard = document.getElementById('send-result-card');
                if (resultCard) resultCard.style.display = 'block';

                const badge = document.getElementById('send-status-badge');
                if (badge) {
                    badge.className = 'badge-pill ' + (result.success ? 'badge-in' : 'badge-out');
                    badge.textContent = result.success ? `HTTP ${result.code || 200} SUCCESS` : `HTTP ${result.code || 500} FAILED`;
                }

                const timestampEl = document.getElementById('send-timestamp');
                if (timestampEl) timestampEl.textContent = 'Waktu respon: ' + new Date().toLocaleString('id-ID');

                const msgEl = document.getElementById('send-result-msg');
                if (msgEl) {
                    msgEl.innerHTML = result.success
                        ? `✅ <strong>Berhasil Terkirim:</strong> ${result.message || 'Data kontainer telah terkirim ke CEISA 4.0'}. Anda dapat memverifikasinya di menu <a href="report_cont.php" style="color:var(--accent-green); text-decoration:underline;">Laporan Coarri Codeco</a>.`
                        : `❌ <strong>Gagal:</strong> ${result.message || 'Pengiriman ditolak oleh gateway CEISA 4.0'}`;
                }

                const rawEl = document.getElementById('send-raw-response');
                if (rawEl) {
                    rawEl.textContent = JSON.stringify(result, null, 4);
                    rawEl.style.display = 'block';
                }

                if (result.success) {
                    Swal.fire({
                        title: '🎉 Berhasil Terkirim!',
                        text: result.message || 'Data kontainer berhasil terkirim ke CEISA 4.0.',
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: '📊 Buka Laporan Coarri Codeco',
                        cancelButtonText: 'Tutup',
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#64748b'
                    }).then((r) => {
                        if (r.isConfirmed) {
                            window.location.href = 'report_cont.php';
                        }
                    });
                    showToast('Pengiriman ke CEISA 4.0 berhasil!', 'success');
                } else {
                    Swal.fire({
                        title: 'Pengiriman Gagal',
                        text: result.message || 'Pengiriman ditolak oleh CEISA 4.0',
                        icon: 'error',
                        confirmButtonColor: '#ef4444'
                    });
                    showToast('Pengiriman gagal: ' + (result.message || 'Periksa detail respon'), 'error');
                }

            } catch (err) {
                console.error(err);
                showToast('Terjadi kesalahan saat pengiriman: ' + err.message, 'error');
            } finally {
                btnSend.disabled = false;
                spinner.style.display = 'none';
                icon.style.display = 'inline-block';
            }
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
                    showToast('Gagal memperbarui token: ' + data.message, 'error');
                }
            } catch (e) {
                showToast('Gagal terhubung ke API SSO', 'error');
            }
        }

        // Theme management
        document.getElementById('theme-toggle').addEventListener('click', () => {
            const cur = document.documentElement.getAttribute('data-theme') || 'dark';
            const next = cur === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('ceisa_theme', next);
            document.querySelector('.theme-toggle-icon').textContent = next === 'dark' ? '🌙' : '☀️';
            document.querySelector('.theme-toggle-text').textContent = next === 'dark' ? 'Dark' : 'Light';
        });

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
    </script>
</body>
</html>
