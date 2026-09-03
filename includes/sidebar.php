<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$isDashboard = ($currentPage === 'dashboard.php' || $currentPage === 'index.php');
$isCCTV = ($currentPage === 'cctv.php');
$isPostData = ($currentPage === 'post_dokumen.php');
$isCoCoCont = ($currentPage === 'cococont.php');
$isCoCoKms = ($currentPage === 'cocokms.php');
$isTpsTracking = ($currentPage === 'tps_tracking.php');
$isTpsTrackingBatch = ($currentPage === 'tps_tracking_batch.php');
$isReportCont = ($currentPage === 'report_cont.php' || $currentPage === 'report.php');
$isReportKms = ($currentPage === 'report_kms.php');
$isReportTracking = ($currentPage === 'report_tracking.php');
$isReportTrackingBatch = ($currentPage === 'report_tracking_batch.php');
$isLaporanYor = ($currentPage === 'laporan_yor.php');
$isReportYor = ($currentPage === 'report_yor.php');
// Check if any Kirim Data sub-page is active (for auto-expanding the submenu)
$isKirimDataActive = ($isCoCoCont || $isCoCoKms || $isTpsTracking || $isTpsTrackingBatch || $isLaporanYor);
if (!isset($endpoints) && function_exists('getEndpointDefinitions')) {
    $endpoints = getEndpointDefinitions();
}
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-icon">🏛️</div>
            <div class="brand-text">
                <h2>CEISA 4.0</h2>
                <span>TPS Online H2H</span>
            </div>
        </div>
        <div class="sidebar-badge-env">SANDBOX</div>
    </div>

    <nav class="sidebar-nav">
        <!-- Dashboard Utama -->
        <div class="nav-section">
            <a href="dashboard.php" class="nav-item <?= $isDashboard ? 'active' : '' ?>">
                <span class="nav-icon">📊</span>
                <span>Dashboard Status</span>
            </a>
        </div>

        <!-- Monitoring CCTV -->
        <div class="nav-section">
            <a href="cctv.php" class="nav-item <?= $isCCTV ? 'active' : '' ?>">
                <span class="nav-icon">📹</span>
                <span>Integrasi CCTV</span>
                <span class="nav-badge" style="background:rgba(239, 68, 68, 0.2); color:#ef4444; border:1px solid rgba(239,68,68,0.3);">LIVE</span>
            </a>
        </div>

        <!-- Kirim Dokumen POST -->
        <div class="nav-section">
            <a href="post_dokumen.php" class="nav-item <?= $isPostData ? 'active' : '' ?>" style="text-decoration:none; color:inherit; <?= $isPostData ? 'cursor:default;' : '' ?>">
                <span class="nav-icon">📤</span>
                <span>Kirim Dokumen (POST)</span>
            </a>
        </div>

        <!-- Section Laporan -->
        <div class="nav-section-label" style="margin-top: 15px; padding: 0 15px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary); font-weight: 600;">Laporan</div>
        <div class="nav-section">
            <a href="report_cont.php" class="nav-item <?= $isReportCont ? 'active' : '' ?>" style="text-decoration:none; color:inherit; <?= $isReportCont ? 'cursor:default;' : '' ?>">
                <span class="nav-icon">📊</span>
                <span>Laporan Coarri Codeco (Container)</span>
                <span class="nav-badge" style="background:rgba(59, 130, 246, 0.2); color:#3b82f6; border:1px solid rgba(59,130,246,0.3);">Container</span>
            </a>
        </div>
        <div class="nav-section">
            <a href="report_kms.php" class="nav-item <?= $isReportKms ? 'active' : '' ?>" style="text-decoration:none; color:inherit; <?= $isReportKms ? 'cursor:default;' : '' ?>">
                <span class="nav-icon">📊</span>
                <span>Laporan Coarri Codeco (Kemasan)</span>
                <span class="nav-badge" style="background:rgba(139, 92, 246, 0.2); color:#a78bfa; border:1px solid rgba(139,92,246,0.3);">Kemasan</span>
            </a>
        </div>
        <div class="nav-section">
            <a href="report_tracking.php" class="nav-item <?= $isReportTracking ? 'active' : '' ?>" style="text-decoration:none; color:inherit; <?= $isReportTracking ? 'cursor:default;' : '' ?>">
                <span class="nav-icon">📍</span>
                <span>Laporan TPS Tracking (Kontainer)</span>
                <span class="nav-badge" style="background:rgba(16, 185, 129, 0.2); color:#10b981; border:1px solid rgba(16,185,129,0.3);">Tracking</span>
            </a>
        </div>
        <div class="nav-section">
            <a href="report_tracking_batch.php" class="nav-item <?= $isReportTrackingBatch ? 'active' : '' ?>" style="text-decoration:none; color:inherit; <?= $isReportTrackingBatch ? 'cursor:default;' : '' ?>">
                <span class="nav-icon">📦</span>
                <span>Laporan TPS Tracking Batch</span>
                <span class="nav-badge" style="background:rgba(245, 158, 11, 0.2); color:#f59e0b; border:1px solid rgba(245,158,11,0.3);">Batch</span>
            </a>
        </div>
        <div class="nav-section">
            <a href="report_yor.php" class="nav-item <?= $isReportYor ? 'active' : '' ?>" style="text-decoration:none; color:inherit; <?= $isReportYor ? 'cursor:default;' : '' ?>">
                <span class="nav-icon">📈</span>
                <span>Laporan YOR Terkirim</span>
                <span class="nav-badge" style="background:rgba(59, 130, 246, 0.2); color:#3b82f6; border:1px solid rgba(59,130,246,0.3);">YOR</span>
            </a>
        </div>

        <div class="nav-section-label" style="margin-top: 15px; padding: 0 15px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary); font-weight: 600;">Layanan Bea Cukai</div>

        <!-- Kirim Data (Collapsible Submenu) -->
        <div class="nav-section">
            <div class="nav-item kirim-data-toggle <?= $isKirimDataActive ? 'expanded' : '' ?>" id="kirimDataToggle" style="cursor: pointer;">
                <span class="nav-icon">📤</span>
                <span>Kirim Data</span>
                <span class="nav-badge" style="background:rgba(59, 130, 246, 0.2); color:#3b82f6; border:1px solid rgba(59,130,246,0.3);">5</span>
                <span class="chevron">›</span>
            </div>
            <div class="nav-subitems <?= $isKirimDataActive ? 'open' : '' ?>" id="kirimDataSubitems">
                <!-- Coarri Codeco (Container) -->
                <a href="cococont.php" class="nav-subitem-link <?= $isCoCoCont ? 'active' : '' ?>" style="text-decoration:none; color:inherit;">
                    <div class="nav-subitem <?= $isCoCoCont ? 'active' : '' ?>">
                        <span class="nav-sub-icon">📦</span>
                        <span>Coarri Codeco (Container)</span>
                        <span class="nav-badge-sm" style="background:rgba(59, 130, 246, 0.2); color:#3b82f6; border:1px solid rgba(59,130,246,0.3);">CEISA 4.0</span>
                    </div>
                </a>
                <!-- Coarri Codeco (Kemasan) -->
                <a href="cocokms.php" class="nav-subitem-link <?= $isCoCoKms ? 'active' : '' ?>" style="text-decoration:none; color:inherit;">
                    <div class="nav-subitem <?= $isCoCoKms ? 'active' : '' ?>">
                        <span class="nav-sub-icon">📦</span>
                        <span>Coarri Codeco (Kemasan)</span>
                        <span class="nav-badge-sm" style="background:rgba(139, 92, 246, 0.2); color:#a78bfa; border:1px solid rgba(139,92,246,0.3);">CEISA 4.0</span>
                    </div>
                </a>
                <!-- TPS Tracking Kontainer -->
                <a href="tps_tracking.php" class="nav-subitem-link <?= $isTpsTracking ? 'active' : '' ?>" style="text-decoration:none; color:inherit;">
                    <div class="nav-subitem <?= $isTpsTracking ? 'active' : '' ?>">
                        <span class="nav-sub-icon">📍</span>
                        <span>TPS Tracking Kontainer</span>
                        <span class="nav-badge-sm" style="background:rgba(16, 185, 129, 0.2); color:#10b981; border:1px solid rgba(16,185,129,0.3);">BARU</span>
                    </div>
                </a>
                <!-- TPS Tracking Batch -->
                <a href="tps_tracking_batch.php" class="nav-subitem-link <?= $isTpsTrackingBatch ? 'active' : '' ?>" style="text-decoration:none; color:inherit;">
                    <div class="nav-subitem <?= $isTpsTrackingBatch ? 'active' : '' ?>">
                        <span class="nav-sub-icon">📦</span>
                        <span>TPS Tracking Batch</span>
                        <span class="nav-badge-sm" style="background:rgba(245, 158, 11, 0.2); color:#f59e0b; border:1px solid rgba(245,158,11,0.3);">BATCH</span>
                    </div>
                </a>
                <!-- Laporan YOR (Yard Occupancy) -->
                <a href="laporan_yor.php" class="nav-subitem-link <?= $isLaporanYor ? 'active' : '' ?>" style="text-decoration:none; color:inherit;">
                    <div class="nav-subitem <?= $isLaporanYor ? 'active' : '' ?>">
                        <span class="nav-sub-icon">📈</span>
                        <span>Laporan YOR (Yard Occupancy)</span>
                        <span class="nav-badge-sm" style="background:rgba(59, 130, 246, 0.2); color:#3b82f6; border:1px solid rgba(59,130,246,0.3);">YOR</span>
                    </div>
                </a>
            </div>
        </div>

        <!-- Dynamic Categories -->
        <?php foreach ($endpoints as $catKey => $category): ?>
        <div class="nav-section">
            <div class="nav-item dynamic-cat-toggle" data-category="<?= e($catKey) ?>" style="cursor: pointer;">
                <span class="nav-icon"><?= $category['icon'] ?></span>
                <span><?= e($category['label']) ?></span>
                <span class="nav-badge"><?= count($category['endpoints']) ?></span>
                <span class="chevron">›</span>
            </div>
            <div class="nav-subitems">
                <?php foreach ($category['endpoints'] as $epKey => $ep): ?>
                <?php if ($isDashboard): ?>
                <div class="nav-subitem" data-endpoint="<?= e($epKey) ?>">
                    <?= e($ep['label']) ?>
                </div>
                <?php else: ?>
                <a href="dashboard.php#<?= e($catKey) ?>/<?= e($epKey) ?>" class="nav-subitem-link" style="text-decoration:none; color:inherit;">
                    <div class="nav-subitem">
                        <?= e($ep['label']) ?>
                    </div>
                </a>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </nav>

    <!-- User Profile Footer in Sidebar -->
    <div style="padding: 14px 16px; border-top: 1px solid var(--border-subtle); background: rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px; overflow: hidden;">
                <div class="user-avatar" style="width:34px; height:34px; font-size:0.8rem; flex-shrink:0; background: linear-gradient(135deg, #10b981, #059669); color:#fff; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700;">
                    <?= strtoupper(substr($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'PS', 0, 2)) ?>
                </div>
                <div style="display: flex; flex-direction: column; overflow: hidden; line-height: 1.25;">
                    <span style="font-size: 0.82rem; font-weight: 700; color: var(--text-primary); white-space: nowrap; text-overflow: ellipsis; overflow: hidden;" title="<?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User') ?>">
                        <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User') ?>
                    </span>
                    <span style="font-size: 0.72rem; color: #10b981; font-weight:600; text-transform: uppercase;">
                        ● <?= htmlspecialchars($_SESSION['role'] ?? 'Admin') ?>
                    </span>
                </div>
            </div>
            <a href="logout.php" title="Keluar / Logout dari sistem" style="color: #ef4444; padding: 6px 9px; border-radius: 6px; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.25); text-decoration: none; font-size: 0.9rem; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s ease;">
                🚪
            </a>
        </div>
    </div>
