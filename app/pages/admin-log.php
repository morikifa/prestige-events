<?php

$entityFilter = (string) input('entity', '');
$actionFilter = (string) input('action_name', '');
$search = (string) input('search', '');
$page = max(1, (int) input('page', 1));
$perPage = 30;

$sql = 'SELECT l.*, u.last_name, u.first_name, u.email
        FROM action_log l
        LEFT JOIN users u ON u.id = l.user_id
        WHERE 1 = 1';
$params = [];

if ($entityFilter !== '') {
    $sql .= ' AND l.entity = ?';
    $params[] = $entityFilter;
}
if ($actionFilter !== '') {
    $sql .= ' AND l.action = ?';
    $params[] = $actionFilter;
}
if ($search !== '') {
    $sql .= ' AND (u.last_name LIKE ? OR u.email LIKE ? OR l.details LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$total = (int) db_val(preg_replace('/^SELECT .*? FROM/s', 'SELECT COUNT(*) FROM', $sql), $params, 0);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows = db_all($sql . ' ORDER BY l.created_at DESC, l.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);

$entities = array_column(db_all('SELECT DISTINCT entity FROM action_log ORDER BY entity'), 'entity');
$actions = array_column(db_all('SELECT DISTINCT action FROM action_log ORDER BY action'), 'action');

render('admin-log', [
    'title' => 'Журнал действий',
    'navActive' => 'admin',
    'rows' => $rows,
    'entities' => $entities,
    'actions' => $actions,
    'entityFilter' => $entityFilter,
    'actionFilter' => $actionFilter,
    'search' => $search,
    'page' => $page,
    'pages' => $pages,
    'total' => $total,
]);
