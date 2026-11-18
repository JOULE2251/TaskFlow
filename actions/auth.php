<?php
declare(strict_types=1);

try {
    verify_csrf();
    $action = $page;
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ($action === 'login') {
        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['auth_error'] = 'Email atau password tidak valid.';
            redirect('login');
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        unset($_SESSION['active_workspace_id']);
        redirect('dashboard');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $_SESSION['auth_error'] = 'Nama atau email tidak valid.';
        redirect('register');
    }
    if (mb_strlen($password) < 8 || mb_strlen($password) > 1024) {
        $_SESSION['auth_error'] = 'Password harus memiliki 8 hingga 1024 karakter.';
        redirect('register');
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int)$pdo->lastInsertId();

    $workspaceName = 'Personal Workspace';
    $stmt = $pdo->prepare('INSERT INTO workspaces (name, type, created_by) VALUES (?, ?, ?)');
    $stmt->execute([$workspaceName, 'Personal', $userId]);
    $workspaceId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO workspace_members (workspace_id, user_id) VALUES (?, ?)')->execute([$workspaceId, $userId]);

    $setting = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $setting->execute(['legacy_owner_email']);
    $legacyOwnerEmail = $setting->fetchColumn();
    if (is_string($legacyOwnerEmail) && hash_equals(strtolower($legacyOwnerEmail), $email)) {
        $pdo->prepare('UPDATE categories SET workspace_id = ? WHERE workspace_id IS NULL')->execute([$workspaceId]);
        $pdo->prepare('UPDATE tasks SET workspace_id = ?, created_by = ? WHERE workspace_id IS NULL')->execute([$workspaceId, $userId]);
    }

    $categoryInsert = $pdo->prepare('INSERT IGNORE INTO categories (workspace_id, name) VALUES (?, ?)');
    foreach (['Web Development', 'SEO', 'Research', 'Administration', 'Personal', 'Other'] as $categoryName) {
        $categoryInsert->execute([$workspaceId, $categoryName]);
    }
    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['active_workspace_id'] = $workspaceId;
    redirect('dashboard');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('TaskFlow authentication failed: ' . $exception->getMessage());
    $_SESSION['auth_error'] = str_contains($exception->getMessage(), 'Duplicate')
        ? 'Email tersebut sudah terdaftar.'
        : 'Akun belum dapat diproses. Silakan coba kembali.';
    redirect($page === 'login' ? 'login' : 'register');
} catch (InvalidArgumentException|RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['auth_error'] = $exception->getMessage();
    redirect($page === 'login' ? 'login' : 'register');
}
