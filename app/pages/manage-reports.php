<?php

$user = current_user();
$stats = event_stats($user);
$events = events_report_rows($user);

$totals = ['waiting' => 0, 'registered' => 0, 'attended' => 0];
foreach ($events as $event) {
    $totals['waiting'] += (int) $event['waiting'];
    $totals['registered'] += (int) $event['registered'];
    $totals['attended'] += (int) $event['attended'];
}

render('manage-reports', [
    'title' => 'Отчёты по мероприятиям',
    'navActive' => 'manage',
    'stats' => $stats,
    'events' => $events,
    'totals' => $totals,
]);
