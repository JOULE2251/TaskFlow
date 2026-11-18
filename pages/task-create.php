<?php
$isEdit = $page === 'task-edit';
$prefilledDeadline = $_GET['deadline'] ?? '';
if (!is_string($prefilledDeadline) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $prefilledDeadline)) {
    $prefilledDeadline = '';
} else {
    [$deadlineYear, $deadlineMonth, $deadlineDay] = array_map('intval', explode('-', $prefilledDeadline));
    if (!checkdate($deadlineMonth, $deadlineDay, $deadlineYear)) {
        $prefilledDeadline = '';
    }
}
$prefilledProjectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$prefilledProjectId = $prefilledProjectId === false ? null : (int)$prefilledProjectId;
$task = $isEdit ? get_task($pdo, valid_id($_GET['id'] ?? null)) : ['id' => 0, 'title' => '', 'description' => '', 'category_id' => null, 'project_id' => null, 'priority' => 'Medium', 'deadline' => $prefilledDeadline];
if (!$isEdit && $prefilledProjectId !== null) {
    $task['project_id'] = $prefilledProjectId;
}
$categoryStmt = $pdo->prepare('SELECT id, name FROM categories WHERE workspace_id = ? ORDER BY name');
$categoryStmt->execute([active_workspace_id()]);
$categories = $categoryStmt->fetchAll();
$projectStmt = $pdo->prepare("SELECT id, name FROM projects WHERE workspace_id = ? AND status <> 'Archived' ORDER BY name");
$projectStmt->execute([active_workspace_id()]);
$projects = $projectStmt->fetchAll();
$members = [];
if ($activeWorkspace['type'] === 'Team') {
    $memberStmt = $pdo->prepare('SELECT u.id, u.name, u.email FROM workspace_members wm INNER JOIN users u ON u.id = wm.user_id WHERE wm.workspace_id = ? ORDER BY u.name');
    $memberStmt->execute([active_workspace_id()]);
    $members = $memberStmt->fetchAll();
}
$assignedIds = [];
if ($isEdit) {
    $assignedStmt = $pdo->prepare('SELECT user_id FROM task_assignees WHERE task_id = ?');
    $assignedStmt->execute([(int)$task['id']]);
    $assignedIds = array_map('intval', $assignedStmt->fetchAll(PDO::FETCH_COLUMN));
}
$existingItems = $isEdit ? $pdo->prepare('SELECT title FROM task_items WHERE task_id = ? ORDER BY sort_order, id') : null;
if ($existingItems) {
    $existingItems->execute([(int)$task['id']]);
    $itemTitles = $existingItems->fetchAll(PDO::FETCH_COLUMN);
} else {
    $itemTitles = [''];
}
?>
<section class="page-heading"><div><p class="eyebrow"><?= $isEdit ? 'TASK SETTINGS' : 'GET STARTED' ?></p><h1><?= $isEdit ? 'Edit task' : 'Buat tugas baru' ?></h1><p class="muted"><?= $isEdit ? 'Perbarui informasi tugasmu.' : 'Tentukan detail dan pecah pekerjaan menjadi langkah kecil.' ?></p></div><a class="button button-secondary" href="<?= $isEdit ? 'index.php?page=task-detail&id=' . (int)$task['id'] : 'index.php?page=tasks' ?>"><i class="bi bi-arrow-left"></i> Kembali</a></section>
<form class="panel task-form" method="post" action="index.php?page=<?= $isEdit ? 'task-edit' : 'task-create' ?>" data-validate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= $isEdit ? 'task-update' : 'task-store' ?>"><input type="hidden" name="return_page" value="<?= $isEdit ? 'task-edit' : 'task-create' ?>"><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$task['id'] ?>"><?php endif; ?>
    <div class="form-section"><h2>Task information</h2><div class="form-field"><label for="title">Judul tugas <span>*</span></label><input id="title" name="title" maxlength="160" required value="<?= e($task['title']) ?>" placeholder="Contoh: Rilis website portfolio"><small>Gunakan judul yang jelas dan mudah dikenali.</small></div><div class="form-field"><label for="description">Deskripsi</label><textarea id="description" name="description" rows="4" maxlength="5000" placeholder="Tambahkan konteks atau detail tugas..."><?= e($task['description']) ?></textarea></div><div class="form-grid"><div class="form-field"><label for="category">Kategori</label><select id="category" name="category_id"><option value="">Tanpa kategori</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"<?= (string)$task['category_id'] === (string)$category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select><a class="field-link" href="index.php?page=categories">Kelola kategori <i class="bi bi-arrow-up-right"></i></a></div><div class="form-field"><label for="project">Proyek</label><select id="project" name="project_id"><option value="">Tanpa proyek</option><?php foreach ($projects as $project): ?><option value="<?= (int)$project['id'] ?>"<?= (string)($task['project_id'] ?? '') === (string)$project['id'] ? ' selected' : '' ?>><?= e($project['name']) ?></option><?php endforeach; ?></select><a class="field-link" href="index.php?page=projects">Kelola proyek <i class="bi bi-arrow-up-right"></i></a></div><div class="form-field"><label for="priority">Prioritas</label><select id="priority" name="priority"><?php foreach (['Low','Medium','High','Urgent'] as $priority): ?><option<?= $task['priority'] === $priority ? ' selected' : '' ?>><?= e($priority) ?></option><?php endforeach; ?></select></div><div class="form-field"><label for="deadline">Deadline</label><input id="deadline" name="deadline" type="date" value="<?= e($task['deadline']) ?>"><small>Opsional · zona waktu Asia/Jakarta.</small></div></div></div>
    <?php if ($members): ?>
    <div class="form-section assignee-form-section"><h2>Assignees</h2><p class="muted">Pilih anggota yang bertanggung jawab pada tugas ini.</p><div class="assignee-picker"><?php foreach ($members as $member): ?><label class="assignee-option"><input type="checkbox" name="assignee_ids[]" value="<?= (int)$member['id'] ?>"<?= in_array((int)$member['id'], $assignedIds, true) ? ' checked' : '' ?>><span><strong><?= e($member['name']) ?></strong><small><?= e($member['email']) ?></small></span></label><?php endforeach; ?></div></div>
    <?php endif; ?>
    <div class="form-section checklist-form-section"><?php if ($isEdit): ?><div class="panel-heading"><div><h2>Checklist items</h2><p class="muted">Checklist dikelola langsung di halaman detail tugas.</p></div><a class="button button-secondary button-small" href="index.php?page=task-detail&id=<?= (int)$task['id'] ?>"><i class="bi bi-check2-square"></i> Kelola checklist</a></div><?php else: ?><div class="panel-heading"><div><h2>Checklist items</h2><p class="muted">Pecah tugas menjadi langkah yang bisa ditindaklanjuti.</p></div><button type="button" class="button button-secondary button-small" id="addChecklistField"><i class="bi bi-plus-lg"></i> Tambah item</button></div><div id="checklistFields"><?php foreach ($itemTitles as $itemTitle): ?><div class="checklist-input-row"><i class="bi bi-grip-vertical"></i><input name="checklist[]" maxlength="240" value="<?= e($itemTitle) ?>" placeholder="Langkah pekerjaan"><button class="icon-button remove-checklist-field" type="button" aria-label="Hapus item"><i class="bi bi-x-lg"></i></button></div><?php endforeach; ?></div><?php endif; ?></div>
    <div class="form-actions"><a class="button button-secondary" href="<?= $isEdit ? 'index.php?page=task-detail&id=' . (int)$task['id'] : 'index.php?page=tasks' ?>">Batal</a><button type="submit" class="button button-primary"><i class="bi bi-check2"></i> <?= $isEdit ? 'Simpan perubahan' : 'Buat tugas' ?></button></div>
</form>
