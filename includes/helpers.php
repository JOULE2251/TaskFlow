<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        throw new RuntimeException('Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.');
    }
}

function redirect(string $page, array $query = []): never
{
    $query = ['page' => $page] + $query;
    header('Location: index.php?' . http_build_query($query));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function record_activity(PDO $pdo, int $taskId, string $action, string $description): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_logs (task_id, action, description) VALUES (?, ?, ?)');
    $stmt->execute([$taskId, $action, mb_substr($description, 0, 240)]);
}

function sync_task_status(PDO $pdo, int $taskId): array
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(is_completed), 0) AS completed FROM task_items WHERE task_id = ?');
    $stmt->execute([$taskId]);
    $counts = $stmt->fetch();
    $total = (int)$counts['total'];
    $completed = (int)$counts['completed'];
    $progress = $total === 0 ? 0 : (int)round($completed / $total * 100);
    $status = $total === 0 || $completed === 0 ? 'Pending' : ($completed === $total ? 'Completed' : 'In Progress');
    $stmt = $pdo->prepare('UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$status, $taskId]);
    return ['total' => $total, 'completed' => $completed, 'progress' => $progress, 'status' => $status];
}

function task_progress(PDO $pdo, int $taskId): array
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(is_completed), 0) AS completed FROM task_items WHERE task_id = ?');
    $stmt->execute([$taskId]);
    $counts = $stmt->fetch();
    $total = (int)$counts['total'];
    $completed = (int)$counts['completed'];
    return ['total' => $total, 'completed' => $completed, 'progress' => $total === 0 ? 0 : (int)round($completed / $total * 100)];
}

function valid_id(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        throw new InvalidArgumentException('ID tidak valid.');
    }
    return $id;
}

function task_deadline(array $task): array
{
    if (empty($task['deadline'])) {
        return ['label' => 'Tanpa deadline', 'tone' => 'neutral', 'overdue' => false];
    }
    if (($task['status'] ?? '') === 'Completed') {
        return ['label' => date('d M Y', strtotime($task['deadline'])), 'tone' => 'muted', 'overdue' => false];
    }
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));
    $deadline = new DateTimeImmutable(substr($task['deadline'], 0, 10), new DateTimeZone('Asia/Jakarta'));
    $days = (int)$today->diff($deadline)->format('%r%a');
    if ($days < 0) {
        return ['label' => 'Terlambat · ' . date('d M Y', strtotime($task['deadline'])), 'tone' => 'danger', 'overdue' => true];
    }
    if ($days <= 3) {
        return ['label' => ($days === 0 ? 'Hari ini' : "{$days} hari lagi") . ' · ' . date('d M Y', strtotime($task['deadline'])), 'tone' => 'warning', 'overdue' => false];
    }
    return ['label' => date('d M Y', strtotime($task['deadline'])), 'tone' => 'success', 'overdue' => false];
}

function get_task(PDO $pdo, int $id, bool $includeArchived = true): array
{
    $sql = 'SELECT t.*, c.name AS category_name, p.name AS project_name FROM tasks t LEFT JOIN categories c ON c.id = t.category_id LEFT JOIN projects p ON p.id = t.project_id WHERE t.id = ? AND t.workspace_id = ?';
    $params = [$id, active_workspace_id()];
    if (!$includeArchived) {
        $sql .= ' AND t.archived_at IS NULL';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $task = $stmt->fetch();
    if (!$task) {
        throw new InvalidArgumentException('Tugas tidak ditemukan.');
    }
    return $task;
}

function task_rows(PDO $pdo, string $where, array $params = [], string $order = 't.created_at DESC'): array
{
    $sql = "SELECT t.*, c.name AS category_name,
        COALESCE(i.item_total, 0) AS item_total,
        COALESCE(i.item_completed, 0) AS item_completed,
        CASE WHEN COALESCE(i.item_total, 0) = 0 THEN 0 ELSE ROUND(i.item_completed * 100 / i.item_total) END AS progress
        FROM tasks t LEFT JOIN categories c ON c.id = t.category_id
        LEFT JOIN (
            SELECT task_id, COUNT(*) AS item_total, COALESCE(SUM(is_completed), 0) AS item_completed
            FROM task_items GROUP BY task_id
        ) i ON i.task_id = t.id
        WHERE {$where} AND t.workspace_id = ?
        ORDER BY {$order}";
    $stmt = $pdo->prepare($sql);
    $params[] = active_workspace_id();
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function status_class(string $status): string
{
    return match ($status) {
        'Completed' => 'status-completed',
        'In Progress' => 'status-progress',
        default => 'status-pending',
    };
}

function sync_task_assignees(PDO $pdo, int $taskId, mixed $values): void
{
    $ids = [];
    if (is_array($values)) {
        foreach ($values as $value) {
            $id = valid_id($value);
            $stmt = $pdo->prepare('SELECT 1 FROM workspace_members WHERE workspace_id = ? AND user_id = ?');
            $stmt->execute([active_workspace_id(), $id]);
            if (!$stmt->fetchColumn()) {
                throw new InvalidArgumentException('Assignee harus anggota workspace aktif.');
            }
            $ids[$id] = $id;
        }
    }
    $pdo->prepare('DELETE FROM task_assignees WHERE task_id = ?')->execute([$taskId]);
    $insert = $pdo->prepare('INSERT INTO task_assignees (task_id, user_id) VALUES (?, ?)');
    foreach ($ids as $id) {
        $insert->execute([$taskId, $id]);
    }
}

function create_notification(PDO $pdo, int $userId, string $title, string $message, ?string $link = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, workspace_id, title, message, link) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, active_workspace_id(), mb_substr($title, 0, 160), mb_substr($message, 0, 500), $link ? mb_substr($link, 0, 500) : null]);
}
