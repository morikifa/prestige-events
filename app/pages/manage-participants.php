<?php

$user = current_user();
$eventId = (int) input('id', 0);
$event = event_load($eventId);

if (!$event) {
    flash('error', 'Выберите мероприятие.');
    redirect('manage/events');
}
if (!event_can_manage($event, $user)) {
    abort(403, 'Мероприятие принадлежит другому организатору.');
}

if (is_post()) {
    $action = (string) input('action');

    if ($action === 'attendance') {
        $token = mb_strtolower((string) input('token'));
        $registrationId = (int) input('registration_id');

        if ($registrationId > 0) {
            $registration = db_one('SELECT * FROM registrations WHERE id = ? AND event_id = ?', [$registrationId, $eventId]);
            if ($registration) {
                $token = $registration['qr_token'];
            }
        }

        $result = event_mark_attended($token, $user);
        flash(isset($result['error']) ? 'error' : 'success', $result['error'] ?? 'Посещение отмечено.');
    }

    if ($action === 'cancel_registration' || $action === 'confirm_registration') {
        $registrationId = (int) input('registration_id');
        $registration = db_one('SELECT * FROM registrations WHERE id = ? AND event_id = ?', [$registrationId, $eventId]);
        if ($registration) {
            if ($action === 'cancel_registration') {
                db_exec("UPDATE registrations SET status = 'cancelled', cancelled_at = CURRENT_TIMESTAMP WHERE id = ?", [$registrationId]);
                log_action('registration_cancelled', 'registration', $registrationId, ['event_id' => $eventId]);
                flash('success', 'Запись отменена.');
            } else {
                db_exec("UPDATE registrations SET status = 'confirmed', cancelled_at = NULL WHERE id = ?", [$registrationId]);
                log_action('registration_confirmed', 'registration', $registrationId, ['event_id' => $eventId]);
                flash('success', 'Запись подтверждена.');
            }
        } else {
            flash('error', 'Запись не найдена.');
        }
    }

    if ($action === 'remove_registration') {
        $registrationId = (int) input('registration_id');
        db_exec('DELETE FROM registrations WHERE id = ? AND event_id = ?', [$registrationId, $eventId]);
        log_action('registration_deleted', 'registration', $registrationId, ['event_id' => $eventId]);
        flash('success', 'Запись удалена из списка.');
    }

    if ($action === 'waitlist_status') {
        $waitlistId = (int) input('waitlist_id');
        $status = (string) input('status');
        if (in_array($status, ['waiting', 'invited', 'registered', 'rejected'], true)) {
            db_exec('UPDATE waitlist SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND event_id = ?', [$status, $waitlistId, $eventId]);
            flash('success', 'Статус листа ожидания обновлён.');
        }
    }

    if ($action === 'notify') {
        $subject = (string) input('subject');
        $message = (string) input('message');

        if ($subject === '' || $message === '') {
            flash('error', 'Заполните тему и текст уведомления.');
        } else {
            $recipients = db_all(
                "SELECT DISTINCT user_id FROM registrations WHERE event_id = ? AND status IN ('registered', 'confirmed', 'attended')
                 UNION SELECT DISTINCT user_id FROM waitlist WHERE event_id = ? AND status IN ('waiting', 'invited')",
                [$eventId, $eventId]
            );

            foreach ($recipients as $recipient) {
                notify((int) $recipient['user_id'], $eventId, 'organizer_message', $subject, $message);
            }
            log_action('notifications_sent', 'event', $eventId, ['count' => count($recipients)]);
            flash('success', 'Уведомление отправлено: ' . count($recipients) . ' получателей.');
        }
    }

    redirect('manage/participants', ['id' => $eventId]);
}

$registrations = db_all(
    'SELECT r.*, u.last_name, u.first_name, u.middle_name, u.email, u.phone
     FROM registrations r
     JOIN users u ON u.id = r.user_id
     WHERE r.event_id = ?
     ORDER BY r.registered_at',
    [$eventId]
);

$waitlistRows = db_all(
    'SELECT w.*, u.last_name, u.first_name, u.middle_name, u.email
     FROM waitlist w
     JOIN users u ON u.id = w.user_id
     WHERE w.event_id = ?
     ORDER BY w.position',
    [$eventId]
);

$seats = event_seats($event);

render('manage-participants', [
    'title' => 'Участники мероприятия',
    'navActive' => 'manage',
    'event' => $event,
    'registrations' => $registrations,
    'waitlistRows' => $waitlistRows,
    'seats' => $seats,
]);
