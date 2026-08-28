<?php
/**
 * CCTV Monitoring — CEISA 4.0 & TPS Online Depo
 * Live CCTV Stream Viewer with HTML5 Canvas & RTSP Integration
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

requireAuth();

$config = require __DIR__ . '/config.php';
$endpoints = getEndpointDefinitions();
$username = $_SESSION['name'] ?? $_SESSION['username'] ?? $config['username'] ?? 'User';
$loginTime = $_SESSION['login_time'] ?? time();
$userInitial = strtoupper(substr($username, 0, 2));

// Default RTSP & Stream settings
$defaultRtspUrl = 'rtsp://admin:Password123@192.168.1.101:554/Streaming/Channels/101';
$defaultWsBridge = 'ws://' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ':9999';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Live CCTV Monitoring — TPS Online Depo Primamas">
    <title>Live CCTV Monitoring — <?= e($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('ceisa_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        /* CCTV Dedicated Styling */
        .cctv-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .cctv-main-card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }

        .cctv-card-header {
            padding: 16px 20px;
            background: var(--card-header-bg);
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .cctv-header-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cctv-icon-badge {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .cctv-title-text h2 {
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--text-heading);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cctv-title-text p {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin: 2px 0 0 0;
            font-family: 'JetBrains Mono', monospace;
        }

        .cctv-header-badges {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .badge-live {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            letter-spacing: 0.5px;
        }

        .badge-live .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ef4444;
            box-shadow: 0 0 8px #ef4444;
            animation: pulse-dot 1.5s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }

        .badge-info-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            border: 1px solid var(--border-subtle);
            font-family: 'JetBrains Mono', monospace;
        }

        /* Video Stage */
        .cctv-stage {
            position: relative;
            background: #000000;
            width: 100%;
            aspect-ratio: 16 / 9;
            max-height: 720px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .cctv-canvas {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            background: #050811;
        }

        /* OSD Overlays */
        .cctv-osd-top-left {
            position: absolute;
            top: 14px;
            left: 16px;
            color: #ffffff;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            font-weight: 600;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.9), 0 0 8px rgba(0,0,0,0.8);
            pointer-events: none;
            display: flex;
            flex-direction: column;
            gap: 3px;
            z-index: 5;
        }

        .cctv-osd-top-right {
            position: absolute;
            top: 14px;
            right: 16px;
            color: #ffffff;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            font-weight: 600;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.9), 0 0 8px rgba(0,0,0,0.8);
            pointer-events: none;
            text-align: right;
            z-index: 5;
        }

        .cctv-osd-rec {
            color: #ef4444;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .cctv-osd-rec::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ef4444;
            display: inline-block;
            box-shadow: 0 0 6px #ef4444;
        }

        /* Toolbar Controls */
        .cctv-toolbar {
            padding: 12px 18px;
            background: var(--card-header-bg);
            border-top: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .cctv-toolbar-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .cctv-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid var(--border-subtle);
            background: var(--bg-surface);
            color: var(--text-primary);
            text-decoration: none;
            user-select: none;
        }

        .cctv-btn:hover {
            background: var(--bg-card-hover);
            border-color: var(--border-hover);
            transform: translateY(-1px);
        }

        .cctv-btn-primary {
            background: var(--accent-blue-gradient);
            border-color: transparent;
            color: #ffffff;
        }

        .cctv-btn-primary:hover {
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
        }

        .cctv-btn-success {
            background: var(--accent-green-gradient);
            border-color: transparent;
            color: #ffffff;
        }

        .cctv-select {
            padding: 8px 12px;
            border-radius: var(--radius-md);
            background: var(--bg-input);
            border: 1px solid var(--border-subtle);
            color: var(--text-primary);
            font-size: 0.85rem;
            font-family: inherit;
            cursor: pointer;
            outline: none;
            transition: var(--transition);
        }

        .cctv-select:focus {
            border-color: var(--border-focus);
        }

        /* Detail & Info Grid */
        .cctv-grid-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }

        .cctv-info-card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-sm);
        }

        .cctv-info-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-heading);
            margin: 0 0 14px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cctv-kv-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .cctv-kv-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            font-size: 0.85rem;
            border-bottom: 1px dashed var(--border-subtle);
            padding-bottom: 8px;
        }

        .cctv-kv-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .cctv-kv-key {
            color: var(--text-secondary);
            flex-shrink: 0;
        }

        .cctv-kv-val {
            color: var(--text-primary);
            font-weight: 500;
            font-family: 'JetBrains Mono', monospace;
            text-align: right;
            word-break: break-all;
        }

        .cctv-code-snippet {
            background: var(--modal-code-bg);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: var(--modal-code-color);
            overflow-x: auto;
            margin-top: 10px;
            line-height: 1.5;
            position: relative;
        }

        /* Modal Snapshot */
        .snapshot-modal-img {
            width: 100%;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-subtle);
            margin-top: 10px;
            background: #000;
        }

        /* Fullscreen Canvas Handling */
        .cctv-stage:fullscreen,
        .cctv-stage:-webkit-full-screen {
            width: 100vw !important;
            height: 100vh !important;
            max-height: 100vh !important;
            aspect-ratio: auto !important;
        }
    </style>
