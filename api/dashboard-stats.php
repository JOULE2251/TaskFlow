<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');
$stats = $pdo->query("SELECT
    SUM(archived_at IS NULL) AS total,
    SUM(archived_at IS NULL AND status = 'Pending') AS pending,
    SUM(archived_at IS NULL AND status = 'In Progress') AS in_progress,
    SUM(archived_at IS NULL AND status = 'Completed') AS completed
    FROM tasks")->fetch();
echo json_encode(['ok' => true, 'stats' => array_map('intval', $stats)], JSON_THROW_ON_ERROR);
