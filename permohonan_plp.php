<?php
/**
 * Permohonan PLP (POST /permohonan-plp) — CEISA 4.0 TPS Online
 * Formulir pengiriman permohonan pemindahan lokasi penimbunan ke Bea Cukai
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

requireAuth();

$config = require __DIR__ . '/config.php';
$endpoints = getEndpointDefinitions();
$username = $_SESSION['name'] ?? $_SESSION['username'] ?? $config['username'] ?? 'User';
$loginTime = $_SESSION['login_time'] ?? time();
$userInitial = strtoupper(substr($username, 0, 2));

$todayDateDMY = date('d-m-Y');
$todayDateYMD = date('Y-m-d');
$todayCode = date('ymd');

// 3 Ref Number yang diterbitkan resmi untuk pengujian hari ini
$ref1 = "PSU0-PLP-{$todayCode}-001";
$ref2 = "PSU0-PLP-{$todayCode}-002";
$ref3 = "PSU0-PLP-{$todayCode}-003";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permohonan PLP (POST) — <?= e($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
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
        .plp-form-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08);
        }
        .plp-section-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
        }
        .plp-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .plp-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .plp-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        @media (max-width: 992px) {
            .plp-grid-2, .plp-grid-3, .plp-grid-4 {
                grid-template-columns: 1fr;
            }
        }
        .ref-preset-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(59, 130, 246, 0.12);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .ref-preset-badge:hover {
            background: rgba(59, 130, 246, 0.25);
            transform: translateY(-1px);
        }
        .scenario-pill {
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .pill-tc001 { background: rgba(16, 185, 129, 0.15); color: #10b981; border-color: rgba(16, 185, 129, 0.3); }
        .pill-tc001:hover { background: rgba(16, 185, 129, 0.25); }
        .pill-tc002 { background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-color: rgba(59, 130, 246, 0.3); }
        .pill-tc002:hover { background: rgba(59, 130, 246, 0.25); }
        .pill-tc003 { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3); }
        .pill-tc003:hover { background: rgba(245, 158, 11, 0.25); }
        .pill-tc004 { background: rgba(139, 92, 246, 0.15); color: #a78bfa; border-color: rgba(139, 92, 246, 0.3); }
        .pill-tc004:hover { background: rgba(139, 92, 246, 0.25); }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        .items-table th {
            background: var(--bg-hover);
            padding: 10px 12px;
            text-align: left;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
        }
        .items-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        .items-table input, .items-table select {
            width: 100%;
            padding: 7px 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            background: var(--bg-input);
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        .btn-sm-del {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-sm-del:hover {
            background: #ef4444;
            color: white;
        }
        .btn-sm-add {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 4px;
            padding: 6px 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }
        .btn-sm-add:hover {
            background: #3b82f6;
            color: white;
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
                        <a href="dashboard.php" style="text-decoration:none; color:inherit;">Dashboard</a>
                        <span class="separator">/</span>
                        <span class="current">Kirim Permohonan PLP</span>
                    </div>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="theme-toggle" title="Ubah Mode (Gelap / Terang)">
                        <span class="theme-toggle-icon">🌙</span>
                        <span class="theme-toggle-text">Dark</span>
                    </button>
                    <div class="api-badge">
                        <span class="dot"></span>
                        <span>API Key Active</span>
                    </div>
                    <div class="header-user">
                        <div class="user-avatar"><?= e($userInitial) ?></div>
                        <span><?= e($username) ?></span>
                        <a href="logout.php" style="color:var(--accent-red); margin-left:6px; text-decoration:none;">🚪</a>
                    </div>
                </div>
            </header>

            <main class="content" style="padding: 24px; overflow-y: auto;">
                <!-- Header Card -->
                <div class="plp-form-card" style="border-left: 4px solid var(--accent-blue);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                        <div>
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                                <h1 style="font-size:1.4rem; font-weight:800; margin:0; color:var(--text-primary);">
                                    📤 Kirim Permohonan PLP (POST /permohonan-plp)
                                </h1>
                                <span class="badge-size" style="background:rgba(59,130,246,0.2); color:#3b82f6;">CEISA 4.0</span>
                            </div>
                            <p style="margin:0; color:var(--text-secondary); font-size:0.92rem;">
                                Penyusunan dokumen elektronik Permohonan Pemindahan Lokasi Penimbunan (PLP) ke server Gateway CEISA Bea Cukai.
                            </p>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:0.8rem; color:var(--text-muted); display:block;">TPS Lini 2:</span>
                            <b style="font-size:1rem; color:var(--text-primary);">PSU0 — PT Primamas Segara Unggul (GPSU)</b>
                        </div>
                    </div>

                    <!-- 3 Ref Number Resmi yang Diterbitkan -->
                    <div style="margin-top:18px; padding-top:16px; border-top:1px dashed var(--border-color);">
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:8px;">
                            <span style="font-size:0.85rem; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">
                                🎯 3 Nomor Referensi (RefNumber) Diterbitkan untuk Uji Coba:
                            </span>
                            <span style="font-size:0.8rem; color:var(--text-muted);">Klik nomor untuk langsung memasukkan ke form</span>
                        </div>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <button type="button" class="ref-preset-badge" onclick="selectRef('<?= $ref1 ?>')">
                                <span>#1</span> <b><?= $ref1 ?></b>
                            </button>
                            <button type="button" class="ref-preset-badge" onclick="selectRef('<?= $ref2 ?>')">
                                <span>#2</span> <b><?= $ref2 ?></b>
                            </button>
                            <button type="button" class="ref-preset-badge" onclick="selectRef('<?= $ref3 ?>')">
                                <span>#3</span> <b><?= $ref3 ?></b>
                            </button>
                        </div>
                    </div>

                    <!-- Template Preset Skenario -->
                    <div style="margin-top:14px;">
                        <span style="font-size:0.85rem; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:8px;">
                            ⚡ Template Data Skenario Pengujian:
                        </span>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <button type="button" class="scenario-pill pill-tc001" onclick="loadTemplate('TC-PLP-001')">
                                📦 TC-PLP-001 (1 Kontainer)
                            </button>
                            <button type="button" class="scenario-pill pill-tc002" onclick="loadTemplate('TC-PLP-002')">
                                📦📦 TC-PLP-002 (Multi Kontainer)
                            </button>
                            <button type="button" class="scenario-pill pill-tc003" onclick="loadTemplate('TC-PLP-003')">
                                🛍️ TC-PLP-003 (Kemasan / Kargo)
                            </button>
                            <button type="button" class="scenario-pill pill-tc004" onclick="loadTemplate('TC-PLP-004')">
                                🔀 TC-PLP-004 (Gabungan Kontainer & Kemasan)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Form Permohonan PLP -->
                <form id="formPermohonanPlp" onsubmit="event.preventDefault(); submitPermohonan();">
                    <!-- Section 1: Header Dokumen & Pemohon -->
                    <div class="plp-form-card">
                        <div class="plp-section-title">
                            <span>📄</span>
                            <span>1. Informasi Dokumen & Identitas Permohonan</span>
                        </div>
                        <div class="plp-grid-4">
                            <div class="form-group">
                                <label class="form-label">Nomor Referensi (Ref Number) *</label>
                                <input type="text" id="refNumber" name="refNumber" class="form-input" value="<?= $ref1 ?>" required maxlength="20" style="font-family:'JetBrains Mono',monospace; font-weight:700;">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Nomor Surat Permohonan *</label>
                                <input type="text" id="nomorSurat" name="nomorSurat" class="form-input" value="SRT-001/PSU/PLP/09/2026" required maxlength="60">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tanggal Surat Permohonan *</label>
                                <input type="text" id="tanggalSurat" name="tanggalSurat" class="form-input" value="<?= $todayDateDMY ?>" required placeholder="dd-MM-yyyy">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kode Kantor Pabean *</label>
                                <input type="text" id="kodeKantor" name="kodeKantor" class="form-input" value="040300" required maxlength="6">
                            </div>
                        </div>

                        <div class="plp-grid-4" style="margin-top:14px;">
                            <div class="form-group">
                                <label class="form-label">Nomor BC 1.1 *</label>
                                <input type="text" id="nomorBc11" name="nomorBc11" class="form-input" value="003881" required maxlength="6">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tanggal BC 1.1 *</label>
                                <input type="text" id="tanggalBc11" name="tanggalBc11" class="form-input" value="<?= $todayDateDMY ?>" required placeholder="dd-MM-yyyy">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Nama Pemohon *</label>
                                <input type="text" id="namaPemohon" name="namaPemohon" class="form-input" value="PT PRIMAMAS SEGARA UNGGUL" required maxlength="50">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Alasan PLP *</label>
                                <select id="kodeAlasanPlp" name="kodeAlasanPlp" class="form-input" required>
                                    <option value="1" selected>1 — Kongesti Lapangan / YOR Tinggi</option>
                                    <option value="2">2 — Fasilitas Penimbunan Khusus</option>
                                    <option value="3">3 — Relokasi / Pemeriksaan Fisik Terpadu</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Rute TPS & Sarana Pengangkut -->
                    <div class="plp-form-card">
                        <div class="plp-section-title">
                            <span>🚢</span>
                            <span>2. Rute Pemindahan & Sarana Pengangkut</span>
                        </div>
                        <div class="plp-grid-4">
                            <div class="form-group">
                                <label class="form-label">Kode TPS Asal (Lini 1) *</label>
                                <input type="text" id="kodeTpsAsal" name="kodeTpsAsal" class="form-input" value="KOJA" required maxlength="4">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Gudang / Lapangan Asal *</label>
                                <input type="text" id="gudangAsal" name="gudangAsal" class="form-input" value="TPK1" required maxlength="4">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kode TPS Tujuan (Lini 2) *</label>
                                <input type="text" id="kodeTpsTujuan" name="kodeTpsTujuan" class="form-input" value="PSU0" required maxlength="4" style="font-weight:700; color:var(--accent-blue);">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Gudang / Lapangan Tujuan *</label>
                                <input type="text" id="gudangTujuan" name="gudangTujuan" class="form-input" value="GPSU" required maxlength="4" style="font-weight:700; color:var(--accent-blue);">
                            </div>
                        </div>

                        <div class="plp-grid-4" style="margin-top:14px;">
                            <div class="form-group">
                                <label class="form-label">Nama Kapal / Sarana Angkut *</label>
                                <input type="text" id="namaAngkut" name="namaAngkut" class="form-input" value="MV. EVER GIVEN" required maxlength="50">
                            </div>
                            <div class="form-group">
                                <label class="form-label">No. Voyage / Flight *</label>
                                <input type="text" id="noVoyFlight" name="noVoyFlight" class="form-input" value="V-2026A" required maxlength="20">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Call Sign</label>
                                <input type="text" id="callSign" name="callSign" class="form-input" value="9V2026" maxlength="20">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tanggal Tiba</label>
                                <input type="text" id="tanggalTiba" name="tanggalTiba" class="form-input" value="<?= $todayDateDMY ?>" placeholder="dd-MM-yyyy">
                            </div>
                        </div>

                        <div class="plp-grid-2" style="margin-top:14px;">
                            <div class="form-group">
                                <label class="form-label">YOR Asal (%) *</label>
                                <input type="number" step="0.1" id="yorAsal" name="yorAsal" class="form-input" value="85.5" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">YOR Tujuan (%) *</label>
                                <input type="number" step="0.1" id="yorTujuan" name="yorTujuan" class="form-input" value="42.0" required>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Rincian Kontainer -->
                    <div class="plp-form-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <div class="plp-section-title" style="margin:0; border:none; padding:0;">
                                <span>📦</span>
                                <span>3. Rincian Kontainer (<span id="contCountBadge">2</span> unit)</span>
                            </div>
                            <button type="button" class="btn-sm-add" onclick="addContRow()">
                                <span>➕</span> Tambah Kontainer
                            </button>
                        </div>
                        <table class="items-table" id="kontainerTable">
                            <thead>
                                <tr>
                                    <th style="width:40px; text-align:center;">No</th>
                                    <th>Nomor Kontainer</th>
                                    <th style="width:140px;">Ukuran</th>
                                    <th style="width:140px;">Jenis Muat</th>
                                    <th style="width:50px; text-align:center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="kontainerTableBody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Section 4: Rincian Kemasan / Kargo -->
                    <div class="plp-form-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <div class="plp-section-title" style="margin:0; border:none; padding:0;">
                                <span>🛍️</span>
                                <span>4. Rincian Kemasan / Kargo (<span id="kemCountBadge">1</span> item)</span>
                            </div>
                            <button type="button" class="btn-sm-add" onclick="addKemRow()">
                                <span>➕</span> Tambah Kemasan
                            </button>
                        </div>
                        <table class="items-table" id="kemasanTable">
                            <thead>
                                <tr>
                                    <th style="width:40px; text-align:center;">No</th>
                                    <th style="width:150px;">Jenis Kemasan</th>
                                    <th style="width:140px;">Jumlah</th>
                                    <th>Nomor B/L atau AWB</th>
                                    <th style="width:160px;">Tanggal B/L</th>
                                    <th style="width:50px; text-align:center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="kemasanTableBody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Action Bar -->
                    <div style="display:flex; gap:12px; margin-bottom:30px; flex-wrap:wrap;">
                        <button type="submit" class="btn-action btn-primary" id="btnSubmitPlp" style="padding:12px 28px; font-size:1rem; font-weight:700;">
                            <span>🚀</span> Kirim ke Bea Cukai (POST /permohonan-plp)
                        </button>
                        <button type="button" class="btn-action btn-secondary" onclick="previewJsonPayload()">
                            <span>📋</span> Preview Payload JSON
                        </button>
                        <button type="button" class="btn-action btn-secondary" onclick="resetToDefault()">
                            <span>🔄</span> Reset Form
                        </button>
                    </div>
                </form>

                <!-- Section 5: Riwayat Permohonan Terkirim -->
                <div class="plp-form-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <div class="plp-section-title" style="margin:0; border:none; padding:0;">
                            <span>📋</span>
                            <span>Riwayat Permohonan PLP yang Pernah Dikirim</span>
                        </div>
                        <button type="button" class="btn-sm-add" onclick="loadHistory()">
                            <span>🔄</span> Refresh Riwayat
                        </button>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="items-table" id="historyTable">
                            <thead>
                                <tr>
                                    <th style="width:50px;">ID</th>
                                    <th>Ref Number</th>
                                    <th>No Surat</th>
                                    <th>Rute Asal ➔ Tujuan</th>
                                    <th>BC 1.1</th>
                                    <th>Item</th>
                                    <th>Status</th>
                                    <th>Waktu Kirim</th>
                                    <th style="width:80px; text-align:center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <tr><td colspan="9" style="text-align:center; color:var(--text-muted);">Memuat riwayat...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- JSON Preview Modal -->
    <div class="modal-overlay" id="plp-json-modal">
        <div class="modal" style="max-width:850px; width:90%;">
            <div class="modal-header">
                <h3 style="margin:0;">📋 Preview Payload Permohonan PLP</h3>
                <button class="modal-close" onclick="closePlpModal()">×</button>
            </div>
            <div class="modal-body">
                <pre id="plp-json-preview" style="background:#1e1e1e; color:#d4d4d4; padding:15px; border-radius:8px; max-height:500px; overflow:auto; font-family:'JetBrains Mono',monospace; font-size:13px;"></pre>
            </div>
            <div style="padding:15px 20px; border-top:1px solid var(--border-color); text-align:right;">
                <button class="btn-action btn-secondary" onclick="closePlpModal()">Tutup</button>
            </div>
        </div>
    </div>

    <script>
        let kontainerData = [
            { nomor: 'EMCU1234567', ukuran: '20', jenisMuat: 'FCL' },
            { nomor: 'TGHU9876543', ukuran: '40', jenisMuat: 'FCL' }
        ];

        let kemasanData = [
            { jenis: 'CTN', jumlah: 150, noBl: 'BL-EMCU-001', tglBl: '<?= $todayDateDMY ?>' }
        ];

        document.addEventListener('DOMContentLoaded', () => {
            renderKontainerTable();
            renderKemasanTable();
            loadHistory();
        });

        function selectRef(ref) {
            document.getElementById('refNumber').value = ref;
            Swal.fire({
                icon: 'info',
                title: 'Ref Number Terpilih',
                text: 'Form menggunakan Ref Number: ' + ref,
                timer: 1500,
                showConfirmButton: false
            });
        }

        function loadTemplate(scenario) {
            const today = '<?= $todayDateDMY ?>';
            if (scenario === 'TC-PLP-001') {
                // 1 Kontainer
                document.getElementById('refNumber').value = '<?= $ref1 ?>';
                document.getElementById('nomorSurat').value = 'SRT-001/PSU/PLP/09/2026';
                kontainerData = [
                    { nomor: 'EMCU1234567', ukuran: '20', jenisMuat: 'FCL' }
                ];
                kemasanData = [];
            } else if (scenario === 'TC-PLP-002') {
                // Multi Kontainer
                document.getElementById('refNumber').value = '<?= $ref2 ?>';
                document.getElementById('nomorSurat').value = 'SRT-002/PSU/PLP/09/2026';
                kontainerData = [
                    { nomor: 'EMCU1234567', ukuran: '20', jenisMuat: 'FCL' },
                    { nomor: 'TGHU9876543', ukuran: '40', jenisMuat: 'FCL' },
                    { nomor: 'SUDU4567890', ukuran: '20', jenisMuat: 'FCL' }
                ];
                kemasanData = [];
            } else if (scenario === 'TC-PLP-003') {
                // Kemasan saja
                document.getElementById('refNumber').value = '<?= $ref3 ?>';
                document.getElementById('nomorSurat').value = 'SRT-003/PSU/PLP/09/2026';
                kontainerData = [];
                kemasanData = [
                    { jenis: 'CTN', jumlah: 250, noBl: 'BL-KMS-003', tglBl: today }
                ];
            } else if (scenario === 'TC-PLP-004') {
                // Gabungan Kontainer & Kemasan
                document.getElementById('refNumber').value = '<?= $ref1 ?>';
                document.getElementById('nomorSurat').value = 'SRT-004/PSU/PLP/09/2026';
                kontainerData = [
                    { nomor: 'EMCU1234567', ukuran: '20', jenisMuat: 'FCL' },
                    { nomor: 'TGHU9876543', ukuran: '40', jenisMuat: 'FCL' }
                ];
                kemasanData = [
                    { jenis: 'CTN', jumlah: 150, noBl: 'BL-EMCU-001', tglBl: today }
                ];
            }

            renderKontainerTable();
            renderKemasanTable();

            Swal.fire({
                icon: 'success',
                title: 'Template ' + scenario + ' Dimuat',
                text: 'Data contoh telah disesuaikan dengan skenario ' + scenario,
                timer: 1500,
                showConfirmButton: false
            });
        }

        function renderKontainerTable() {
            const tbody = document.getElementById('kontainerTableBody');
            document.getElementById('contCountBadge').textContent = kontainerData.length;

            if (kontainerData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:15px;">Tidak ada kontainer (Opsional jika hanya memindahkan kemasan).</td></tr>';
                return;
            }

            tbody.innerHTML = kontainerData.map((item, idx) => `
                <tr>
                    <td style="text-align:center; font-weight:700;">${idx + 1}</td>
                    <td>
                        <input type="text" value="${item.nomor}" onchange="kontainerData[${idx}].nomor = this.value.toUpperCase();" style="font-family:'JetBrains Mono',monospace; font-weight:700;">
                    </td>
                    <td>
                        <select onchange="kontainerData[${idx}].ukuran = this.value;">
                            <option value="20" ${item.ukuran === '20' ? 'selected' : ''}>20 ft</option>
                            <option value="40" ${item.ukuran === '40' ? 'selected' : ''}>40 ft</option>
                            <option value="45" ${item.ukuran === '45' ? 'selected' : ''}>45 ft</option>
                        </select>
                    </td>
                    <td>
                        <select onchange="kontainerData[${idx}].jenisMuat = this.value;">
                            <option value="FCL" ${item.jenisMuat === 'FCL' ? 'selected' : ''}>FCL</option>
                            <option value="LCL" ${item.jenisMuat === 'LCL' ? 'selected' : ''}>LCL</option>
                        </select>
                    </td>
                    <td style="text-align:center;">
                        <button type="button" class="btn-sm-del" onclick="removeContRow(${idx})" title="Hapus Kontainer">🗑️</button>
                    </td>
                </tr>
            `).join('');
        }

        function addContRow() {
            kontainerData.push({ nomor: 'CONT' + Math.floor(1000000 + Math.random() * 9000000), ukuran: '20', jenisMuat: 'FCL' });
            renderKontainerTable();
        }

        function removeContRow(idx) {
            kontainerData.splice(idx, 1);
            renderKontainerTable();
        }

        function renderKemasanTable() {
            const tbody = document.getElementById('kemasanTableBody');
            document.getElementById('kemCountBadge').textContent = kemasanData.length;

            if (kemasanData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:15px;">Tidak ada kemasan (Opsional jika hanya memindahkan kontainer).</td></tr>';
                return;
            }

            tbody.innerHTML = kemasanData.map((item, idx) => `
                <tr>
                    <td style="text-align:center; font-weight:700;">${idx + 1}</td>
                    <td>
                        <input type="text" value="${item.jenis}" onchange="kemasanData[${idx}].jenis = this.value.toUpperCase();">
                    </td>
                    <td>
                        <input type="number" value="${item.jumlah}" onchange="kemasanData[${idx}].jumlah = Number(this.value);">
                    </td>
                    <td>
                        <input type="text" value="${item.noBl}" onchange="kemasanData[${idx}].noBl = this.value;" style="font-family:'JetBrains Mono',monospace;">
                    </td>
                    <td>
                        <input type="text" value="${item.tglBl}" onchange="kemasanData[${idx}].tglBl = this.value;" placeholder="dd-MM-yyyy">
                    </td>
                    <td style="text-align:center;">
                        <button type="button" class="btn-sm-del" onclick="removeKemRow(${idx})" title="Hapus Kemasan">🗑️</button>
                    </td>
                </tr>
            `).join('');
        }

        function addKemRow() {
            kemasanData.push({ jenis: 'CTN', jumlah: 100, noBl: 'BL-' + Math.floor(1000 + Math.random() * 9000), tglBl: '<?= $todayDateDMY ?>' });
            renderKemasanTable();
        }

        function removeKemRow(idx) {
            kemasanData.splice(idx, 1);
            renderKemasanTable();
        }

        function buildPayload() {
            const header = {
                refNumber: document.getElementById('refNumber').value.trim(),
                nomorSurat: document.getElementById('nomorSurat').value.trim(),
                tanggalSurat: document.getElementById('tanggalSurat').value.trim(),
                kodeKantor: document.getElementById('kodeKantor').value.trim(),
                nomorBc11: document.getElementById('nomorBc11').value.trim(),
                tanggalBc11: document.getElementById('tanggalBc11').value.trim(),
                namaPemohon: document.getElementById('namaPemohon').value.trim(),
                kodeAlasanPlp: document.getElementById('kodeAlasanPlp').value.trim(),
                kodeTpsAsal: document.getElementById('kodeTpsAsal').value.trim(),
                gudangAsal: document.getElementById('gudangAsal').value.trim(),
                kodeTpsTujuan: document.getElementById('kodeTpsTujuan').value.trim(),
                gudangTujuan: document.getElementById('gudangTujuan').value.trim(),
                namaAngkut: document.getElementById('namaAngkut').value.trim(),
                noVoyFlight: document.getElementById('noVoyFlight').value.trim(),
                callSign: document.getElementById('callSign').value.trim(),
                tanggalTiba: document.getElementById('tanggalTiba').value.trim(),
                yorAsal: Number(document.getElementById('yorAsal').value),
                yorTujuan: Number(document.getElementById('yorTujuan').value),
                tipeData: '1'
            };

            const kontainerList = kontainerData.map(c => ({
                nomorKontainer: c.nomor,
                ukuranKontainer: c.ukuran,
                jenisKontainer: '2',
                jenisMuat: c.jenisMuat
            }));

            const kemasanList = kemasanData.map(k => ({
                jenisKemasan: k.jenis,
                jumlahKemasan: String(k.jumlah),
                nomorBlAwb: k.noBl,
                tanggalBlAwb: k.tglBl
            }));

            return {
                header: header,
                detil: [
                    {
                        kontainer: kontainerList,
                        kemasan: kemasanList
                    }
                ]
            };
        }

        function previewJsonPayload() {
            const payload = buildPayload();
            document.getElementById('plp-json-preview').textContent = JSON.stringify(payload, null, 2);
            document.getElementById('plp-json-modal').classList.add('visible');
        }

        function closePlpModal() {
            document.getElementById('plp-json-modal').classList.remove('visible');
        }

        async function submitPermohonan() {
            const payload = buildPayload();

            if (payload.detil[0].kontainer.length === 0 && payload.detil[0].kemasan.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Rincian Kosong',
                    text: 'Harus ada minimal 1 Kontainer atau 1 Kemasan yang diajukan!'
                });
                return;
            }

            const confirm = await Swal.fire({
                title: 'Konfirmasi Pengiriman PLP',
                html: `Kirim Permohonan PLP dengan RefNumber: <b>${payload.header.refNumber}</b> ke Gateway CEISA 4.0 Bea Cukai?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Kirim Sekarang!',
                cancelButtonText: 'Batal'
            });

            if (!confirm.isConfirmed) return;

            const btn = document.getElementById('btnSubmitPlp');
            btn.disabled = true;
            btn.innerHTML = '<span>⏳</span> Mengirim ke Bea Cukai...';

            try {
                const res = await fetch('api/permohonan_plp.php?action=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (data.success || data.code === 200 || data.code === 201) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Permohonan Berhasil Dikirim!',
                        html: `
                            <p>${data.message || 'Permohonan PLP telah diterima oleh CEISA 4.0.'}</p>
                            <pre style="text-align:left; background:#1e1e1e; color:#a7f3d0; padding:10px; border-radius:6px; font-size:12px; max-height:200px; overflow:auto;">${JSON.stringify(data.raw_response || data, null, 2)}</pre>
                        `
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: `Gagal Kirim (HTTP ${data.code || 'Error'})`,
                        html: `
                            <p>${data.message || 'Terjadi kesalahan saat pengiriman ke Bea Cukai.'}</p>
                            <pre style="text-align:left; background:#1e1e1e; color:#fca5a5; padding:10px; border-radius:6px; font-size:12px; max-height:200px; overflow:auto;">${JSON.stringify(data.raw_response || data, null, 2)}</pre>
                        `
                    });
                }
                loadHistory();
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Koneksi',
                    text: err.message
                });
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span>🚀</span> Kirim ke Bea Cukai (POST /permohonan-plp)';
            }
        }

        async function loadHistory() {
            const tbody = document.getElementById('historyTableBody');
            try {
                const res = await fetch('api/permohonan_plp.php?action=history');
                const json = await res.json();
                if (json.success && json.data && json.data.length > 0) {
                    tbody.innerHTML = json.data.map(row => `
                        <tr>
                            <td>#${row.id}</td>
                            <td><b style="font-family:'JetBrains Mono',monospace;">${row.ref_number}</b></td>
                            <td>${row.no_surat || '-'} (${row.tgl_surat || '-'})</td>
                            <td>${row.kd_tps_asal || '-'} ➔ <b>${row.kd_tps_tujuan || '-'}</b></td>
                            <td>${row.no_bc11 || '-'}</td>
                            <td>${row.total_kontainer} Cont / ${row.total_kemasan} Kms</td>
                            <td>
                                <span class="plp-tag-status ${row.status_kirim === 'SUCCESS' ? 'tag-setuju' : 'tag-tolak'}">
                                    ${row.status_kirim} (${row.http_code || '-'})
                                </span>
                            </td>
                            <td>${row.created_at}</td>
                            <td style="text-align:center;">
                                <button type="button" class="ref-preset-badge" onclick='showRawDetail(${JSON.stringify(row.raw_response || "{}")})'>Detail</button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; color:var(--text-muted); padding:15px;">Belum ada riwayat pengiriman permohonan PLP.</td></tr>';
                }
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; color:#ef4444; padding:15px;">Gagal memuat riwayat.</td></tr>';
            }
        }

        function showRawDetail(raw) {
            let content = raw;
            if (typeof raw === 'string') {
                try { content = JSON.parse(raw); } catch (e) {}
            }
            document.getElementById('plp-json-preview').textContent = JSON.stringify(content, null, 2);
            document.getElementById('plp-json-modal').classList.add('visible');
        }

        function resetToDefault() {
            loadTemplate('TC-PLP-004');
        }
    </script>
</body>
</html>