</head>
<body data-login-time="<?= $loginTime ?>">
    <div class="dashboard">
        <!-- Sidebar Overlay (Mobile) -->
        <div class="sidebar-overlay"></div>

        <!-- ===== SIDEBAR ===== -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <div class="brand-icon">🏛️</div>
                    <div class="brand-text">
                        <h2>CEISA 4.0</h2>
                        <span>TPS Online H2H</span>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <!-- Home -->
                <div class="nav-section">
                    <a href="dashboard.php" class="nav-item" style="text-decoration:none; color:inherit;">
                        <span class="nav-icon">🏠</span>
                        <span>Dashboard</span>
                    </a>
                </div>

                <!-- CCTV Menu (Active) -->
                <div class="nav-section">
                    <div class="nav-section-label">Monitoring & Security</div>
                    <div class="nav-item active" style="cursor:default;">
                        <span class="nav-icon">📹</span>
                        <span>CCTV Live Stream</span>
                        <span class="nav-badge" style="background:rgba(239, 68, 68, 0.2); color:#ef4444; border:1px solid rgba(239,68,68,0.3);">LIVE</span>
                    </div>
                </div>

                <!-- CEISA Dynamic Categories Links to Dashboard -->
                <div class="nav-section-label">Layanan Bea Cukai</div>
                <?php foreach ($endpoints as $catKey => $category): ?>
                <div class="nav-section">
                    <a href="dashboard.php" class="nav-item" style="text-decoration:none; color:inherit;">
                        <span class="nav-icon"><?= $category['icon'] ?></span>
                        <span><?= e($category['label']) ?></span>
                        <span class="nav-badge"><?= count($category['endpoints']) ?></span>
                    </a>
                </div>
                <?php endforeach; ?>
            </nav>
        </aside>

        <!-- ===== MAIN CONTENT ===== -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="menu-toggle" id="menu-toggle">☰</button>
                    <div class="header-breadcrumb">
                        <a href="dashboard.php" style="color:var(--text-secondary); text-decoration:none;">Dashboard</a>
                        <span class="separator">/</span>
                        <span class="current">CCTV Stream Canvas</span>
                    </div>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="theme-toggle" title="Ubah Mode (Gelap / Terang)" aria-label="Toggle theme">
                        <span class="theme-toggle-icon">🌙</span>
                        <span class="theme-toggle-text">Dark</span>
                    </button>
                    <div class="header-user">
                        <div class="user-avatar"><?= e($userInitial) ?></div>
                        <span><?= e($username) ?></span>
                        <a href="logout.php" title="Keluar dari sistem" style="color:var(--accent-red); margin-left:6px; font-size:0.9rem; text-decoration:none; opacity:0.8; transition:0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.8">🚪</a>
                    </div>
                </div>
            </header>

            <!-- CCTV Content Area -->
            <main class="content">
                <div class="cctv-container">
                    
                    <!-- Player Main Card -->
                    <div class="cctv-main-card">
                        <!-- Card Header -->
                        <div class="cctv-card-header">
                            <div class="cctv-header-title">
                                <div class="cctv-icon-badge">📹</div>
                                <div class="cctv-title-text">
                                    <h2>CCTV Gate In & Depo Primamas <span style="font-size:0.8rem; font-weight:normal; color:var(--text-muted);">(Channel 101)</span></h2>
                                    <p id="cctv-stream-url-display"><?= e($defaultRtspUrl) ?></p>
                                </div>
                            </div>
                            <div class="cctv-header-badges">
                                <span class="badge-live" id="stream-status-badge">
                                    <span class="live-dot"></span>
                                    <span id="stream-status-text">LIVE CANVAS</span>
                                </span>
                                <span class="badge-info-pill" id="cctv-fps-badge">FPS: 25.0</span>
                                <span class="badge-info-pill" id="cctv-res-badge">1920x1080 (1080p)</span>
                            </div>
                        </div>

                        <!-- Video Stage / Canvas Area -->
                        <div class="cctv-stage" id="cctv-stage">
                            <!-- OSD Top Left -->
                            <div class="cctv-osd-top-left">
                                <div style="letter-spacing:1px;">CAM 01 - GATE IN DEPO</div>
                                <div style="font-size:0.75rem; opacity:0.85;" id="osd-channel-label">CH 101 / MAIN STREAM</div>
                            </div>

                            <!-- OSD Top Right -->
                            <div class="cctv-osd-top-right">
                                <div id="osd-clock">2026-08-28 00:00:00</div>
                                <div class="cctv-osd-rec">● REC</div>
                            </div>

                            <!-- HTML5 Video Canvas -->
                            <canvas id="cctv-canvas" class="cctv-canvas" width="1280" height="720"></canvas>
                        </div>

                        <!-- Player Toolbar -->
                        <div class="cctv-toolbar">
                            <div class="cctv-toolbar-group">
                                <button class="cctv-btn cctv-btn-primary" id="btn-play-pause">
                                    <span id="btn-play-icon">⏸️</span>
                                    <span id="btn-play-text">Pause Stream</span>
                                </button>
                                <button class="cctv-btn" id="btn-reconnect" title="Koneksikan ulang RTSP stream">
                                    <span>🔄</span>
                                    <span>Reconnect</span>
                                </button>
                                <select class="cctv-select" id="channel-selector" title="Pilih Channel Stream">
                                    <option value="101" selected>Stream 101: Main Stream (1080p HD)</option>
                                    <option value="102">Stream 102: Sub Stream (720p SD)</option>
                                </select>
                            </div>

                            <div class="cctv-toolbar-group">
                                <button class="cctv-btn cctv-btn-success" id="btn-snapshot" title="Ambil foto dari canvas">
                                    <span>📸</span>
                                    <span>Snapshot</span>
                                </button>
                                <button class="cctv-btn" id="btn-motion" title="Aktifkan AI Motion Detection">
                                    <span>🏃</span>
                                    <span id="btn-motion-text">Motion: ON</span>
                                </button>
                                <button class="cctv-btn" id="btn-settings" title="Pengaturan RTSP & Bridge">
                                    <span>⚙️</span>
                                    <span>Settings</span>
                                </button>
                                <button class="cctv-btn" id="btn-fullscreen" title="Layar Penuh (Fullscreen)">
                                    <span>⛶</span>
                                    <span>Fullscreen</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Information & Setup Cards Grid -->
                    <div class="cctv-grid-cards">
                        <!-- RTSP Stream Info -->
                        <div class="cctv-info-card">
                            <h3><span>📡</span> Parameter Stream RTSP</h3>
                            <div class="cctv-kv-list">
                                <div class="cctv-kv-item">
                                    <span class="cctv-kv-key">RTSP URL:</span>
                                    <span class="cctv-kv-val" id="info-rtsp-url"><?= e($defaultRtspUrl) ?></span>
                                </div>
                                <div class="cctv-kv-item">
                                    <span class="cctv-kv-key">IP Camera:</span>
                                    <span class="cctv-kv-val">192.168.1.101</span>
                                </div>
                                <div class="cctv-kv-item">
                                    <span class="cctv-kv-key">Port RTSP:</span>
                                    <span class="cctv-kv-val">554</span>
                                </div>
                                <div class="cctv-kv-item">
                                    <span class="cctv-kv-key">Autentikasi:</span>
                                    <span class="cctv-kv-val">admin / *******</span>
                                </div>
                                <div class="cctv-kv-item">
                                    <span class="cctv-kv-key">Target Element:</span>
                                    <span class="cctv-kv-val">&lt;canvas id="cctv-canvas"&gt;</span>
                                </div>
                                <div class="cctv-kv-item">
                                    <span class="cctv-kv-key">Metode Render:</span>
                                    <span class="cctv-kv-val">HTML5 2D / WebGL Canvas</span>
                                </div>
                            </div>
                        </div>

                        <!-- WebSocket Bridge & FFmpeg Relay Guide -->
                        <div class="cctv-info-card">
                            <h3><span>🌉</span> WebSocket RTSP Bridge (JSMpeg / Relay)</h3>
                            <p style="font-size:0.83rem; color:var(--text-secondary); margin:0 0 10px 0;">
                                Browser modern merender RTSP ke Canvas secara langsung menggunakan WebSocket relay stream (JSMpeg/FFmpeg).
                            </p>
                            <div class="cctv-kv-list">
                                <div class="cctv-kv-item">
                                    <span class="cctv-kv-key">WS Bridge URL:</span>
                                    <span class="cctv-kv-val" id="info-ws-url"><?= e($defaultWsBridge) ?></span>
                                </div>
                            </div>
                            <div class="cctv-code-snippet">
