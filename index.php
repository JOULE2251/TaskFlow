<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/functions.php';

$page = (string)($_GET['page'] ?? 'dashboard');
$routes = [
    'dashboard' => 'dashboard.php',
    'tasks' => 'tasks.php',
    'calendar' => 'calendar.php',
    'task-detail' => 'task-detail.php',
    'task-create' => 'task-create.php',
    'task-edit' => 'task-edit.php',
    'completed' => 'completed.php',
    'archive' => 'archive.php',
    'categories' => 'categories.php',
    'settings' => 'settings.php',
];
if (!isset($routes[$page])) {
    http_response_code(404);
    $page = 'dashboard';
    $pageError = 'Halaman tidak ditemukan.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !str_starts_with((string)($_GET['page'] ?? ''), 'api-')) {
    require __DIR__ . '/actions/handle.php';
}

$pageTitle = match ($page) {
    'tasks' => 'All Tasks',
    'calendar' => 'Calendar',
    'task-detail' => 'Task Details',
    'task-create' => 'New Task',
    'task-edit' => 'Edit Task',
    'completed' => 'Completed',
    'archive' => 'Archive',
    'categories' => 'Categories',
    'settings' => 'Settings',
    default => 'Dashboard',
};
try {
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/includes/sidebar.php';
    echo '<main class="main-content"><div class="topbar"><button class="icon-button mobile-menu" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-label="Buka navigasi"><i class="bi bi-list"></i></button><div class="breadcrumb-line"><span>TaskFlow</span><i class="bi bi-chevron-right"></i><strong>' . e($pageTitle) . '</strong></div><div class="topbar-right"><span class="today-label">' . e((new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('l, d M Y')) . '</span><a class="button button-primary button-small" href="index.php?page=task-create"><i class="bi bi-plus-lg"></i> Add New Task</a></div></div><div class="page-content">';
    if (isset($pageError)) {
        echo '<div class="alert-inline">' . e($pageError) . '</div>';
    }
    require __DIR__ . '/pages/' . $routes[$page];
    echo '</div></main>';
    require __DIR__ . '/includes/footer.php';
} catch (Throwable $exception) {
    error_log('TaskFlow page error: ' . $exception->getMessage());
    http_response_code(500);
    echo '<main class="fatal-error"><h1>Terjadi kesalahan</h1><p>Permintaan belum dapat diproses. Silakan coba kembali.</p><a href="index.php">Kembali ke dashboard</a></main>';
    require __DIR__ . '/includes/footer.php';
}
