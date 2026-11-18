<?php
$projectId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$projectId = $projectId === false ? null : (int)$projectId;
$project = null;

if ($projectId !== null) {
    $stmt = $pdo->prepare('SELECT p.*, u.name AS creator_name FROM projects p LEFT JOIN users u ON u.id = p.created_by WHERE p.id = ? AND p.workspace_id = ?');
    $stmt->execute([$projectId, active_workspace_id()]);
    $project = $stmt->fetch();
    if (!$project) {
        throw new InvalidArgumentException('Proyek tidak ditemukan.');
    }
}

if ($project) {
    $taskStmt = $pdo->prepare("SELECT t.*, c.name AS category_name, COALESCE(i.item_total, 0) AS item_total, COALESCE(i.item_completed, 0) AS item_completed, CASE WHEN COALESCE(i.item_total, 0) = 0 THEN 0 ELSE ROUND(i.item_completed * 100 / i.item_total) END AS progress FROM tasks t LEFT JOIN categories c ON c.id = t.category_id LEFT JOIN (SELECT task_id, COUNT(*) AS item_total, COALESCE(SUM(is_completed), 0) AS item_completed FROM task_items GROUP BY task_id) i ON i.task_id = t.id WHERE t.project_id = ? AND t.workspace_id = ? AND t.archived_at IS NULL ORDER BY t.created_at DESC");
    $taskStmt->execute([$projectId, active_workspace_id()]);
    $projectTasks = $taskStmt->fetchAll();
    $statusCounts = ['Planning' => 0, 'Pending' => 0, 'In Progress' => 0, 'Completed' => 0];
    foreach ($projectTasks as $projectTask) {
        $statusCounts[$projectTask['status']] = ($statusCounts[$projectTask['status']] ?? 0) + 1;
    }
    $projectProgress = count($projectTasks) === 0 ? 0 : (int)round($statusCounts['Completed'] * 100 / count($projectTasks));
?>
<section class="page-heading">
    <div><p class="eyebrow"><a href="index.php?page=projects">PROJECTS</a> / PROJECT DETAIL</p><h1><?= e($project['name']) ?></h1><p class="muted"><?= e($project['description'] ?: 'Tidak ada deskripsi proyek.') ?></p></div>
    <div class="detail-actions">
        <a class="button button-secondary button-small" href="index.php?page=task-create&project_id=<?= $projectId ?>"><i class="bi bi-plus-lg"></i> Tambah tugas</a>
        <button class="button button-secondary button-small" type="button" data-project-edit><i class="bi bi-pencil"></i> Edit</button>
        <form method="post" action="index.php?page=projects&id=<?= $projectId ?>" data-confirm="Hapus proyek ini? Tugas tetap tersimpan tanpa proyek.">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="project-delete">
            <input type="hidden" name="id" value="<?= $projectId ?>">
            <input type="hidden" name="return_page" value="projects">
            <button class="button button-danger button-small" type="submit"><i class="bi bi-trash3"></i></button>
        </form>
    </div>
    <div class="project-collaboration-layout">
        <?php
        $resourceStmt = $pdo->prepare('SELECT pr.*, u.name AS uploader_name FROM project_resources pr LEFT JOIN users u ON u.id = pr.uploaded_by WHERE pr.project_id = ? ORDER BY pr.created_at DESC');
        $resourceStmt->execute([$projectId]);
        $resources = $resourceStmt->fetchAll();
        $updateStmt = $pdo->prepare('SELECT du.*, u.name AS author_name FROM daily_updates du LEFT JOIN users u ON u.id = du.user_id WHERE du.workspace_id = ? AND du.project_id = ? ORDER BY du.update_date DESC, du.created_at DESC');
        $updateStmt->execute([active_workspace_id(), $projectId]);
        $dailyUpdates = $updateStmt->fetchAll();
        ?>
        <section class="panel">
            <div class="panel-heading"><div><p class="eyebrow">DAILY WORK UPDATE</p><h2>Catatan harian</h2></div></div>
            <form class="daily-update-form" method="post" action="index.php?page=projects&id=<?= $projectId ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="daily-update-store">
                <input type="hidden" name="project_id" value="<?= $projectId ?>">
                <input type="hidden" name="return_page" value="projects">
                <div class="form-grid"><div class="form-field"><label for="update-date">Tanggal</label><input id="update-date" name="update_date" type="date" value="<?= e(date('Y-m-d')) ?>"></div></div>
                <div class="form-field"><label for="update-content">Pembaruan kerja</label><textarea id="update-content" name="content" rows="3" maxlength="5000" required placeholder="Apa yang sudah dikerjakan hari ini?"></textarea></div>
                <div class="form-actions"><button class="button button-primary button-small" type="submit"><i class="bi bi-plus-lg"></i> Tambah update</button></div>
            </form>
            <?php if (!$dailyUpdates): ?><p class="compact-empty">Belum ada daily work update.</p><?php else: ?><div class="daily-update-list"><?php foreach ($dailyUpdates as $update): ?><article class="daily-update-card"><div><strong><?= e($update['author_name'] ?: 'Pengguna') ?></strong><small><?= e(date('d M Y', strtotime($update['update_date']))) ?></small></div><p><?= nl2br(e($update['content'])) ?></p></article><?php endforeach; ?></div><?php endif; ?>
        </section>
        <section class="panel">
            <div class="panel-heading"><div><p class="eyebrow">PROJECT RESOURCES</p><h2>Resource</h2></div></div>
            <form class="resource-form" method="post" action="index.php?page=projects&id=<?= $projectId ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="project-resource-store">
                <input type="hidden" name="project_id" value="<?= $projectId ?>">
                <input type="hidden" name="return_page" value="projects">
                <div class="form-grid"><div class="form-field"><label for="resource-name">Nama</label><input id="resource-name" name="name" maxlength="160" required placeholder="Dokumentasi"></div><div class="form-field"><label for="resource-url">URL</label><input id="resource-url" name="url" type="url" maxlength="2048" required placeholder="https://..."></div></div>
                <div class="form-actions"><button class="button button-primary button-small" type="submit"><i class="bi bi-link-45deg"></i> Tambah resource</button></div>
            </form>
            <?php if (!$resources): ?><p class="compact-empty">Belum ada resource proyek.</p><?php else: ?><div class="resource-list"><?php foreach ($resources as $resource): ?><article class="resource-row"><a href="<?= e($resource['url']) ?>" target="_blank" rel="noopener"><i class="bi bi-link-45deg"></i><span><strong><?= e($resource['name']) ?></strong><small><?= e($resource['uploader_name'] ?: 'Pengguna') ?> · <?= e(date('d M Y', strtotime($resource['created_at']))) ?></small></span></a><form method="post" action="index.php?page=projects&id=<?= $projectId ?>" data-confirm="Hapus resource ini?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="project-resource-delete"><input type="hidden" name="project_id" value="<?= $projectId ?>"><input type="hidden" name="resource_id" value="<?= (int)$resource['id'] ?>"><input type="hidden" name="return_page" value="projects"><button class="icon-button danger-hover" type="submit" aria-label="Hapus resource"><i class="bi bi-trash3"></i></button></form></article><?php endforeach; ?></div><?php endif; ?>
        </section>
    </div>
</section>
<section class="stats-grid project-stat-grid">
    <article class="stat-card"><div class="stat-top"><span>Total tugas</span><i class="bi bi-layers stat-icon accent"></i></div><strong><?= count($projectTasks) ?></strong></article>
    <article class="stat-card"><div class="stat-top"><span>Selesai</span><i class="bi bi-check-circle stat-icon success"></i></div><strong><?= (int)$statusCounts['Completed'] ?></strong></article>
    <article class="stat-card"><div class="stat-top"><span>Dikerjakan</span><i class="bi bi-arrow-repeat stat-icon purple"></i></div><strong><?= (int)$statusCounts['In Progress'] ?></strong></article>
    <article class="stat-card"><div class="stat-top"><span>Progress</span><i class="bi bi-activity stat-icon accent"></i></div><strong><?= $projectProgress ?>%</strong></article>
</section>
<div class="project-detail-layout">
    <section class="panel">
        <div class="panel-heading"><div><p class="eyebrow">PROJECT TASKS</p><h2>Tugas proyek</h2></div><span class="muted"><?= count($projectTasks) ?> tugas</span></div>
        <?php if (!$projectTasks): ?>
        <div class="empty-state"><span class="empty-icon"><i class="bi bi-kanban"></i></span><h3>Belum ada tugas</h3><p><a class="text-link" href="index.php?page=task-create&project_id=<?= $projectId ?>">Tambahkan tugas pertama</a> untuk proyek ini.</p></div>
        <?php else: ?>
        <div class="task-cards">
            <?php foreach ($projectTasks as $task): $deadline = task_deadline($task); ?>
            <article class="task-card">
                <div class="task-card-top"><div><a class="task-card-title" href="index.php?page=task-detail&id=<?= (int)$task['id'] ?>"><?= e($task['title']) ?></a><p class="task-card-description"><?= e($task['description'] ?: 'Tidak ada deskripsi.') ?></p></div><span class="status-pill <?= e(status_class($task['status'])) ?>"><?= e($task['status']) ?></span></div>
                <div class="task-card-bottom"><div class="task-tags"><span class="category-tag"><i class="bi bi-tag"></i> <?= e($task['category_name'] ?: 'Tanpa kategori') ?></span><span class="priority priority-<?= strtolower(e($task['priority'])) ?>"><?= e($task['priority']) ?></span><span class="deadline-text deadline-<?= e($deadline['tone']) ?>"><i class="bi bi-calendar3"></i> <?= e($deadline['label']) ?></span></div><div class="task-progress-wrap"><div class="mini-progress"><span style="width:<?= (int)$task['progress'] ?>%"></span></div><small><?= (int)$task['item_completed'] ?>/<?= (int)$task['item_total'] ?> · <?= (int)$task['progress'] ?>%</small></div></div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <aside class="detail-side">
        <section class="panel project-info-panel">
            <div class="panel-heading"><div><p class="eyebrow">PROJECT INFO</p><h2>Informasi</h2></div></div>
            <div class="quick-facts">
                <div><span>Status</span><strong><?= e($project['status']) ?></strong></div>
                <div><span>Tanggal mulai</span><strong><?= $project['start_date'] ? e(date('d M Y', strtotime($project['start_date']))) : 'Belum ditentukan' ?></strong></div>
                <div><span>Tanggal selesai</span><strong><?= $project['end_date'] ? e(date('d M Y', strtotime($project['end_date']))) : 'Belum ditentukan' ?></strong></div>
                <div><span>Dibuat oleh</span><strong><?= e($project['creator_name'] ?: 'Pengguna') ?></strong></div>
            </div>
        </section>
    </aside>
</div>
<div class="project-modal-backdrop" data-project-modal hidden>
    <section class="panel project-modal">
        <div class="panel-heading"><div><p class="eyebrow">PROJECT SETTINGS</p><h2>Edit proyek</h2></div><button class="icon-button" type="button" data-project-modal-close aria-label="Tutup"><i class="bi bi-x-lg"></i></button></div>
        <form method="post" action="index.php?page=projects&id=<?= $projectId ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="project-update">
            <input type="hidden" name="id" value="<?= $projectId ?>">
            <input type="hidden" name="return_page" value="projects">
            <?php require __DIR__ . '/project-form-fields.php'; ?>
        </form>
    </section>
</div>
<?php
} else {
    $statusFilter = (string)($_GET['status'] ?? '');
    $where = ['p.workspace_id = ?'];
    $params = [active_workspace_id()];
    if (in_array($statusFilter, ['Planning', 'In Progress', 'Completed', 'Archived'], true)) {
        $where[] = 'p.status = ?';
        $params[] = $statusFilter;
    }
    $whereSql = implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT p.*, u.name AS creator_name, COUNT(t.id) AS task_count, COALESCE(SUM(t.status = 'Completed'), 0) AS completed_count FROM projects p LEFT JOIN users u ON u.id = p.created_by LEFT JOIN tasks t ON t.project_id = p.id AND t.archived_at IS NULL WHERE {$whereSql} GROUP BY p.id ORDER BY p.updated_at DESC");
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
?>
<section class="page-heading">
    <div><p class="eyebrow">COLLABORATION</p><h1>Projects</h1><p class="muted"><?= count($projects) ?> proyek dalam workspace aktif</p></div>
    <button class="button button-primary button-small" type="button" data-project-create><i class="bi bi-plus-lg"></i> Buat proyek</button>
</section>
<form class="filter-panel project-filter" method="get" action="index.php">
    <input type="hidden" name="page" value="projects">
    <select name="status" aria-label="Filter status proyek">
        <option value="">Semua status</option>
        <?php foreach (['Planning', 'In Progress', 'Completed', 'Archived'] as $status): ?>
        <option<?= $statusFilter === $status ? ' selected' : '' ?>><?= e($status) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="button button-secondary button-small" type="submit">Terapkan</button>
</form>
<?php if (!$projects): ?>
<div class="panel empty-state list-empty"><span class="empty-icon"><i class="bi bi-kanban"></i></span><h3>Belum ada proyek</h3><p>Buat proyek pertama untuk mengelompokkan tugas workspace.</p></div>
<?php else: ?>
<div class="project-grid">
    <?php foreach ($projects as $projectCard): $projectCardProgress = (int)$projectCard['task_count'] === 0 ? 0 : (int)round((int)$projectCard['completed_count'] * 100 / (int)$projectCard['task_count']); ?>
    <article class="panel project-card">
        <div class="project-card-top"><span class="project-status project-status-<?= strtolower(str_replace(' ', '-', e($projectCard['status']))) ?>"><?= e($projectCard['status']) ?></span><small><?= (int)$projectCard['task_count'] ?> tugas</small></div>
        <a href="index.php?page=projects&id=<?= (int)$projectCard['id'] ?>"><h2><?= e($projectCard['name']) ?></h2></a>
        <p><?= e($projectCard['description'] ?: 'Tidak ada deskripsi proyek.') ?></p>
        <div class="project-card-meta"><span><i class="bi bi-calendar3"></i> <?= $projectCard['end_date'] ? e(date('d M Y', strtotime($projectCard['end_date']))) : 'Tanpa target akhir' ?></span><strong><?= $projectCardProgress ?>%</strong></div>
        <div class="progress-track"><span style="width:<?= $projectCardProgress ?>%"></span></div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="project-modal-backdrop" data-project-modal hidden>
    <section class="panel project-modal">
        <div class="panel-heading"><div><p class="eyebrow">NEW PROJECT</p><h2>Buat proyek</h2></div><button class="icon-button" type="button" data-project-modal-close aria-label="Tutup"><i class="bi bi-x-lg"></i></button></div>
        <form method="post" action="index.php?page=projects">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="project-store">
            <input type="hidden" name="return_page" value="projects">
            <?php require __DIR__ . '/project-form-fields.php'; ?>
        </form>
    </section>
</div>
<?php } ?>
