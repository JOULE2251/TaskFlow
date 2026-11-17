<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $task = get_task($pdo, valid_id($_GET['id'] ?? null), false);
    echo json_encode(['ok' => true, 'task' => ['id' => (int)$task['id'], 'status' => $task['status']] + task_progress($pdo, (int)$task['id'])], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code($exception instanceof InvalidArgumentException ? 404 : 500);
    echo json_encode(['ok' => false, 'error' => 'Progress tidak tersedia.']);
}
