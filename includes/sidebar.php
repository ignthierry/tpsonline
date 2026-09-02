<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$isDashboard = ($currentPage === 'dashboard.php' || $currentPage === 'index.php');
$isCCTV = ($currentPage === 'cctv.php');
$isPostData = ($currentPage === 'post_dokumen.php');
$isCoCoCont = ($currentPage === 'cococont.php');
$isCoCoKms = ($currentPage === 'cocokms.php');
$isTpsTracking = ($currentPage === 'tps_tracking.php');
$isReportCont = ($currentPage === 'report_cont.php' || $currentPage === 'report.php');
$isReportKms = ($currentPage === 'report_kms.php');
$isReportTracking = ($currentPage === 'report_tracking.php');
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

        <!-- Coarri Codeco (Container) CEISA 4.0 -->
        <div class="nav-section">
            <a href="cococont.php" class="nav-item <?= $isCoCoCont ? 'active' : '' ?>" style="text-decoration:none; color:inherit; <?= $isCoCoCont ? 'cursor:default;' : '' ?>">
                <span class="nav-icon">📦</span>
                <span>Coarri Codeco (Container)</span>
                <span class="nav-badge" style="background:rgba(59, 130, 246, 0.2); color:#3b82f6; border:1px solid rgba(59,130,246,0.3);">CEISA 4.0</span>
            </a>
        </div>

        <!-- Coarri Codeco (Kemasan) CEISA 4.0 -->
        <div class="nav-section">
            <a href="cocokms.php" class="nav-item <?= $isCoCoKms ? 'active' : '' ?>" style="text-decoration:none; color:inherit; <?= $isCoCoKms ? 'cursor:default;' : '' ?>">
                <span class="nav-icon">📦</span>
                <span>Coarri Codeco (Kemasan)</span>
                <span class="nav-badge" style="background:rgba(139, 92, 246, 0.2); color:#a78bfa; border:1px solid rgba(139,92,246,0.3);">CEISA 4.0</span>
            </a>
        </div>

        <!-- TPS Tracking Kontainer CEISA 4.0 -->
        <div class="nav-section">
            <a href="tps_tracking.php" class="nav-item <?= $isTpsTracking ? 'active' : '' ?>" style="text-decoration:none; color:inherit; <?= $isTpsTracking ? 'cursor:default;' : '' ?>">
                <span class="nav-icon">📍</span>
                <span>TPS Tracking Kontainer</span>
                <span class="nav-badge" style="background:rgba(16, 185, 129, 0.2); color:#10b981; border:1px solid rgba(16,185,129,0.3);">BARU</span>
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

        <div class="nav-section-label" style="margin-top: 15px; padding: 0 15px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary); font-weight: 600;">Layanan Bea Cukai</div>
        <!-- Dynamic Categories -->
        <?php foreach ($endpoints as $catKey => $category): ?>
        <div class="nav-section">
            <?php if ($isDashboard): ?>
                <div class="nav-item" data-category="<?= e($catKey) ?>">
                    <span class="nav-icon"><?= $category['icon'] ?></span>
                    <span><?= e($category['label']) ?></span>
                    <span class="nav-badge"><?= count($category['endpoints']) ?></span>
                    <span class="chevron">›</span>
                </div>
                <div class="nav-subitems">
                    <?php foreach ($category['endpoints'] as $epKey => $ep): ?>
                    <div class="nav-subitem" data-endpoint="<?= e($epKey) ?>">
                        <?= e($ep['label']) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <a href="dashboard.php" class="nav-item" style="text-decoration:none; color:inherit;">
                    <span class="nav-icon"><?= $category['icon'] ?></span>
                    <span><?= e($category['label']) ?></span>
                    <span class="nav-badge"><?= count($category['endpoints']) ?></span>
                </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </nav>
</aside>
