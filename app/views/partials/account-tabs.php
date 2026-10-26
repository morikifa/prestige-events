<?php
$accountUser = current_user();
$unreadCount = $accountUser ? (int) db_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [(int) $accountUser['id']], 0) : 0;
?>
<nav class="tabs" aria-label="Разделы кабинета">
    <a class="<?= $active === 'account' ? 'is-active' : '' ?>" href="<?= e(url('account')) ?>">Профиль</a>
    <a class="<?= $active === 'events' ? 'is-active' : '' ?>" href="<?= e(url('account/events')) ?>">Мои мероприятия</a>
    <a class="<?= $active === 'notifications' ? 'is-active' : '' ?>" href="<?= e(url('account/notifications')) ?>">Уведомления<?= $unreadCount > 0 ? ' · ' . $unreadCount : '' ?></a>
    <?php if (user_has_role('organizer')): ?>
        <a href="<?= e(url('manage/events')) ?>">Управление</a>
    <?php endif; ?>
    <?php if (user_has_role('admin')): ?>
        <a href="<?= e(url('admin/users')) ?>">Администрирование</a>
    <?php endif; ?>
</nav>
