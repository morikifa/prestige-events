<?php

$user = current_user();
$eventId = (int) input('id', 0);
$event = $eventId > 0 ? event_load($eventId) : null;

if ($eventId > 0 && !$event) {
    flash('error', 'Мероприятие не найдено.');
    redirect('manage/events');
}

if ($event && !event_can_manage($event, $user)) {
    abort(403, 'Мероприятие принадлежит другому организатору.');
}

$values = [
    'title' => $event['title'] ?? '',
    'slug' => $event['slug'] ?? '',
    'category_id' => $event['category_id'] ?? '',
    'venue_id' => $event['venue_id'] ?? '',
    'organizer_id' => $event['organizer_id'] ?? (int) $user['id'],
    'description' => $event['description'] ?? '',
    'capacity' => $event['capacity'] ?? '',
    'registration_opens_at' => dt_input($event['registration_opens_at'] ?? null),
    'registration_closes_at' => dt_input($event['registration_closes_at'] ?? null),
    'starts_at' => dt_input($event['starts_at'] ?? null),
    'ends_at' => dt_input($event['ends_at'] ?? null),
    'status' => $event['status'] ?? 'draft',
];

$errors = [];
$categories = db_all('SELECT id, name FROM categories ORDER BY name');
$venues = db_all('SELECT id, name FROM venues ORDER BY name');
$organizers = $user['role_code'] === 'admin' ? event_organizer_options() : [];

if (is_post()) {
    foreach ($values as $key => $default) {
        $values[$key] = (string) input($key, '');
    }

    if ($values['title'] === '') {
        $errors[] = 'Укажите название мероприятия.';
    }
    if ((int) $values['category_id'] <= 0) {
        $errors[] = 'Выберите категорию.';
    }
    if ($values['starts_at'] === '' || $values['ends_at'] === '') {
        $errors[] = 'Укажите дату и время начала и окончания.';
    }

    $startsAt = normalize_datetime($values['starts_at']);
    $endsAt = normalize_datetime($values['ends_at']);
    $regOpens = normalize_datetime($values['registration_opens_at']);
    $regCloses = normalize_datetime($values['registration_closes_at']);

    if ($startsAt && $endsAt && strtotime($endsAt) < strtotime($startsAt)) {
        $errors[] = 'Окончание не может быть раньше начала.';
    }
    if ($regOpens && $regCloses && strtotime($regCloses) < strtotime($regOpens)) {
        $errors[] = 'Закрытие регистрации не может быть раньше открытия.';
    }
    if ($values['capacity'] !== '' && (int) $values['capacity'] <= 0) {
        $errors[] = 'Вместимость должна быть положительным числом.';
    }
    if (!array_key_exists($values['status'], event_status_labels())) {
        $errors[] = 'Неизвестный статус мероприятия.';
    }

    $organizerId = (int) $values['organizer_id'];
    if ($user['role_code'] !== 'admin' || $organizerId <= 0) {
        $organizerId = (int) $user['id'];
    }
    $organizerRow = db_one(
        "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? AND r.code IN ('organizer', 'admin')",
        [$organizerId]
    );
    if (!$organizerRow) {
        $errors[] = 'Выберите организатора из списка.';
    }

    if (!$errors) {
        $slug = event_unique_slug($values['slug'] !== '' ? $values['slug'] : $values['title'], $event ? (int) $event['id'] : null);
        $params = [
            $values['title'],
            $slug,
            (int) $values['category_id'],
            $values['venue_id'] !== '' ? (int) $values['venue_id'] : null,
            $organizerId,
            $values['description'],
            $values['capacity'] !== '' ? (int) $values['capacity'] : null,
            $regOpens,
            $regCloses,
            $startsAt,
            $endsAt,
            $values['status'],
        ];

        if ($event) {
            $params[] = (int) $event['id'];
            db_exec(
                'UPDATE events SET title = ?, slug = ?, category_id = ?, venue_id = ?, organizer_id = ?, description = ?,
                        capacity = ?, registration_opens_at = ?, registration_closes_at = ?, starts_at = ?, ends_at = ?,
                        status = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?',
                $params
            );
            log_action('event_updated', 'event', (int) $event['id'], ['title' => $values['title']]);
            flash('success', 'Мероприятие сохранено.');
            redirect('manage/events');
        }

        db_exec(
            'INSERT INTO events (title, slug, category_id, venue_id, organizer_id, description, capacity,
                    registration_opens_at, registration_closes_at, starts_at, ends_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $params
        );
        $newId = db_last_id();
        log_action('event_created', 'event', $newId, ['title' => $values['title']]);
        flash('success', 'Мероприятие создано. Добавьте пункты программы.');
        redirect('manage/program', ['id' => $newId]);
    }
}

render('manage-event-edit', [
    'title' => $event ? 'Редактирование мероприятия' : 'Новое мероприятие',
    'navActive' => 'manage',
    'event' => $event,
    'values' => $values,
    'errors' => $errors,
    'categories' => $categories,
    'venues' => $venues,
    'organizers' => $organizers,
]);
