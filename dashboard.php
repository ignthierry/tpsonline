<?php
/**
 * Dashboard — CEISA 4.0 TPS Online Dashboard
 * Layout utama dengan sidebar navigation dan dynamic content area
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
    <meta name="description" content="Dashboard TPS Online H2H — CEISA 4.0 Bea Cukai">
    <title><?= e($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <!-- jQuery & DataTables -->
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
</head>
<body data-login-time="<?= $loginTime ?>">
    <!-- Endpoint Definitions for JS -->
    <script type="application/json" id="endpoint-definitions"><?= json_encode($endpoints, JSON_UNESCAPED_UNICODE) ?></script>

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
                    <div class="header-breadcrumb" id="header-breadcrumb">
                        <span class="current">Dashboard</span>
                    </div>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="theme-toggle" title="Ubah Mode (Gelap / Terang)" aria-label="Toggle theme">
                        <span class="theme-toggle-icon">🌙</span>
                        <span class="theme-toggle-text">Dark</span>
                    </button>
                    <div class="api-badge" title="Header beacukai-api-key aktif">
                        <span class="dot"></span>
                        <span>API Key Active</span>
                    </div>
                    <button class="btn-refresh-token" onclick="refreshAccessToken()" title="Perbarui token JWT dari server CEISA">
                        <span>🔄</span>
                        <span>Refresh Token</span>
                    </button>
                    <div class="header-user">
                        <div class="user-avatar"><?= e($userInitial) ?></div>
                        <span><?= e($username) ?></span>
                        <a href="logout.php" title="Keluar dari sistem" style="color:var(--accent-red); margin-left:6px; font-size:0.9rem; text-decoration:none; opacity:0.8; transition:0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.8">🚪</a>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="content" id="main-content">
                <!-- Content is rendered dynamically by JavaScript -->
            </main>
        </div>
    </div>

    <!-- JSON Modal -->
    <div class="modal-overlay" id="json-modal">
        <div class="modal">
            <div class="modal-header">
                <h3>📋 Raw JSON Response</h3>
                <button class="modal-close" onclick="CeisaApp.closeJSON()">×</button>
            </div>
            <div class="modal-body">
                <pre id="json-content"></pre>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- App Script -->
    <script src="assets/js/app.js?v=<?= time() ?>"></script>
    <script>
        async function refreshAccessToken() {
            try {
                CeisaApp.showToast('Memperbarui token dari SSO Bea Cukai...', 'info');
                const res = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ refresh: true })
                });
                const data = await res.json();
                if (data.success) {
                    CeisaApp.showToast('Token berhasil diperbarui!', 'success');
                } else {
                    CeisaApp.showToast(data.message || 'Gagal memperbarui token', 'error');
                }
            } catch (e) {
                CeisaApp.showToast('Gagal terhubung ke server', 'error');
            }
        }
    </script>
</body>
</html>
