<?php
$search = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$categoryFilter = trim((string)($_GET['category'] ?? ''));
$priorityFilter = trim((string)($_GET['priority'] ?? ''));
$deadlineFilter = trim((string)($_GET['deadline'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'newest');
$sorts = [
    'newest' => 't.created_at DESC',
    'oldest' => 't.created_at ASC',
    'deadline-asc' => 't.deadline IS NULL, t.deadline ASC',
    'deadline-desc' => 't.deadline IS NULL, t.deadline DESC',
    'priority' => "FIELD(t.priority, 'Urgent', 'High', 'Medium', 'Low'), t.created_at DESC",
    'progress' => 'progress DESC, t.created_at DESC',
];
$where = ['t.archived_at IS NULL'];
$params = [];
if ($search !== '') {
    $where[] = '(t.title LIKE ? OR t.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if (in_array($statusFilter, ['Pending', 'In Progress', 'Completed'], true)) {
    $where[] = 't.status = ?';
    $params[] = $statusFilter;
}
if ($categoryFilter !== '' && ctype_digit($categoryFilter)) {
    $where[] = 't.category_id = ?';
    $params[] = (int)$categoryFilter;
}
if (in_array($priorityFilter, ['Low', 'Medium', 'High', 'Urgent'], true)) {
    $where[] = 't.priority = ?';
    $params[] = $priorityFilter;
}
if ($deadlineFilter === 'overdue') {
    $where[] = "t.deadline < CURDATE() AND t.status <> 'Completed'";
} elseif ($deadlineFilter === 'upcoming') {
    $where[] = "t.deadline >= CURDATE() AND t.status <> 'Completed'";
} elseif ($deadlineFilter === 'none') {
    $where[] = 't.deadline IS NULL';
}
$whereSql = implode(' AND ', $where);
$perPage = 10;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$countStmt = $pdo->prepare("SELECT COUNT(DISTINCT t.id) FROM tasks t LEFT JOIN categories c ON c.id=t.category_id WHERE {$whereSql}");
$countStmt->execute($params);
$totalTasks = (int)$countStmt->fetchColumn();
$pageCount = max(1, (int)ceil($totalTasks / $perPage));
$currentPage = min($currentPage, $pageCount);
$sortSql = $sorts[$sort] ?? $sorts['newest'];
$sql = "SELECT t.*, c.name AS category_name, COALESCE(i.item_total,0) AS item_total, COALESCE(i.item_completed,0) AS item_completed, CASE WHEN COALESCE(i.item_total,0)=0 THEN 0 ELSE ROUND(i.item_completed*100/i.item_total) END AS progress FROM tasks t LEFT JOIN categories c ON c.id=t.category_id LEFT JOIN (SELECT task_id, COUNT(*) AS item_total, COALESCE(SUM(is_completed),0) AS item_completed FROM task_items GROUP BY task_id) i ON i.task_id=t.id WHERE {$whereSql} ORDER BY {$sortSql} LIMIT {$perPage} OFFSET " . (($currentPage - 1) * $perPage);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
?>
<section class="page-heading"><div><p class="eyebrow">YOUR WORKSPACE</p><h1>All Tasks</h1><p class="muted"><?= $totalTasks ?> tugas ditemukan</p></div></section>
<form class="filter-panel" method="get" action="index.php"><input type="hidden" name="page" value="tasks"><label class="search-input"><i class="bi bi-search"></i><input name="q" value="<?= e($search) ?>" placeholder="Cari judul atau deskripsi..."></label>
<select name="status" aria-label="Filter status"><option value="">Semua status</option><?php foreach (['Pending','In Progress','Completed'] as $option): ?><option<?= $statusFilter === $option ? ' selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
<select name="category" aria-label="Filter kategori"><option value="">Semua kategori</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"<?= (string)$categoryFilter === (string)$category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select>
<select name="priority" aria-label="Filter prioritas"><option value="">Semua prioritas</option><?php foreach (['Urgent','High','Medium','Low'] as $option): ?><option<?= $priorityFilter === $option ? ' selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
<select name="deadline" aria-label="Filter deadline"><option value="">Semua deadline</option><option value="upcoming"<?= $deadlineFilter === 'upcoming' ? ' selected' : '' ?>>Mendatang</option><option value="overdue"<?= $deadlineFilter === 'overdue' ? ' selected' : '' ?>>Terlambat</option><option value="none"<?= $deadlineFilter === 'none' ? ' selected' : '' ?>>Tanpa deadline</option></select>
<select name="sort" aria-label="Urutkan"><?php foreach (['newest'=>'Terbaru','oldest'=>'Terlama','deadline-asc'=>'Deadline terdekat','deadline-desc'=>'Deadline terjauh','priority'=>'Prioritas tertinggi','progress'=>'Progress tertinggi'] as $key=>$label): ?><option value="<?= e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="button button-secondary button-small" type="submit">Terapkan</button></form>
<?php if (!$tasks): ?><div class="panel empty-state list-empty"><span class="empty-icon"><i class="bi bi-search"></i></span><h3>Tidak ada tugas yang cocok</h3><p>Coba ubah filter atau gunakan tombol Add New Task untuk membuat tugas.</p></div>
<?php else: ?><div class="task-cards"><?php foreach ($tasks as $task): $deadline = task_deadline($task); ?><article class="task-card"><div class="task-card-top"><div><a class="task-card-title" href="index.php?page=task-detail&id=<?= (int)$task['id'] ?>"><?= e($task['title']) ?></a><p class="task-card-description"><?= e($task['description'] ?: 'Tidak ada deskripsi.') ?></p></div><span class="status-pill <?= e(status_class($task['status'])) ?>"><?= e($task['status']) ?></span></div><div class="task-card-bottom"><div class="task-tags"><span class="category-tag"><i class="bi bi-tag"></i> <?= e($task['category_name'] ?: 'Tanpa kategori') ?></span><span class="priority priority-<?= strtolower(e($task['priority'])) ?>"><?= e($task['priority']) ?></span><span class="deadline-text deadline-<?= e($deadline['tone']) ?>"><i class="bi bi-calendar3"></i> <?= e($deadline['label']) ?></span></div><div class="task-progress-wrap"><div class="mini-progress"><span style="width:<?= (int)$task['progress'] ?>%"></span></div><small><?= (int)$task['item_completed'] ?>/<?= (int)$task['item_total'] ?> · <?= (int)$task['progress'] ?>%</small></div></div></article><?php endforeach; ?></div>
<?php if ($pageCount > 1): ?><nav class="pagination-wrap" aria-label="Halaman"><?php for ($i=1;$i<=$pageCount;$i++): $query=$_GET; $query['p']=$i; ?><a class="<?= $i===$currentPage?'current':'' ?>" href="index.php?<?= e(http_build_query($query)) ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?><?php endif; ?>
