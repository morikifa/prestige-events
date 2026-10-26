<?php

$user = current_user();

if (is_post()) {
    $action = (string) input('action');

    if ($action === 'read_all') {
        db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [(int) $user['id']]);
        flash('success', 'Все уведомления отмечены как прочитанные.');
    }

    if ($action === 'read') {
        $id = (int) input('notification_id');
        db_exec('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$id, (int) $user['id']]);
    }

    redirect('account/notifications');
}

$notifications = db_all(
    'SELECT n.*, e.slug AS event_slug, e.title AS event_title
     FROM notifications n
     LEFT JOIN events e ON e.id = n.event_id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC',
    [(int) $user['id']]
);

render('account-notifications', [
    'title' => 'Уведомления',
    'navActive' => 'account',
    'notifications' => $notifications,
]);
