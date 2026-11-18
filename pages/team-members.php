<?php
$workspace = $GLOBALS['activeWorkspace'];
if ($workspace['type'] !== 'Team') {
    echo '<section class="panel empty-state list-empty"><span class="empty-icon"><i class="bi bi-person-workspace"></i></span><h3>Halaman khusus Team Workspace</h3><p>Pindah ke Team Workspace untuk melihat anggota dan mengirim undangan.</p></section>';
} else {
    $workspaceId = (int)$workspace['id'];
    require_workspace_membership($pdo, $workspaceId);

    $memberStmt = $pdo->prepare('SELECT u.id, u.name, u.email, wm.joined_at FROM workspace_members wm INNER JOIN users u ON u.id = wm.user_id WHERE wm.workspace_id = ? ORDER BY wm.joined_at ASC, u.name ASC');
    $memberStmt->execute([$workspaceId]);
    $members = $memberStmt->fetchAll();

    $invitationStmt = $pdo->prepare('SELECT wi.id, wi.email, wi.status, wi.created_at, u.name AS invited_by_name FROM workspace_invitations wi LEFT JOIN users u ON u.id = wi.invited_by WHERE wi.workspace_id = ? AND wi.status = ? ORDER BY wi.created_at DESC');
    $invitationStmt->execute([$workspaceId, 'Pending']);
    $pendingInvitations = $invitationStmt->fetchAll();

    $emailStmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $emailStmt->execute([current_user_id()]);
    $currentEmail = strtolower((string)$emailStmt->fetchColumn());
    $myInvitationStmt = $pdo->prepare("SELECT id, workspace_id FROM workspace_invitations WHERE email = ? AND status = 'Pending' AND workspace_id = ?");
    $myInvitationStmt->execute([$currentEmail, $workspaceId]);
    $myInvitation = $myInvitationStmt->fetch();
?>
<section class="page-heading">
    <div><p class="eyebrow">COLLABORATION</p><h1>Team Members</h1><p class="muted"><?= count($members) ?> anggota di <?= e($workspace['name']) ?></p></div>
</section>
<?php if ($myInvitation): ?>
<div class="invitation-banner">
    <span><i class="bi bi-envelope-check"></i> Kamu memiliki undangan untuk bergabung ke workspace ini.</span>
    <form method="post" action="index.php?page=team-members">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="workspace-invite-accept">
        <input type="hidden" name="invitation_id" value="<?= (int)$myInvitation['id'] ?>">
        <button class="button button-primary button-small" type="submit">Terima Undangan</button>
    </form>
</div>
<?php endif; ?>
<div class="team-members-layout">
    <section class="panel">
        <div class="panel-heading"><div><p class="eyebrow">MEMBERS</p><h2>Daftar Anggota</h2></div></div>
        <div class="member-list">
            <?php foreach ($members as $member): ?>
            <article class="member-row">
                <span class="member-avatar"><?= e(strtoupper(substr($member['name'], 0, 1))) ?></span>
                <div class="member-info"><strong><?= e($member['name']) ?></strong><small><?= e($member['email']) ?></small></div>
                <small class="member-date">Bergabung <?= e(date('d M Y', strtotime($member['joined_at']))) ?></small>
                <?php if ((int)$member['id'] !== current_user_id()): ?>
                <form method="post" action="index.php?page=team-members" data-confirm="Keluarkan anggota ini dari Team Workspace?">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="workspace-member-remove">
                    <input type="hidden" name="member_id" value="<?= (int)$member['id'] ?>">
                    <button class="icon-button danger-hover" type="submit" aria-label="Hapus anggota"><i class="bi bi-person-x"></i></button>
                </form>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="panel team-invite-panel">
        <div class="panel-heading"><div><p class="eyebrow">INVITE</p><h2>Undang Anggota</h2></div></div>
        <form class="invite-form" method="post" action="index.php?page=team-members">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="workspace-invite">
            <input type="hidden" name="return_page" value="team-members">
            <label for="invite-email">Email pengguna</label>
            <input id="invite-email" name="email" type="email" maxlength="190" required placeholder="nama@email.com">
            <button class="button button-primary" type="submit"><i class="bi bi-envelope-plus"></i> Buat Undangan</button>
        </form>
        <div class="pending-invitations">
            <h3>Menunggu penerimaan</h3>
            <?php if (!$pendingInvitations): ?>
            <p class="compact-empty">Belum ada undangan aktif.</p>
            <?php else: foreach ($pendingInvitations as $invitation): ?>
            <article class="invitation-row">
                <span><i class="bi bi-envelope"></i><strong><?= e($invitation['email']) ?></strong><small>Dibuat <?= e(date('d M Y, H:i', strtotime($invitation['created_at']))) ?><?= $invitation['invited_by_name'] ? ' oleh ' . e($invitation['invited_by_name']) : '' ?></small></span>
                <form method="post" action="index.php?page=team-members" data-confirm="Batalkan undangan ini?">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="workspace-invite-cancel">
                    <input type="hidden" name="invitation_id" value="<?= (int)$invitation['id'] ?>">
                    <button class="text-button danger-text" type="submit">Batalkan</button>
                </form>
            </article>
            <?php endforeach; endif; ?>
        </div>
    </section>
</div>
<?php } ?>
