<?php
declare(strict_types=1);

function require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        redirect('login');
    }
}

function require_api_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new RuntimeException('Sesi login telah berakhir.');
    }
}

function current_user_id(): int
{
    return valid_id($_SESSION['user_id'] ?? null);
}

function active_workspace_id(): int
{
    return valid_id($_SESSION['active_workspace_id'] ?? null);
}

function initialize_workspace_context(PDO $pdo): void
{
    $userId = current_user_id();
    $userStmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ?');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    if (!$user) {
        $_SESSION = [];
        session_regenerate_id(true);
        redirect('login');
    }

    $workspaceStmt = $pdo->prepare('SELECT w.id, w.name, w.type FROM workspaces w INNER JOIN workspace_members wm ON wm.workspace_id = w.id WHERE wm.user_id = ? ORDER BY w.type = \'Personal\' DESC, w.name ASC');
    $workspaceStmt->execute([$userId]);
    $workspaces = $workspaceStmt->fetchAll();
    $workspaceIds = array_map(static fn(array $workspace): int => (int)$workspace['id'], $workspaces);
    $activeId = (int)($_SESSION['active_workspace_id'] ?? 0);
    if (!in_array($activeId, $workspaceIds, true)) {
        $personalWorkspace = null;
        foreach ($workspaces as $workspace) {
            if ($workspace['type'] === 'Personal') {
                $personalWorkspace = $workspace;
                break;
            }
        }
        if (!$personalWorkspace) {
            throw new RuntimeException('Personal workspace pengguna tidak ditemukan.');
        }
        $activeId = (int)$personalWorkspace['id'];
        $_SESSION['active_workspace_id'] = $activeId;
    }

    $activeWorkspace = null;
    foreach ($workspaces as $workspace) {
        if ((int)$workspace['id'] === $activeId) {
            $activeWorkspace = $workspace;
            break;
        }
    }
    $GLOBALS['currentUser'] = $user;
    $GLOBALS['userWorkspaces'] = $workspaces;
    $GLOBALS['activeWorkspace'] = $activeWorkspace;
}

function require_workspace_membership(PDO $pdo, int $workspaceId, ?int $userId = null): void
{
    $userId ??= current_user_id();
    $stmt = $pdo->prepare('SELECT 1 FROM workspace_members WHERE workspace_id = ? AND user_id = ?');
    $stmt->execute([$workspaceId, $userId]);
    if (!$stmt->fetchColumn()) {
        throw new InvalidArgumentException('Workspace tidak ditemukan atau tidak dapat diakses.');
    }
}
