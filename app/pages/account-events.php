<?php

$user = current_user();

if (is_post()) {
    $action = (string) input('action');

    if ($action === 'cancel') {
        $registrationId = (int) input('registration_id');
        $registration = db_one('SELECT * FROM registrations WHERE id = ? AND user_id = ?', [$registrationId, (int) $user['id']]);
        if (!$registration) {
            flash('error', 'Запись не найдена.');
        } else {
            $result = event_cancel((int) $registration['event_id'], (int) $user['id']);
            flash(isset($result['error']) ? 'error' : 'success', $result['error'] ?? 'Запись отменена.');
        }
    }

    if ($action === 'leave_waitlist') {
        $entryId = (int) input('waitlist_id');
        $entry = db_one('SELECT * FROM waitlist WHERE id = ? AND user_id = ?', [$entryId, (int) $user['id']]);
        if (!$entry) {
            flash('error', 'Запись в листе ожидания не найдена.');
        } else {
            $result = waitlist_leave((int) $entry['event_id'], (int) $user['id']);
            flash(isset($result['error']) ? 'error' : 'success', $result['error'] ?? 'Вы вышли из листа ожидания.');
        }
    }

    redirect('account/events');
}

$registrations = db_all(
    'SELECT r.*, e.title, e.slug, e.starts_at, e.ends_at, e.status AS event_status,
            c.name AS category_name, v.name AS venue_name
     FROM registrations r
     JOIN events e ON e.id = r.event_id
     JOIN categories c ON c.id = e.category_id
     LEFT JOIN venues v ON v.id = e.venue_id
     WHERE r.user_id = ?
     ORDER BY e.starts_at DESC',
    [(int) $user['id']]
);

$waitlist = db_all(
    'SELECT w.*, e.title, e.slug, e.starts_at
     FROM waitlist w
     JOIN events e ON e.id = w.event_id
     WHERE w.user_id = ? AND w.status IN (?, ?)
     ORDER BY e.starts_at',
    [(int) $user['id'], 'waiting', 'invited']
);

render('account-events', [
    'title' => 'Мои мероприятия',
    'navActive' => 'account',
    'registrations' => $registrations,
    'waitlist' => $waitlist,
]);
