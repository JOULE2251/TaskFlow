CREATE DATABASE IF NOT EXISTS taskflow_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE taskflow_db;

SET time_zone = '+07:00';

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS workspaces (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS workspace_members (
    workspace_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (workspace_id, user_id),
    KEY idx_workspace_members_user (user_id, workspace_id),
    CONSTRAINT fk_workspace_members_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_workspace_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS workspace_invitations (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(80) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workspace_id BIGINT UNSIGNED NULL,
    name VARCHAR(80) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_workspace_name (workspace_id, name),
    KEY idx_categories_workspace (workspace_id),
    CONSTRAINT fk_categories_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS projects (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tasks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workspace_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    category_id BIGINT UNSIGNED NULL,
    priority ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL DEFAULT 'Medium',
    status ENUM('Pending', 'In Progress', 'Completed') NOT NULL DEFAULT 'Pending',
    deadline DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_tasks_workspace (workspace_id),
    KEY idx_tasks_created_by (created_by),
    KEY idx_tasks_status_archive (status, archived_at),
    KEY idx_tasks_deadline (deadline),
    KEY idx_tasks_category (category_id),
    KEY idx_tasks_created (created_at),
    KEY idx_tasks_project (project_id),
    CONSTRAINT fk_tasks_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tasks_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tasks_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS task_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(240) NOT NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_items_task_order (task_id, sort_order),
    CONSTRAINT fk_items_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS task_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notes_task_created (task_id, created_at),
    CONSTRAINT fk_notes_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS task_assignees (
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id),
    KEY idx_task_assignees_user (user_id, task_id),
    CONSTRAINT fk_task_assignees_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_task_assignees_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS task_comments (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS daily_updates (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_resources (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(48) NOT NULL,
    description VARCHAR(240) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_task_created (task_id, created_at),
    CONSTRAINT fk_activity_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
