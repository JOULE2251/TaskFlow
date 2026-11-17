<?php
try {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    switch ($action) {
        case 'task-store':
            $title = task_title($_POST['title'] ?? '');
            $description = task_description($_POST['description'] ?? '');
            $categoryId = validate_category($pdo, $_POST['category_id'] ?? null);
            $priority = validate_priority($_POST['priority'] ?? 'Medium');
            $deadline = validate_deadline($_POST['deadline'] ?? null);
            $items = checklist_input($_POST['checklist'] ?? []);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO tasks (title, description, category_id, priority, deadline) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$title, $description, $categoryId, $priority, $deadline]);
            $taskId = (int)$pdo->lastInsertId();
            $insertItem = $pdo->prepare('INSERT INTO task_items (task_id, title, sort_order) VALUES (?, ?, ?)');
            foreach ($items as $order => $itemTitle) {
                $insertItem->execute([$taskId, $itemTitle, $order]);
            }
            sync_task_status($pdo, $taskId);
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
            $priority = validate_priority($_POST['priority'] ?? 'Medium');
            $deadline = validate_deadline($_POST['deadline'] ?? null);
            $stmt = $pdo->prepare('UPDATE tasks SET title = ?, description = ?, category_id = ?, priority = ?, deadline = ? WHERE id = ?');
            $stmt->execute([$title, $description, $categoryId, $priority, $deadline, $taskId]);
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
            $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
            flash('success', 'Kategori ditambahkan.');
            redirect('categories');

        case 'category-update':
            $id = valid_id($_POST['id'] ?? null);
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 80) {
                throw new InvalidArgumentException('Nama kategori wajib diisi dan maksimal 80 karakter.');
            }
            $stmt = $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?');
            $stmt->execute([$name, $id]);
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
                $check->execute([$id]);
                if (!$check->fetch()) {
                    throw new InvalidArgumentException('Kategori tidak ditemukan.');
                }
            }
            flash('success', 'Kategori diperbarui.');
            redirect('categories');

        case 'category-delete':
            $id = valid_id($_POST['id'] ?? null);
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash('success', 'Kategori dihapus. Tugas terkait tetap tersimpan tanpa kategori.');
            redirect('categories');

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
    $allowedReturns = ['dashboard', 'tasks', 'task-create', 'task-edit', 'categories', 'settings', 'task-detail', 'archive'];
    http_response_code(303);
    redirect(in_array($return, $allowedReturns, true) ? $return : 'tasks', isset($_POST['id']) ? ['id' => (int)$_POST['id']] : []);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('TaskFlow action failed: ' . $exception->getMessage());
    flash('error', str_contains($exception->getMessage(), 'Duplicate') ? 'Nama kategori tersebut sudah digunakan.' : 'Operasi gagal disimpan. Silakan coba kembali.');
    redirect((string)($_POST['return_page'] ?? 'tasks'), isset($_POST['id']) ? ['id' => (int)$_POST['id']] : []);
}
