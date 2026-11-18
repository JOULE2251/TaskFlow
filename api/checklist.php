<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.php';
start_secure_session();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
try {
    require_api_login();
    initialize_workspace_context($pdo);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Metode tidak diizinkan.');
    }
    verify_csrf();
    $taskId = valid_id($_POST['task_id'] ?? null);
    $task = get_task($pdo, $taskId, false);
    $action = (string)($_POST['action'] ?? '');
    $pdo->beginTransaction();
    if ($action === 'add') {
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 240) {
            throw new InvalidArgumentException('Checklist wajib diisi dan maksimal 240 karakter.');
        }
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM task_items WHERE task_id = ?');
        $stmt->execute([$taskId]);
        $order = (int)$stmt->fetchColumn();
        $pdo->prepare('INSERT INTO task_items (task_id, title, sort_order) VALUES (?, ?, ?)')->execute([$taskId, $title, $order]);
        record_activity($pdo, $taskId, 'checklist_added', 'Checklist added: ' . $title);
    } elseif ($action === 'toggle') {
        $itemId = valid_id($_POST['item_id'] ?? null);
        $stmt = $pdo->prepare('SELECT id, title, is_completed FROM task_items WHERE id = ? AND task_id = ? FOR UPDATE');
        $stmt->execute([$itemId, $taskId]);
        $item = $stmt->fetch();
        if (!$item) {
            throw new InvalidArgumentException('Checklist tidak ditemukan pada tugas ini.');
        }
        $completed = !(bool)$item['is_completed'];
        $pdo->prepare('UPDATE task_items SET is_completed = ?, completed_at = ? WHERE id = ?')->execute([$completed ? 1 : 0, $completed ? date('Y-m-d H:i:s') : null, $itemId]);
        record_activity($pdo, $taskId, $completed ? 'checklist_completed' : 'checklist_unchecked', $completed ? 'Checklist completed: ' . $item['title'] : 'Checklist unchecked: ' . $item['title']);
    } elseif ($action === 'edit') {
        $itemId = valid_id($_POST['item_id'] ?? null);
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 240) {
            throw new InvalidArgumentException('Nama checklist wajib diisi dan maksimal 240 karakter.');
        }
        $stmt = $pdo->prepare('UPDATE task_items SET title = ? WHERE id = ? AND task_id = ?');
        $stmt->execute([$title, $itemId, $taskId]);
        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT id FROM task_items WHERE id = ? AND task_id = ?');
            $check->execute([$itemId, $taskId]);
            if (!$check->fetch()) {
                throw new InvalidArgumentException('Checklist tidak ditemukan pada tugas ini.');
            }
        }
        record_activity($pdo, $taskId, 'checklist_updated', 'Checklist updated: ' . $title);
    } elseif ($action === 'delete') {
        $itemId = valid_id($_POST['item_id'] ?? null);
        $stmt = $pdo->prepare('SELECT title FROM task_items WHERE id = ? AND task_id = ?');
        $stmt->execute([$itemId, $taskId]);
        $item = $stmt->fetch();
        if (!$item) {
            throw new InvalidArgumentException('Checklist tidak ditemukan pada tugas ini.');
        }
        $pdo->prepare('DELETE FROM task_items WHERE id = ? AND task_id = ?')->execute([$itemId, $taskId]);
        record_activity($pdo, $taskId, 'checklist_deleted', 'Checklist deleted: ' . $item['title']);
    } elseif ($action === 'reorder') {
        $ids = $_POST['item_ids'] ?? [];
        if (!is_array($ids) || count($ids) > 200) {
            throw new InvalidArgumentException('Urutan checklist tidak valid.');
        }
        $stmt = $pdo->prepare('SELECT id FROM task_items WHERE task_id = ? ORDER BY sort_order, id');
        $stmt->execute([$taskId]);
        $existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $submitted = array_map(static fn($id) => valid_id($id), $ids);
        sort($existing);
        $sortedSubmitted = $submitted;
        sort($sortedSubmitted);
        if ($existing !== $sortedSubmitted) {
            throw new InvalidArgumentException('Daftar urutan checklist tidak cocok.');
        }
        $update = $pdo->prepare('UPDATE task_items SET sort_order = ? WHERE id = ? AND task_id = ?');
        foreach ($submitted as $order => $itemId) {
            $update->execute([$order, $itemId, $taskId]);
        }
    } else {
        throw new InvalidArgumentException('Operasi checklist tidak dikenal.');
    }
    $state = sync_task_status($pdo, $taskId);
    $pdo->commit();
    $stmt = $pdo->prepare('SELECT id, title, is_completed, sort_order FROM task_items WHERE task_id = ? ORDER BY sort_order, id');
    $stmt->execute([$taskId]);
    echo json_encode(['ok' => true, 'state' => $state, 'items' => $stmt->fetchAll()], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $code = $exception instanceof InvalidArgumentException ? 422 : ($exception instanceof RuntimeException ? (in_array(http_response_code(), [401, 405], true) ? http_response_code() : 403) : 500);
    http_response_code($code);
    if ($code === 500) {
        error_log('TaskFlow checklist API failed: ' . $exception->getMessage());
    }
    echo json_encode(['ok' => false, 'error' => $code === 500 ? 'Checklist gagal diperbarui.' : $exception->getMessage()]);
}
