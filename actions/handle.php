<?php
try {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    switch ($action) {
        case 'task-store':
            $title = task_title($_POST['title'] ?? '');
            $description = task_description($_POST['description'] ?? '');
            $categoryId = validate_category($pdo, $_POST['category_id'] ?? null);
            $projectId = validate_project($pdo, $_POST['project_id'] ?? null);
            $priority = validate_priority($_POST['priority'] ?? 'Medium');
            $deadline = validate_deadline($_POST['deadline'] ?? null);
            $items = checklist_input($_POST['checklist'] ?? []);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO tasks (workspace_id, created_by, project_id, title, description, category_id, priority, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([active_workspace_id(), current_user_id(), $projectId, $title, $description, $categoryId, $priority, $deadline]);
            $taskId = (int)$pdo->lastInsertId();
            $insertItem = $pdo->prepare('INSERT INTO task_items (task_id, title, sort_order) VALUES (?, ?, ?)');
            foreach ($items as $order => $itemTitle) {
                $insertItem->execute([$taskId, $itemTitle, $order]);
            }
            sync_task_status($pdo, $taskId);
            sync_task_assignees($pdo, $taskId, $_POST['assignee_ids'] ?? []);
            $assigneeStmt = $pdo->prepare('SELECT user_id FROM task_assignees WHERE task_id = ?');
            $assigneeStmt->execute([$taskId]);
            foreach ($assigneeStmt->fetchAll(PDO::FETCH_COLUMN) as $assigneeId) {
                if ((int)$assigneeId !== current_user_id()) {
                    create_notification($pdo, (int)$assigneeId, 'Tugas baru ditugaskan', 'Kamu ditugaskan pada tugas: ' . $title, 'index.php?page=task-detail&id=' . $taskId);
                }
            }
            record_activity($pdo, $taskId, 'task_created', 'Task created');
            $pdo->commit();
            flash('success', 'Tugas berhasil dibuat.');
            redirect('task-detail', ['id' => $taskId]);

        case 'task-update':
            $taskId = valid_id($_POST['id'] ?? null);
            get_task($pdo, $taskId);
            $title = task_title($_POST['title'] ?? '');
            $description = task_description($_POST['description'] ?? '');
            $categoryId = validate_category($pdo, $_POST['category_id'] ?? null);
            $projectId = validate_project($pdo, $_POST['project_id'] ?? null);
            $priority = validate_priority($_POST['priority'] ?? 'Medium');
            $deadline = validate_deadline($_POST['deadline'] ?? null);
            $stmt = $pdo->prepare('UPDATE tasks SET title = ?, description = ?, category_id = ?, project_id = ?, priority = ?, deadline = ? WHERE id = ?');
            $stmt->execute([$title, $description, $categoryId, $projectId, $priority, $deadline, $taskId]);
            sync_task_assignees($pdo, $taskId, $_POST['assignee_ids'] ?? []);
            record_activity($pdo, $taskId, 'task_updated', 'Task details updated');
            flash('success', 'Perubahan tugas disimpan.');
            redirect('task-detail', ['id' => $taskId]);

        case 'task-delete':
            $taskId = valid_id($_POST['id'] ?? null);
            get_task($pdo, $taskId);
            record_activity($pdo, $taskId, 'task_deleted', 'Task permanently deleted');
            $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
            $stmt->execute([$taskId]);
            flash('success', 'Tugas dihapus permanen.');
            redirect('tasks');

        case 'task-archive':
        case 'task-restore':
            $taskId = valid_id($_POST['id'] ?? null);
            get_task($pdo, $taskId);
            $archive = $action === 'task-archive';
            $stmt = $pdo->prepare('UPDATE tasks SET archived_at = ' . ($archive ? 'CURRENT_TIMESTAMP' : 'NULL') . ' WHERE id = ?');
            $stmt->execute([$taskId]);
            record_activity($pdo, $taskId, $archive ? 'task_archived' : 'task_restored', $archive ? 'Task archived' : 'Task restored');
            flash('success', $archive ? 'Tugas dipindahkan ke arsip.' : 'Tugas dikembalikan.');
            redirect($archive ? 'tasks' : 'task-detail', $archive ? [] : ['id' => $taskId]);

        case 'note-store':
            $taskId = valid_id($_POST['task_id'] ?? null);
            get_task($pdo, $taskId);
            $content = trim((string)($_POST['content'] ?? ''));
            if ($content === '' || mb_strlen($content) > 4000) {
                throw new InvalidArgumentException('Catatan wajib diisi dan maksimal 4.000 karakter.');
            }
            $stmt = $pdo->prepare('INSERT INTO task_notes (task_id, content) VALUES (?, ?)');
            $stmt->execute([$taskId, $content]);
            record_activity($pdo, $taskId, 'note_added', 'Note added');
            flash('success', 'Catatan ditambahkan.');
            redirect('task-detail', ['id' => $taskId, 'section' => 'notes']);

        case 'note-update':
        case 'note-delete':
            $taskId = valid_id($_POST['task_id'] ?? null);
            $noteId = valid_id($_POST['note_id'] ?? null);
            get_task($pdo, $taskId);
            $stmt = $pdo->prepare('SELECT id FROM task_notes WHERE id = ? AND task_id = ?');
            $stmt->execute([$noteId, $taskId]);
            if (!$stmt->fetch()) {
                throw new InvalidArgumentException('Catatan tidak ditemukan pada tugas ini.');
            }
            if ($action === 'note-delete') {
                $pdo->prepare('DELETE FROM task_notes WHERE id = ?')->execute([$noteId]);
                record_activity($pdo, $taskId, 'note_deleted', 'Note deleted');
                flash('success', 'Catatan dihapus.');
            } else {
                $content = trim((string)($_POST['content'] ?? ''));
                if ($content === '' || mb_strlen($content) > 4000) {
                    throw new InvalidArgumentException('Catatan wajib diisi dan maksimal 4.000 karakter.');
                }
                $pdo->prepare('UPDATE task_notes SET content = ? WHERE id = ?')->execute([$content, $noteId]);
                record_activity($pdo, $taskId, 'note_updated', 'Note updated');
                flash('success', 'Catatan diperbarui.');
            }
            redirect('task-detail', ['id' => $taskId, 'section' => 'notes']);

        case 'category-store':
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 80) {
                throw new InvalidArgumentException('Nama kategori wajib diisi dan maksimal 80 karakter.');
            }
            $pdo->prepare('INSERT INTO categories (workspace_id, name) VALUES (?, ?)')->execute([active_workspace_id(), $name]);
            flash('success', 'Kategori ditambahkan.');
            redirect('categories');

        case 'category-update':
            $id = valid_id($_POST['id'] ?? null);
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 80) {
                throw new InvalidArgumentException('Nama kategori wajib diisi dan maksimal 80 karakter.');
            }
            $stmt = $pdo->prepare('UPDATE categories SET name = ? WHERE id = ? AND workspace_id = ?');
            $stmt->execute([$name, $id, active_workspace_id()]);
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND workspace_id = ?');
                $check->execute([$id, active_workspace_id()]);
                if (!$check->fetch()) {
                    throw new InvalidArgumentException('Kategori tidak ditemukan.');
                }
            }
            flash('success', 'Kategori diperbarui.');
            redirect('categories');

        case 'category-delete':
            $id = valid_id($_POST['id'] ?? null);
            $pdo->prepare('DELETE FROM categories WHERE id = ? AND workspace_id = ?')->execute([$id, active_workspace_id()]);
            flash('success', 'Kategori dihapus. Tugas terkait tetap tersimpan tanpa kategori.');
            redirect('categories');

        case 'project-store':
        case 'project-update':
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $status = (string)($_POST['status'] ?? 'Planning');
            $startDate = trim((string)($_POST['start_date'] ?? ''));
            $endDate = trim((string)($_POST['end_date'] ?? ''));
            if ($name === '' || mb_strlen($name) > 160 || mb_strlen($description) > 5000) {
                throw new InvalidArgumentException('Nama proyek wajib diisi dan maksimal 160 karakter. Deskripsi maksimal 5.000 karakter.');
            }
            if (!in_array($status, ['Planning', 'In Progress', 'Completed', 'Archived'], true)) {
                throw new InvalidArgumentException('Status proyek tidak valid.');
            }
            foreach ([&$startDate, &$endDate] as &$dateValue) {
                if ($dateValue === '') {
                    $dateValue = null;
                    continue;
                }
                $date = DateTime::createFromFormat('Y-m-d', $dateValue);
                if (!$date || $date->format('Y-m-d') !== $dateValue) {
                    throw new InvalidArgumentException('Tanggal proyek tidak valid.');
                }
            }
            unset($dateValue);
            if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
                throw new InvalidArgumentException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
            }
            if ($action === 'project-store') {
                $stmt = $pdo->prepare('INSERT INTO projects (workspace_id, name, description, status, start_date, end_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([active_workspace_id(), $name, $description, $status, $startDate, $endDate, current_user_id()]);
                $projectId = (int)$pdo->lastInsertId();
                flash('success', 'Proyek berhasil dibuat.');
            } else {
                $projectId = valid_id($_POST['id'] ?? null);
                $stmt = $pdo->prepare('UPDATE projects SET name = ?, description = ?, status = ?, start_date = ?, end_date = ? WHERE id = ? AND workspace_id = ?');
                $stmt->execute([$name, $description, $status, $startDate, $endDate, $projectId, active_workspace_id()]);
                if ($stmt->rowCount() === 0) {
                    $check = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND workspace_id = ?');
                    $check->execute([$projectId, active_workspace_id()]);
                    if (!$check->fetch()) {
                        throw new InvalidArgumentException('Proyek tidak ditemukan.');
                    }
                }
                flash('success', 'Proyek berhasil diperbarui.');
            }
            redirect('projects', ['id' => $projectId]);

        case 'project-delete':
            $projectId = valid_id($_POST['id'] ?? null);
            $stmt = $pdo->prepare('DELETE FROM projects WHERE id = ? AND workspace_id = ?');
            $stmt->execute([$projectId, active_workspace_id()]);
            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException('Proyek tidak ditemukan.');
            }
            flash('success', 'Proyek dihapus. Tugas terkait tetap tersimpan tanpa proyek.');
            redirect('projects');

        case 'task-comment-store':
        case 'task-comment-update':
        case 'task-comment-delete':
            $taskId = valid_id($_POST['task_id'] ?? null);
            get_task($pdo, $taskId);
            $commentId = valid_id($_POST['comment_id'] ?? null);
            if ($action === 'task-comment-store') {
                $content = trim((string)($_POST['content'] ?? ''));
                if ($content === '' || mb_strlen($content) > 4000) {
                    throw new InvalidArgumentException('Komentar wajib diisi dan maksimal 4.000 karakter.');
                }
                $stmt = $pdo->prepare('INSERT INTO task_comments (task_id, user_id, content) VALUES (?, ?, ?)');
                $stmt->execute([$taskId, current_user_id(), $content]);
                record_activity($pdo, $taskId, 'comment_added', 'Task comment added');
                flash('success', 'Komentar ditambahkan.');
            } else {
                $stmt = $pdo->prepare('SELECT id FROM task_comments WHERE id = ? AND task_id = ?');
                $stmt->execute([$commentId, $taskId]);
                if (!$stmt->fetch()) {
                    throw new InvalidArgumentException('Komentar tidak ditemukan.');
                }
                if ($action === 'task-comment-delete') {
                    $pdo->prepare('DELETE FROM task_comments WHERE id = ? AND task_id = ?')->execute([$commentId, $taskId]);
                    record_activity($pdo, $taskId, 'comment_deleted', 'Task comment deleted');
                    flash('success', 'Komentar dihapus.');
                } else {
                    $content = trim((string)($_POST['content'] ?? ''));
                    if ($content === '' || mb_strlen($content) > 4000) {
                        throw new InvalidArgumentException('Komentar wajib diisi dan maksimal 4.000 karakter.');
                    }
                    $pdo->prepare('UPDATE task_comments SET content = ? WHERE id = ? AND task_id = ?')->execute([$content, $commentId, $taskId]);
                    record_activity($pdo, $taskId, 'comment_updated', 'Task comment updated');
                    flash('success', 'Komentar diperbarui.');
                }
            }
            redirect('task-detail', ['id' => $taskId]);

        case 'daily-update-store':
            $workspaceId = active_workspace_id();
            require_workspace_membership($pdo, $workspaceId);
            $projectId = validate_project($pdo, $_POST['project_id'] ?? null);
            $content = trim((string)($_POST['content'] ?? ''));
            $updateDate = trim((string)($_POST['update_date'] ?? date('Y-m-d')));
            $date = DateTime::createFromFormat('Y-m-d', $updateDate);
            if ($content === '' || mb_strlen($content) > 5000) {
                throw new InvalidArgumentException('Daily update wajib diisi dan maksimal 5.000 karakter.');
            }
            if (!$date || $date->format('Y-m-d') !== $updateDate) {
                throw new InvalidArgumentException('Tanggal update tidak valid.');
            }
            $stmt = $pdo->prepare('INSERT INTO daily_updates (workspace_id, project_id, user_id, content, update_date) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$workspaceId, $projectId, current_user_id(), $content, $updateDate]);
            flash('success', 'Daily work update ditambahkan.');
            redirect('projects', $projectId ? ['id' => $projectId] : []);

        case 'project-resource-store':
        case 'project-resource-delete':
            $projectId = valid_id($_POST['project_id'] ?? null);
            $stmt = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND workspace_id = ?');
            $stmt->execute([$projectId, active_workspace_id()]);
            if (!$stmt->fetch()) {
                throw new InvalidArgumentException('Proyek tidak ditemukan.');
            }
            if ($action === 'project-resource-delete') {
                $resourceId = valid_id($_POST['resource_id'] ?? null);
                $delete = $pdo->prepare('DELETE FROM project_resources WHERE id = ? AND project_id = ?');
                $delete->execute([$resourceId, $projectId]);
                if ($delete->rowCount() === 0) {
                    throw new InvalidArgumentException('Resource tidak ditemukan.');
                }
                flash('success', 'Resource dihapus.');
            } else {
                $name = trim((string)($_POST['name'] ?? ''));
                $url = trim((string)($_POST['url'] ?? ''));
                if ($name === '' || mb_strlen($name) > 160 || mb_strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new InvalidArgumentException('Nama resource dan URL valid wajib diisi.');
                }
                $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?: '');
                if (!in_array($scheme, ['http', 'https'], true)) {
                    throw new InvalidArgumentException('Resource hanya boleh menggunakan URL http atau https.');
                }
                $stmt = $pdo->prepare('INSERT INTO project_resources (project_id, uploaded_by, name, url) VALUES (?, ?, ?, ?)');
                $stmt->execute([$projectId, current_user_id(), $name, $url]);
                flash('success', 'Resource ditambahkan.');
            }
            redirect('projects', ['id' => $projectId]);

        case 'notifications-read':
            $notificationId = (int)($_POST['notification_id'] ?? 0);
            if ($notificationId === 0) {
                $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND workspace_id = ?');
                $stmt->execute([current_user_id(), active_workspace_id()]);
            } else {
                $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
                $stmt->execute([$notificationId, current_user_id()]);
            }
            redirect('notifications');

        case 'workspace-switch':
            $workspaceId = valid_id($_POST['workspace_id'] ?? null);
            require_workspace_membership($pdo, $workspaceId);
            $_SESSION['active_workspace_id'] = $workspaceId;
            flash('success', 'Workspace aktif diperbarui.');
            redirect('dashboard');

        case 'workspace-create':
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            if ($name === '' || mb_strlen($name) > 120 || mb_strlen($description) > 2000) {
                throw new InvalidArgumentException('Nama team wajib diisi dan maksimal 120 karakter. Deskripsi maksimal 2.000 karakter.');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO workspaces (name, description, type, created_by) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $description, 'Team', current_user_id()]);
            $workspaceId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO workspace_members (workspace_id, user_id) VALUES (?, ?)')->execute([$workspaceId, current_user_id()]);
            $pdo->commit();
            $_SESSION['active_workspace_id'] = $workspaceId;
            flash('success', 'Team Workspace berhasil dibuat.');
            redirect('dashboard');

        case 'workspace-invite':
            $workspaceId = active_workspace_id();
            require_workspace_membership($pdo, $workspaceId);
            $workspaceStmt = $pdo->prepare('SELECT type FROM workspaces WHERE id = ?');
            $workspaceStmt->execute([$workspaceId]);
            if ($workspaceStmt->fetchColumn() !== 'Team') {
                throw new InvalidArgumentException('Undangan hanya dapat dibuat untuk Team Workspace.');
            }
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
                throw new InvalidArgumentException('Email undangan tidak valid.');
            }
            $memberStmt = $pdo->prepare('SELECT 1 FROM users u INNER JOIN workspace_members wm ON wm.user_id = u.id WHERE wm.workspace_id = ? AND u.email = ?');
            $memberStmt->execute([$workspaceId, $email]);
            if ($memberStmt->fetch()) {
                throw new InvalidArgumentException('Pengguna tersebut sudah menjadi anggota workspace.');
            }
            $existingStmt = $pdo->prepare('SELECT id, status FROM workspace_invitations WHERE workspace_id = ? AND email = ?');
            $existingStmt->execute([$workspaceId, $email]);
            $existing = $existingStmt->fetch();
            if ($existing && $existing['status'] === 'Pending') {
                throw new InvalidArgumentException('Undangan untuk email tersebut masih menunggu.');
            }
            $token = bin2hex(random_bytes(32));
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE workspace_invitations SET status = 'Pending', token = ?, invited_by = ?, created_at = CURRENT_TIMESTAMP, responded_at = NULL WHERE id = ?");
                $stmt->execute([$token, current_user_id(), (int)$existing['id']]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO workspace_invitations (workspace_id, email, invited_by, token) VALUES (?, ?, ?, ?)');
                $stmt->execute([$workspaceId, $email, current_user_id(), $token]);
            }
            flash('success', 'Undangan berhasil dibuat. Anggota dapat menerima undangan dari daftar Team Members.');
            redirect('team-members');

        case 'workspace-invite-cancel':
            $workspaceId = active_workspace_id();
            require_workspace_membership($pdo, $workspaceId);
            $invitationId = valid_id($_POST['invitation_id'] ?? null);
            $stmt = $pdo->prepare("UPDATE workspace_invitations SET status = 'Cancelled', responded_at = CURRENT_TIMESTAMP WHERE id = ? AND workspace_id = ? AND status = 'Pending'");
            $stmt->execute([$invitationId, $workspaceId]);
            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException('Undangan tidak ditemukan.');
            }
            flash('success', 'Undangan dibatalkan.');
            redirect('team-members');

        case 'workspace-invite-accept':
        case 'workspace-invite-join':
            $invitationId = valid_id($_POST['invitation_id'] ?? null);
            $stmt = $pdo->prepare('SELECT wi.*, u.id AS user_id FROM workspace_invitations wi INNER JOIN users u ON u.email = wi.email WHERE wi.id = ? AND wi.status = ? AND u.id = ?');
            $stmt->execute([$invitationId, 'Pending', current_user_id()]);
            $invitation = $stmt->fetch();
            if (!$invitation) {
                throw new InvalidArgumentException('Undangan tidak tersedia untuk akun ini.');
            }
            $pdo->beginTransaction();
            $insert = $pdo->prepare('INSERT IGNORE INTO workspace_members (workspace_id, user_id) VALUES (?, ?)');
            $insert->execute([(int)$invitation['workspace_id'], current_user_id()]);
            $update = $pdo->prepare("UPDATE workspace_invitations SET status = 'Accepted', responded_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'Pending'");
            $update->execute([$invitationId]);
            $pdo->commit();
            $_SESSION['active_workspace_id'] = (int)$invitation['workspace_id'];
            flash('success', 'Undangan diterima. Kamu sudah bergabung ke Team Workspace.');
            redirect('dashboard');

        case 'workspace-member-remove':
            $workspaceId = active_workspace_id();
            require_workspace_membership($pdo, $workspaceId);
            $workspaceStmt = $pdo->prepare('SELECT type FROM workspaces WHERE id = ?');
            $workspaceStmt->execute([$workspaceId]);
            if ($workspaceStmt->fetchColumn() !== 'Team') {
                throw new InvalidArgumentException('Operasi anggota hanya tersedia di Team Workspace.');
            }
            $memberId = valid_id($_POST['member_id'] ?? null);
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM workspace_members WHERE workspace_id = ?');
            $countStmt->execute([$workspaceId]);
            if ((int)$countStmt->fetchColumn() <= 1) {
                throw new InvalidArgumentException('Anggota terakhir tidak dapat dikeluarkan.');
            }
            $remove = $pdo->prepare('DELETE FROM workspace_members WHERE workspace_id = ? AND user_id = ?');
            $remove->execute([$workspaceId, $memberId]);
            if ($remove->rowCount() === 0) {
                throw new InvalidArgumentException('Anggota tidak ditemukan.');
            }
            flash('success', 'Anggota dikeluarkan dari Team Workspace.');
            redirect('team-members');

        case 'profile-update':
            $name = trim((string)($_POST['name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
                throw new InvalidArgumentException('Nama atau email tidak valid.');
            }
            $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
            $stmt->execute([$name, $email, current_user_id()]);
            flash('success', 'Profil berhasil diperbarui.');
            redirect('profile');

        case 'password-change':
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([current_user_id()]);
            $passwordHash = $stmt->fetchColumn();
            if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
                throw new InvalidArgumentException('Password saat ini tidak sesuai.');
            }
            if (mb_strlen($newPassword) < 8 || mb_strlen($newPassword) > 1024) {
                throw new InvalidArgumentException('Password baru harus memiliki 8 hingga 1024 karakter.');
            }
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), current_user_id()]);
            flash('success', 'Password berhasil diperbarui.');
            redirect('profile');

        case 'update-settings':
            $_SESSION['compact_mode'] = isset($_POST['compact_mode']);
            flash('success', 'Pengaturan tampilan disimpan.');
            redirect('settings');

        default:
            throw new InvalidArgumentException('Operasi tidak dikenal.');
    }
} catch (InvalidArgumentException|RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $exception->getMessage());
    $return = (string)($_POST['return_page'] ?? 'tasks');
    $allowedReturns = ['dashboard', 'tasks', 'task-create', 'task-edit', 'categories', 'settings', 'task-detail', 'archive', 'profile', 'workspace-create', 'team-members', 'projects', 'notifications'];
    http_response_code(303);
    redirect(in_array($return, $allowedReturns, true) ? $return : 'tasks', isset($_POST['id']) ? ['id' => (int)$_POST['id']] : []);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('TaskFlow action failed: ' . $exception->getMessage());
    flash('error', str_contains($exception->getMessage(), 'Duplicate') ? 'Email atau nama kategori tersebut sudah digunakan.' : 'Operasi gagal disimpan. Silakan coba kembali.');
    redirect((string)($_POST['return_page'] ?? 'tasks'), isset($_POST['id']) ? ['id' => (int)$_POST['id']] : []);
}
