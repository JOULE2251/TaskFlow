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
    $task = get_task($pdo, valid_id($_GET['id'] ?? null), false);
    echo json_encode(['ok' => true, 'task' => ['id' => (int)$task['id'], 'status' => $task['status']] + task_progress($pdo, (int)$task['id'])], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    $code = $exception instanceof InvalidArgumentException ? 404 : ($exception instanceof RuntimeException && http_response_code() === 401 ? 401 : 500);
    http_response_code($code);
    if ($code === 500) {
        error_log('TaskFlow task progress API failed: ' . $exception->getMessage());
    }
    echo json_encode(['ok' => false, 'error' => $code === 401 ? $exception->getMessage() : 'Progress tidak tersedia.']);
}