</aside>

<!-- Sidebar Toggle Script -->
<script>
(function() {
    function initSidebarToggle() {
        // Kirim Data toggle (aktif di semua halaman)
        var kirimToggle = document.getElementById('kirimDataToggle');
        var kirimSubitems = document.getElementById('kirimDataSubitems');
        if (kirimToggle && kirimSubitems && !kirimToggle.dataset.bound) {
            kirimToggle.dataset.bound = 'true';
            kirimToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var isOpen = kirimToggle.classList.contains('expanded');
                if (isOpen) {
                    kirimToggle.classList.remove('expanded');
                    kirimSubitems.classList.remove('open');
                } else {
                    kirimToggle.classList.add('expanded');
                    kirimSubitems.classList.add('open');
                }
            });
        }

        <?php if (!$isDashboard): ?>
        // Dynamic category toggles (Impor, Ekspor, PLP, etc.)
        // Hanya di-bind di sini jika bukan halaman dashboard (di dashboard sudah di-handle oleh app.js)
        var catToggles = document.querySelectorAll('.dynamic-cat-toggle');
        for (var i = 0; i < catToggles.length; i++) {
            (function(catToggle) {
                if (catToggle.dataset.bound) return;
                catToggle.dataset.bound = 'true';
                catToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var catSubitems = catToggle.nextElementSibling;
                    var isOpen = catToggle.classList.contains('expanded');
                    if (isOpen) {
                        catToggle.classList.remove('expanded');
                        if (catSubitems) catSubitems.classList.remove('open');
                    } else {
                        catToggle.classList.add('expanded');
                        if (catSubitems) catSubitems.classList.add('open');
                    }
                });
            })(catToggles[i]);
        }
        <?php endif; ?>
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebarToggle);
    } else {
        initSidebarToggle();
    }
})();
</script>
