<?php

$user = current_user();
$slug = (string) input('slug', '');

$event = db_one(
    'SELECT e.*, c.name AS category_name, v.name AS venue_name, v.address AS venue_address, v.capacity AS venue_capacity,
            u.last_name AS organizer_last, u.first_name AS organizer_first, u.middle_name AS organizer_middle, u.email AS organizer_email
     FROM events e
     JOIN categories c ON c.id = e.category_id
     LEFT JOIN venues v ON v.id = e.venue_id
     JOIN users u ON u.id = e.organizer_id
     WHERE e.slug = ?',
    [$slug]
);

if (!$event) {
    abort(404, 'Мероприятие не найдено.');
}

$canManage = $user && event_can_manage($event, $user);

if ($event['status'] === 'draft' && !$canManage) {
    abort(404, 'Мероприятие ещё не опубликовано.');
}

if (is_post()) {
    require_login();
    $action = (string) input('action', '');

    if ($action === 'register') {
        $result = event_register((int) $event['id'], (int) $user['id']);
        if (isset($result['error'])) {
            flash('error', $result['error']);
        } elseif (!empty($result['waitlist'])) {
            flash('success', 'Мест нет. Вы добавлены в лист ожидания под номером ' . (int) $result['position'] . '.');
        } else {
            flash('success', 'Вы записаны. Билет с QR-кодом доступен в личном кабинете.');
        }
    }

    if ($action === 'cancel') {
        $result = event_cancel((int) $event['id'], (int) $user['id']);
        flash(isset($result['error']) ? 'error' : 'success', $result['error'] ?? 'Запись отменена.');
    }

    if ($action === 'leave_waitlist') {
        $result = waitlist_leave((int) $event['id'], (int) $user['id']);
        flash(isset($result['error']) ? 'error' : 'success', $result['error'] ?? 'Вы вышли из листа ожидания.');
    }

    redirect('event', ['slug' => $event['slug']]);
}

$seats = event_seats($event);
$program = event_program((int) $event['id']);
$registration = $user ? event_active_registration((int) $event['id'], (int) $user['id']) : null;
$waitlistEntry = $user ? event_waitlist_entry((int) $event['id'], (int) $user['id']) : null;
$registrationOpen = event_registration_open($event);

render('event', [
    'title' => $event['title'],
    'navActive' => 'home',
    'event' => $event,
    'seats' => $seats,
    'program' => $program,
    'registration' => $registration,
    'waitlistEntry' => $waitlistEntry,
    'registrationOpen' => $registrationOpen,
    'canManage' => $canManage,
]);
