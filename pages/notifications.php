<?php
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? AND (workspace_id = ? OR workspace_id IS NULL) ORDER BY is_read ASC, created_at DESC');
$stmt->execute([current_user_id(), active_workspace_id()]);
$notifications = $stmt->fetchAll();
?>
<section class="page-heading"><div><p class="eyebrow">ACTIVITY</p><h1>Notifications</h1><p class="muted"><?= count(array_filter($notifications, static fn(array $item): bool => (int)$item['is_read'] === 0)) ?> belum dibaca</p></div><?php if ($notifications): ?><form method="post" action="index.php?page=notifications"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="notifications-read"><input type="hidden" name="notification_id" value="0"><button class="button button-secondary button-small" type="submit"><i class="bi bi-check2-all"></i> Tandai dibaca</button></form><?php endif; ?></section>
<section class="panel notifications-panel">
<?php if (!$notifications): ?>
<div class="empty-state"><span class="empty-icon"><i class="bi bi-bell"></i></span><h3>Tidak ada notifikasi</h3><p>Aktivitas yang perlu perhatianmu akan tampil di sini.</p></div>
<?php else: ?>
<div class="notification-list"><?php foreach ($notifications as $notification): ?><article class="notification-row<?= (int)$notification['is_read'] ? '' : ' unread' ?>"><span class="notification-icon"><i class="bi <?= (int)$notification['is_read'] ? 'bi-bell' : 'bi-bell-fill' ?>"></i></span><div class="notification-body"><?php if ($notification['link']): ?><a href="<?= e($notification['link']) ?>"><strong><?= e($notification['title']) ?></strong></a><?php else: ?><strong><?= e($notification['title']) ?></strong><?php endif; ?><p><?= e($notification['message']) ?></p><small><?= e(date('d M Y, H:i', strtotime($notification['created_at']))) ?></small></div><?php if (!(int)$notification['is_read']): ?><form method="post" action="index.php?page=notifications"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="notifications-read"><input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>"><button class="icon-button" type="submit" aria-label="Tandai dibaca"><i class="bi bi-check2"></i></button></form><?php endif; ?></article><?php endforeach; ?></div>
<?php endif; ?>
</section>
