<?php
declare(strict_types=1);

$host = getenv('TASKFLOW_DB_HOST') ?: '127.0.0.1';
$database = getenv('TASKFLOW_DB_NAME') ?: 'taskflow_db';
$username = getenv('TASKFLOW_DB_USER') ?: 'root';
$password = getenv('TASKFLOW_DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $exception) {
    error_log('TaskFlow database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Database tidak dapat dihubungkan. Periksa konfigurasi dan impor database TaskFlow.');
}
