<aside class="sidebar">
    <a class="brand" href="index.php"><span class="brand-mark"><img src="assets/image/logo%20TaskFlow.png" alt=""></span><span>TaskFlow</span></a>
    <div class="workspace-switcher">
        <label for="active-workspace">ACTIVE WORKSPACE</label>
        <form method="post" action="index.php?page=workspace-switch">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="workspace-switch">
            <select id="active-workspace" name="workspace_id" aria-label="Pilih workspace" onchange="this.form.submit()"><?php foreach ($userWorkspaces as $workspace): ?><option value="<?= (int)$workspace['id'] ?>"<?= (int)$workspace['id'] === (int)$activeWorkspace['id'] ? ' selected' : '' ?>><?= e($workspace['name']) ?></option><?php endforeach; ?></select>
        </form>
        <a href="index.php?page=workspace-create"><i class="bi bi-plus-lg"></i> Buat Team Workspace</a>
    </div>
    <div class="sidebar-section-label">WORKSPACE</div>
    <nav class="side-nav">
        <?php
        $taskCountStmt = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE archived_at IS NULL AND workspace_id = ?');
        $taskCountStmt->execute([active_workspace_id()]);
        $workspaceTaskCount = (int)$taskCountStmt->fetchColumn();
        $notificationStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND (workspace_id = ? OR workspace_id IS NULL) AND is_read = 0");
        $notificationStmt->execute([current_user_id(), active_workspace_id()]);
        $unreadNotificationCount = (int)$notificationStmt->fetchColumn();
        $inviteCountStmt = $pdo->prepare("SELECT COUNT(*) FROM workspace_invitations wi INNER JOIN users u ON u.email = wi.email WHERE u.id = ? AND wi.status = 'Pending'");
        $inviteCountStmt->execute([current_user_id()]);
        $unreadNotificationCount += (int)$inviteCountStmt->fetchColumn();
        $navItems = [
            ['dashboard', 'Dashboard', 'bi-grid-1x2'],
            ['tasks', 'All Tasks', 'bi-check2-square'],
            ['calendar', 'Calendar', 'bi-calendar3'],
            ['in-progress', 'In Progress', 'bi-arrow-repeat'],
            ['pending', 'Pending', 'bi-circle'],
            ['completed', 'Completed', 'bi-check-circle'],
            ['archive', 'Archive', 'bi-archive'],
            ['categories', 'Categories', 'bi-tags'],
            ['projects', 'Projects', 'bi-kanban'],
            ['notifications', 'Notifications', 'bi-bell'],
        ];
        if ($activeWorkspace['type'] === 'Team') {
            $navItems[] = ['team-members', 'Team Members', 'bi-people'];
        }
        foreach ($navItems as [$target, $label, $icon]):
            $href = in_array($target, ['in-progress', 'pending'], true) ? 'index.php?page=tasks&status=' . rawurlencode($target === 'in-progress' ? 'In Progress' : 'Pending') : 'index.php?page=' . $target;
            $active = $page === $target || ($page === 'tasks' && in_array($target, ['pending', 'in-progress'], true) && ($_GET['status'] ?? '') === ($target === 'pending' ? 'Pending' : 'In Progress'));
        ?>
            <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= e($href) ?>"><i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span><?php if ($target === 'tasks'): ?><span class="nav-count"><?= $workspaceTaskCount ?></span><?php elseif ($target === 'notifications' && $unreadNotificationCount > 0): ?><span class="nav-count"><?= $unreadNotificationCount ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <a class="nav-link<?= $page === 'profile' ? ' active' : '' ?>" href="index.php?page=profile"><i class="bi bi-person"></i><span>Profile</span></a>
        <a class="nav-link<?= $page === 'settings' ? ' active' : '' ?>" href="index.php?page=settings"><i class="bi bi-sliders"></i><span>Pengaturan tampilan</span></a>
        <form method="post" action="index.php?page=logout" class="logout-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="nav-link" type="submit"><i class="bi bi-box-arrow-right"></i><span>Logout</span></button></form>
        <div class="app-info"><span><?= e($activeWorkspace['name']) ?></span><small>Versi 1.0.0</small></div>
    </div>
</aside>
<div class="offcanvas offcanvas-start mobile-offcanvas" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
    <div class="offcanvas-header"><a class="brand" href="index.php" id="mobileNavLabel"><span class="brand-mark"><img src="assets/image/logo%20TaskFlow.png" alt=""></span><span>TaskFlow</span></a><button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button></div>
    <div class="offcanvas-body">
        <div class="workspace-switcher mobile-workspace-switcher"><label for="mobile-active-workspace">ACTIVE WORKSPACE</label><form method="post" action="index.php?page=workspace-switch"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="workspace-switch"><select id="mobile-active-workspace" name="workspace_id" aria-label="Pilih workspace" onchange="this.form.submit()"><?php foreach ($userWorkspaces as $workspace): ?><option value="<?= (int)$workspace['id'] ?>"<?= (int)$workspace['id'] === (int)$activeWorkspace['id'] ? ' selected' : '' ?>><?= e($workspace['name']) ?></option><?php endforeach; ?></select></form><a href="index.php?page=workspace-create"><i class="bi bi-plus-lg"></i> Buat Team Workspace</a></div>
        <nav class="side-nav"><?php foreach ($navItems as [$target, $label, $icon]): $mobileHref = in_array($target, ['in-progress', 'pending'], true) ? 'index.php?page=tasks&status=' . rawurlencode($target === 'in-progress' ? 'In Progress' : 'Pending') : 'index.php?page=' . $target; ?><a class="nav-link<?= $page === $target ? ' active' : '' ?>" href="<?= e($mobileHref) ?>"><i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span><?php if ($target === 'notifications' && $unreadNotificationCount > 0): ?><span class="nav-count"><?= $unreadNotificationCount ?></span><?php endif; ?></a><?php endforeach; ?></nav>
        <nav class="side-nav account-mobile-nav"><a class="nav-link" href="index.php?page=profile"><i class="bi bi-person"></i><span>Profile</span></a><form method="post" action="index.php?page=logout"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="nav-link" type="submit"><i class="bi bi-box-arrow-right"></i><span>Logout</span></button></form></nav>
    </div>
</div>
