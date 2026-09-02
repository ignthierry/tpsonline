<?php
/**
 * Laporan Data Terkirim YOR (Yard Occupancy Rate) CEISA 4.0
 * Endpoint: /kirim-laporan-yor
 * Diselaraskan sepenuhnya dengan report_cont.php, report_kms.php, report_tracking.php
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
    <title>Laporan YOR Terkirim CEISA 4.0 — <?= e($config['app_name']) ?></title>
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
        .content-area::-webkit-scrollbar { width: 8px; }
        .content-area::-webkit-scrollbar-track { background: var(--bg-base); }
        .content-area::-webkit-scrollbar-thumb { background: var(--border-medium); border-radius: 4px; }
        .content-area::-webkit-scrollbar-thumb:hover { background: var(--accent-blue); }

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
            .filter-grid { grid-template-columns: 1fr; }
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
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
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
        .btn-copy-ref:hover { color: #ffffff; }

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
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .btn-table-detail:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.3) 0%, rgba(37, 99, 235, 0.42) 100%);
            color: #93c5fd;
            transform: translateY(-1px);
        }

        /* Modal Details */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .modal-card {
            background: var(--bg-card);
            border: 1px solid var(--border-medium);
            border-radius: 12px;
            width: 100%;
            max-width: 950px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            overflow: hidden;
        }
        .btn-subtab {
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 8px 16px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.88rem;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-subtab.active {
            color: var(--accent-blue);
            border-bottom-color: var(--accent-blue);
        }
    </style>
</head>
<body data-login-time="<?= $_SESSION['login_time'] ?? time() ?>">
    <div class="dashboard">
        <div class="sidebar-overlay"></div>
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="header">
                <div class="header-left">
                    <button class="menu-toggle" id="menu-toggle">☰</button>
                    <div class="header-breadcrumb">
                        <span>CEISA 4.0</span>
                        <span class="separator">/</span>
                        <span>Laporan</span>
                        <span class="separator">/</span>
                        <span class="current">Laporan YOR Terkirim</span>
                    </div>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="theme-toggle" title="Ubah Mode (Gelap / Terang)">
                        <span class="theme-toggle-icon">🌙</span>
                        <span class="theme-toggle-text">Dark</span>
                    </button>
                    <button class="btn-refresh-token" onclick="refreshAccessToken()" title="Perbarui token JWT">
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

            <main class="content-area">
                <div class="report-container">

                    <!-- Filter Card -->
                    <div class="report-card">
                        <div style="margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                            <div>
                                <h2 style="margin:0; font-size:1.25rem; color:var(--text-primary); font-weight:700; display:flex; align-items:center; gap:8px;">
                                    <span>📊</span> Laporan YOR (Yard Occupancy Rate) Terkirim
                                </h2>
                                <p style="margin:4px 0 0; color:var(--text-secondary); font-size:0.88rem;">
                                    Monitoring riwayat pelaporan utilisasi lapangan (YOR) & kapasitas gudang TPS ke sistem Bea Cukai.
                                </p>
                            </div>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <a href="laporan_yor.php" class="btn-action-sm" style="text-decoration:none; padding:8px 16px; border-radius:8px; font-weight:600; display:inline-flex; align-items:center; gap:6px; background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.35);">
                                    <span>➕</span> Kirim Laporan YOR Baru
                                </a>
                                <span class="badge-pill badge-ceisa">POST /kirim-laporan-yor</span>
                            </div>
                        </div>

                        <form id="filter-form" onsubmit="event.preventDefault(); loadReportData(true);">
                            <div class="filter-grid">
                                <div class="input-group">
                                    <label for="tgl-awal">Tanggal Awal</label>
                                    <input type="date" id="tgl-awal" class="input-control" value="<?= $firstDayOfMonth ?>" required onchange="loadReportData(false)">
                                </div>
                                <div class="input-group">
                                    <label for="tgl-akhir">Tanggal Akhir</label>
                                    <input type="date" id="tgl-akhir" class="input-control" value="<?= $todayDate ?>" required onchange="loadReportData(false)">
                                </div>
                                <div style="display:flex; gap:10px;">
                                    <button type="submit" class="btn-action-sm" style="height:44px; padding:0 22px; font-weight:600; border-radius:8px; display:inline-flex; align-items:center; gap:6px; background:linear-gradient(135deg, #3b82f6, #2563eb); color:#fff; border:none; cursor:pointer;">
                                        <span>🔍</span> Filter Data
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Stats Row -->
                    <div class="stats-bar" id="stats-bar" style="display:none;">
                        <div class="stat-item">
                            <span class="stat-label">Total Laporan YOR</span>
                            <span class="stat-value" id="stat-total" style="color:var(--accent-blue);">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Rata-rata YOR Impor</span>
                            <span class="stat-value" id="stat-avg-impor" style="color:#10b981;">0.00%</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Kontainer Impor</span>
                            <span class="stat-value" id="stat-total-cont" style="color:#f59e0b;">0 Box</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Kegiatan Ekspor</span>
                            <span class="stat-value" style="color:var(--text-muted); font-size:1.3rem;">Nihil (0%)</span>
                        </div>
                    </div>

                    <!-- Result Panel -->
                    <div class="report-card" id="result-card" style="display:none;">
                        <div class="tabs-nav">
                            <button type="button" class="tab-btn active" onclick="switchTab('tab-table', this)">
                                <span>📋</span> Pratinjau Tabel Laporan (<span id="tab-count">0</span>)
                            </button>
                            <button type="button" class="tab-btn" onclick="switchTab('tab-json', this)">
                                <span>⚡</span> Raw JSON Payload & Log
                            </button>
                        </div>

                        <!-- Tab 1: Tabel Laporan YOR -->
                        <div class="tab-content active" id="tab-table">
                            <div class="table-responsive">
                                <table id="table-report" class="display responsive nowrap" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th style="width:40px; text-align:center;">No</th>
                                            <th>Nomor Referensi (refNumber)</th>
                                            <th>Tanggal Laporan</th>
                                            <th>YOR Impor (%)</th>
                                            <th>Total Kontainer</th>
                                            <th>TPS / Gudang</th>
                                            <th>Status Gateway</th>
                                            <th style="text-align:center; width:150px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="table-body"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab 2: Raw JSON -->
                        <div class="tab-content" id="tab-json">
                            <div style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <span style="font-size:0.88rem; color:var(--text-secondary);">
                                    Log JSON Response & Payload Laporan YOR CEISA 4.0:
                                </span>
                                <div style="display:flex; gap:10px;">
                                    <button type="button" class="btn-action-sm" onclick="copyJson()">
                                        <span>📋</span> Salin JSON
                                    </button>
                                </div>
                            </div>
                            <textarea id="json-viewer" class="json-box" readonly></textarea>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Modal Detail Laporan YOR -->
    <div id="modal-detail-yor" class="modal-overlay" onclick="if(event.target===this)closeDetailModal()">
        <div class="modal-card">
            <div style="padding:18px 24px; border-bottom:1px solid var(--border-medium); display:flex; justify-content:space-between; align-items:center; background:var(--bg-surface);">
                <div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.35rem;">📊</span>
                        <h3 style="margin:0; font-size:1.15rem; color:var(--text-primary); font-weight:700;">
                            Rincian Laporan YOR CEISA 4.0
                        </h3>
                    </div>
                    <div style="margin-top:4px; font-size:0.85rem; color:var(--text-secondary); display:flex; align-items:center; gap:10px;">
                        <span>Ref:</span>
                        <code id="modal-ref-no" style="font-family:'JetBrains Mono',monospace; color:var(--accent-blue); font-weight:700;">-</code>
                        <span id="modal-status-badge" class="badge-pill badge-in">SUCCESS</span>
                    </div>
                </div>
                <button type="button" onclick="closeDetailModal()" style="background:none; border:none; color:var(--text-secondary); font-size:1.8rem; cursor:pointer; padding:2px 8px; line-height:1; border-radius:6px;" title="Tutup">&times;</button>
            </div>

            <div style="padding:20px 24px; overflow-y:auto; flex-grow:1;">
                <div id="modal-loading" style="text-align:center; padding:40px;">
                    <span class="pulse-dot" style="background:#3b82f6;"></span>
                    <p style="margin-top:10px; color:var(--text-secondary); font-size:0.9rem;">Memuat rincian laporan YOR...</p>
                </div>

                <div id="modal-content" style="display:none;">
                    <!-- Grid Header Info -->
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:20px; background:var(--bg-surface); padding:16px; border-radius:10px; border:1px solid var(--border-subtle);">
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-secondary); font-weight:600; text-transform:uppercase;">Tanggal Laporan</div>
                            <div id="modal-tgl" style="font-size:0.92rem; font-weight:600; color:var(--text-primary); margin-top:2px;">-</div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-secondary); font-weight:600; text-transform:uppercase;">TPS / Gudang</div>
                            <div id="modal-tps-gudang" style="font-size:0.92rem; font-weight:600; color:var(--text-primary); margin-top:2px;">-</div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-secondary); font-weight:600; text-transform:uppercase;">Waktu Pengiriman</div>
                            <div id="modal-created-at" style="font-size:0.92rem; font-weight:600; color:var(--text-primary); margin-top:2px;">-</div>
                        </div>
                    </div>

                    <!-- Subtabs -->
                    <div style="display:flex; border-bottom:1px solid var(--border-medium); margin-bottom:16px; gap:8px;">
                        <button type="button" class="btn-subtab active" id="subtab-btn-table" onclick="switchModalSubtab('table')">
                            📊 Rincian Utilisasi (Impor vs Ekspor)
                        </button>
                        <button type="button" class="btn-subtab" id="subtab-btn-raw" onclick="switchModalSubtab('raw')">
                            ⚡ Respon & Payload Gateway
                        </button>
                    </div>

                    <!-- Content Subtab 1: Table -->
                    <div id="modal-subtab-table">
                        <table class="batch-table" style="width:100%; border-collapse:collapse; margin-bottom:16px;">
                            <thead>
                                <tr style="background:var(--bg-surface);">
                                    <th>Kategori</th>
                                    <th>Kapasitas Lapangan</th>
                                    <th>Kapasitas Gudang</th>
                                    <th>Kontainer 20f</th>
                                    <th>Kontainer 40f</th>
                                    <th>Kontainer 45f</th>
                                    <th>Total Box</th>
                                    <th>Total Kemasan</th>
                                    <th>YOR (%)</th>
                                </tr>
                            </thead>
                            <tbody id="modal-table-body"></tbody>
                        </table>
                    </div>

                    <!-- Content Subtab 2: Raw JSON -->
                    <div id="modal-subtab-raw" style="display:none;">
                        <pre id="modal-raw-viewer" style="background:#0d131f; color:#7dd3fc; font-family:'JetBrains Mono',monospace; font-size:12px; padding:16px; border-radius:8px; max-height:360px; overflow:auto; margin:0; border:1px solid var(--border-medium);"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="toast-container" style="position:fixed; bottom:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:10px;"></div>

    <script>
        let dataTableInstance = null;
        let cachedRows = [];
        let rawApiResponse = {};

        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.style.cssText = 'padding:12px 20px;border-radius:8px;font-size:0.9rem;font-weight:500;box-shadow:0 4px 14px rgba(0,0,0,0.3);transition:all 0.3s ease;display:flex;align-items:center;gap:10px;';
            if (type === 'success') {
                toast.style.background = '#065f46'; toast.style.color = '#a7f3d0'; toast.style.border = '1px solid #10b981';
                toast.innerHTML = '<span>✅</span> ' + message;
            } else if (type === 'error') {
                toast.style.background = '#7f1d1d'; toast.style.color = '#fecaca'; toast.style.border = '1px solid #ef4444';
                toast.innerHTML = '<span>❌</span> ' + message;
            } else {
                toast.style.background = '#1e293b'; toast.style.color = '#e2e8f0'; toast.style.border = '1px solid #475569';
                toast.innerHTML = '<span>ℹ️</span> ' + message;
            }
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(10px)'; setTimeout(() => toast.remove(), 300); }, 4000);
        }

        async function refreshAccessToken() {
            try {
                showToast('Memperbarui token dari SSO Bea Cukai...', 'info');
                const res = await fetch('api/auth.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({refresh:true}) });
                const data = await res.json();
                if (data.success) showToast('Token berhasil diperbarui!', 'success');
                else showToast('Gagal refresh token: ' + (data.message||'Error'), 'error');
            } catch(e) { showToast('Koneksi auth error: ' + e.message, 'error'); }
        }

        function switchTab(tabId, btn) {
            $('.tab-btn').removeClass('active');
            $('.tab-content').removeClass('active');
            if (btn) $(btn).addClass('active');
            $('#' + tabId).addClass('active');
        }

        function switchModalSubtab(type) {
            $('.btn-subtab').removeClass('active');
            $('#modal-subtab-table').hide();
            $('#modal-subtab-raw').hide();
            if (type === 'table') {
                $('#subtab-btn-table').addClass('active');
                $('#modal-subtab-table').show();
            } else {
                $('#subtab-btn-raw').addClass('active');
                $('#modal-subtab-raw').show();
            }
        }

        function loadReportData(showNotify = false) {
            const tglAwal = $('#tgl-awal').val();
            const tglAkhir = $('#tgl-akhir').val();

            $.ajax({
                url: 'api/laporan_yor.php',
                type: 'GET',
                data: {
                    action: 'report',
                    start_date: tglAwal,
                    end_date: tglAkhir
                },
                dataType: 'json',
                success: function(res) {
                    rawApiResponse = res;
                    cachedRows = res.rows || [];
                    const summary = res.summary || {};

                    $('#stats-bar').show();
                    $('#result-card').show();

                    $('#stat-total').text(summary.total || 0);
                    $('#stat-avg-impor').text((summary.avg_impor || 0) + '%');
                    $('#stat-avg-ekspor').text((summary.avg_ekspor || 0) + '%');
                    $('#stat-total-cont').text((summary.total_kontainer || 0) + ' Box');
                    $('#tab-count').text(cachedRows.length);

                    renderReportTable(cachedRows);
                    $('#json-viewer').val(JSON.stringify(rawApiResponse, null, 4));

                    if (showNotify) {
                        showToast(`Ditemukan ${cachedRows.length} laporan YOR!`, 'success');
                    }
                },
                error: function(xhr, status, error) {
                    showToast('Gagal memuat data laporan: ' + error, 'error');
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
                const isOk = (r.status_kirim === 'SUCCESS');
                tr.innerHTML = `
                    <td style="text-align:center;">${idx + 1}</td>
                    <td>
                        <span class="ref-badge">
                            <span>📑</span>
                            <span>${r.ref_number}</span>
                            <button type="button" class="btn-copy-ref" onclick="copyText('${r.ref_number}')" title="Salin Ref Number">📋</button>
                        </span>
                    </td>
                    <td><b style="color:var(--text-primary);">${r.tanggal_laporan}</b></td>
                    <td><span class="badge-pill" style="background:rgba(16,185,129,0.15); color:#10b981; font-weight:700; font-size:0.95rem;">${r.impor_yor}%</span></td>
                    <td><b>${r.impor_kontainer}</b> <small style="color:var(--text-secondary);">Box</small></td>
                    <td><code>${r.kode_tps}</code> / <code>${r.kode_gudang}</code></td>
                    <td><span class="badge-pill ${isOk ? 'badge-in' : 'badge-out'}">${isOk ? 'HTTP 200 OK' : 'HTTP ' + r.http_code + ' FAILED'}</span></td>
                    <td style="text-align:center;">
                        <button type="button" class="btn-table-detail" onclick="openDetailModal(${r.id})">
                            <span>🔍</span> Detail YOR
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            dataTableInstance = $('#table-report').DataTable({
                responsive: true,
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ baris",
                    info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                    paginate: { first: "«", previous: "‹", next: "›", last: "»" },
                    emptyTable: "Tidak ada riwayat pengiriman Laporan YOR pada rentang tanggal ini"
                },
                order: [[0, 'asc']]
            });
        }

        function openDetailModal(id) {
            $('#modal-detail-yor').css('display', 'flex');
            $('#modal-loading').show();
            $('#modal-content').hide();
            switchModalSubtab('table');

            $.ajax({
                url: 'api/laporan_yor.php',
                type: 'GET',
                data: { action: 'detail', id: id },
                dataType: 'json',
                success: function(res) {
                    $('#modal-loading').hide();
                    $('#modal-content').show();

                    if (!res.success) {
                        showToast(res.message || 'Gagal memuat detail', 'error');
                        return;
                    }

                    const d = res.data || {};
                    const p = res.payload || {};
                    const imp = p.impor || {};
                    const eksp = p.ekspor || {};

                    $('#modal-ref-no').text(d.ref_number || p.refNumber || '-');
                    $('#modal-tgl').text(d.tanggal_laporan || p.tanggalLaporan || '-');
                    $('#modal-tps-gudang').text((d.kode_tps || p.kodeTps || 'PSU0') + ' / ' + (d.kode_gudang || p.kodeGudang || 'CPSU'));
                    $('#modal-created-at').text(d.created_at || '-');

                    const badge = $('#modal-status-badge');
                    badge.removeClass('badge-in badge-out');
                    if (d.status_kirim === 'SUCCESS') {
                        badge.addClass('badge-in').text('HTTP 200 OK');
                    } else {
                        badge.addClass('badge-out').text('HTTP ' + d.http_code + ' FAILED');
                    }

                    // Render Table
                    const tbody = document.getElementById('modal-table-body');
                    tbody.innerHTML = `
                        <tr>
                            <td><strong style="color:#10b981;">📥 IMPOR</strong></td>
                            <td>${imp.kapasitasLapangan || 0}</td>
                            <td>${imp.kapasitasGudang || 0}</td>
                            <td>${imp.jumlahKontainer20f || 0}</td>
                            <td>${imp.jumlahKontainer40f || 0}</td>
                            <td>${imp.jumlahKontainer45f || 0}</td>
                            <td><b>${imp.totalKontainer || 0}</b></td>
                            <td>${imp.totalKemasan || 0}</td>
                            <td><span class="badge-pill badge-in" style="font-weight:700;">${imp.yor || 0}%</span></td>
                        </tr>
                        <tr>
                            <td><strong style="color:#a78bfa;">📤 EKSPOR</strong></td>
                            <td>${eksp.kapasitasLapangan || 0}</td>
                            <td>${eksp.kapasitasGudang || 0}</td>
                            <td>${eksp.jumlahKontainer20f || 0}</td>
                            <td>${eksp.jumlahKontainer40f || 0}</td>
                            <td>${eksp.jumlahKontainer45f || 0}</td>
                            <td><b>${eksp.totalKontainer || 0}</b></td>
                            <td>${eksp.totalKemasan || 0}</td>
                            <td><span class="badge-pill" style="background:rgba(139,92,246,0.15); color:#a78bfa; font-weight:700;">${eksp.yor || 0}%</span></td>
                        </tr>
                    `;

                    // Raw Viewer
                    $('#modal-raw-viewer').text(JSON.stringify({
                        laporanData: d,
                        payloadSent: p,
                        ceisaResponse: res.response
                    }, null, 4));
                },
                error: function(xhr, status, error) {
                    $('#modal-loading').hide();
                    showToast('Gagal memuat detail YOR: ' + error, 'error');
                }
            });
        }

        function closeDetailModal() {
            $('#modal-detail-yor').hide();
        }

        function copyText(txt) {
            navigator.clipboard.writeText(txt).then(() => showToast('Berhasil disalin ke clipboard!', 'success'));
        }

        function copyJson() {
            const txt = $('#json-viewer').val();
            navigator.clipboard.writeText(txt).then(() => showToast('JSON log berhasil disalin!', 'success'));
        }

        $(document).ready(function() {
            loadReportData(false);

            // Theme toggle
            const themeBtn = document.getElementById('theme-toggle');
            if (themeBtn) {
                themeBtn.addEventListener('click', () => {
                    const cur = document.documentElement.getAttribute('data-theme') || 'dark';
                    const next = cur === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', next);
                    localStorage.setItem('ceisa_theme', next);
                    const ic = document.querySelector('.theme-toggle-icon');
                    const tx = document.querySelector('.theme-toggle-text');
                    if (ic) ic.textContent = next === 'dark' ? '🌙' : '☀️';
                    if (tx) tx.textContent = next === 'dark' ? 'Dark' : 'Light';
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
