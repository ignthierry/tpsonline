<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$isDashboard = ($currentPage === 'dashboard.php' || $currentPage === 'index.php');
$isCCTV = ($currentPage === 'cctv.php');
$isPostData = ($currentPage === 'post_dokumen.php');
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
    </div>

    <nav class="sidebar-nav">
        <!-- Home -->
        <div class="nav-section">
            <?php if ($isDashboard): ?>
            <div class="nav-item active" data-page="home">
                <span class="nav-icon">🏠</span>
                <span>Dashboard</span>
            </div>
            <?php else: ?>
            <a href="dashboard.php" class="nav-item" style="text-decoration:none; color:inherit;">
                <span class="nav-icon">🏠</span>
                <span>Dashboard</span>
            </a>
            <?php endif; ?>
        </div>

        <!-- CCTV Monitoring -->
        <div class="nav-section">
            <a href="cctv.php" class="nav-item <?= $isCCTV ? 'active' : '' ?>" style="text-decoration:none; color:inherit; <?= $isCCTV ? 'cursor:default;' : '' ?>">
                <span class="nav-icon">📹</span>
                <span>CCTV Live Stream</span>
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
