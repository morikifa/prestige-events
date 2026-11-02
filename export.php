<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['organizer', 'admin']);

$mode = $_GET['mode'] ?? '';
$format = $_GET['format'] ?? 'csv';

if ($mode === 'summary') {
    if ($user['role_code'] === 'admin') {
        $events = $pdo->query('SELECT * FROM events ORDER BY starts_at ASC')->fetchAll();
    } else {
        $stmt = $pdo->prepare('SELECT * FROM events WHERE organizer_id = ? ORDER BY starts_at ASC');
        $stmt->execute([$user['id']]);
        $events = $stmt->fetchAll();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="prestige-report.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Мероприятие', 'Дата', 'Статус', 'Мест', 'Записано', 'Посетило', 'Лист ожидания'], ';');
    foreach ($events as $ev) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status = 'attended'");
        $stmt->execute([$ev['id']]);
        $attended = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM waitlist WHERE event_id = ? AND status = 'waiting'");
        $stmt->execute([$ev['id']]);
        $waitCount = (int) $stmt->fetchColumn();
        fputcsv($out, [$ev['title'], formatDate($ev['starts_at']), eventStatusName($ev['status']), $ev['capacity'], registeredCount($pdo, $ev['id']), $attended, $waitCount], ';');
    }
    fclose($out);
    logAction($pdo, $user['id'], 'export_report', 'events', null);
    exit;
}

$eventId = (int) ($_GET['event_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event || !canEditEvent($user, $event)) {
    die('Мероприятие не найдено.');
}

$stmt = $pdo->prepare('SELECT reg.*, u.last_name, u.first_name, u.middle_name, u.email, u.phone FROM registrations reg INNER JOIN users u ON u.id = reg.user_id WHERE reg.event_id = ? ORDER BY reg.registered_at ASC');
$stmt->execute([$eventId]);
$rows = $stmt->fetchAll();

if ($format === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="participants-event-' . $eventId . '.xls"');
    echo '<table border="1"><tr><th>Фамилия</th><th>Имя</th><th>Отчество</th><th>Email</th><th>Телефон</th><th>Статус</th><th>Дата записи</th><th>Токен</th></tr>';
    foreach ($rows as $row) {
        echo '<tr><td>' . e($row['last_name']) . '</td><td>' . e($row['first_name']) . '</td><td>' . e($row['middle_name'] ?? '') . '</td><td>' . e($row['email']) . '</td><td>' . e($row['phone'] ?? '') . '</td><td>' . e(regStatusName($row['status'])) . '</td><td>' . e(formatDate($row['registered_at'])) . '</td><td>' . e($row['qr_token']) . '</td></tr>';
    }
    echo '</table>';
    logAction($pdo, $user['id'], 'export_participants_xls', 'events', $eventId);
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="participants-event-' . $eventId . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, ['Фамилия', 'Имя', 'Отчество', 'Email', 'Телефон', 'Статус', 'Дата записи', 'Токен'], ';');
foreach ($rows as $row) {
    fputcsv($out, [$row['last_name'], $row['first_name'], $row['middle_name'], $row['email'], $row['phone'], regStatusName($row['status']), formatDate($row['registered_at']), $row['qr_token']], ';');
}
fclose($out);
logAction($pdo, $user['id'], 'export_participants_csv', 'events', $eventId);
