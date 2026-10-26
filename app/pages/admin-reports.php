<?php

require PRESTIGE_APP . '/lib/export.php';

$user = current_user();

if (input('export') !== '') {
    $rows = [];
    foreach (events_report_rows($user) as $row) {
        $rows[] = [
            $row['title'],
            $row['category_name'],
            dt($row['starts_at']),
            event_status_label($row['status']),
            $row['capacity'] !== null ? (int) $row['capacity'] : '',
            (int) $row['registered'],
            (int) $row['attended'],
            (int) $row['cancelled'],
            (int) $row['waiting'],
        ];
    }

    $header = ['Мероприятие', 'Категория', 'Начало', 'Статус', 'Мест', 'Записано', 'Посетило', 'Отменено', 'Лист ожидания'];
    $name = 'prestige-report-' . date('Y-m-d');
    log_action('report_exported', 'platform', null, ['format' => input('export'), 'rows' => count($rows)]);

    if (input('export') === 'xlsx') {
        export_xlsx($name . '.xlsx', 'Отчёт', $header, $rows);
    }

    export_csv($name . '.csv', $header, $rows);
}

$stats = event_stats($user);
$byCategory = events_by_category_report();
$events = events_report_rows($user);

$totals = [
    'users' => (int) db_val('SELECT COUNT(*) FROM users', [], 0),
    'venues' => (int) db_val('SELECT COUNT(*) FROM venues', [], 0),
    'waitlist' => (int) db_val("SELECT COUNT(*) FROM waitlist WHERE status IN ('waiting', 'invited')", [], 0),
    'capacity' => (int) db_val('SELECT COALESCE(SUM(capacity), 0) FROM events', [], 0),
];

render('admin-reports', [
    'title' => 'Сводные отчёты',
    'navActive' => 'admin',
    'stats' => $stats,
    'byCategory' => $byCategory,
    'events' => $events,
    'totals' => $totals,
]);
