<?php
/**
 * Laporan YOR (Yard Occupancy Rate) CEISA 4.0 — TPS Online Dashboard
 * Khusus Kegiatan IMPOR (Kegiatan Ekspor Nihil / Otomatis 0)
 * Menggunakan Parameter Acuan dari Master_Constanta (tppconstanta: YOR=1090, SOR_lcl=3750)
 * Target Endpoint: POST /kirim-laporan-yor
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

requireAuth();

$config = require __DIR__ . '/config.php';
$username = $_SESSION['name'] ?? $_SESSION['username'] ?? $config['username'] ?? 'User';
$loginTime = $_SESSION['login_time'] ?? time();
$userInitial = strtoupper(substr($username, 0, 2));

$todayDmy = date('d-m-Y');
$defaultRef = 'YOR-PSU0-' . date('ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan YOR (Yard Occupancy Rate) — <?= e($config['app_name']) ?></title>
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
        
        .yor-container {
            padding: 24px 24px 80px 24px;
            max-width: 1440px;
            margin: 0 auto;
        }
        .yor-card {
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
            justify-content: space-between;
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
        .field-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin: 0;
        }
        .field-group label .req-star {
            color: var(--accent-red);
            font-weight: 700;
        }
        .field-group .helper-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 2px;
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
        .input-control[readonly] {
            background: rgba(0, 0, 0, 0.12);
            color: var(--text-secondary);
            cursor: not-allowed;
        }
        .json-box {
            width: 100%;
            height: 480px;
            background: #0d131f;
            color: #7dd3fc;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #1e293b;
            resize: vertical;
            line-height: 1.55;
        }
        .btn-send-yor {
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
        .btn-send-yor:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4); }
        .btn-send-yor:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }

        .btn-auto-calc {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid rgba(59, 130, 246, 0.4);
            background: rgba(59, 130, 246, 0.12);
            color: #60a5fa;
            transition: all 0.2s;
        }
        .btn-auto-calc:hover {
            background: rgba(59, 130, 246, 0.22);
            color: #93c5fd;
        }
        .yor-stat-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.15rem;
            font-weight: 700;
            color: #10b981;
            padding: 4px 12px;
            border-radius: 8px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .info-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            padding: 4px 10px;
            border-radius: 6px;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.25);
            color: #93c5fd;
        }

        /* Tombol Departemen Operasional Lini 2 */
        .dept-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            border-radius: 10px;
            border: 1px solid var(--border-medium);
            background: var(--bg-surface);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.25s ease;
            text-align: left;
            width: 100%;
        }
        .dept-btn:hover {
            border-color: var(--accent-blue);
            color: var(--text-primary);
            background: var(--bg-card);
        }
        .dept-btn.active.dept-tpp {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.25));
            border-color: var(--accent-blue);
            color: #60a5fa;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        .dept-btn.active.dept-gudang {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.25));
            border-color: #10b981;
            color: #34d399;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
    </style>
