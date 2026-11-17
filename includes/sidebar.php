<aside class="sidebar">
    <a class="brand" href="index.php"><span class="brand-mark"><img src="assets/image/logo%20TaskFlow.png" alt=""></span><span>TaskFlow</span></a>
    <div class="sidebar-section-label">WORKSPACE</div>
    <nav class="side-nav">
        <?php
        $navItems = [
            ['dashboard', 'Dashboard', 'bi-grid-1x2'],
            ['tasks', 'All Tasks', 'bi-check2-square'],
            ['calendar', 'Calendar', 'bi-calendar3'],
            ['in-progress', 'In Progress', 'bi-arrow-repeat'],
            ['pending', 'Pending', 'bi-circle'],
            ['completed', 'Completed', 'bi-check-circle'],
            ['archive', 'Archive', 'bi-archive'],
            ['categories', 'Categories', 'bi-tags'],
        ];
        foreach ($navItems as [$target, $label, $icon]):
            $href = in_array($target, ['in-progress', 'pending'], true) ? 'index.php?page=tasks&status=' . rawurlencode($target === 'in-progress' ? 'In Progress' : 'Pending') : 'index.php?page=' . $target;
            $active = $page === $target || ($page === 'tasks' && in_array($target, ['pending', 'in-progress'], true) && ($_GET['status'] ?? '') === ($target === 'pending' ? 'Pending' : 'In Progress'));
        ?>
            <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= e($href) ?>"><i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span><?php if ($target === 'tasks'): ?><span class="nav-count"><?= (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE archived_at IS NULL")->fetchColumn() ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <a class="nav-link<?= $page === 'settings' ? ' active' : '' ?>" href="index.php?page=settings"><i class="bi bi-sliders"></i><span>Pengaturan tampilan</span></a>
        <div class="app-info"><span>TaskFlow Personal</span><small>Versi 1.0.0</small></div>
    </div>
</aside>
<div class="offcanvas offcanvas-start mobile-offcanvas" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
    <div class="offcanvas-header"><a class="brand" href="index.php" id="mobileNavLabel"><span class="brand-mark"><img src="assets/image/logo%20TaskFlow.png" alt=""></span><span>TaskFlow</span></a><button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button></div>
    <div class="offcanvas-body"><nav class="side-nav"><?php foreach ($navItems as [$target, $label, $icon]): $mobileHref = in_array($target, ['in-progress', 'pending'], true) ? 'index.php?page=tasks&status=' . rawurlencode($target === 'in-progress' ? 'In Progress' : 'Pending') : 'index.php?page=' . $target; ?><a class="nav-link<?= $page === $target ? ' active' : '' ?>" href="<?= e($mobileHref) ?>"><i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span></a><?php endforeach; ?></nav></div>
</div>
