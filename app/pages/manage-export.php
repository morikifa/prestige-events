<?php

require PRESTIGE_APP . '/lib/export.php';

$user = current_user();
$eventId = (int) input('id', 0);
$format = input('format') === 'xlsx' ? 'xlsx' : 'csv';

$sql = "SELECT e.title AS event_title, c.name AS category_name, e.starts_at,
               u.last_name, u.first_name, u.middle_name, u.email, u.phone,
               r.status, r.qr_token, r.registered_at, r.attended_at
        FROM registrations r
        JOIN events e ON e.id = r.event_id
        JOIN categories c ON c.id = e.category_id
        JOIN users u ON u.id = r.user_id
        WHERE 1 = 1";
$params = [];

if ($eventId > 0) {
    $event = event_load($eventId);
    if (!$event || !event_can_manage($event, $user)) {
        abort(403, 'Мероприятие недоступно.');
    }
    $sql .= ' AND r.event_id = ?';
    $params[] = $eventId;
}

if ($user['role_code'] !== 'admin') {
    $sql .= ' AND e.organizer_id = ?';
    $params[] = (int) $user['id'];
}

$sql .= ' ORDER BY e.starts_at DESC, u.last_name, u.first_name';

$rows = [];
foreach (db_all($sql, $params) as $row) {
    $rows[] = [
        $row['event_title'],
        $row['category_name'],
        dt($row['starts_at']),
        $row['last_name'],
        $row['first_name'],
        $row['middle_name'] ?? '',
        $row['email'],
        $row['phone'] ?? '',
        registration_status_label($row['status']),
        $row['qr_token'],
        dt($row['registered_at']),
        $row['attended_at'] ? dt($row['attended_at']) : '',
    ];
}

$header = [
    'Мероприятие', 'Категория', 'Начало', 'Фамилия', 'Имя', 'Отчество',
    'Электронная почта', 'Телефон', 'Статус', 'Токен', 'Дата записи', 'Посещение',
];

$name = $eventId > 0 ? 'participants-event-' . $eventId : 'participants-all';
log_action('report_exported', 'event', $eventId > 0 ? $eventId : null, ['format' => $format, 'rows' => count($rows)]);

if ($format === 'xlsx') {
    export_xlsx($name . '.xlsx', 'Участники', $header, $rows);
}

export_csv($name . '.csv', $header, $rows);