</head>
<body data-login-time="<?= $loginTime ?>">
    <div class="dashboard">
        <div class="sidebar-overlay"></div>
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="main-content">
            <!-- Header Topbar -->
            <header class="header">
                <div class="header-left">
                    <button class="menu-toggle" id="menu-toggle">☰</button>
                    <div class="header-breadcrumb">
                        <span>CEISA 4.0</span>
                        <span class="separator">/</span>
                        <span>Kirim Dokumen</span>
                        <span class="separator">/</span>
                        <span class="current">Laporan YOR (Yard Occupancy Rate)</span>
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

            <!-- Main Content Area -->
            <main class="content-area">
                <div class="yor-container">

                    <!-- Header Action Card -->
                    <div class="yor-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                            <div>
                                <h2 style="margin: 0; font-size: 1.3rem; color: var(--text-primary); font-weight: 700; display: flex; align-items: center; gap: 10px;">
                                    <span>📊</span> Laporan YOR (Yard Occupancy Rate) TPS — Khusus Impor
                                </h2>
                                <p style="margin: 6px 0 0; color: var(--text-secondary); font-size: 0.88rem;">
                                    Pelaporan utilisasi lapangan (YOR) & kapasitas penimbunan impor PT. Primamas Segara Utama (PSU) ke Bea Cukai CEISA 4.0.
                                </p>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <a href="report_yor.php" class="btn-action-sm" style="text-decoration:none; padding:8px 16px; border-radius:8px; font-weight:600; display:inline-flex; align-items:center; gap:6px; background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.35);">
                                    <span>📋</span> Laporan YOR Terkirim
                                </a>
                                <span class="badge-pill badge-ceisa">POST /kirim-laporan-yor</span>
                                <span class="badge-pill" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                                    <span class="pulse-dot"></span> CEISA 4.0 OpenAPI
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Layout: Form Kiri & Live JSON Kanan -->
                    <div style="display: grid; grid-template-columns: 1.35fr 1fr; gap: 24px; align-items: start;">

                        <!-- KOLOM KIRI: FORMULIR YOR (KHUSUS IMPOR) -->
                        <div>
                            <!-- 0. Toggle Departemen Operasional Lini 2 -->
                            <div class="yor-card" style="padding: 16px 20px; margin-bottom: 20px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                                        🏬 Departemen Operasional Lini 2
                                    </span>
                                    <span id="dept-database-badge" style="font-size: 0.78rem; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-family: 'JetBrains Mono', monospace; background: rgba(59, 130, 246, 0.15); color: var(--accent-blue); border: 1px solid rgba(59, 130, 246, 0.3);">
                                        DB: tpp_primamas (PLP FCL)
                                    </span>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <button type="button" id="btn-dept-tpp" class="dept-btn active dept-tpp" onclick="setYorDepartment('tpp')">
                                        <span style="font-size: 1.15rem;">🏢</span>
                                        <div>
                                            <strong style="display: block; font-size: 0.95rem;">TPP (PLP / Lapangan)</strong>
                                            <small style="opacity: 0.8; font-size: 0.75rem;">Kode Gudang: CPSU • Kapasitas: 1.090 TEUs</small>
                                        </div>
                                    </button>
                                    <button type="button" id="btn-dept-gudang" class="dept-btn" onclick="setYorDepartment('gudang')">
                                        <span style="font-size: 1.15rem;">🏬</span>
                                        <div>
                                            <strong style="display: block; font-size: 0.95rem;">Gudang (LCL / CFS)</strong>
                                            <small style="opacity: 0.8; font-size: 0.75rem;">Kode Gudang: GPSU • Kapasitas: 14.196 M³</small>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- 1. Header Dokumen Laporan -->
                            <div class="yor-card">
                                <div class="section-header-row">
                                    <span>📑 1. Identitas Dokumen & Lokasi TPS</span>
                                    <button type="button" id="btn-tarik-stok" class="btn-auto-calc" onclick="fetchLiveDepoStock()" title="Tarik data riil dari database & Master Constanta">
                                        <span>⚡</span> <span id="lbl-btn-tarik">Tarik dari TPP & Master Constanta</span>
                                    </button>
                                </div>

                                <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 16px; margin-bottom: 16px;">
                                    <div class="field-group">
                                        <label for="ref-number">Nomor Referensi (refNumber) <span class="req-star">*</span></label>
                                        <input type="text" id="ref-number" class="input-control" value="<?= e($defaultRef) ?>" maxlength="20" placeholder="YOR-PSU0-YYMMDD-001" oninput="updateJsonPreview()">
                                        <span class="helper-text">Maksimal 20 karakter unik untuk referensi pelaporan</span>
                                    </div>
                                    <div class="field-group">
                                        <label for="tgl-laporan">Tanggal Laporan <span class="req-star">*</span></label>
                                        <input type="text" id="tgl-laporan" class="input-control" value="<?= e($todayDmy) ?>" placeholder="dd-MM-yyyy" maxlength="10" onchange="fetchLiveDepoStock()" oninput="updateJsonPreview()">
                                        <span class="helper-text">Format wajib: dd-MM-yyyy</span>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                    <div class="field-group">
                                        <label for="kode-tps">Kode TPS <span class="req-star">*</span></label>
                                        <input type="text" id="kode-tps" class="input-control" value="PSU0" readonly title="Kode TPS Baku: PT. Primamas Segara Utama (readonly)">
                                        <span class="helper-text">PSU0 (PT. Primamas Segara Utama)</span>
                                    </div>
                                    <div class="field-group">
                                        <label for="kode-gudang">Kode Gudang <span class="req-star">*</span></label>
                                        <input type="text" id="kode-gudang" class="input-control" value="CPSU" readonly title="Kode Gudang Baku (readonly)">
                                        <span class="helper-text" id="kode-gudang-helper">CPSU (Container Yard TPS Lini 2)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Objek Data YOR Impor -->
                            <div class="yor-card">
                                <div class="section-header-row">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span id="section-2-title"><span>📥</span> 2. Data Penggunaan Lapangan Penumpukan (IMPOR)</span>
                                        <span class="info-pill" title="TPS Primamas hanya melayani kegiatan Impor">Kegiatan Ekspor Nihil</span>
                                    </div>
                                    <span class="yor-stat-badge" id="badge-yor-impor">YOR: 0.00%</span>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                                    <div class="field-group">
                                        <label for="impor-kap-lap">Kapasitas Lapangan (TEUs) <span class="req-star">*</span></label>
                                        <input type="number" id="impor-kap-lap" class="input-control" value="1090" step="any" min="0" oninput="calcYor()">
                                        <span class="helper-text" id="helper-kap-lap">Acuan Master_Constanta (tppconstanta.YOR = 1090 TEUs)</span>
                                    </div>
                                    <div class="field-group">
                                        <label for="impor-kap-gud">Kapasitas Gudang (m² / Ton) <span class="req-star">*</span></label>
                                        <input type="number" id="impor-kap-gud" class="input-control" value="0" step="any" min="0" oninput="calcYor()">
                                        <span class="helper-text" id="helper-kap-gud" style="color:#10b981;">✓ Kode CPSU = 0 (Container Yard / Lapangan, bukan Gudang)</span>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                                    <div class="field-group">
                                        <label for="impor-c20">Kontainer 20ft (Box) <span class="req-star">*</span></label>
                                        <input type="number" id="impor-c20" class="input-control" value="0" min="0" oninput="calcYor()">
                                        <span class="helper-text">Dihitung = 1 TEU per box</span>
                                    </div>
                                    <div class="field-group">
                                        <label for="impor-c40">Kontainer 40ft (Box) <span class="req-star">*</span></label>
                                        <input type="number" id="impor-c40" class="input-control" value="0" min="0" oninput="calcYor()">
                                        <span class="helper-text">Dihitung = 2 TEU per box</span>
                                    </div>
                                    <div class="field-group">
                                        <label for="impor-c45">Kontainer 45ft (Box) <span class="req-star">*</span></label>
                                        <input type="number" id="impor-c45" class="input-control" value="0" min="0" oninput="calcYor()">
                                        <span class="helper-text">Dihitung = 2 TEU per box</span>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr 1.2fr; gap: 14px;">
                                    <div class="field-group">
                                        <label for="impor-total-cont">Total Kontainer (Box) <span class="req-star">*</span></label>
                                        <input type="number" id="impor-total-cont" class="input-control" value="0" readonly title="Dihitung otomatis (20f + 40f + 45f)">
                                        <span class="helper-text" id="txt-teus-info">Total TEUs: 0</span>
                                    </div>
                                    <div class="field-group">
                                        <label for="impor-kemasan">Total Kemasan <span class="req-star">*</span></label>
                                        <input type="number" id="impor-kemasan" class="input-control" value="0" min="0" oninput="calcYor()">
                                        <span class="helper-text" id="helper-kemasan" style="color:#10b981;">✓ Kode CPSU = 0 (Tidak ada kemasan di CY)</span>
                                    </div>
                                    <div class="field-group">
                                        <label for="impor-yor">Persentase YOR / SOR (%) <span class="req-star">*</span></label>
                                        <input type="number" id="impor-yor" class="input-control" value="0" step="any" oninput="updateJsonPreview()">
                                        <span class="helper-text" id="helper-yor-desc">Formula RPLP_YOR: Presisi Float Penuh (tanpa pembulatan)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Keterangan Ekspor Otomatis 0 -->
                            <div style="background: rgba(0,0,0,0.12); border: 1px dashed var(--border-subtle); border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem; color: var(--text-secondary);">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span>ℹ️</span>
                                    <span><b>Catatan Ekspor:</b> Perusahaan tidak melayani ekspor. Objek <code>ekspor</code> otomatis dikirim ke CEISA dengan nilai <b>0</b> agar skema OpenAPI tetap valid.</span>
                                </div>
                                <span class="badge-pill" style="background: rgba(148, 163, 184, 0.15); color: #94a3b8;">Ekspor = 0</span>
                            </div>
                        </div>

                        <!-- KOLOM KANAN: LIVE JSON PREVIEW & ACTION SEND -->
                        <div style="position: sticky; top: 80px;">
                            <div class="yor-card" style="margin-bottom: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 1.15rem;">⚡</span>
                                        <strong style="color: var(--text-primary); font-size: 1rem;">Live JSON Payload</strong>
                                    </div>
                                    <button type="button" class="btn-action-sm" onclick="copyJsonPayload()" style="padding:6px 14px; border-radius:6px; font-weight:600; display:inline-flex; align-items:center; gap:4px; background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.35); cursor:pointer; font-size:0.8rem;">
                                        <span>📋</span> Salin JSON
                                    </button>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.8rem;">
                                    <span style="color: var(--text-secondary);">Target: <code>/kirim-laporan-yor</code></span>
                                    <span id="schema-status" style="color: #10b981; font-weight: 600;">✓ Payload Siap Dikirim</span>
                                </div>

                                <textarea id="json-yor-preview" class="json-box" readonly></textarea>

                                <div style="margin-top: 20px;">
                                    <button type="button" id="btn-submit-yor" class="btn-send-yor" onclick="sendLaporanYor()">
                                        <span id="send-spinner" style="display: none;">⏳</span>
                                        <span id="send-icon">🚀</span>
                                        <span id="send-text">Kirim Laporan YOR ke CEISA 4.0</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Respon Card -->
                            <div id="send-result-card" class="yor-card" style="display: none; margin-top: 20px; padding: 20px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span id="send-status-badge" class="badge-pill"></span>
                                        <span id="send-timestamp" style="font-size: 0.85rem; color: var(--text-secondary);"></span>
                                    </div>
                                    <button type="button" class="btn-action-sm" onclick="$('#send-raw-response').slideToggle(200)" style="padding:6px 14px; border-radius:6px; font-weight:600; display:inline-flex; align-items:center; gap:4px; background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.35); cursor:pointer; font-size:0.8rem;">
                                        <span>📋</span> Toggle Raw Response
                                    </button>
                                </div>
                                <div id="send-result-msg" style="font-size: 0.92rem; color: var(--text-primary); margin-bottom: 12px;"></div>
                                <pre id="send-raw-response" style="display: none; background: #0d131f; color: #a5f3fc; padding: 16px; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 13px; max-height: 260px; overflow: auto; margin: 0;"></pre>
                            </div>
                        </div>

                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="toast-container" style="position:fixed; bottom:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:10px;"></div>

    <script>
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

        let currentDept = 'tpp';
        let currentStockData = null;

        function setYorDepartment(dept) {
            currentDept = dept;
            const btnTpp = document.getElementById('btn-dept-tpp');
            const btnGudang = document.getElementById('btn-dept-gudang');
            const badge = document.getElementById('dept-database-badge');

            if (dept === 'gudang') {
                btnTpp.className = 'dept-btn';
                btnGudang.className = 'dept-btn active dept-gudang';
                badge.innerHTML = 'DB: primamas (Gudang LCL)';
                badge.style.background = 'rgba(16, 185, 129, 0.15)';
                badge.style.color = '#10b981';
                badge.style.borderColor = 'rgba(16, 185, 129, 0.3)';

                $('#kode-gudang').val('GPSU');
                $('#kode-gudang-helper').text('GPSU (Gudang CFS TPS Lini 2)');
                $('#lbl-btn-tarik').text('Tarik dari Gudang LCL (primamas)');

                $('#section-2-title').html('<span>🏬</span> 2. Data Penggunaan Gudang CFS & Kargo LCL (IMPOR)');
                
                // Set Kapasitas Lapangan = 0 (Gudang tertutup, bukan CY)
                $('#impor-kap-lap').val(0).prop('readonly', true).css('background', 'rgba(0,0,0,0.12)');
                $('#helper-kap-lap').html('<span style="color:#10b981;">✓ Kode GPSU = 0 (Gudang Tertutup CFS, bukan Lapangan Terbuka)</span>');

                // Set Kapasitas Gudang = 14196 (M3) dari Beranda.php (Luas 2.366 m2 x Tinggi 6m)
                $('#impor-kap-gud').val(14196).prop('readonly', false).css('background', 'var(--bg-input)');
                $('#helper-kap-gud').html('Acuan Gudang LCL: Luas 2.366 m² × Tinggi Max 6 m = <b>14.196 M³</b>');

                // Gudang adalah fasilitas kemasan, seluruh kontainer diset 0 & readonly
                $('#impor-c20').val(0).prop('readonly', true).css('background', 'rgba(0,0,0,0.12)');
                $('#impor-c40').val(0).prop('readonly', true).css('background', 'rgba(0,0,0,0.12)');
                $('#impor-c45').val(0).prop('readonly', true).css('background', 'rgba(0,0,0,0.12)');
                $('#impor-total-cont').val(0);
                $('#txt-teus-info').text('Total Kontainer: 0 Box (Gudang CFS Khusus Kemasan LCL)');

                // Kemasan Aktif
                $('#impor-kemasan').prop('readonly', false).css('background', 'var(--bg-input)');
                $('#helper-kemasan').html('Stok koli kemasan aktif di gudang (database primamas)');
                $('#helper-yor-desc').text('Formula SOR: (Volume Kargo / 14.196 M³) × 100%');

                showToast('Departemen Gudang (GPSU / database primamas) aktif — Mode Kemasan', 'info');
            } else {
                btnGudang.className = 'dept-btn';
                btnTpp.className = 'dept-btn active dept-tpp';
                badge.innerHTML = 'DB: tpp_primamas (PLP FCL)';
                badge.style.background = 'rgba(59, 130, 246, 0.15)';
                badge.style.color = 'var(--accent-blue)';
                badge.style.borderColor = 'rgba(59, 130, 246, 0.3)';

                $('#kode-gudang').val('CPSU');
                $('#kode-gudang-helper').text('CPSU (Container Yard TPS Lini 2)');
                $('#lbl-btn-tarik').text('Tarik dari TPP & Master Constanta');

                $('#section-2-title').html('<span>📥</span> 2. Data Penggunaan Lapangan Penumpukan (IMPOR)');

                // Set Kapasitas Lapangan = 1090
                $('#impor-kap-lap').val(1090).prop('readonly', false).css('background', 'var(--bg-input)');
                $('#helper-kap-lap').html('Acuan Master_Constanta (tppconstanta.YOR = 1090 TEUs)');

                // Kontainer Aktif untuk TPP Lapangan
                $('#impor-c20').prop('readonly', false).css('background', 'var(--bg-input)');
                $('#impor-c40').prop('readonly', false).css('background', 'var(--bg-input)');
                $('#impor-c45').prop('readonly', false).css('background', 'var(--bg-input)');

                // Set Kapasitas Gudang = 0
                $('#impor-kap-gud').val(0).prop('readonly', true).css('background', 'rgba(0,0,0,0.12)');
                $('#helper-kap-gud').html('<span style="color:#10b981;">✓ Kode CPSU = 0 (Container Yard / Lapangan, bukan Gudang)</span>');

                // Kemasan 0
                $('#impor-kemasan').val(0).prop('readonly', true).css('background', 'rgba(0,0,0,0.12)');
                $('#helper-kemasan').html('<span style="color:#10b981;">✓ Kode CPSU = 0 (Tidak ada kemasan di CY)</span>');
                $('#helper-yor-desc').text('Formula YOR: (Total TEUs / Kapasitas Lapangan) × 100%');

                showToast('Departemen TPP (CPSU / database tpp_primamas) aktif — Mode Lapangan Kontainer', 'info');
            }

            fetchLiveDepoStock(false);
        }

        function calcYor() {
            const isCPSU = ($('#kode-gudang').val() || '').trim().toUpperCase() === 'CPSU';

            let yorVal = 0;
            if (isCPSU) {
                const c20 = parseInt(document.getElementById('impor-c20').value || 0, 10);
                const c40 = parseInt(document.getElementById('impor-c40').value || 0, 10);
                const c45 = parseInt(document.getElementById('impor-c45').value || 0, 10);
                const totalCont = c20 + c40 + c45;
                document.getElementById('impor-total-cont').value = totalCont;

                const teus = (c20 * 1) + (c40 * 2) + (c45 * 2);
                document.getElementById('txt-teus-info').textContent = `Total TEUs: ${teus}`;

                const kapLap = parseFloat(document.getElementById('impor-kap-lap').value || 1090);
                if (kapLap > 0) {
                    yorVal = (teus / kapLap) * 100;
                }
                document.getElementById('badge-yor-impor').textContent = `YOR: ${yorVal.toFixed(2)}%`;
            } else {
                // Untuk Gudang (SOR): Khusus kemasan/kargo, kontainer = 0
                document.getElementById('impor-c20').value = 0;
                document.getElementById('impor-c40').value = 0;
                document.getElementById('impor-c45').value = 0;
                document.getElementById('impor-total-cont').value = 0;
                document.getElementById('txt-teus-info').textContent = `Total Kontainer: 0 Box (Gudang CFS Khusus Kemasan LCL)`;

                // Rasio okupansi volume gudang (Kapasitas: 14.196 M3)
                const kapGud = parseFloat(document.getElementById('impor-kap-gud').value || 14196);
                if (currentStockData && currentStockData.totalVolume && kapGud > 0) {
                    yorVal = (currentStockData.totalVolume / kapGud) * 100;
                } else if (kapGud > 0) {
                    const totalKms = parseFloat(document.getElementById('impor-kemasan').value || 0);
                    yorVal = ((totalKms * 0.384) / kapGud) * 100;
                }
                document.getElementById('badge-yor-impor').textContent = `SOR: ${yorVal.toFixed(2)}%`;
            }

            document.getElementById('impor-yor').value = yorVal;
            document.getElementById('badge-yor-impor').title = `Nilai Riil Floating Point: ${yorVal}%`;

            updateJsonPreview();
        }

        function buildPayload() {
            const isCPSU = ($('#kode-gudang').val() || '').trim().toUpperCase() === 'CPSU';

            const impor = {
                jumlahKontainer20f: isCPSU ? parseInt($('#impor-c20').val() || 0, 10) : 0,
                jumlahKontainer40f: isCPSU ? parseInt($('#impor-c40').val() || 0, 10) : 0,
                jumlahKontainer45f: isCPSU ? parseInt($('#impor-c45').val() || 0, 10) : 0,
                kapasitasGudang: isCPSU ? 0 : parseFloat($('#impor-kap-gud').val() || 0),
                kapasitasLapangan: isCPSU ? parseFloat($('#impor-kap-lap').val() || 0) : 0,
                totalKemasan: isCPSU ? 0 : parseFloat($('#impor-kemasan').val() || 0),
                totalKontainer: isCPSU ? parseInt($('#impor-total-cont').val() || 0, 10) : 0,
                yor: parseFloat($('#impor-yor').val() || 0)
            };

            // Karena TPS Primamas hanya melayani Impor, seluruh objek ekspor diset 0
            const ekspor = {
                jumlahKontainer20f: 0,
                jumlahKontainer40f: 0,
                jumlahKontainer45f: 0,
                kapasitasGudang: 0,
                kapasitasLapangan: 0,
                totalKemasan: 0,
                totalKontainer: 0,
                yor: 0
            };

            return {
                refNumber: ($('#ref-number').val() || '').trim(),
                tanggalLaporan: ($('#tgl-laporan').val() || '').trim(),
                kodeTps: ($('#kode-tps').val() || 'PSU0').trim().toUpperCase(),
                kodeGudang: ($('#kode-gudang').val() || 'CPSU').trim().toUpperCase(),
                impor: impor,
                ekspor: ekspor
            };
        }

        function updateJsonPreview() {
            const payload = buildPayload();
            $('#json-yor-preview').val(JSON.stringify(payload, null, 4));

            const isValid = (payload.refNumber && payload.tanggalLaporan && payload.kodeTps && payload.kodeGudang);
            if (isValid) {
                $('#schema-status').html('<span style="color:#10b981;">✓ Payload Siap Dikirim</span>');
            } else {
                $('#schema-status').html('<span style="color:#f59e0b;">⚠️ Field Bertanda * Wajib Diisi</span>');
            }
        }

        function copyJsonPayload() {
            const text = $('#json-yor-preview').val();
            navigator.clipboard.writeText(text).then(() => {
                showToast('JSON payload berhasil disalin ke clipboard!', 'success');
            });
        }

        async function fetchLiveDepoStock(notify = true) {
            const isCPSU = ($('#kode-gudang').val() || '').trim().toUpperCase() === 'CPSU';
            const dept = isCPSU ? 'tpp' : 'gudang';
            const kodeGudang = isCPSU ? 'CPSU' : 'GPSU';

            if (notify) showToast(`Menghubungkan ke database ${isCPSU ? 'TPP (tpp_primamas)' : 'Gudang (primamas)'}...`, 'info');
            try {
                const tgl = ($('#tgl-laporan').val() || '').trim();
                const res = await fetch(`api/laporan_yor.php?action=fetch_stock&tanggalLaporan=${encodeURIComponent(tgl)}&dept=${dept}&kodeGudang=${kodeGudang}`);
                const data = await res.json();
                if (data.success && data.stock) {
                    const st = data.stock.impor || {};
                    currentStockData = st;

                    if (isCPSU) {
                        if (st.kapasitasLapangan) $('#impor-kap-lap').val(st.kapasitasLapangan);
                        $('#impor-kap-gud').val(0);
                        $('#impor-c20').val(st.c20 || 0);
                        $('#impor-c40').val(st.c40 || 0);
                        $('#impor-c45').val(st.c45 || 0);
                        $('#impor-kemasan').val(0);
                        if (st.yor !== undefined) $('#impor-yor').val(st.yor);
                    } else {
                        $('#impor-kap-lap').val(0);
                        if (st.kapasitasGudang) $('#impor-kap-gud').val(st.kapasitasGudang);
                        $('#impor-c20').val(0);
                        $('#impor-c40').val(0);
                        $('#impor-c45').val(0);
                        $('#impor-total-cont').val(0);
                        $('#impor-kemasan').val(st.totalKemasan || 0);
                        if (st.yor !== undefined) $('#impor-yor').val(st.yor);
                    }

                    calcYor();

                    if (notify) {
                        if (isCPSU) {
                            showToast(`Stok Depo TPP termuat: ${st.total || 0} box (${st.teus || 0} TEUs), Kapasitas Lapangan: ${st.kapasitasLapangan || 1090} TEUs!`, 'success');
                        } else {
                            showToast(`Stok Gudang CFS termuat: ${st.totalKemasan || 0} kemasan (${(st.totalVolume || 0).toFixed(2)} M³), Kapasitas: ${st.kapasitasGudang || 14196} M³!`, 'success');
                        }
                    }
                } else if (notify) {
                    showToast('Gagal menarik data: ' + (data.message || 'Error'), 'error');
                }
            } catch(e) {
                if (notify) showToast('Kesalahan koneksi database: ' + e.message, 'error');
            }
        }

        async function sendLaporanYor() {
            const payload = buildPayload();

            if (!payload.refNumber) {
                Swal.fire({ title: 'refNumber Kosong', text: 'Nomor referensi wajib diisi!', icon: 'warning', confirmButtonColor: '#10b981' });
                $('#ref-number').focus();
                return;
            }

            if (!payload.tanggalLaporan) {
                Swal.fire({ title: 'Tanggal Laporan Kosong', text: 'Tanggal laporan wajib diisi format dd-MM-yyyy!', icon: 'warning', confirmButtonColor: '#10b981' });
                $('#tgl-laporan').focus();
                return;
            }

            // Konfirmasi SweetAlert2 (Fokus Impor)
            const confirmRes = await Swal.fire({
                title: 'Konfirmasi Laporan YOR (Impor)',
                html: `
                    <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                        <p style="margin-bottom:8px;">Kirimkan laporan YOR Tempat Penimbunan Pabean / TPS ke <b>CEISA 4.0</b>?</p>
                        <div style="background:rgba(0,0,0,0.2); border:1px solid var(--border-medium); border-radius:8px; padding:12px; margin-bottom:10px;">
                            <div>📑 <b>Ref Number:</b> <code style="color:#38bdf8;">${payload.refNumber}</code></div>
                            <div>📅 <b>Tanggal Laporan:</b> ${payload.tanggalLaporan}</div>
                            <div>🏢 <b>TPS / Gudang:</b> ${payload.kodeTps} / ${payload.kodeGudang}</div>
                            <div style="margin-top:8px; border-top:1px solid var(--border-subtle); padding-top:8px;">
                                <div>📥 <b>YOR Impor:</b> <b style="color:#10b981; font-size:1.1rem;">${payload.impor.yor}%</b></div>
                                <div>📦 <b>Total Kontainer:</b> ${payload.impor.totalKontainer} Box (20f: ${payload.impor.jumlahKontainer20f}, 40f: ${payload.impor.jumlahKontainer40f})</div>
                                <div>🏟️ <b>Kapasitas Lapangan:</b> ${payload.impor.kapasitasLapangan} TEUs</div>
                                <div>🏭 <b>Kapasitas Gudang:</b> ${payload.impor.kapasitasGudang} (Kemasan: ${payload.impor.totalKemasan})</div>
                                <div style="margin-top:4px; font-size:12px; color:var(--text-muted);">📤 <i>Ekspor: Nihil (Nilai 0)</i></div>
                            </div>
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '🚀 Ya, Kirim Laporan YOR',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                reverseButtons: true
            });

            if (!confirmRes.isConfirmed) return;

            const btn = document.getElementById('btn-submit-yor');
            const spinner = document.getElementById('send-spinner');
            const icon = document.getElementById('send-icon');
            btn.disabled = true; spinner.style.display = 'inline-block'; icon.style.display = 'none';

            Swal.fire({
                title: 'Merekam Laporan YOR ke CEISA 4.0...',
                html: `Sedang mengirimkan laporan YOR <b>${payload.refNumber}</b> ke gateway Bea Cukai...`,
                allowOutsideClick: false, allowEscapeKey: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const res = await fetch('api/laporan_yor.php?action=send', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({ payload: payload })
                });
                const result = await res.json();
                const ceisaRaw = result.raw || result;

                // Tampilkan Response Card
                $('#send-result-card').show();
                const badge = $('#send-status-badge');
                badge.removeClass('badge-in badge-out').removeAttr('style');

                if (result.success) {
                    badge.addClass('badge-in').text(`HTTP ${result.code || 200} SUCCESS`);
                    $('#send-result-msg').html(`✅ <b>Berhasil:</b> ${result.message || 'Laporan YOR berhasil direkam di CEISA 4.0'}`);
                    Swal.fire({
                        title: '🎉 Laporan YOR Berhasil Direkam!',
                        html: `
                            <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                                Data Laporan YOR <b>${payload.refNumber}</b> telah berhasil diterima oleh <b>CEISA 4.0</b>.<br><br>
                                <div style="background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); border-radius:6px; padding:10px;">
                                    <span>📅 <b>Tanggal Laporan:</b> ${payload.tanggalLaporan}</span><br>
                                    <span>📥 <b>YOR Impor:</b> ${payload.impor.yor}% (${payload.impor.totalKontainer} Box)</span><br>
                                    <span>🏢 <b>Gudang / TPS:</b> ${payload.kodeGudang} / ${payload.kodeTps}</span>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: '📊 Buka Laporan YOR',
                        cancelButtonText: 'Tutup',
                        confirmButtonColor: '#10b981'
                    }).then(r => {
                        if (r.isConfirmed) window.location.href = 'report_yor.php';
                    });
                    showToast('Laporan YOR sukses terkirim!', 'success');
                } else {
                    badge.addClass('badge-out').text(`HTTP ${result.code || 400} FAILED`);
                    $('#send-result-msg').html(`❌ <b>Gagal:</b> ${result.message || 'Pengiriman ditolak oleh gateway CEISA 4.0'}`);
                    Swal.fire({
                        title: 'Pengiriman Laporan YOR Ditolak',
                        html: `<p style="color:#ef4444; font-weight:600;">${result.message || 'Gateway mengembalikan error'}</p>`,
                        icon: 'error',
                        confirmButtonColor: '#ef4444'
                    });
                    showToast('Pengiriman laporan YOR gagal: ' + (result.message||''), 'error');
                }

                $('#send-timestamp').text('Respon: ' + new Date().toLocaleTimeString('id-ID'));
                $('#send-raw-response').text(JSON.stringify(ceisaRaw, null, 4)).show();

            } catch (err) {
                Swal.fire({ title: 'Kesalahan Sistem', text: err.message, icon: 'error' });
                showToast('Terjadi kesalahan jaringan: ' + err.message, 'error');
            } finally {
                btn.disabled = false; spinner.style.display = 'none'; icon.style.display = 'inline-block';
            }
        }

        // Init
        $(document).ready(function() {
            calcYor();
            updateJsonPreview();
            // Auto fetch saat halaman dibuka pertama kali
            fetchLiveDepoStock(false);

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

            // Mobile toggle
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
