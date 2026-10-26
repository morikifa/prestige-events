<?php

$filters = [
    'category_id' => (int) input('category', 0),
    'venue_id' => (int) input('venue', 0),
    'date' => (string) input('date', ''),
    'search' => (string) input('search', ''),
];

$events = events_for_afisha($filters);
$categories = db_all('SELECT id, name FROM categories ORDER BY name');
$venues = db_all('SELECT id, name FROM venues ORDER BY name');

$totals = [
    'events' => (int) db_val("SELECT COUNT(*) FROM events WHERE status = 'published'", [], 0),
    'participants' => (int) db_val("SELECT COUNT(DISTINCT user_id) FROM registrations WHERE status <> 'cancelled'", [], 0),
    'categories' => (int) db_val('SELECT COUNT(*) FROM categories', [], 0),
];

$isFiltered = $filters['category_id'] || $filters['venue_id'] || $filters['date'] !== '' || $filters['search'] !== '';

render('home', [
    'title' => 'Афиша мероприятий',
    'navActive' => 'home',
    'events' => $events,
    'categories' => $categories,
    'venues' => $venues,
    'filters' => $filters,
    'isFiltered' => $isFiltered,
    'totals' => $totals,
]);
