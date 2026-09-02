<?php
/**
 * TPS Tracking Batch (Kirim Banyak Kontainer Sekaligus) CEISA 4.0 — TPS Online Dashboard
 * Halaman perekaman data tracking pergerakan kontainer secara batch
 * Target Endpoint: POST /tps-tracking/batch
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

requireAuth();

$config = require __DIR__ . '/config.php';
$username = $_SESSION['name'] ?? $_SESSION['username'] ?? $config['username'] ?? 'User';
$loginTime = $_SESSION['login_time'] ?? time();
$userInitial = strtoupper(substr($username, 0, 2));

$nowDmyHis = date('d-m-Y H:i:s');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TPS Tracking Batch — <?= e($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
        .content-area::-webkit-scrollbar { width: 8px; }
        .content-area::-webkit-scrollbar-track { background: var(--bg-base); }
        .content-area::-webkit-scrollbar-thumb { background: var(--border-medium); border-radius: 4px; }
        .content-area::-webkit-scrollbar-thumb:hover { background: var(--accent-blue); }
        .batch-container {
            padding: 24px 24px 80px 24px;
            max-width: 1500px;
            margin: 0 auto;
        }
        .batch-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid var(--border-subtle);
            margin-bottom: 24px;
        }
        .batch-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 1100px) {
            .batch-grid {
                grid-template-columns: 1fr;
            }
        }
        .batch-table-wrap {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border-subtle);
        }
        .batch-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        .batch-table th {
            background: var(--bg-surface);
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-size: 0.72rem;
            padding: 10px 8px;
            position: sticky;
            top: 0;
            z-index: 2;
            border-bottom: 2px solid var(--border-medium);
            white-space: nowrap;
        }
        .batch-table td {
            padding: 6px 6px;
            border-bottom: 1px solid var(--border-subtle);
            vertical-align: middle;
        }
        .batch-table tr:hover td {
            background: rgba(59, 130, 246, 0.05);
        }
        .batch-table input, .batch-table select {
            width: 100%;
            padding: 6px 8px;
            background: var(--bg-input);
            border: 1px solid var(--border-medium);
            border-radius: 6px;
            color: var(--text-primary);
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.82rem;
            transition: border-color 0.2s;
        }
        .batch-table input:focus, .batch-table select:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
        }
        .batch-table .col-no { width: 36px; text-align: center; color: var(--text-secondary); font-weight: 600; }
        .batch-table .col-cont { min-width: 130px; }
        .batch-table .col-sz { width: 60px; }
        .batch-table .col-jenis { width: 70px; }
        .batch-table .col-keg { width: 80px; }
        .batch-table .col-waktu { min-width: 160px; }
        .batch-table .col-blok { width: 65px; }
        .batch-table .col-slot { width: 50px; }
        .batch-table .col-tier { width: 50px; }
        .batch-table .col-nopol { min-width: 90px; }
        .batch-table .col-bl { min-width: 110px; }
        .batch-table .col-dok { width: 70px; }
        .batch-table .col-nodok { min-width: 90px; }
        .batch-table .col-act { width: 36px; text-align: center; }

        .batch-table input.cont-input { text-transform: uppercase; font-weight: 600; }
        .batch-table input.nopol-input { text-transform: uppercase; }

        .btn-add-row {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.35);
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-add-row:hover { background: rgba(16, 185, 129, 0.25); }

        .btn-del-row {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.15s;
        }
        .btn-del-row:hover { background: rgba(239, 68, 68, 0.3); }

        .json-box {
            width: 100%;
            min-height: 300px;
            max-height: 500px;
            background: #0d131f;
            color: #a5f3fc;
            padding: 16px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            line-height: 1.6;
            border: 1px solid #1e293b;
            resize: vertical;
        }
        .btn-send-batch {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        }
        .btn-send-batch:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4); }
        .btn-send-batch:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }

        .global-field {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
        }
        .global-field label {
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-size: 0.72rem;
            white-space: nowrap;
        }
        .global-field input {
            padding: 6px 10px;
            background: var(--bg-card);
            border: 1px solid var(--border-medium);
            border-radius: 6px;
            color: var(--text-primary);
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            font-size: 0.85rem;
            width: 70px;
            cursor: not-allowed;
            opacity: 0.9;
        }
        .stat-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .stat-item {
            padding: 8px 16px;
            background: var(--bg-surface);
            border-radius: 8px;
            border: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }
        .stat-item .stat-val {
            font-weight: 700;
            color: var(--accent-blue);
            font-family: 'JetBrains Mono', monospace;
        }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
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
            max-width: 960px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            overflow: hidden;
        }
    </style>
</head>
<body data-login-time="<?= $loginTime ?>">
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
                        <span>Kirim Dokumen</span>
                        <span class="separator">/</span>
                        <span class="current">TPS Tracking Batch</span>
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
                <div class="batch-container">

                    <!-- Header Card -->
                    <div class="batch-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                            <div>
                                <h2 style="margin: 0; font-size: 1.3rem; color: var(--text-primary); font-weight: 700; display: flex; align-items: center; gap: 10px;">
                                    <span>📦</span> TPS Tracking Batch — Kirim Banyak Kontainer
                                </h2>
                                <p style="margin: 6px 0 0; color: var(--text-secondary); font-size: 0.88rem;">
                                    Kirim data tracking pergerakan <strong>banyak kontainer sekaligus</strong> ke sistem Bea Cukai via REST API CEISA 4.0.
                                </p>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <a href="report_tracking_batch.php" class="btn-action-sm" style="text-decoration:none; padding:8px 16px; border-radius:8px; font-weight:600; display:inline-flex; align-items:center; gap:6px; background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.35);">
                                    <span>📊</span> Laporan Batch Terkirim
                                </a>
                                <a href="tps_tracking.php" class="btn-action-sm" style="text-decoration:none; padding:8px 16px; border-radius:8px; font-weight:600; display:inline-flex; align-items:center; gap:6px; background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.35);">
                                    <span>📍</span> Kirim Satuan
                                </a>
                                <span class="badge-pill badge-ceisa">POST /tps-tracking/batch</span>
                                <span class="badge-pill" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                                    <span class="pulse-dot"></span> CEISA 4.0 OpenAPI
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Global Settings & Stat -->
                    <div class="batch-card" style="padding: 16px 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                                <div class="global-field">
                                    <label>Kode TPS:</label>
                                    <input type="text" id="global-kode-tps" value="PSU0" readonly title="Kode TPS Baku (readonly)">
                                </div>
                                <div class="global-field">
                                    <label>Kode Gudang:</label>
                                    <input type="text" id="global-kode-gudang" value="CPSU" readonly title="Kode Gudang Baku (readonly)">
                                </div>
                                <div class="global-field">
                                    <label>Kode Kegiatan:</label>
                                    <select id="global-kode-kegiatan" style="padding:6px 8px; background:var(--bg-input); border:1px solid var(--border-medium); border-radius:6px; color:var(--text-primary); font-size:0.82rem; min-width: 200px;" onchange="applyGlobalKegiatan()">
                                        <optgroup label="📥 Impor — Lini 1 (Pelabuhan)">
                                            <option value="1">1 — DISCHARGE</option>
                                            <option value="3">3 — GATE OUT (Codeco Impor)</option>
                                            <option value="10">10 — STACKING DISCHARGE</option>
                                            <option value="12">12 — TRUCK IN</option>
                                            <option value="13">13 — PICKUP</option>
                                            <option value="14">14 — BEHANDLE</option>
                                            <option value="15">15 — SHIFTING</option>
                                            <option value="16">16 — STRIPPING STUFFING</option>
                                        </optgroup>
                                        <optgroup label="📤 Ekspor — Lini 1 (Pelabuhan)">
                                            <option value="2">2 — LOADING</option>
                                            <option value="4">4 — GATE IN RECEIVING (Codeco Ekspor)</option>
                                            <option value="11">11 — STACKING EKSPOR</option>
                                        </optgroup>
                                        <optgroup label="🏭 Impor — Lini 2 (PLP / Depo)">
                                            <option value="5" selected>5 — GATE IN PLP</option>
                                            <option value="6">6 — GATE OUT LINI 2</option>
                                            <option value="9">9 — GATE OUT BATAL EKSPOR</option>
                                            <option value="17">17 — STACKING DISCHARGE LINI 2</option>
                                            <option value="19">19 — TRUCK IN LINI 2</option>
                                            <option value="20">20 — PICKUP LINI 2</option>
                                            <option value="21">21 — BEHANDLE LINI 2</option>
                                            <option value="22">22 — SHIFTING LINI 2</option>
                                            <option value="23">23 — STRIPPING STUFFING LINI 2</option>
                                        </optgroup>
                                        <optgroup label="🚢 Ekspor — Lini 2">
                                            <option value="7">7 — GATE IN EKSPOR LINI 2</option>
                                            <option value="8">8 — GATE OUT EKSPOR LINI 2</option>
                                            <option value="18">18 — STACKING EKSPOR LINI 2</option>
                                            <option value="24">24 — STUFFING KE GUDANG LINI 2</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>
                            <div class="stat-bar" style="margin-bottom: 0;">
                                <div class="stat-item">
                                    <span>📦</span>
                                    <span>Total Baris:</span>
                                    <span class="stat-val" id="stat-total-rows">1</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Batch Grid: Table + JSON Preview -->
                    <div class="batch-grid">

                        <!-- LEFT: Tabel Input Batch -->
                        <div class="batch-card" style="margin-bottom: 0; padding: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 1.1rem;">📝</span>
                                    <strong style="color: var(--text-primary); font-size: 0.95rem;">Daftar Kontainer Batch</strong>
                                </div>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <button type="button" class="btn-add-row" style="background:rgba(139,92,246,0.15); color:#a78bfa; border-color:rgba(139,92,246,0.35);" onclick="openPlpModal()">
                                        <span>📥</span> Tarik dari PLP
                                    </button>
                                    <button type="button" class="btn-add-row" onclick="addRow()">
                                        <span>➕</span> Tambah Baris
                                    </button>
                                    <button type="button" class="btn-add-row" style="background:rgba(59,130,246,0.15); color:#60a5fa; border-color:rgba(59,130,246,0.35);" onclick="setAllWaktuNow()">
                                        <span>⏱️</span> Set Waktu Sekarang
                                    </button>
                                    <button type="button" class="btn-add-row" style="background:rgba(239,68,68,0.12); color:#ef4444; border-color:rgba(239,68,68,0.3);" onclick="clearAllRows()">
                                        <span>🗑️</span> Kosongkan
                                    </button>
                                </div>
                            </div>

                            <div class="batch-table-wrap" style="max-height: 500px; overflow: auto;">
                                <table class="batch-table" id="batch-table">
                                    <thead>
                                        <tr>
                                            <th class="col-no">#</th>
                                            <th class="col-cont">No Kontainer *</th>
                                            <th class="col-sz">Ukuran *</th>
                                            <th class="col-jenis">Jenis *</th>
                                            <th class="col-waktu">Waktu Kegiatan *</th>
                                            <th class="col-blok">Block</th>
                                            <th class="col-slot">Slot</th>
                                            <th class="col-tier">Tier</th>
                                            <th class="col-nopol">Nopol</th>
                                            <th class="col-bl">No B/L</th>
                                            <th class="col-dok">Kd Dok</th>
                                            <th class="col-nodok">No Dok</th>
                                            <th class="col-act"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="batch-tbody">
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- RIGHT: JSON Preview + Send -->
                        <div style="position: sticky; top: 80px;">
                            <div class="batch-card" style="margin-bottom: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 1.15rem;">⚡</span>
                                        <strong style="color: var(--text-primary); font-size: 1rem;">Live JSON Array</strong>
                                    </div>
                                    <button type="button" class="btn-action-sm" onclick="copyBatchJson()" style="padding:6px 14px; border-radius:6px; font-weight:600; display:inline-flex; align-items:center; gap:4px; background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.35); cursor:pointer; font-size:0.8rem;">
                                        <span>📋</span> Salin JSON
                                    </button>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.8rem;">
                                    <span style="color: var(--text-secondary);">Target: <code>/tps-tracking/batch</code></span>
                                    <span id="batch-status" style="color: #10b981; font-weight: 600;">✓ Array siap dikirim</span>
                                </div>

                                <textarea id="json-batch-preview" class="json-box" readonly></textarea>

                                <div style="margin-top: 20px;">
                                    <button type="button" id="btn-send-batch" class="btn-send-batch" onclick="sendBatch()">
                                        <span id="batch-spinner" style="display: none;">⏳</span>
                                        <span id="batch-icon">🚀</span>
                                        <span id="batch-text">Kirim Batch Tracking ke CEISA 4.0</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Response Card -->
                            <div id="batch-result-card" class="batch-card" style="display: none; margin-top: 20px; padding: 20px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span id="batch-result-badge" class="badge-pill"></span>
                                        <span id="batch-result-time" style="font-size: 0.85rem; color: var(--text-secondary);"></span>
                                    </div>
                                    <button type="button" style="padding:6px 14px; border-radius:6px; font-weight:600; display:inline-flex; align-items:center; gap:4px; background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.35); cursor:pointer; font-size:0.8rem;" onclick="$('#batch-raw-response').slideToggle(200)">
                                        <span>📋</span> Toggle Raw Response
                                    </button>
                                </div>
                                <div id="batch-result-msg" style="font-size: 0.92rem; color: var(--text-primary); margin-bottom: 12px;"></div>
                                <pre id="batch-raw-response" style="display: none; background: #0d131f; color: #a5f3fc; padding: 16px; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 13px; max-height: 260px; overflow: auto; margin: 0;"></pre>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Modal Tarik Kontainer dari PLP -->
    <div id="modal-plp-picker" class="modal-overlay" onclick="if(event.target===this)closePlpModal()">
        <div class="modal-card">
            <div style="padding:18px 24px; border-bottom:1px solid var(--border-medium); display:flex; justify-content:space-between; align-items:center; background:var(--bg-surface);">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:1.35rem;">📦</span>
                    <h3 style="margin:0; font-size:1.15rem; color:var(--text-primary); font-weight:700;">
                        Pilih Data Kontainer dari Database PLP (tppcontplp)
                    </h3>
                </div>
                <button type="button" onclick="closePlpModal()" style="background:none; border:none; color:var(--text-secondary); font-size:1.8rem; cursor:pointer; padding:2px 8px; line-height:1; border-radius:6px;" title="Tutup">&times;</button>
            </div>
            <div style="padding:16px 24px; border-bottom:1px solid var(--border-subtle); display:flex; gap:12px; align-items:center; background:var(--bg-base);">
                <input type="text" id="plp-search-input" placeholder="🔍 Cari Nomor Kontainer / No B/L / Nopol..." style="flex:1; padding:10px 14px; background:var(--bg-input); border:1px solid var(--border-medium); border-radius:8px; color:var(--text-primary); font-size:0.9rem;" oninput="debouncePlpSearch()">
                <button type="button" class="btn-action-sm" onclick="loadPlpContainers($('#plp-search-input').val())" style="padding:10px 16px; border-radius:8px; font-weight:600; background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.35); cursor:pointer;">
                    <span>🔄</span> Cari
                </button>
            </div>
            <div style="padding:16px 24px; overflow-y:auto; flex-grow:1; max-height:52vh;">
                <div id="plp-loading" style="display:none; text-align:center; padding:30px;">
                    <span class="pulse-dot" style="background:#3b82f6;"></span>
                    <p style="margin-top:10px; color:var(--text-secondary); font-size:0.88rem;">Memuat data kontainer dari database operasional...</p>
                </div>
                <table class="batch-table" id="table-plp-picker">
                    <thead>
                        <tr>
                            <th style="width:36px; text-align:center;"><input type="checkbox" id="check-all-plp" onchange="toggleSelectAllPlp(this)"></th>
                            <th>No Kontainer</th>
                            <th>Ukuran</th>
                            <th>Status</th>
                            <th>Posisi Yard</th>
                            <th>Nopol</th>
                            <th>No B/L</th>
                            <th>No Dokumen</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-plp-picker"></tbody>
                </table>
            </div>
            <div style="padding:16px 24px; border-top:1px solid var(--border-medium); display:flex; justify-content:space-between; align-items:center; background:var(--bg-surface);">
                <span id="plp-selected-count" style="font-size:0.85rem; color:var(--text-secondary);">0 kontainer dipilih</span>
                <div style="display:flex; gap:10px;">
                    <button type="button" onclick="closePlpModal()" style="padding:8px 18px; border-radius:8px; background:transparent; border:1px solid var(--border-medium); color:var(--text-secondary); font-weight:600; cursor:pointer;">Batal</button>
                    <button type="button" onclick="insertSelectedPlp()" style="padding:8px 22px; border-radius:8px; background:linear-gradient(135deg, #10b981, #059669); color:#fff; border:none; font-weight:700; cursor:pointer; box-shadow:0 2px 8px rgba(16,185,129,0.3);">
                        ➕ Masukkan ke Tabel Batch
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="toast-container" style="position:fixed; bottom:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:10px;"></div>

    <script>
        let rowCounter = 0;
        let plpLoadedData = [];
        let plpSearchTimeout = null;

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

        function getNowFormatted() {
            const now = new Date();
            const d = String(now.getDate()).padStart(2,'0');
            const m = String(now.getMonth()+1).padStart(2,'0');
            const y = now.getFullYear();
            const H = String(now.getHours()).padStart(2,'0');
            const i = String(now.getMinutes()).padStart(2,'0');
            const s = String(now.getSeconds()).padStart(2,'0');
            return `${d}-${m}-${y} ${H}:${i}:${s}`;
        }

        function addRow(data = {}) {
            rowCounter++;
            const idx = rowCounter;
            const nowStr = data.waktuKegiatan || getNowFormatted();
            const sz = data.size || (data.size_type && data.size_type.includes('20') ? '20' : (data.size_type && data.size_type.includes('45') ? '45' : '40'));
            let jenis = data.jenis || '8';
            if (data.status === 'EMPTY') jenis = '4';
            else if (data.status === 'LCL') jenis = '7';

            const tr = document.createElement('tr');
            tr.id = `row-${idx}`;
            tr.innerHTML = `
                <td class="col-no">${idx}</td>
                <td class="col-cont"><input type="text" class="cont-input" data-field="nomorKontainer" value="${data.container_no || ''}" maxlength="11" placeholder="MSNU1234567" oninput="this.value=this.value.toUpperCase(); updateBatchJson();" onblur="autoFillSingleRow(this)"></td>
                <td class="col-sz">
                    <select data-field="ukuranKontainer" onchange="updateBatchJson()">
                        <option value="20" ${sz==='20'?'selected':''}>20</option>
                        <option value="40" ${sz==='40'?'selected':''}>40</option>
                        <option value="45" ${sz==='45'?'selected':''}>45</option>
                    </select>
                </td>
                <td class="col-jenis">
                    <select data-field="jenisKontainer" onchange="updateBatchJson()">
                        <option value="8" ${jenis==='8'?'selected':''}>FCL</option>
                        <option value="7" ${jenis==='7'?'selected':''}>LCL</option>
                        <option value="4" ${jenis==='4'?'selected':''}>EMPTY</option>
                    </select>
                </td>
                <td class="col-waktu"><input type="text" data-field="waktuKegiatan" value="${nowStr}" placeholder="dd-mm-yyyy HH:mm:ss" oninput="updateBatchJson()"></td>
                <td class="col-blok"><input type="text" data-field="block" value="${data.yard_block || ''}" maxlength="10" oninput="updateBatchJson()"></td>
                <td class="col-slot"><input type="text" data-field="slot" value="${data.slot || ''}" maxlength="10" oninput="updateBatchJson()"></td>
                <td class="col-tier"><input type="text" data-field="tier" value="${data.tier || ''}" maxlength="10" oninput="updateBatchJson()"></td>
                <td class="col-nopol"><input type="text" class="nopol-input" data-field="nomorPolisi" value="${data.nopol || ''}" maxlength="15" oninput="this.value=this.value.toUpperCase(); updateBatchJson()"></td>
                <td class="col-bl"><input type="text" data-field="nomorBlAwb" value="${data.no_bl || ''}" maxlength="50" oninput="updateBatchJson()"></td>
                <td class="col-dok"><input type="text" data-field="kodeDokumen" value="${data.kode_dok || '3'}" maxlength="10" oninput="updateBatchJson()"></td>
                <td class="col-nodok"><input type="text" data-field="nomorDokumen" value="${data.no_dokumen || ''}" maxlength="50" oninput="updateBatchJson()"></td>
                <td class="col-act"><button type="button" class="btn-del-row" onclick="removeRow(${idx})" title="Hapus baris">×</button></td>
            `;
            document.getElementById('batch-tbody').appendChild(tr);
            updateBatchJson();
            updateRowNumbers();
        }

        async function autoFillSingleRow(inputEl) {
            const val = (inputEl.value || '').trim().toUpperCase();
            if (val.length < 4) return;
            const tr = inputEl.closest('tr');
            if (!tr) return;

            // Cek jika field lain masih kosong
            const blokVal = tr.querySelector('input[data-field="block"]').value;
            const nopolVal = tr.querySelector('input[data-field="nomorPolisi"]').value;
            if (blokVal && nopolVal) return; // sudah terisi

            try {
                const res = await fetch(`api/tps_tracking_batch.php?action=search_containers&q=${encodeURIComponent(val)}`);
                const data = await res.json();
                if (data.results && data.results.length > 0) {
                    const match = data.results.find(r => r.container_no === val) || data.results[0];
                    if (match) {
                        const sz = match.size || (match.size_type && match.size_type.includes('20') ? '20' : (match.size_type && match.size_type.includes('45') ? '45' : '40'));
                        tr.querySelector('select[data-field="ukuranKontainer"]').value = sz;
                        tr.querySelector('select[data-field="jenisKontainer"]').value = match.status === 'EMPTY' ? '4' : (match.status === 'LCL' ? '7' : '8');
                        if (match.yard_block) tr.querySelector('input[data-field="block"]').value = match.yard_block;
                        if (match.slot) tr.querySelector('input[data-field="slot"]').value = match.slot;
                        if (match.tier) tr.querySelector('input[data-field="tier"]').value = match.tier;
                        if (match.nopol) tr.querySelector('input[data-field="nomorPolisi"]').value = match.nopol;
                        if (match.no_bl) tr.querySelector('input[data-field="nomorBlAwb"]').value = match.no_bl;
                        if (match.no_dokumen) {
                            tr.querySelector('input[data-field="nomorDokumen"]').value = match.no_dokumen;
                            tr.querySelector('input[data-field="kodeDokumen"]').value = '20';
                        }
                        updateBatchJson();
                        showToast(`Data kontainer ${match.container_no} berhasil dimuat otomatis!`, 'success');
                    }
                }
            } catch(e) {
                console.error("AutoFill lookup error: ", e);
            }
        }

        function clearAllRows() {
            document.getElementById('batch-tbody').innerHTML = '';
            rowCounter = 0;
            addRow();
            showToast('Tabel batch telah dikosongkan', 'info');
        }

        // ===== MODAL TARIK DARI PLP =====
        function openPlpModal() {
            document.getElementById('modal-plp-picker').style.display = 'flex';
            loadPlpContainers();
        }

        function closePlpModal() {
            document.getElementById('modal-plp-picker').style.display = 'none';
        }

        function debouncePlpSearch() {
            clearTimeout(plpSearchTimeout);
            plpSearchTimeout = setTimeout(() => {
                const q = document.getElementById('plp-search-input').value;
                loadPlpContainers(q);
            }, 300);
        }

        async function loadPlpContainers(q = '') {
            document.getElementById('plp-loading').style.display = 'block';
            document.getElementById('tbody-plp-picker').innerHTML = '';
            document.getElementById('check-all-plp').checked = false;
            updatePlpSelectedCount();

            try {
                const res = await fetch(`api/tps_tracking_batch.php?action=search_containers&q=${encodeURIComponent(q)}`);
                const data = await res.json();
                document.getElementById('plp-loading').style.display = 'none';
                plpLoadedData = data.results || [];

                const tbody = document.getElementById('tbody-plp-picker');
                if (plpLoadedData.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:24px; color:var(--text-secondary);">Tidak ada data kontainer ditemukan di database PLP</td></tr>';
                    return;
                }

                plpLoadedData.forEach((item, i) => {
                    const isEmp = item.status === 'EMPTY';
                    const yard = [item.yard_block, item.slot ? 'S:'+item.slot : '', item.tier ? 'T:'+item.tier : ''].filter(Boolean).join(' ');
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="text-align:center;"><input type="checkbox" class="plp-chk" data-index="${i}" onchange="updatePlpSelectedCount()"></td>
                        <td><strong style="font-family:'JetBrains Mono',monospace; color:var(--text-primary); font-size:0.88rem;">${item.container_no}</strong></td>
                        <td>${item.size_type || '40'}</td>
                        <td><span class="badge-pill ${isEmp ? 'badge-out' : 'badge-in'}" style="font-size:10px;">${item.status || 'FCL'}</span></td>
                        <td><span style="font-family:'JetBrains Mono',monospace; font-size:11px;">${yard || '-'}</span></td>
                        <td><b>${item.nopol || '-'}</b></td>
                        <td><small style="color:var(--text-secondary);">${item.no_bl || '-'}</small></td>
                        <td><small style="color:var(--text-secondary);">${item.no_dokumen || '-'}</small></td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch(e) {
                document.getElementById('plp-loading').style.display = 'none';
                document.getElementById('tbody-plp-picker').innerHTML = `<tr><td colspan="8" style="text-align:center; color:#ef4444; padding:20px;">Gagal memuat: ${e.message}</td></tr>`;
            }
        }

        function toggleSelectAllPlp(master) {
            const chks = document.querySelectorAll('.plp-chk');
            chks.forEach(c => c.checked = master.checked);
            updatePlpSelectedCount();
        }

        function updatePlpSelectedCount() {
            const checked = document.querySelectorAll('.plp-chk:checked').length;
            document.getElementById('plp-selected-count').textContent = `${checked} kontainer dipilih`;
        }

        function insertSelectedPlp() {
            const checkedBoxes = document.querySelectorAll('.plp-chk:checked');
            if (checkedBoxes.length === 0) {
                showToast('Pilih minimal 1 kontainer dari daftar', 'error');
                return;
            }

            // Hapus semua baris eksisting yang masih kosong (nomor kontainer belum diisi)
            const existingRows = document.querySelectorAll('#batch-tbody tr');
            existingRows.forEach(tr => {
                const contInp = tr.querySelector('input[data-field="nomorKontainer"]');
                if (!contInp || !contInp.value.trim()) {
                    tr.remove();
                }
            });
            // Jika setelah menghapus baris kosong tabel jadi kosong, reset counter
            if (document.querySelectorAll('#batch-tbody tr').length === 0) {
                rowCounter = 0;
            }

            let inserted = 0;
            checkedBoxes.forEach(chk => {
                const idx = parseInt(chk.dataset.index, 10);
                const item = plpLoadedData[idx];
                if (item) {
                    addRow(item);
                    inserted++;
                }
            });

            closePlpModal();
            showToast(`${inserted} kontainer berhasil ditambahkan ke tabel batch!`, 'success');
        }

        function removeRow(idx) {
            const row = document.getElementById(`row-${idx}`);
            if (row) row.remove();
            updateBatchJson();
            updateRowNumbers();
            if (document.querySelectorAll('#batch-tbody tr').length === 0) {
                addRow();
            }
        }

        function updateRowNumbers() {
            const rows = document.querySelectorAll('#batch-tbody tr');
            rows.forEach((r, i) => {
                r.querySelector('.col-no').textContent = i + 1;
            });
            document.getElementById('stat-total-rows').textContent = rows.length;
        }

        function applyGlobalKegiatan() {
            updateBatchJson();
        }

        function setAllWaktuNow() {
            const nowStr = getNowFormatted();
            document.querySelectorAll('#batch-tbody input[data-field="waktuKegiatan"]').forEach(inp => {
                inp.value = nowStr;
            });
            updateBatchJson();
            showToast('Waktu kegiatan semua baris diset ke sekarang', 'info');
        }

        function buildBatchPayload() {
            const kodeTps = document.getElementById('global-kode-tps').value;
            const kodeGudang = document.getElementById('global-kode-gudang').value;
            const kodeKegiatan = parseInt(document.getElementById('global-kode-kegiatan').value, 10);

            const items = [];
            document.querySelectorAll('#batch-tbody tr').forEach(tr => {
                const item = {
                    kodeTps: kodeTps,
                    kodeGudang: kodeGudang,
                    kodeKegiatan: kodeKegiatan
                };
                tr.querySelectorAll('input[data-field], select[data-field]').forEach(el => {
                    const field = el.dataset.field;
                    let val = el.value.trim();
                    if (field === 'kodeKegiatan') val = parseInt(val, 10);
                    if (val !== '' && val !== 0) {
                        item[field] = val;
                    }
                });
                items.push(item);
            });
            return items;
        }

        function updateBatchJson() {
            const items = buildBatchPayload();
            document.getElementById('json-batch-preview').value = JSON.stringify(items, null, 4);

            const valid = items.filter(it => it.nomorKontainer && it.waktuKegiatan);
            if (valid.length === items.length && items.length > 0) {
                document.getElementById('batch-status').innerHTML = '<span style="color:#10b981;">✓ ' + items.length + ' item siap dikirim</span>';
            } else {
                document.getElementById('batch-status').innerHTML = '<span style="color:#f59e0b;">⚠️ ' + valid.length + '/' + items.length + ' item valid</span>';
            }
        }

        function copyBatchJson() {
            const text = document.getElementById('json-batch-preview').value;
            navigator.clipboard.writeText(text).then(() => showToast('JSON array disalin ke clipboard!', 'success'));
        }

        async function sendBatch() {
            const items = buildBatchPayload();
            const validItems = items.filter(it => it.nomorKontainer && it.waktuKegiatan);

            if (validItems.length === 0) {
                Swal.fire({ title: 'Tidak ada data valid', text: 'Isi minimal 1 baris dengan No Kontainer dan Waktu Kegiatan.', icon: 'warning', confirmButtonColor: '#10b981' });
                return;
            }

            const kegLabel = $('#global-kode-kegiatan option:selected').text();
            const confirmRes = await Swal.fire({
                title: 'Konfirmasi Kirim Batch',
                html: `
                    <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                        <p>Kirim <b>${validItems.length} kontainer</b> sekaligus ke <b>CEISA 4.0</b>?</p>
                        <div style="background:rgba(0,0,0,0.2); border:1px solid var(--border-medium); border-radius:8px; padding:12px;">
                            <div>⚡ <b>Kegiatan:</b> ${kegLabel}</div>
                            <div>🏢 <b>TPS / Gudang:</b> PSU0 / CPSU</div>
                            <div>📦 <b>Kontainer:</b> ${validItems.map(i => '<code>' + i.nomorKontainer + '</code>').join(', ')}</div>
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '🚀 Ya, Kirim Batch',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                reverseButtons: true
            });

            if (!confirmRes.isConfirmed) return;

            const btn = document.getElementById('btn-send-batch');
            const spinner = document.getElementById('batch-spinner');
            const icon = document.getElementById('batch-icon');
            btn.disabled = true; spinner.style.display = 'inline-block'; icon.style.display = 'none';

            Swal.fire({
                title: 'Mengirim Batch ke CEISA 4.0...',
                html: `Sedang mengirim <b>${validItems.length} kontainer</b> ke gateway Bea Cukai...`,
                allowOutsideClick: false, allowEscapeKey: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const res = await fetch('api/tps_tracking_batch.php?action=send', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({ items: items })
                });
                const result = await res.json();
                const ceisaRaw = result.raw || result;

                // Show result card
                $('#batch-result-card').show();
                const badge = $('#batch-result-badge');
                badge.removeClass('badge-in badge-out').removeAttr('style');

                if (result.success) {
                    badge.addClass('badge-in').text(`HTTP ${result.code || 201} — BATCH OK`);
                    $('#batch-result-msg').html(`✅ <b>Batch Berhasil:</b> ${result.total_sent || validItems.length} kontainer berhasil dikirim.<br><small style="color:var(--text-secondary);">Batch ID: ${result.batch_id || '-'}</small>`);
                    Swal.fire({
                        title: '🎉 Batch Tracking Berhasil!',
                        html: `<div style="text-align:left; font-size:13.5px;"><p>${result.total_sent || validItems.length} kontainer berhasil direkam di CEISA 4.0.</p><p style="color:var(--text-secondary);">Batch ID: <code>${result.batch_id || '-'}</code></p></div>`,
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: '📊 Buka Laporan Batch',
                        cancelButtonText: 'Tutup',
                        confirmButtonColor: '#10b981'
                    }).then(r => { if (r.isConfirmed) window.location.href = 'report_tracking_batch.php'; });
                    showToast(`Batch ${validItems.length} kontainer berhasil dikirim!`, 'success');
                } else {
                    badge.addClass('badge-out').text(`HTTP ${result.code || 400} — FAILED`);
                    $('#batch-result-msg').html(`❌ <b>Gagal:</b> ${result.message || 'Pengiriman ditolak oleh CEISA 4.0'}`);
                    if (result.validation_errors && result.validation_errors.length > 0) {
                        $('#batch-result-msg').append('<br><br><b>Validasi Error:</b><ul style="margin:4px 0; padding-left:20px;">' + result.validation_errors.map(e => '<li style="color:#f59e0b; font-size:0.85rem;">' + e + '</li>').join('') + '</ul>');
                    }
                    Swal.fire({ title: 'Batch Tracking Gagal', html: `<p>${result.message || 'Ditolak oleh gateway'}</p>`, icon: 'error', confirmButtonColor: '#ef4444' });
                    showToast('Batch tracking gagal: ' + (result.message || ''), 'error');
                }

                $('#batch-result-time').text('Respon: ' + new Date().toLocaleTimeString('id-ID'));
                $('#batch-raw-response').text(JSON.stringify(ceisaRaw, null, 4)).show();

            } catch (err) {
                Swal.fire({ title: 'Kesalahan Sistem', text: err.message, icon: 'error' });
                showToast('Terjadi kesalahan jaringan: ' + err.message, 'error');
            } finally {
                btn.disabled = false; spinner.style.display = 'none'; icon.style.display = 'inline-block';
            }
        }

        // Init
        $(document).ready(function() {
            // Tambah 3 baris awal
            addRow();
            addRow();
            addRow();

            // Theme management
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

            // Mobile menu
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
