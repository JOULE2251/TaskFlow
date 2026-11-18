<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.php';
start_secure_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
try {
    require_api_login();
    initialize_workspace_context($pdo);
    $stmt = $pdo->prepare("SELECT
        SUM(archived_at IS NULL) AS total,
        SUM(archived_at IS NULL AND status = 'Pending') AS pending,
        SUM(archived_at IS NULL AND status = 'In Progress') AS in_progress,
        SUM(archived_at IS NULL AND status = 'Completed') AS completed
        FROM tasks WHERE workspace_id = ?");
    $stmt->execute([active_workspace_id()]);
    $stats = $stmt->fetch();
    echo json_encode(['ok' => true, 'stats' => array_map('intval', $stats)], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    $status = http_response_code();
    if ($status < 400) {
        $status = 500;
        http_response_code($status);
    }
    if ($status === 500) {
        error_log('TaskFlow dashboard stats API failed: ' . $exception->getMessage());
    }
    echo json_encode(['ok' => false, 'error' => $status === 401 ? $exception->getMessage() : 'Statistik tidak tersedia.'], JSON_THROW_ON_ERROR);
}
