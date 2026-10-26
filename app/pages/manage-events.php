<?php

$user = current_user();

if (is_post()) {
    $action = (string) input('action');
    $eventId = (int) input('event_id');
    $event = event_load($eventId);

    if (!$event || !event_can_manage($event, $user)) {
        flash('error', 'Мероприятие не найдено.');
        redirect('manage/events');
    }

    if ($action === 'status') {
        $status = (string) input('status');
        if (!array_key_exists($status, event_status_labels())) {
            flash('error', 'Неизвестный статус.');
        } else {
            db_exec('UPDATE events SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$status, $eventId]);
            log_action('event_status_changed', 'event', $eventId, ['status' => $status]);
            flash('success', 'Статус мероприятия изменён: ' . event_status_label($status) . '.');
        }
    }

    if ($action === 'delete') {
        db_exec('DELETE FROM events WHERE id = ?', [$eventId]);
        log_action('event_deleted', 'event', $eventId, ['title' => $event['title']]);
        flash('success', 'Мероприятие удалено.');
    }

    redirect('manage/events');
}

$statusFilter = (string) input('status', '');
$events = events_for_manager($user, ['status' => $statusFilter]);

render('manage-events', [
    'title' => 'Мероприятия',
    'navActive' => 'manage',
    'events' => $events,
    'statusFilter' => $statusFilter,
    'user' => $user,
]);
