<?php
/**
 * POST Dokumen InOut — CEISA 4.0 TPS Online
 * Halaman untuk menyiapkan payload JSON Dokumen InOut (Coarri/Codeco)
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

requireAuth();

$config = require __DIR__ . '/config.php';
$endpoints = getEndpointDefinitions();
$username = $_SESSION['name'] ?? $_SESSION['username'] ?? $config['username'] ?? 'User';
$loginTime = $_SESSION['login_time'] ?? time();
$userInitial = strtoupper(substr($username, 0, 2));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirim Dokumen (POST) — <?= e($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('ceisa_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        .post-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .post-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .post-title h2 {
            margin: 0;
            font-size: 1.25rem;
            color: var(--text-primary);
        }
        .post-title p {
            margin: 5px 0 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .json-editor {
            width: 100%;
            height: 400px;
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            resize: vertical;
            line-height: 1.5;
        }
        .json-editor:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }
        .action-bar {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-hover);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.15);
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        .btn-warning:hover {
            background: #d97706;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-draft { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .status-valid { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-invalid { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
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
                    <div class="breadcrumb">
                        <span>CEISA 4.0</span>
                        <span class="separator">/</span>
                        <span>POST</span>
                        <span class="separator">/</span>
                        <span class="current">Dokumen InOut</span>
                    </div>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="theme-toggle" title="Toggle Dark/Light Mode">🌙</button>
                    <div class="user-profile">
                        <div class="avatar"><?= $userInitial ?></div>
                        <div class="user-info">
                            <span class="user-name"><?= e($username) ?></span>
                            <span class="user-role">Administrator</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="content-area">
                <div class="post-container">
                    <div class="post-card">
                        <div class="post-header">
                            <div class="post-title">
                                <h2>Persiapan Payload JSON (Dokumen InOut)</h2>
                                <p>Endpoint: <code>/portal/apis/92505fb1-951c-4eff-ba36-2df4dd91cc42/resources/6a6b209b-0a3d-4dd7-a2d3-e4fde7b7f894/POST</code></p>
                            </div>
                            <div>
                                <span id="json-status" class="status-badge status-draft">DRAFT</span>
                            </div>
                        </div>

                        <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 15px;">
                            *Catatan: Ke depannya, data di bawah ini akan ditarik secara otomatis dari tabel database <code>tpp_primamas</code>. Saat ini Anda dapat memodifikasi JSON secara manual untuk proses validasi struktur.
                        </p>

                        <textarea id="json-editor" class="json-editor" spellcheck="false" placeholder="Paste atau generate JSON di sini..."></textarea>
                        
                        <div id="validation-message" style="margin-top: 10px; font-size: 0.9rem; display: none;"></div>

                        <div class="action-bar">
                            <button id="btn-generate" class="btn-action btn-secondary">
                                <span>📄</span> Generate Sample JSON
                            </button>
                            <button id="btn-validate" class="btn-action btn-warning">
                                <span>🔍</span> Validasi JSON
                            </button>
                            <button id="btn-save-draft" class="btn-action btn-success">
                                <span>💾</span> Simpan Konsep (Lokal)
                            </button>
                            <div style="flex-grow: 1;"></div>
                            <button id="btn-post-prod" class="btn-action btn-primary" style="opacity: 0.5; cursor: not-allowed;" title="Fitur ini akan diaktifkan setelah integrasi database selesai" disabled>
                                <span>🚀</span> Kirim ke CEISA (Production)
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Script for JSON Formatting and Validation -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const jsonEditor = document.getElementById('json-editor');
            const btnGenerate = document.getElementById('btn-generate');
            const btnValidate = document.getElementById('btn-validate');
            const btnSaveDraft = document.getElementById('btn-save-draft');
            const statusBadge = document.getElementById('json-status');
            const validationMsg = document.getElementById('validation-message');

            // Load from LocalStorage if exists
            const savedJson = localStorage.getItem('ceisa_post_draft');
            if (savedJson) {
                jsonEditor.value = savedJson;
            }

            // Sample JSON provided by user
            const sampleJson = {
                "header": {
                    "kodeDokumen": "PlWCsVYQ",
                    "noBc11": "hVF",
                    "tanggalBc11": "GifucVtQrX",
                    "nomorVoyFlight": "a",
                    "tanggalBerangkat": "puepJveQBb",
                    "namaAngkut": "",
                    "refNumber": "cQ",
                    "kodeSaranaPengangkut": "nHHybJfOsinrRD",
                    "kodeTps": "aAUt",
                    "tanggalTiba": "GHbjbAvYh",
                    "kodeGudang": "rwwpsWInSyjevN",
                    "callSign": ""
                },
                "kontainer": [
                    {
                        "tanggalSegelBc": "pycXafHybCGpB",
                        "tanggalDokumenInOut": "OSnCVWx",
                        "tanggalBlAwb": "yTsNsiTBtmnKmL",
                        "flagKontainerKosong": false,
                        "waktuInOut": "rHpi",
                        "gudangTujuan": "VwLYMqo",
                        "kodeDokumenInOut": "bKVSDTUJ",
                        "ukuranKontainer": "TGyt",
                        "flagKontainer": false,
                        "kodeTimbun": "rXankceFqfa",
                        "noMasterBlAwb": "FI",
                        "pelabuhanBongkar": "kjRBsxD",
                        "nomorDokumenInOut": "ybICwSvV",
                        "nomorPolisi": "vIkGJagrCPxuA",
                        "nomorIjinTps": "jQxCIfdCtCCNLn",
                        "nomorPosBc11": "F",
                        "tanggalMasterBlAwb": "PBHT",
                        "nomorSegelBc": "",
                        "consignee": "",
                        "pelabuhanMuat": "eFv",
                        "nomorDaftarPabean": "",
                        "noBlAwb": "Iyio",
                        "kodeKantor": "pr",
                        "nomorKontainer": "FdGCtQ",
                        "idConsignee": "IjMBugwJNdNM",
                        "jenisKontainer": "jYSGfR",
                        "nomorSegel": "qdXv",
                        "isoCode": "uucllMyjoEWIc",
                        "tanggalDaftarPabean": "uWgqUWI",
                        "pelabuhanTransit": "",
                        "bruto": 1.847811338284768E9,
                        "tanggalIjinTps": "jKANkpfIuEbn"
                    }
                ]
            };

            function showMessage(msg, type) {
                validationMsg.style.display = 'block';
                validationMsg.textContent = msg;
                if (type === 'error') {
                    validationMsg.style.color = '#ef4444';
                    statusBadge.className = 'status-badge status-invalid';
                    statusBadge.textContent = 'INVALID JSON';
                } else if (type === 'success') {
                    validationMsg.style.color = '#10b981';
                    statusBadge.className = 'status-badge status-valid';
                    statusBadge.textContent = 'VALID JSON';
                } else {
                    validationMsg.style.color = 'var(--text-secondary)';
                    statusBadge.className = 'status-badge status-draft';
                    statusBadge.textContent = 'DRAFT';
                }
                setTimeout(() => { validationMsg.style.display = 'none'; }, 4000);
            }

            btnGenerate.addEventListener('click', () => {
                if(confirm("Timpa editor dengan JSON sample standar?")) {
                    jsonEditor.value = JSON.stringify(sampleJson, null, 4);
                    showMessage('Sample JSON berhasil dimuat.', 'info');
                }
            });

            btnValidate.addEventListener('click', () => {
                const text = jsonEditor.value.trim();
                if (!text) {
                    showMessage('JSON masih kosong!', 'error');
                    return;
                }
                try {
                    const parsed = JSON.parse(text);
                    // Format it nicely back to editor
                    jsonEditor.value = JSON.stringify(parsed, null, 4);
                    showMessage('Format JSON valid!', 'success');
                } catch (e) {
                    showMessage('Format JSON tidak valid: ' + e.message, 'error');
                }
            });

            btnSaveDraft.addEventListener('click', () => {
                const text = jsonEditor.value.trim();
                localStorage.setItem('ceisa_post_draft', text);
                showMessage('Konsep JSON berhasil disimpan di memori lokal.', 'info');
            });

            // Theme Toggle Logic
            const themeToggleBtn = document.getElementById('theme-toggle');
            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', () => {
                    const html = document.documentElement;
                    const currentTheme = html.getAttribute('data-theme');
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    html.setAttribute('data-theme', newTheme);
                    localStorage.setItem('ceisa_theme', newTheme);
                });
            }

            // Mobile Menu Toggle
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', () => {
                    sidebar.classList.add('active');
                    if (overlay) overlay.classList.add('active');
                });
            }
            if (overlay) {
                overlay.addEventListener('click', () => {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                });
            }
        });
    </script>
</body>
</html>
