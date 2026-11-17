<?php
$stats = $pdo->query("SELECT
    COUNT(*) AS total,
    SUM(status = 'Pending') AS pending,
    SUM(status = 'In Progress') AS in_progress,
    SUM(status = 'Completed') AS completed
    FROM tasks WHERE archived_at IS NULL")->fetch();
$recent = task_rows($pdo, 't.archived_at IS NULL', [], 't.created_at DESC');
$recent = array_slice($recent, 0, 5);
$upcoming = task_rows($pdo, "t.archived_at IS NULL AND t.deadline >= CURDATE() AND t.status <> 'Completed'", [], 't.deadline ASC');
$overdue = task_rows($pdo, "t.archived_at IS NULL AND t.deadline < CURDATE() AND t.status <> 'Completed'", [], 't.deadline ASC');
$done = (int)$stats['completed'];
$total = (int)$stats['total'];
$active = (int)$stats['pending'] + (int)$stats['in_progress'];
$completion = $total > 0 ? (int)round($done * 100 / $total) : 0;
?>
<section class="page-heading">
    <div><p class="eyebrow">OVERVIEW</p><h1>Dashboard</h1><p class="muted">Ringkasan pekerjaan dan fokusmu hari ini.</p></div>
</section>
<section class="stats-grid">
    <?php foreach ([['Total Tasks', $stats['total'], 'bi-layers', 'accent'], ['Pending Tasks', $stats['pending'], 'bi-circle', 'warning'], ['In Progress', $stats['in_progress'], 'bi-arrow-repeat', 'purple'], ['Completed', $stats['completed'], 'bi-check-circle', 'success']] as [$label, $value, $icon, $tone]): ?>
    <article class="stat-card"><div class="stat-top"><span><?= e($label) ?></span><i class="bi <?= e($icon) ?> stat-icon <?= e($tone) ?>"></i></div><strong><?= (int)$value ?></strong><small><?= $label === 'Total Tasks' ? 'Tugas aktif' : 'Tugas aktif' ?></small></article>
    <?php endforeach; ?>
</section>
<div class="dashboard-grid">
    <section class="panel recent-panel">
        <div class="panel-heading"><div><h2>Recent Tasks</h2><p class="muted">Tugas yang terakhir diperbarui</p></div><a class="text-link" href="index.php?page=tasks">Lihat semua <i class="bi bi-arrow-up-right"></i></a></div>
        <?php if (!$recent): ?><div class="empty-state"><span class="empty-icon"><i class="bi bi-check2-square"></i></span><h3>Belum ada tugas</h3><p>Mulai dengan menambahkan tugas pertamamu melalui tombol Add New Task.</p></div>
        <?php else: ?><div class="task-list"><?php foreach ($recent as $task): $deadline = task_deadline($task); ?>
            <a class="task-row" href="index.php?page=task-detail&id=<?= (int)$task['id'] ?>"><span class="task-symbol"><i class="bi bi-check2-square"></i></span><span class="task-main"><strong><?= e($task['title']) ?></strong><small><?= e($task['category_name'] ?: 'Tanpa kategori') ?> <span class="dot">·</span> <span class="priority priority-<?= strtolower(e($task['priority'])) ?>"><?= e($task['priority']) ?></span></small><span class="mini-progress"><span style="width:<?= (int)$task['progress'] ?>%"></span></span></span><span class="task-side"><span class="status-pill <?= e(status_class($task['status'])) ?>"><?= e($task['status']) ?></span><small class="deadline-text deadline-<?= e($deadline['tone']) ?>"><?= e($deadline['label']) ?></small></span></a>
        <?php endforeach; ?></div><?php endif; ?>
    </section>
    <div class="dashboard-side">
        <section class="panel overview-panel"><div class="panel-heading"><div><h2>Productivity</h2><p class="muted">Progres keseluruhan</p></div><i class="bi bi-activity panel-icon"></i></div><div class="productivity-number"><?= $completion ?><small>%</small></div><div class="progress-track"><span style="width:<?= $completion ?>%"></span></div><div class="overview-meta"><span><i class="bi bi-check-circle-fill text-success"></i> <?= $done ?> selesai</span><span><?= $active ?> aktif</span></div></section>
        <section class="panel deadlines-panel"><div class="panel-heading"><div><h2>Upcoming deadlines</h2><p class="muted">Yang perlu diperhatikan</p></div></div>
        <?php if (!$upcoming): ?><div class="compact-empty">Tidak ada deadline mendatang.</div><?php else: ?><div class="deadline-list"><?php foreach ($upcoming as $task): $deadline = task_deadline($task); ?><a href="index.php?page=task-detail&id=<?= (int)$task['id'] ?>" class="deadline-row"><span class="deadline-dot <?= e($deadline['tone']) ?>"></span><span><strong><?= e($task['title']) ?></strong><small><?= e($deadline['label']) ?></small></span></a><?php endforeach; ?></div><?php endif; ?></section>
    </div>
</div>
<section class="panel overdue-panel"><div class="panel-heading"><div><h2><i class="bi bi-exclamation-circle text-danger"></i> Overdue tasks</h2><p class="muted">Tugas aktif yang melewati deadline</p></div><span class="count-badge"><?= count($overdue) ?></span></div>
<?php if (!$overdue): ?><div class="compact-empty">Bagus, tidak ada tugas yang terlambat.</div><?php else: ?><div class="overdue-grid"><?php foreach ($overdue as $task): $deadline = task_deadline($task); ?><a href="index.php?page=task-detail&id=<?= (int)$task['id'] ?>" class="overdue-item"><strong><?= e($task['title']) ?></strong><small><?= e($deadline['label']) ?></small></a><?php endforeach; ?></div><?php endif; ?></section>
