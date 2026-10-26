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

$errors = [];
$editId = (int) input('edit', 0);
$editItem = $editId > 0 ? db_one('SELECT * FROM event_program WHERE id = ? AND event_id = ?', [$editId, $eventId]) : null;

$values = [
    'title' => $editItem['title'] ?? '',
    'speaker' => $editItem['speaker'] ?? '',
    'place' => $editItem['place'] ?? '',
    'starts_at' => dt_input($editItem['starts_at'] ?? null),
    'ends_at' => dt_input($editItem['ends_at'] ?? null),
    'sort_order' => $editItem['sort_order'] ?? 0,
];

if (is_post()) {
    $action = (string) input('action');

    if ($action === 'delete') {
        $itemId = (int) input('program_id');
        db_exec('DELETE FROM event_program WHERE id = ? AND event_id = ?', [$itemId, $eventId]);
        log_action('program_deleted', 'event_program', $itemId, ['event_id' => $eventId]);
        flash('success', 'Пункт программы удалён.');
        redirect('manage/program', ['id' => $eventId]);
    }

    foreach (array_keys($values) as $key) {
        $values[$key] = (string) input($key, '');
    }

    $programId = (int) input('program_id', 0);
    $startsAt = normalize_datetime($values['starts_at']);
    $endsAt = normalize_datetime($values['ends_at']);

    if ($values['title'] === '') {
        $errors[] = 'Укажите название пункта программы.';
    }
    if (!$startsAt || !$endsAt) {
        $errors[] = 'Укажите время начала и окончания пункта.';
    } elseif (strtotime($endsAt) < strtotime($startsAt)) {
        $errors[] = 'Окончание пункта не может быть раньше начала.';
    }

    if (!$errors) {
        $params = [
            $values['title'],
            $values['speaker'] !== '' ? $values['speaker'] : null,
            $values['place'] !== '' ? $values['place'] : null,
            $startsAt,
            $endsAt,
            (int) $values['sort_order'],
        ];

        if ($programId > 0) {
            $params[] = $programId;
            $params[] = $eventId;
            db_exec(
                'UPDATE event_program SET title = ?, speaker = ?, place = ?, starts_at = ?, ends_at = ?, sort_order = ?
                 WHERE id = ? AND event_id = ?',
                $params
            );
            log_action('program_updated', 'event_program', $programId, ['event_id' => $eventId]);
            flash('success', 'Пункт программы обновлён.');
        } else {
            $params[] = $eventId;
            db_exec(
                'INSERT INTO event_program (title, speaker, place, starts_at, ends_at, sort_order, event_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                $params
            );
            log_action('program_created', 'event_program', db_last_id(), ['event_id' => $eventId]);
            flash('success', 'Пункт программы добавлен.');
        }

        redirect('manage/program', ['id' => $eventId]);
    }
}

$program = event_program($eventId);

render('manage-program', [
    'title' => 'Программа мероприятия',
    'navActive' => 'manage',
    'event' => $event,
    'program' => $program,
    'values' => $values,
    'errors' => $errors,
    'editItem' => $editItem,
]);
