<?php
/**
 * Coarri Codeco (CoCoKms & CoCoCont) CEISA 4.0 — TPS Online Dashboard
 * Halaman penarikan data Gate-In & Gate-Out untuk 3 Sub-Tab:
 * 1. Kemasan (In_kms / Out_kms) -> POST /coarri-codeco-kemasan
 * 2. Container LCL (In_container_lcl / Out_container_lcl) -> POST /coarri-codeco-container
 * 3. Container PJT (In_container_pjt / Out_container_pjt) -> POST /coarri-codeco-container
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
    <title>Coarri Codeco CEISA 4.0 — <?= e($config['app_name']) ?></title>
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
    <!-- SweetAlert2 CDN -->
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
            max-width: 1560px;
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
        .subtab-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--border-subtle);
            flex-wrap: wrap;
        }
        .subtab-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-right: 6px;
        }
        .subtab-btn {
            padding: 8px 18px;
            border-radius: 8px;
            border: 1px solid var(--border-subtle);
            background: var(--bg-surface);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.88rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .subtab-btn:hover {
            background: var(--bg-card-hover);
            color: var(--text-primary);
            border-color: var(--border-medium);
        }
        .subtab-btn.active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(37, 99, 235, 0.25) 100%);
            border-color: var(--accent-blue);
            color: #60a5fa;
            box-shadow: 0 2px 10px rgba(59, 130, 246, 0.2);
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
        .badge-purple { background: rgba(139, 92, 246, 0.15); color: #c4b5fd; border: 1px solid rgba(139, 92, 246, 0.3); }
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
        .bl-badge {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            color: var(--accent-blue);
            background: rgba(59, 130, 246, 0.1);
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid rgba(59, 130, 246, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .cont-badge {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            color: #60a5fa;
            background: rgba(37, 99, 235, 0.15);
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid rgba(59, 130, 246, 0.35);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-copy-mini {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.8rem;
            padding: 2px 4px;
            border-radius: 4px;
            transition: color 0.15s;
        }
        .btn-copy-mini:hover {
            color: var(--text-primary);
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
                        <span>Transaksi</span>
                        <span class="separator">/</span>
                        <span class="current" id="page-breadcrumb">Coarri Codeco (Kemasan)</span>
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

                    <!-- Filter Control Card -->
                    <div class="coco-card">
                        <div style="margin-bottom: 18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                            <div>
                                <h2 id="card-main-title" style="margin:0; font-size:1.25rem; color:var(--text-primary); font-weight:700; display:flex; align-items:center; gap:8px;">
                                    <span>📦</span> Coarri Codeco Kemasan (CoCoKms)
                                </h2>
                                <p id="card-main-desc" style="margin:4px 0 0; color:var(--text-secondary); font-size:0.88rem;">
                                    Tarik data Gate-In & Gate-Out kemasan LCL / kontainer dari database primamas & bentuk payload sesuai standar REST API CEISA 4.0.
                                </p>
                            </div>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <a href="report_kms.php" class="btn-action-sm" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                    <span>📊</span> Laporan CoCoKms
                                </a>
                                <span class="badge-pill badge-ceisa" id="header-endpoint-badge">
                                    POST /coarri-codeco-kemasan
                                </span>
                            </div>
                        </div>

                        <!-- Form Filter -->
                        <form id="filter-form" onsubmit="event.preventDefault(); loadData(true);">
                            <div class="filter-grid">
                                <div>
                                    <label style="display:block; font-size:0.82rem; font-weight:600; color:var(--text-secondary); margin-bottom:6px; text-transform:uppercase;">Alur Pergerakan</label>
                                    <div class="type-toggle-group">
                                        <button type="button" class="type-btn active" id="btn-type-in" onclick="setType('In')">
                                            <span>📥</span> Gate-In (Pemasukan)
                                        </button>
                                        <button type="button" class="type-btn" id="btn-type-out" onclick="setType('Out')">
                                            <span>📤</span> Gate-Out (Pengeluaran)
                                        </button>
                                    </div>
                                    <input type="hidden" id="type-input" value="In">
                                    <input type="hidden" id="subtype-input" value="kemasan">
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

                            <!-- 3 Sub-Tabs Bar: Kemasan, Container LCL, Container PJT -->
                            <div class="subtab-bar">
                                <span class="subtab-label">Kategori / Tab:</span>
                                <button type="button" class="subtab-btn active" id="subtab-kemasan" onclick="setSubType('kemasan')">
                                    <span>📦</span> Kemasan
                                </button>
                                <button type="button" class="subtab-btn" id="subtab-container_lcl" onclick="setSubType('container_lcl')">
                                    <span>🚚</span> Container LCL
                                </button>
                                <button type="button" class="subtab-btn" id="subtab-container_pjt" onclick="setSubType('container_pjt')">
                                    <span>📮</span> Container PJT
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Stats Row -->
                    <div class="stats-bar" id="stats-bar" style="display:none;">
                        <div class="stat-item">
                            <span class="stat-label" id="stat-label-item">Total Kemasan</span>
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
                                <span>📋</span> Pratinjau Tabel (<span id="tab-count">0</span>)
                            </button>
                            <button class="tab-btn" onclick="switchTab('tab-json', this)">
                                <span>📦</span> JSON Payload CEISA 4.0
                            </button>
                        </div>

                        <!-- Tab 1: Table -->
                        <div class="tab-content active" id="tab-table">
                            <div style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <div style="font-size:0.88rem; color:var(--text-secondary);">
                                    <span id="table-subtitle">Tabel Interaktif (Sorting, Real-Time Filter & Pagination DataTables aktif)</span>
                                </div>
                                <button type="button" class="btn-action-sm" style="color:var(--accent-blue); border-color:rgba(59,130,246,0.4); background:rgba(59,130,246,0.12);" onclick="switchTab('tab-json', document.querySelectorAll('.tab-btn')[1])">
                                    <span>⚡</span> Buka JSON Payload
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table display responsive nowrap" id="table-kms" style="width:100%;">
                                    <thead id="table-head">
                                        <!-- Rendered dynamically depending on subType -->
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

                            <textarea id="json-viewer" class="json-box" spellcheck="false" placeholder="Payload JSON akan otomatis terbentuk saat data ditarik..."></textarea>

                            <div class="action-row">
                                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                    <button type="button" class="btn-action-sm" style="background:rgba(59,130,246,0.15); color:var(--accent-blue); border-color:rgba(59,130,246,0.4);" onclick="loadData(true)">
                                        <span>⚡</span> Re-generate JSON
                                    </button>
                                    <button type="button" class="btn-action-sm" onclick="copyJson()">
                                        <span>📋</span> Salin JSON
                                    </button>
                                    <button type="button" class="btn-action-sm" onclick="downloadJson()">
                                        <span>💾</span> Unduh .json
                                    </button>
                                    <button type="button" class="btn-action-sm" onclick="validateSchema()">
                                        <span>🔍</span> Validasi Skema
                                    </button>
                                </div>
                                <span id="schema-badge" class="badge-pill badge-ceisa">Struktur CEISA 4.0 Valid</span>
                            </div>
                        </div>

                        <!-- Multi-Batch Notice (Muncul jika ada B/L atau Kontainer ganda) -->
                        <div id="batch-notice-card" style="display:none; margin-top:20px; background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.3); border-radius:10px; padding:14px 18px;">
                            <div style="display:flex; align-items:flex-start; gap:12px;">
                                <span style="font-size:1.3rem;">⚠️</span>
                                <div style="flex-grow:1;">
                                    <div style="font-weight:600; color:#f59e0b; font-size:0.92rem; margin-bottom:4px;">
                                        Deteksi Pengiriman Bertahap (<span id="batch-count-badge">0</span> Batch Diperlukan)
                                    </div>
                                    <div style="font-size:0.85rem; color:var(--text-secondary); line-height:1.5;">
                                        Terdeteksi data dengan nomor identitas sama (<code id="batch-dup-bls" style="color:#f59e0b; background:rgba(245,158,11,0.15); padding:1px 6px; border-radius:4px;"></code>). 
                                        Untuk mencegah penolakan duplikat di CEISA 4.0 dan memastikan <strong>seluruh data terkirim 100% tanpa ada yang dikurangi</strong>, sistem membagi pengiriman menjadi <strong id="batch-count-text">2</strong> tahap secara otomatis.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Target Endpoint & Send Action Row -->
                        <div class="action-row" style="background:var(--bg-surface); padding:16px 20px; border-radius:10px; margin-top:24px;">
                            <div style="flex-grow:1; max-width:650px;">
                                <label style="font-size:0.8rem; font-weight:600; color:var(--text-secondary); display:block; margin-bottom:4px;">
                                    TARGET ENDPOINT CEISA 4.0 (OPENAPI):
                                </label>
                                <input type="text" id="target-endpoint" class="input-control" value="coarri-codeco-kemasan" readonly style="font-family:'JetBrains Mono',monospace; font-size:0.88rem;">
                            </div>
                            <div>
                                <button type="button" class="btn-send-prod" id="btn-send-ceisa" onclick="sendToCeisa()">
                                    <span id="send-spinner" style="display:none;">⏳</span>
                                    <span id="send-icon">🚀</span>
                                    <span>Kirim ke CEISA 4.0</span>
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
                                    <a href="report_kms.php" class="btn-action-sm" style="background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.3); text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                        <span>📊</span> Buka Laporan
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
        let currentType = 'In';
        let currentSubType = 'kemasan'; // 'kemasan', 'container_lcl', 'container_pjt'
        let currentPayload = null;
        let currentBatches = [];
        let hasDuplicates = false;
        let duplicateKeysList = [];
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
            toast.style.zIndex = '9999';

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

        function setType(type) {
            const prevType = $('#type-input').val();
            $('#type-input').val(type);
            currentType = type;

            if (type === 'In') {
                $('#btn-type-in').addClass('active');
                $('#btn-type-out').removeClass('active');
            } else {
                $('#btn-type-out').addClass('active');
                $('#btn-type-in').removeClass('active');
            }

            updateViewLabels();
            if (prevType !== type) {
                loadData(true);
            }
        }

        function setSubType(subType) {
            const prevSubType = $('#subtype-input').val();
            $('#subtype-input').val(subType);
            currentSubType = subType;

            $('.subtab-btn').removeClass('active');
            $('#subtab-' + subType).addClass('active');

            updateViewLabels();
            if (prevSubType !== subType) {
                loadData(true);
            }
        }

        function updateViewLabels() {
            const typeLabel = (currentType === 'In') ? 'Gate-In (Pemasukan)' : 'Gate-Out (Pengeluaran)';
            let title = '';
            let targetEp = 'coarri-codeco-kemasan';

            if (currentSubType === 'kemasan') {
                title = `📦 Coarri Codeco Kemasan (${typeLabel})`;
                targetEp = 'coarri-codeco-kemasan';
                $('#stat-label-item').text('Total Kemasan');
                $('#page-breadcrumb').text(`Coarri Codeco Kemasan (${currentType})`);
            } else if (currentSubType === 'container_lcl') {
                title = `🚚 Coarri Codeco Container LCL (${typeLabel})`;
                targetEp = 'coarri-codeco-container';
                $('#stat-label-item').text('Total Kontainer');
                $('#page-breadcrumb').text(`Coarri Codeco Container LCL (${currentType})`);
            } else {
                title = `📮 Coarri Codeco Container PJT (${typeLabel})`;
                targetEp = 'coarri-codeco-container';
                $('#stat-label-item').text('Total Kontainer');
                $('#page-breadcrumb').text(`Coarri Codeco Container PJT (${currentType})`);
            }

            $('#card-main-title').html(title);
            $('#header-endpoint-badge').text('POST /' + targetEp);
            $('#target-endpoint').val(targetEp);
        }

        function updateStats(payload, count) {
            if (!payload || count === 0) {
                $('#stats-bar').hide();
                return;
            }
            let totalBruto = 0;
            let bc11Set = new Set();

            if (currentSubType === 'kemasan' && payload.detil && Array.isArray(payload.detil)) {
                payload.detil.forEach(d => {
                    totalBruto += parseFloat(d.bruto || 0);
                    if (d.nomorBc11) bc11Set.add(d.nomorBc11);
                    else if (d.noBc11) bc11Set.add(d.noBc11);
                });
            } else if (payload.kontainer && Array.isArray(payload.kontainer)) {
                payload.kontainer.forEach(c => {
                    totalBruto += parseFloat(c.bruto || 0);
                    if (c.noBc11) bc11Set.add(c.noBc11);
                    else if (c.nomorBc11) bc11Set.add(c.nomorBc11);
                });
            }

            $('#stat-count').text(count);
            $('#stat-bruto').text(Math.round(totalBruto).toLocaleString('id-ID') + ' KG');
            const hBc11 = payload.header ? (payload.header.noBc11 || payload.header.nomorBc11 || '') : '';
            $('#stat-groups').text(bc11Set.size || (hBc11 ? 1 : 0));
            $('#stats-bar').show();
        }

        function renderHeaderTags(h) {
            const container = document.getElementById('header-tags-container');
            if (!container) return;
            if (!h) {
                container.innerHTML = '';
                return;
            }
            const bc11 = h.noBc11 || h.nomorBc11 || '-';
            container.innerHTML = `
                <div class="header-tag">TPS: <strong>${h.kodeTps || 'PSU0'}</strong></div>
                <div class="header-tag">Gudang: <strong>${h.kodeGudang || 'GPSU'}</strong></div>
                <div class="header-tag">Kapal/Voyage: <strong>${h.namaAngkut || '-'} (${h.nomorVoyFlight || '-'})</strong></div>
                <div class="header-tag">Call Sign: <strong>${h.callSign || '-'}</strong></div>
                <div class="header-tag">BC 1.1: <strong>${bc11}</strong></div>
                <div class="header-tag">Tgl Tiba: <strong>${h.tanggalTiba || '-'}</strong></div>
                <div class="header-tag">Ref: <strong style="font-family:monospace; color:#c4b5fd;">${h.refNumber || '-'}</strong></div>
            `;
        }

        function loadData(showNotification = true) {
            const tglAwal = $('#tgl-awal').val();
            const tglAkhir = $('#tgl-akhir').val();

            if (!tglAwal || !tglAkhir) return;

            if (activeAjaxRequest && activeAjaxRequest.readyState !== 4) {
                activeAjaxRequest.abort();
            }

            $('#auto-sync-status').html('<span class="pulse-dot" style="background:#3b82f6;"></span> <span style="color:#60a5fa;">Memproses AJAX...</span>');

            activeAjaxRequest = $.ajax({
                url: 'api/cocokms.php',
                type: 'GET',
                data: {
                    action: 'fetch',
                    type: currentType,
                    subType: currentSubType,
                    tglAwal: tglAwal,
                    tglAkhir: tglAkhir
                },
                dataType: 'json',
                success: function(res) {
                    if (!res.success) {
                        $('#auto-sync-status').html('❌ <span style="color:#ef4444;">Gagal</span>');
                        showToast(res.message || 'Gagal mengambil data', 'error');
                        $('#stats-bar').hide();
                        $('#result-card').hide();
                        return;
                    }

                    currentPayload = res.payload;
                    currentBatches = res.batches || [];
                    hasDuplicates = res.has_duplicates || false;
                    duplicateKeysList = res.duplicate_keys || [];
                    const rows = res.rows || [];
                    const count = res.count || 0;
                    const targetEp = res.targetEndpoint || 'coarri-codeco-kemasan';
                    $('#target-endpoint').val(targetEp);
                    $('#header-endpoint-badge').text('POST /' + targetEp);

                    if (count === 0) {
                        currentPayload = null;
                        currentBatches = [];
                        hasDuplicates = false;
                        duplicateKeysList = [];
                        $('#batch-notice-card').hide();
                        if ($.fn.DataTable.isDataTable('#table-kms')) {
                            $('#table-kms').DataTable().destroy();
                            dataTableInstance = null;
                        }
                        $('#table-body').empty();
                        $('#stats-bar').hide();
                        $('#result-card').hide();
                        $('#auto-sync-status').html('ℹ️ <span style="color:var(--text-secondary);">Tidak ada data</span>');
                        if (showNotification) {
                            showToast(res.message || 'Tidak ada data pada rentang tanggal tersebut', 'info');
                        }
                        return;
                    }

                    // Tampilkan info batch jika ada data ganda
                    if (hasDuplicates && currentBatches.length > 1) {
                        $('#batch-notice-card').show();
                        $('#batch-count-badge').text(currentBatches.length);
                        $('#batch-count-text').text(currentBatches.length);
                        const dupNames = duplicateKeysList.slice(0, 4).join(', ') + (duplicateKeysList.length > 4 ? ` (+${duplicateKeysList.length - 4} lainnya)` : '');
                        $('#batch-dup-bls').text(dupNames);
                    } else {
                        $('#batch-notice-card').hide();
                    }

                    $('#tab-count').text(count);
                    $('#stats-bar').show();
                    $('#result-card').show();

                    // Update stats & tags
                    updateStats(currentPayload, count);
                    renderHeaderTags(currentPayload ? currentPayload.header : null);

                    // Render Dynamic Table
                    renderDataTable(rows, currentSubType);

                    // Render JSON Box
                    $('#json-viewer').val(JSON.stringify(currentPayload, null, 4));
                    $('#schema-badge').text('Struktur CEISA 4.0 Valid').removeClass('badge-out').addClass('badge-ceisa').css({
                        background: 'rgba(16,185,129,0.15)',
                        color: '#10b981',
                        border: '1px solid rgba(16,185,129,0.3)'
                    });

                    $('#send-result-card').hide();
                    const itemWord = (currentSubType === 'kemasan') ? 'Kemasan' : 'Kontainer';
                    $('#auto-sync-status').html('<span class="pulse-dot"></span> <span style="color:#10b981;">Tersinkron (' + count + ' ' + itemWord + (hasDuplicates ? ` - ${currentBatches.length} Batch` : '') + ')</span>');
                    if (showNotification) {
                        showToast(`Ditemukan ${count} data ${itemWord}!`, 'success');
                    }
                },
                error: function(xhr, status, error) {
                    if (status === 'abort') return;
                    console.error('AJAX Error:', error);
                    showToast('Gagal terhubung ke api/cocokms.php: ' + error, 'error');
                    $('#auto-sync-status').html('❌ <span style="color:#ef4444;">Error koneksi</span>');
                }
            });
        }

        function renderDataTable(rows, subType) {
            if ($.fn.DataTable.isDataTable('#table-kms')) {
                $('#table-kms').DataTable().destroy();
                dataTableInstance = null;
            }

            const thead = document.getElementById('table-head');
            const tbody = document.getElementById('table-body');
            tbody.innerHTML = '';

            if (subType === 'kemasan') {
                thead.innerHTML = `
                    <tr>
                        <th style="width:40px; text-align:center;">No</th>
                        <th>Nomor B/L AWB</th>
                        <th>Master B/L</th>
                        <th>Kemasan</th>
                        <th>Bruto (KG)</th>
                        <th>Pos BC 1.1</th>
                        <th>Kontainer Asal</th>
                        <th>No. Polisi</th>
                        <th>Dokumen In/Out</th>
                        <th>Consignee</th>
                        <th>Waktu In/Out</th>
                    </tr>
                `;

                rows.forEach((r, idx) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="text-align:center;">${idx + 1}</td>
                        <td>
                            <span class="bl-badge">
                                <span>${r.nomorBlAwb || '-'}</span>
                                <button type="button" class="btn-copy-mini" onclick="copyText('${r.nomorBlAwb}')" title="Salin No. BL">📋</button>
                            </span>
                            ${r.is_duplicate ? `<span class="badge-pill" style="margin-left:4px; font-size:10px; background:rgba(245,158,11,0.2); color:#f59e0b; border:1px solid rgba(245,158,11,0.4);" title="Nomor B/L muncul lebih dari 1 kali, otomatis dikirim pada ${r.batchLabel}">📦 ${r.batchLabel}</span>` : ''}
                            <div style="font-size:0.75rem; color:var(--text-secondary); margin-top:2px;">${r.tanggalBlAwb || '-'}</div>
                        </td>
                        <td>
                            <span style="font-family:'JetBrains Mono',monospace; font-size:0.85rem; color:var(--text-primary);">${r.nomorMasterBlAwb || '-'}</span>
                        </td>
                        <td>
                            <span class="badge-pill" style="background:rgba(59,130,246,0.12); color:var(--accent-blue); border:1px solid rgba(59,130,246,0.3); font-weight:600;">
                                ${r.jumlahKemasan}
                            </span>
                        </td>
                        <td><strong>${r.bruto}</strong></td>
                        <td><span style="font-family:'JetBrains Mono',monospace; font-size:0.85rem;">${r.nomorPosBc11 || '-'}</span></td>
                        <td><span style="font-family:'JetBrains Mono',monospace; font-weight:600; color:#60a5fa;">${r.kontainerAsal || '-'}</span></td>
                        <td><span class="badge-pill badge-ceisa">${r.nomorPolisi || '-'}</span></td>
                        <td>
                            <div style="font-size:0.82rem; color:var(--text-primary); font-weight:500;">${r.nomorDokInOut || '-'}</div>
                        </td>
                        <td>
                            <div style="font-size:0.82rem; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${r.consignee}">
                                ${r.consignee || '-'}
                            </div>
                        </td>
                        <td>
                            <span style="font-family:'JetBrains Mono',monospace; font-size:0.8rem; color:var(--text-secondary);">${r.waktuInOut || '-'}</span>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

            } else if (subType === 'container_lcl') {
                thead.innerHTML = `
                    <tr>
                        <th style="width:40px; text-align:center;">No</th>
                        <th>Nomor Kontainer</th>
                        <th>Ukuran & Tipe</th>
                        <th>No. Segel</th>
                        <th>Master B/L</th>
                        <th>No. B/L AWB</th>
                        <th>Pos BC 1.1</th>
                        <th>Bruto</th>
                        <th>No. Polisi</th>
                        <th>Dokumen In/Out</th>
                        <th>Consignee</th>
                        <th>Waktu In/Out</th>
                    </tr>
                `;

                rows.forEach((r, idx) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="text-align:center;">${idx + 1}</td>
                        <td>
                            <span class="cont-badge">
                                <span>${r.noCont || '-'}</span>
                                <button type="button" class="btn-copy-mini" onclick="copyText('${r.noCont}')" title="Salin No. Kontainer">📋</button>
                            </span>
                            ${r.is_duplicate ? `<span class="badge-pill" style="margin-left:4px; font-size:10px; background:rgba(245,158,11,0.2); color:#f59e0b; border:1px solid rgba(245,158,11,0.4);" title="Kontainer dikirim pada ${r.batchLabel}">🚚 ${r.batchLabel}</span>` : ''}
                        </td>
                        <td>
                            <span class="badge-pill badge-purple">${r.ukuranKontainer || '20ft LCL'}</span>
                            <span class="badge-pill ${r.statusKosong === 'KOSONG' ? 'badge-out' : 'badge-in'}" style="margin-left:2px;">${r.statusKosong}</span>
                        </td>
                        <td><span style="font-family:'JetBrains Mono',monospace; font-size:0.82rem;">${r.nomorSegel || '-'}</span></td>
                        <td><span style="font-family:'JetBrains Mono',monospace; font-size:0.82rem;">${r.noMasterBlAwb || '-'}</span></td>
                        <td>
                            <span style="font-family:'JetBrains Mono',monospace; font-size:0.82rem; color:var(--accent-blue);">${r.noBlAwb || '-'}</span>
                            <div style="font-size:0.75rem; color:var(--text-secondary);">${r.tanggalBlAwb || ''}</div>
                        </td>
                        <td><span style="font-family:'JetBrains Mono',monospace; font-size:0.82rem;">${r.nomorPosBc11 || '-'}</span></td>
                        <td><strong>${r.bruto}</strong></td>
                        <td><span class="badge-pill badge-ceisa">${r.nomorPolisi || '-'}</span></td>
                        <td><div style="font-size:0.82rem; font-weight:500;">${r.nomorDokInOut || '-'}</div></td>
                        <td>
                            <div style="font-size:0.82rem; max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${r.consignee}">
                                ${r.consignee || '-'}
                            </div>
                        </td>
                        <td><span style="font-family:'JetBrains Mono',monospace; font-size:0.8rem; color:var(--text-secondary);">${r.waktuInOut || '-'}</span></td>
                    `;
                    tbody.appendChild(tr);
                });

            } else { // container_pjt
                thead.innerHTML = `
                    <tr>
                        <th style="width:40px; text-align:center;">No</th>
                        <th>Nomor Kontainer PJT</th>
                        <th>Ukuran & Tipe</th>
                        <th>No. UT / B/L</th>
                        <th>No. BC 1.1</th>
                        <th>Pos BC 1.1</th>
                        <th>No. Polisi</th>
                        <th>Dokumen In/Out</th>
                        <th>Consignee</th>
                        <th>Waktu In/Out</th>
                    </tr>
                `;

                rows.forEach((r, idx) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="text-align:center;">${idx + 1}</td>
                        <td>
                            <span class="cont-badge">
                                <span>${r.noCont || '-'}</span>
                                <button type="button" class="btn-copy-mini" onclick="copyText('${r.noCont}')" title="Salin No. Kontainer">📋</button>
                            </span>
                            ${r.is_duplicate ? `<span class="badge-pill" style="margin-left:4px; font-size:10px; background:rgba(245,158,11,0.2); color:#f59e0b; border:1px solid rgba(245,158,11,0.4);" title="Kontainer dikirim pada ${r.batchLabel}">📮 ${r.batchLabel}</span>` : ''}
                        </td>
                        <td>
                            <span class="badge-pill badge-purple">${r.ukuranKontainer || '20ft LCL'}</span>
                            <span class="badge-pill ${r.statusKosong === 'KOSONG' ? 'badge-out' : 'badge-in'}" style="margin-left:2px;">${r.statusKosong}</span>
                        </td>
                        <td>
                            <span style="font-family:'JetBrains Mono',monospace; font-size:0.82rem; color:var(--accent-blue);">${r.noBlAwb || '-'}</span>
                            <div style="font-size:0.75rem; color:var(--text-secondary);">${r.tanggalBlAwb || ''}</div>
                        </td>
                        <td><span style="font-family:'JetBrains Mono',monospace; font-size:0.82rem;">${r.nomorBc11 || '-'}</span></td>
                        <td><span style="font-family:'JetBrains Mono',monospace; font-size:0.82rem;">${r.nomorPosBc11 || '-'}</span></td>
                        <td><span class="badge-pill badge-ceisa">${r.nomorPolisi || '-'}</span></td>
                        <td><div style="font-size:0.82rem; font-weight:500;">${r.nomorDokInOut || '-'}</div></td>
                        <td>
                            <div style="font-size:0.82rem; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${r.consignee}">
                                ${r.consignee || '-'}
                            </div>
                        </td>
                        <td><span style="font-family:'JetBrains Mono',monospace; font-size:0.8rem; color:var(--text-secondary);">${r.waktuInOut || '-'}</span></td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            dataTableInstance = $('#table-kms').DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
                language: {
                    search: "Cari:",
                    searchPlaceholder: "No Cont / BL / Pos / Polisi / Consignee...",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                    infoEmpty: "Tidak ada data",
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

        function copyText(txt) {
            if (!txt || txt === '-') return;
            navigator.clipboard.writeText(txt).then(() => {
                showToast(`Teks ${txt} disalin ke clipboard!`, 'success');
            }).catch(e => {
                showToast('Gagal menyalin: ' + e, 'error');
            });
        }

        function copyJson() {
            const viewer = document.getElementById('json-viewer');
            viewer.select();
            navigator.clipboard.writeText(viewer.value).then(() => {
                showToast('Payload JSON berhasil disalin!', 'success');
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
            a.download = `CEISA4_${currentSubType}_${currentType}_${tgl}.json`;
            a.click();
            URL.revokeObjectURL(url);
            showToast('File JSON berhasil diunduh!', 'success');
        }

        function validateSchema() {
            try {
                const text = document.getElementById('json-viewer').value;
                const parsed = JSON.parse(text);
                if (!parsed.header) {
                    throw new Error('Objek header wajib ada');
                }
                const hasDetil = parsed.detil && Array.isArray(parsed.detil);
                const hasKontainer = parsed.kontainer && Array.isArray(parsed.kontainer);

                if (!hasDetil && !hasKontainer) {
                    throw new Error('Array detil (kemasan) atau kontainer wajib ada');
                }
                if (hasKontainer && !parsed.header.noBc11 && !parsed.header.nomorBc11) {
                    throw new Error('Header noBc11 wajib diisi untuk pengiriman kontainer');
                }
                const count = hasDetil ? parsed.detil.length : parsed.kontainer.length;
                if (count === 0) {
                    showToast('Peringatan: Array item kosong', 'info');
                    return;
                }
                showToast(`Validasi sukses! Terverifikasi ${count} item siap dikirim ke CEISA 4.0.`, 'success');
            } catch (e) {
                showToast('Validasi gagal: ' + e.message, 'error');
            }
        }

        async function sendToCeisa() {
            try {
                const viewerText = document.getElementById('json-viewer').value;
                if (viewerText && viewerText.trim().startsWith('{')) {
                    currentPayload = JSON.parse(viewerText);
                }
            } catch (e) {
                console.warn('Memakai currentPayload dari memory:', e);
            }

            const isCont = currentPayload && currentPayload.kontainer && Array.isArray(currentPayload.kontainer);
            const items = isCont ? (currentPayload.kontainer || []) : (currentPayload.detil || []);

            if (!currentPayload || items.length === 0) {
                showToast('Tidak ada data untuk dikirim!', 'error');
                return;
            }

            const btnSend = document.getElementById('btn-send-ceisa');
            const spinner = document.getElementById('send-spinner');
            const icon = document.getElementById('send-icon');
            const targetEndpoint = document.getElementById('target-endpoint').value || (isCont ? 'coarri-codeco-container' : 'coarri-codeco-kemasan');
            const itemTypeName = isCont ? 'kontainer' : 'kemasan';

            // KASUS 1: MULTI-BATCH (Ada data ganda)
            if (hasDuplicates && currentBatches.length > 1) {
                const totalAll = currentBatches.reduce((acc, b) => acc + b.item_count, 0);
                const dupNames = duplicateKeysList.slice(0, 5).join(', ') + (duplicateKeysList.length > 5 ? ` (+${duplicateKeysList.length - 5} lainnya)` : '');

                const confirmRes = await Swal.fire({
                    title: 'Pemberitahuan Pengiriman Bertahap',
                    html: `
                        <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                            <p style="margin-bottom:8px;">
                                Terdeteksi <strong>${duplicateKeysList.length} data</strong> dengan identitas ganda:<br>
                                <span style="display:inline-block; margin-top:4px; padding:3px 8px; background:rgba(245,158,11,0.15); color:#f59e0b; border-radius:4px; font-family:monospace; font-weight:600;">
                                    ${dupNames}
                                </span>
                            </p>
                            <p style="margin-bottom:8px;">
                                Gateway CEISA 4.0 membatasi agar tidak ada nomor identitas ganda dalam 1 dokumen pengiriman. 
                                Agar <strong>seluruh ${totalAll} data ${itemTypeName} tetap terkirim 100% tanpa ada yang dikurangi</strong>, pengiriman akan dijalankan dalam <strong>${currentBatches.length} kali pengiriman bertahap</strong>:
                            </p>
                            <div style="background:rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.1); border-radius:6px; padding:10px; margin-bottom:10px;">
                                ${currentBatches.map(b => `
                                    <div style="display:flex; justify-content:space-between; align-items:center; padding:5px 0; border-bottom:1px dashed rgba(255,255,255,0.1);">
                                        <span>📦 <strong>Batch ${b.batch_number}</strong> (${b.item_count} ${itemTypeName})</span>
                                        <code style="font-size:12px; color:#c4b5fd;">${b.payload.header.refNumber}</code>
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
                    confirmButtonColor: '#8b5cf6',
                    cancelButtonColor: '#64748b',
                    reverseButtons: true
                });

                if (!confirmRes.isConfirmed) {
                    return;
                }

                btnSend.disabled = true;
                if (spinner) spinner.style.display = 'inline-block';
                if (icon) icon.style.display = 'none';

                let batchResults = [];
                let allSuccess = true;

                for (let i = 0; i < currentBatches.length; i++) {
                    const b = currentBatches[i];
                    Swal.fire({
                        title: `Mengirim Batch ${b.batch_number} dari ${currentBatches.length}...`,
                        html: `Sedang mengirim <b>${b.item_count} data ${itemTypeName}</b> ke CEISA 4.0...<br><span style="font-size:12px; font-family:monospace; color:#c4b5fd;">Ref: ${b.payload.header.refNumber}</span>`,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    try {
                        const res = await fetch('api/cocokms.php?action=send', {
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
                            count: b.item_count,
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
                            count: b.item_count,
                            success: false,
                            code: 500,
                            message: err.message,
                            raw: { error: err.message }
                        });
                        break;
                    }
                }

                btnSend.disabled = false;
                if (spinner) spinner.style.display = 'none';
                if (icon) icon.style.display = 'inline-block';

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
                                    <span>${r.success ? '✅' : '❌'} <strong>Batch ${r.batch}</strong> (${r.count} ${itemTypeName}, Ref: <code>${r.ref}</code>)</span>
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
                                Seluruh <strong>${currentBatches.length} batch pengiriman</strong> (${totalAll} data ${itemTypeName}) telah berhasil dikirim dan diverifikasi oleh CEISA 4.0!<br><br>
                                Data telah dicatat ke database lokal dan dapat dimonitor langsung di menu laporan.
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonText: '📊 Lihat di Laporan',
                        showCancelButton: true,
                        cancelButtonText: 'Tutup',
                        confirmButtonColor: '#8b5cf6'
                    }).then((r) => {
                        if (r.isConfirmed) {
                            window.location.href = 'report_kms.php';
                        }
                    });
                } else {
                    Swal.fire({
                        title: '⚠️ Pengiriman Terhenti',
                        text: 'Terjadi kendala pada salah satu batch pengiriman. Periksa log detail di bawah formulir.',
                        icon: 'error',
                        confirmButtonText: 'Tutup',
                        confirmButtonColor: '#ef4444'
                    });
                }
                return;
            }

            // KASUS 2: SINGLE BATCH
            const singleConfirm = await Swal.fire({
                title: 'Konfirmasi Pengiriman',
                html: `Kirim <strong>${items.length} data ${itemTypeName}</strong> (${currentType}) ke server CEISA 4.0?<br><code style="font-size:12px; color:#c4b5fd;">Ref: ${currentPayload.header.refNumber}</code><br><span class="badge-pill badge-ceisa" style="margin-top:6px;">${targetEndpoint}</span>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '🚀 Ya, Kirim ke CEISA',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#8b5cf6',
                cancelButtonColor: '#64748b',
                reverseButtons: true
            });

            if (!singleConfirm.isConfirmed) {
                return;
            }

            btnSend.disabled = true;
            if (spinner) spinner.style.display = 'inline-block';
            if (icon) icon.style.display = 'none';

            try {
                showToast('Mengirim payload ke CEISA 4.0...', 'info');

                const response = await fetch('api/cocokms.php?action=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        endpoint: targetEndpoint,
                        payload: currentPayload
                    })
                });

                const result = await response.json();

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
                        ? `✅ <strong>Berhasil Terkirim:</strong> ${result.message || 'Data telah terkirim ke CEISA 4.0'}. Anda dapat memverifikasinya di menu <a href="report_kms.php" style="color:var(--accent-purple); text-decoration:underline;">Laporan Coarri Codeco</a>.`
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
                        text: result.message || 'Data telah berhasil dikirim ke CEISA 4.0!',
                        icon: 'success',
                        confirmButtonText: '📊 Buka Laporan',
                        showCancelButton: true,
                        cancelButtonText: 'Tutup',
                        confirmButtonColor: '#8b5cf6'
                    }).then((r) => {
                        if (r.isConfirmed) {
                            window.location.href = 'report_kms.php';
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'Pengiriman Gagal',
                        text: result.message || 'Pengiriman ditolak oleh gateway Bea Cukai CEISA 4.0',
                        icon: 'error',
                        confirmButtonColor: '#ef4444'
                    });
                }

            } catch (e) {
                console.error('Send Error:', e);
                showToast('Kesalahan koneksi saat mengirim: ' + e, 'error');
            } finally {
                btnSend.disabled = false;
                if (spinner) spinner.style.display = 'none';
                if (icon) icon.style.display = 'inline-block';
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
                    showToast('Token JWT berhasil diperbarui!', 'success');
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
            $('#tgl-awal, #tgl-akhir').on('change', function() {
                loadData(true);
            });

            // Inisialisasi awal
            updateViewLabels();
            loadData(false);
        });
    </script>
</body>
</html>