# Jalankan relay stream di server lokal:<br>
ffmpeg -i "rtsp://admin:Admin123@192.168.1.101:554/Streaming/Channels/101" -f mpegts -codec:v mpeg1video -s 1280x720 -b:v 1500k -bf 0 http://localhost:8081/supersecret
                            </div>
                        </div>
                    </div>

                    <!-- Motion Log Gallery -->
                    <div class="cctv-info-card" style="margin-top: 20px; grid-column: 1 / -1;">
                        <h3 style="color: #ef4444;"><span>🚨</span> Log Aktivitas Pergerakan (Auto Snapshot)</h3>
                        <p style="font-size:0.83rem; color:var(--text-secondary); margin:0 0 15px 0;">
                            Sistem akan otomatis mengambil tangkapan layar setiap kali mendeteksi ada pergerakan di area CCTV.
                        </p>
                        <div id="motion-log-gallery" style="display: flex; gap: 15px; overflow-x: auto; padding: 10px 0; min-height: 160px; align-items: center;">
                            <p style="color:var(--text-secondary); font-size: 0.9rem; margin: auto;" id="motion-log-empty">Belum ada pergerakan yang terdeteksi. Pastikan tombol Motion ON.</p>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Snapshot Modal -->
    <div class="modal-overlay" id="snapshot-modal">
        <div class="modal" style="max-width:700px;">
            <div class="modal-header">
                <h3>📸 Hasil Snapshot CCTV</h3>
                <button class="modal-close" onclick="closeSnapshotModal()">×</button>
            </div>
            <div class="modal-body">
                <img id="snapshot-preview" class="snapshot-modal-img" alt="CCTV Snapshot">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px;">
                    <span id="snapshot-time-label" style="font-size:0.85rem; color:var(--text-muted); font-family:'JetBrains Mono',monospace;"></span>
                    <div style="display:flex; gap:8px;">
                        <a id="btn-download-snapshot" class="cctv-btn cctv-btn-primary" download="cctv_snapshot.png">
                            <span>💾</span>
                            <span>Download Gambar</span>
                        </a>
                        <button class="cctv-btn" onclick="closeSnapshotModal()">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal-overlay" id="settings-modal">
        <div class="modal" style="max-width:550px;">
            <div class="modal-header">
                <h3>⚙️ Pengaturan RTSP Stream</h3>
                <button class="modal-close" onclick="closeSettingsModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="form-cctv-settings" onsubmit="saveCctvSettings(event)">
                    <div class="form-group" style="margin-bottom:14px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px; color:var(--text-primary);">RTSP Stream URL:</label>
                        <input type="text" id="setting-rtsp-url" class="cctv-select" style="width:100%; box-sizing:border-box;" value="<?= e($defaultRtspUrl) ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px; color:var(--text-primary);">WebSocket Relay Bridge URL:</label>
                        <input type="text" id="setting-ws-url" class="cctv-select" style="width:100%; box-sizing:border-box;" value="<?= e($defaultWsBridge) ?>">
                        <small style="color:var(--text-muted); font-size:0.78rem; display:block; margin-top:4px;">Gunakan port WebSocket relay jika menjalankan jsmpeg-node / websocket-relay.</small>
                    </div>
                    <div class="form-group" style="margin-bottom:18px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Nama Kamera (OSD):</label>
                        <input type="text" id="setting-cam-name" class="cctv-select" style="width:100%; box-sizing:border-box;" value="CAM 01 - GATE IN DEPO">
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                        <button type="button" class="cctv-btn" onclick="closeSettingsModal()">Batal</button>
                        <button type="submit" class="cctv-btn cctv-btn-primary">Simpan & Terapkan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- CCTV Canvas & Stream Engine Script -->
    <script>
        // State
        const state = {
            isPlaying: true,
            motionEnabled: true,
            motionDetected: false,
            lastMotionSnapshotTime: 0,
            channel: '101',
            rtspUrl: localStorage.getItem('cctv_rtsp_url') || '<?= $defaultRtspUrl ?>',
            wsUrl: localStorage.getItem('cctv_ws_url') || '<?= $defaultWsBridge ?>',
            camName: localStorage.getItem('cctv_cam_name') || 'CAM 01 - GATE IN DEPO',
            ws: null,
            animFrameId: null,
            simTime: Date.now(),
            fps: 25.0,
            lastFrameTime: performance.now(),
            frameCount: 0
        };

        const canvas = document.getElementById('cctv-canvas');
        const ctx = canvas.getContext('2d');
        const stage = document.getElementById('cctv-stage');

        // Motion Detection Offscreen Canvas
        const motionCanvas = document.createElement('canvas');
        motionCanvas.width = 64; // Downscale to 64x36 for fast diffing
        motionCanvas.height = 36;
        const motionCtx = motionCanvas.getContext('2d', { willReadFrequently: true });
        let previousImageData = null;

        // Theme Toggle Handler
        const themeToggleBtn = document.getElementById('theme-toggle');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                const cur = document.documentElement.getAttribute('data-theme') || 'dark';
                const next = cur === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('ceisa_theme', next);
                updateThemeToggleUI(next);
            });
            updateThemeToggleUI(document.documentElement.getAttribute('data-theme') || 'dark');
        }

        function updateThemeToggleUI(theme) {
            if (!themeToggleBtn) return;
            const icon = themeToggleBtn.querySelector('.theme-toggle-icon');
            const text = themeToggleBtn.querySelector('.theme-toggle-text');
            if (theme === 'light') {
                if (icon) icon.textContent = '☀️';
                if (text) text.textContent = 'Light';
            } else {
                if (icon) icon.textContent = '🌙';
                if (text) text.textContent = 'Dark';
            }
        }

        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active');
            });
            if (overlay) {
                overlay.addEventListener('click', () => {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                });
            }
        }

        // Toast Helper
        function showToast(msg, type = 'info') {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icons = { info: 'ℹ️', success: '✅', warning: '⚠️', error: '❌' };
            toast.innerHTML = `<span class="toast-icon">${icons[type] || 'ℹ️'}</span><div class="toast-content">${msg}</div>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        // Real-time Clock OSD
        function updateClockOSD() {
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const d = String(now.getDate()).padStart(2, '0');
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            const ss = String(now.getSeconds()).padStart(2, '0');
            const str = `${y}-${m}-${d} ${hh}:${mm}:${ss}`;
            const el = document.getElementById('osd-clock');
            if (el) el.textContent = str;
        }
        setInterval(updateClockOSD, 1000);
        updateClockOSD();

        // Canvas Stream Rendering Engine
        function initCanvasStream() {
            // Apply saved settings
            document.getElementById('setting-rtsp-url').value = state.rtspUrl;
            document.getElementById('setting-ws-url').value = state.wsUrl;
            document.getElementById('setting-cam-name').value = state.camName;
            document.getElementById('cctv-stream-url-display').textContent = state.rtspUrl;
            document.getElementById('info-rtsp-url').textContent = state.rtspUrl;
            document.getElementById('info-ws-url').textContent = state.wsUrl;

            tryConnectHttpStream();
            
        }

        // HTTP Snapshot Stream Connection (ISAPI Proxy Polling)
        function tryConnectHttpStream() {
            const badge = document.getElementById('stream-status-text');
            if (badge) badge.textContent = 'CONNECTING...';
            startHttpPollingLoop();
        }

        // Live Canvas Rendering Loop (HTTP Snapshot Polling)
        let isFetchingFrame = false;

        function checkMotion(img) {
            if (!state.motionEnabled) return;
            
            try {
                motionCtx.drawImage(img, 0, 0, motionCanvas.width, motionCanvas.height);
                const currentImageData = motionCtx.getImageData(0, 0, motionCanvas.width, motionCanvas.height);
                
                if (previousImageData) {
                    let diffCount = 0;
                    const data1 = currentImageData.data;
                    const data2 = previousImageData.data;
                    const len = data1.length;
                    
                    let minX = motionCanvas.width, minY = motionCanvas.height, maxX = 0, maxY = 0;
                    
                    for (let i = 0; i < len; i += 4) {
                        const diffR = Math.abs(data1[i] - data2[i]);
                        const diffG = Math.abs(data1[i+1] - data2[i+1]);
                        const diffB = Math.abs(data1[i+2] - data2[i+2]);
                        
                        // threshold
                        if (diffR + diffG + diffB > 120) {
                            diffCount++;
                            let p = i / 4;
                            let x = p % motionCanvas.width;
                            let y = Math.floor(p / motionCanvas.width);
                            if (x < minX) minX = x;
                            if (x > maxX) maxX = x;
                            if (y < minY) minY = y;
                            if (y > maxY) maxY = y;
                        }
                    }
                    
                    const totalPixels = motionCanvas.width * motionCanvas.height;
                    if ((diffCount / totalPixels) > 0.015) {
                        state.motionDetected = true;
                        
                        // Add some padding to bounding box
                        minX = Math.max(0, minX - 2);
                        minY = Math.max(0, minY - 2);
                        maxX = Math.min(motionCanvas.width, maxX + 2);
                        maxY = Math.min(motionCanvas.height, maxY + 2);
                        
                        state.motionBox = {
                            x: (minX / motionCanvas.width) * canvas.width,
                            y: (minY / motionCanvas.height) * canvas.height,
                            w: ((maxX - minX) / motionCanvas.width) * canvas.width,
                            h: ((maxY - minY) / motionCanvas.height) * canvas.height
                        };
                    } else {
                        state.motionDetected = false;
                    }
                }
                previousImageData = currentImageData;
            } catch (err) {
                console.warn('Motion detection error:', err);
            }
        }

        function drawMotionAlert() {
            if (state.motionEnabled && state.motionDetected && state.motionBox) {
                ctx.save();
                
                // Draw tracking bounding box
                ctx.strokeStyle = '#22c55e'; // Green tracking box
                ctx.lineWidth = 3;
                ctx.setLineDash([5, 5]);
                ctx.strokeRect(state.motionBox.x, state.motionBox.y, state.motionBox.w, state.motionBox.h);
                
                // Draw small label on top of bounding box
                ctx.fillStyle = '#22c55e';
                ctx.font = 'bold 12px "JetBrains Mono", monospace';
                ctx.fillText('DETECTED', state.motionBox.x, state.motionBox.y - 5);
                
                // Global Alert text
                ctx.fillStyle = '#ef4444';
                ctx.font = 'bold 24px "JetBrains Mono", monospace';
                ctx.shadowColor = 'rgba(0,0,0,0.8)';
                ctx.shadowBlur = 4;
                ctx.setLineDash([]);
                ctx.fillText('⚠️ MOTION DETECTED', canvas.width / 2 - 120, 40);
                
                ctx.restore();
            }
        }

        function fetchNextFrame() {
            if (!state.isPlaying) return Promise.resolve();
            if (isFetchingFrame) return Promise.resolve();

            isFetchingFrame = true;
            const rtspParts = state.rtspUrl.match(/rtsp:\/\/([^:]+):([^@]+)@([^:]+):?(\d*)\/.*Channels\/(\d+)/);
            let ip = '192.168.1.101', user = 'admin', pass = 'Password123', channel = state.channel;
            
            if (rtspParts) {
                user = rtspParts[1];
                pass = rtspParts[2];
                ip = rtspParts[3];
                channel = rtspParts[5];
            }

            const proxyUrl = `api/cctv_proxy.php?ip=${ip}&channel=${channel}&user=${user}&pass=${pass}&_t=${Date.now()}`;
            
            return new Promise((resolve) => {
                const img = new Image();
                img.onload = () => {
                    const badge = document.getElementById('stream-status-text');
                    if (badge && badge.textContent !== 'LIVE HTTP STREAM') {
                        badge.textContent = 'LIVE HTTP STREAM';
                    }
                    if (state.isPlaying) {
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        checkMotion(img);
                        drawMotionAlert();
                        
                        // Automatic Motion Snapshot Capture
                        if (state.motionEnabled && state.motionDetected) {
                            const now = Date.now();
                            if (now - state.lastMotionSnapshotTime > 3000) { // Max 1 snapshot per 3 seconds
                                state.lastMotionSnapshotTime = now;
                                addMotionSnapshot(canvas.toDataURL('image/jpeg', 0.6));
                            }
                        }
                    }
                    isFetchingFrame = false;
                    resolve();
                };
                img.onerror = () => {
                    isFetchingFrame = false;
                    resolve();
                };
                img.src = proxyUrl;
            });
        }

        function addMotionSnapshot(dataUrl) {
            const gallery = document.getElementById('motion-log-gallery');
            const emptyText = document.getElementById('motion-log-empty');
            if (emptyText) emptyText.style.display = 'none';
            
            const wrapper = document.createElement('div');
            wrapper.style.cssText = 'min-width: 220px; max-width: 220px; border-radius: 8px; overflow: hidden; background: var(--bg-card); border: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; box-shadow: 0 4px 6px rgba(0,0,0,0.3);';
            
            const timeStr = new Date().toISOString().replace('T', ' ').substring(11, 19);
            
            wrapper.innerHTML = `
                <img src="${dataUrl}" style="width: 100%; height: 124px; object-fit: cover; display: block;" alt="Motion Snapshot">
                <div style="padding: 10px; text-align: center; font-size: 0.85rem; font-family: 'JetBrains Mono', monospace; color: #ef4444; font-weight: bold; background: rgba(0,0,0,0.3);">
                    ⚠️ ${timeStr} <span class="sync-status" style="font-size:0.7rem; color:#94a3b8; display:block;">Saving...</span>
                </div>
            `;
            
            gallery.prepend(wrapper);
            
            // Keep only the last 20 snapshots in UI
            while (gallery.children.length > 20) {
                gallery.removeChild(gallery.lastChild);
            }

            // Save to Server
            fetch('api/save_snapshot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    image: dataUrl,
                    channel: state.channel
                })
            }).then(r => r.json()).then(res => {
                const statusSpan = wrapper.querySelector('.sync-status');
                if (res.status === 'success') {
                    if (statusSpan) {
                        statusSpan.textContent = 'Saved to disk';
                        statusSpan.style.color = '#22c55e';
                    }
                } else {
                    if (statusSpan) {
                        statusSpan.textContent = 'Save failed';
                        statusSpan.style.color = '#ef4444';
                    }
                }
            }).catch(e => {
                const statusSpan = wrapper.querySelector('.sync-status');
                if (statusSpan) {
                    statusSpan.textContent = 'Network error';
                    statusSpan.style.color = '#ef4444';
                }
            });
        }

        function renderCanvasFrame() {
            if (!state.isPlaying) {
                state.animFrameId = setTimeout(renderCanvasFrame, 500);
                return;
            }

            const now = performance.now();
            
            // FPS Calculation
            state.frameCount++;
            if (now - state.lastFrameTime >= 1000) {
                state.fps = (state.frameCount * 1000 / (now - state.lastFrameTime)).toFixed(1);
                state.frameCount = 0;
                state.lastFrameTime = now;
                const fpsBadge = document.getElementById('cctv-fps-badge');
                if (fpsBadge) fpsBadge.textContent = `FPS: ${state.fps}`;
            }

            fetchNextFrame().then(() => {
                // Determine refresh rate (approx 10-15 FPS is good for MJPEG polling without killing the server)
                state.animFrameId = setTimeout(renderCanvasFrame, 80); // ~12 FPS
            });
        }

        function startHttpPollingLoop() { if (state.animFrameId) clearTimeout(state.animFrameId); state.animFrameId = setTimeout(renderCanvasFrame, 10); }

        // Motion Detection Button
        const btnMotion = document.getElementById('btn-motion');
        if (btnMotion) {
            btnMotion.addEventListener('click', () => {
                state.motionEnabled = !state.motionEnabled;
                const text = document.getElementById('btn-motion-text');
                if (state.motionEnabled) {
                    btnMotion.className = 'cctv-btn cctv-btn-success';
                    if (text) text.textContent = 'Motion: ON';
                    showToast('AI Motion Detection diaktifkan', 'success');
                } else {
                    btnMotion.className = 'cctv-btn';
                    if (text) text.textContent = 'Motion: ON';
                    state.motionDetected = false;
                    previousImageData = null; // Reset baseline
                    showToast('AI Motion Detection dimatikan', 'info');
                }
            });
        }
        // Play / Pause Stream
        const btnPlayPause = document.getElementById('btn-play-pause');
        if (btnPlayPause) {
            btnPlayPause.addEventListener('click', () => {
                state.isPlaying = !state.isPlaying;
                const icon = document.getElementById('btn-play-icon');
                const text = document.getElementById('btn-play-text');
                const badge = document.getElementById('stream-status-badge');
                if (state.isPlaying) {
                    if (icon) icon.textContent = '⏸️';
                    if (text) text.textContent = 'Pause Stream';
                    btnPlayPause.className = 'cctv-btn cctv-btn-primary';
                    if (badge) badge.style.display = 'inline-flex';
                    showToast('Stream CCTV dilanjutkan', 'info');
                } else {
                    if (icon) icon.textContent = '▶️';
                    if (text) text.textContent = 'Resume Stream';
                    btnPlayPause.className = 'cctv-btn';
                    if (badge) badge.style.display = 'none';
                    showToast('Stream CCTV dijeda', 'warning');
                }
            });
        }

        // Reconnect Button
        const btnReconnect = document.getElementById('btn-reconnect');
        if (btnReconnect) {
            btnReconnect.addEventListener('click', () => {
                showToast('Menghubungkan ulang ke RTSP Stream...', 'info');
                tryConnectHttpStream();
                
            });
        }

        // Channel Selector (101 vs 102)
        const channelSelector = document.getElementById('channel-selector');
        if (channelSelector) {
            channelSelector.addEventListener('change', (e) => {
                const ch = e.target.value;
                state.channel = ch;
                // Swap URL channel
                state.rtspUrl = state.rtspUrl.replace(/\/Channels\/\d+/, `/Channels/${ch}`);
                document.getElementById('cctv-stream-url-display').textContent = state.rtspUrl;
                document.getElementById('info-rtsp-url').textContent = state.rtspUrl;
                const label = document.getElementById('osd-channel-label');
                const resBadge = document.getElementById('cctv-res-badge');
                if (ch === '101') {
                    if (label) label.textContent = 'CH 101 / MAIN STREAM';
                    if (resBadge) resBadge.textContent = '1920x1080 (1080p)';
                } else {
                    if (label) label.textContent = 'CH 102 / SUB STREAM';
                    if (resBadge) resBadge.textContent = '1280x720 (720p)';
                }
                showToast(`Beralih ke Channel ${ch}`, 'success');
                tryConnectHttpStream();
            });
        }

        // Snapshot Button
        const btnSnapshot = document.getElementById('btn-snapshot');
        if (btnSnapshot) {
            btnSnapshot.addEventListener('click', () => {
                try {
                    const dataUrl = canvas.toDataURL('image/png');
                    const img = document.getElementById('snapshot-preview');
                    const dl = document.getElementById('btn-download-snapshot');
                    const timeLabel = document.getElementById('snapshot-time-label');
                    const modal = document.getElementById('snapshot-modal');
                    
                    const timeStr = new Date().toISOString().replace('T', ' ').substring(0, 19);
                    const filename = `cctv_ch${state.channel}_${new Date().toISOString().slice(0,10)}_${Date.now()}.png`;

                    if (img) img.src = dataUrl;
                    if (dl) {
                        dl.href = dataUrl;
                        dl.download = filename;
                    }
                    if (timeLabel) timeLabel.textContent = `Timestamp: ${timeStr} | CAM 01 (CH ${state.channel})`;
                    if (modal) modal.classList.add('active');
                    showToast('Snapshot berhasil diambil!', 'success');
                } catch (e) {
                    showToast('Gagal mengambil snapshot: ' + e.message, 'error');
                }
            });
        }

        function closeSnapshotModal() {
            const modal = document.getElementById('snapshot-modal');
            if (modal) modal.classList.remove('active');
        }

        // Settings Modal
        const btnSettings = document.getElementById('btn-settings');
        if (btnSettings) {
            btnSettings.addEventListener('click', () => {
                const modal = document.getElementById('settings-modal');
                if (modal) modal.classList.add('active');
            });
        }

        function closeSettingsModal() {
            const modal = document.getElementById('settings-modal');
            if (modal) modal.classList.remove('active');
        }

        function saveCctvSettings(e) {
            e.preventDefault();
            const newRtsp = document.getElementById('setting-rtsp-url').value.trim();
            const newWs = document.getElementById('setting-ws-url').value.trim();
            const newName = document.getElementById('setting-cam-name').value.trim();

            if (newRtsp) {
                state.rtspUrl = newRtsp;
                localStorage.setItem('cctv_rtsp_url', newRtsp);
            }
            if (newWs) {
                state.wsUrl = newWs;
                localStorage.setItem('cctv_ws_url', newWs);
            }
            if (newName) {
                state.camName = newName;
                localStorage.setItem('cctv_cam_name', newName);
            }

            document.getElementById('cctv-stream-url-display').textContent = state.rtspUrl;
            document.getElementById('info-rtsp-url').textContent = state.rtspUrl;
            document.getElementById('info-ws-url').textContent = state.wsUrl;
            
            closeSettingsModal();
            showToast('Pengaturan RTSP berhasil disimpan!', 'success');
            tryConnectHttpStream();
        }

        // Fullscreen Toggle
        const btnFullscreen = document.getElementById('btn-fullscreen');
        if (btnFullscreen && stage) {
            btnFullscreen.addEventListener('click', () => {
                if (!document.fullscreenElement) {
                    if (stage.requestFullscreen) stage.requestFullscreen();
                    else if (stage.webkitRequestFullscreen) stage.webkitRequestFullscreen();
                } else {
                    if (document.exitFullscreen) document.exitFullscreen();
                }
            });
        }

        // Start everything on DOM ready
        window.addEventListener('DOMContentLoaded', initCanvasStream);
    </script>
</body>
</html>

