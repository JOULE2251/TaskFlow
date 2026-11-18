<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$legacyTasks = (int)$pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
$legacyCategories = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$legacyOwnerEmail = strtolower(trim((string)(getenv('TASKFLOW_LEGACY_OWNER_EMAIL') ?: '')));
$hasLegacyData = $legacyTasks > 0 || $legacyCategories > 0;

if ($hasLegacyData && !filter_var($legacyOwnerEmail, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Set TASKFLOW_LEGACY_OWNER_EMAIL to the designated owner before migrating existing TaskFlow data.\n");
    exit(1);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS workspaces (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    type ENUM('Personal', 'Team') NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_workspaces_creator (created_by),
    CONSTRAINT fk_workspaces_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS workspace_members (
    workspace_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (workspace_id, user_id),
    KEY idx_workspace_members_user (user_id, workspace_id),
    CONSTRAINT fk_workspace_members_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_workspace_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS workspace_invitations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workspace_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    invited_by BIGINT UNSIGNED NULL,
    status ENUM('Pending', 'Accepted', 'Cancelled') NOT NULL DEFAULT 'Pending',
    token CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_workspace_invitation_email (workspace_id, email),
    UNIQUE KEY uq_workspace_invitation_token (token),
    KEY idx_workspace_invitation_status (workspace_id, status),
    CONSTRAINT fk_workspace_invitation_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_workspace_invitation_inviter FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(80) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS projects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workspace_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    description TEXT NULL,
    status ENUM('Planning', 'In Progress', 'Completed', 'Archived') NOT NULL DEFAULT 'Planning',
    start_date DATE NULL,
    end_date DATE NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_workspace_name (workspace_id, name),
    KEY idx_projects_workspace_status (workspace_id, status),
    KEY idx_projects_dates (start_date, end_date),
    CONSTRAINT fk_projects_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_projects_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS task_assignees (
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id),
    KEY idx_task_assignees_user (user_id, task_id),
    CONSTRAINT fk_task_assignees_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_task_assignees_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS task_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_task_comments_task_created (task_id, created_at),
    CONSTRAINT fk_task_comments_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_task_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS daily_updates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workspace_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    content TEXT NOT NULL,
    update_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_daily_updates_workspace_date (workspace_id, update_date),
    KEY idx_daily_updates_project_date (project_id, update_date),
    CONSTRAINT fk_daily_updates_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_daily_updates_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_daily_updates_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS project_resources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_resources_project (project_id, created_at),
    CONSTRAINT fk_project_resources_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_resources_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NULL,
    title VARCHAR(160) NOT NULL,
    message VARCHAR(500) NOT NULL,
    link VARCHAR(500) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user_read_created (user_id, is_read, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notifications_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB");

$columnExists = static function (string $table, string $column) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
};
$indexExists = static function (string $table, string $index) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
    $stmt->execute([$table, $index]);
    return (bool)$stmt->fetchColumn();
};
$foreignKeyExists = static function (string $table, string $constraint) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = ?');
    $stmt->execute([$table, $constraint, 'FOREIGN KEY']);
    return (bool)$stmt->fetchColumn();
};

foreach ([
    ['categories', 'workspace_id', 'BIGINT UNSIGNED NULL'],
    ['tasks', 'workspace_id', 'BIGINT UNSIGNED NULL'],
    ['tasks', 'created_by', 'BIGINT UNSIGNED NULL'],
    ['tasks', 'project_id', 'BIGINT UNSIGNED NULL'],
    ['task_notes', 'user_id', 'BIGINT UNSIGNED NULL'],
] as [$table, $column, $definition]) {
    if (!$columnExists($table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

if ($indexExists('categories', 'uq_categories_name')) {
    $pdo->exec('ALTER TABLE categories DROP INDEX uq_categories_name');
}
if (!$indexExists('categories', 'uq_categories_workspace_name')) {
    $pdo->exec('ALTER TABLE categories ADD UNIQUE KEY uq_categories_workspace_name (workspace_id, name)');
}
if (!$indexExists('categories', 'idx_categories_workspace')) {
    $pdo->exec('ALTER TABLE categories ADD KEY idx_categories_workspace (workspace_id)');
}
if (!$indexExists('tasks', 'idx_tasks_workspace')) {
    $pdo->exec('ALTER TABLE tasks ADD KEY idx_tasks_workspace (workspace_id)');
}
if (!$indexExists('tasks', 'idx_tasks_created_by')) {
    $pdo->exec('ALTER TABLE tasks ADD KEY idx_tasks_created_by (created_by)');
}
if (!$indexExists('tasks', 'idx_tasks_project')) {
    $pdo->exec('ALTER TABLE tasks ADD KEY idx_tasks_project (project_id)');
}
if (!$indexExists('task_notes', 'idx_notes_user')) {
    $pdo->exec('ALTER TABLE task_notes ADD KEY idx_notes_user (user_id)');
}
if (!$foreignKeyExists('categories', 'fk_categories_workspace')) {
    $pdo->exec('ALTER TABLE categories ADD CONSTRAINT fk_categories_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces (id) ON DELETE CASCADE ON UPDATE CASCADE');
}
if (!$foreignKeyExists('tasks', 'fk_tasks_workspace')) {
    $pdo->exec('ALTER TABLE tasks ADD CONSTRAINT fk_tasks_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces (id) ON DELETE CASCADE ON UPDATE CASCADE');
}
if (!$foreignKeyExists('tasks', 'fk_tasks_creator')) {
    $pdo->exec('ALTER TABLE tasks ADD CONSTRAINT fk_tasks_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
}
if (!$foreignKeyExists('tasks', 'fk_tasks_project')) {
    $pdo->exec('ALTER TABLE tasks ADD CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE');
}
if (!$foreignKeyExists('task_notes', 'fk_notes_user')) {
    $pdo->exec('ALTER TABLE task_notes ADD CONSTRAINT fk_notes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
}

if ($hasLegacyData) {
    $setting = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $setting->execute(['legacy_owner_email']);
    $storedOwnerEmail = $setting->fetchColumn();
    if ($storedOwnerEmail !== false && strtolower((string)$storedOwnerEmail) !== $legacyOwnerEmail) {
        fwrite(STDERR, "TASKFLOW_LEGACY_OWNER_EMAIL does not match the owner already recorded for legacy data.\n");
        exit(1);
    }
    if ($storedOwnerEmail === false) {
        $saveSetting = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)');
        $saveSetting->execute(['legacy_owner_email', $legacyOwnerEmail]);
    }
}

fwrite(STDOUT, "TaskFlow workspace schema is ready. Existing tasks and categories remain intact and unassigned until the designated owner registers.\n");
