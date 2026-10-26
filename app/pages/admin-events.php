<?php

$user = current_user();

if (is_post()) {
    $action = (string) input('action');
    $eventId = (int) input('event_id');

    if ($action === 'status') {
        $status = (string) input('status');
        if (array_key_exists($status, event_status_labels())) {
            db_exec('UPDATE events SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$status, $eventId]);
            log_action('event_status_changed', 'event', $eventId, ['status' => $status]);
            flash('success', 'Статус изменён: ' . event_status_label($status) . '.');
        } else {
            flash('error', 'Неизвестный статус.');
        }
    }

    if ($action === 'delete') {
        db_exec('DELETE FROM events WHERE id = ?', [$eventId]);
        log_action('event_deleted', 'event', $eventId);
        flash('success', 'Мероприятие удалено.');
    }

    redirect('admin/events');
}

$statusFilter = (string) input('status', '');
$search = (string) input('search', '');

$sql = "SELECT e.*, c.name AS category_name, v.name AS venue_name,
               u.last_name AS organizer_last, u.first_name AS organizer_first,
               (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status IN ('registered', 'confirmed', 'attended')) AS taken
        FROM events e
        JOIN categories c ON c.id = e.category_id
        LEFT JOIN venues v ON v.id = e.venue_id
        JOIN users u ON u.id = e.organizer_id
        WHERE 1 = 1";
$params = [];

if ($statusFilter !== '') {
    $sql .= ' AND e.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= ' AND e.title LIKE ?';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY e.starts_at DESC';

$events = db_all($sql, $params);

render('admin-events', [
    'title' => 'Модерация мероприятий',
    'navActive' => 'admin',
    'events' => $events,
    'statusFilter' => $statusFilter,
    'search' => $search,
]);
