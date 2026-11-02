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
    $headers = ['Мероприятие', 'Дата', 'Статус', 'Мест', 'Записано', 'Посетило', 'Лист ожидания'];
    $rows = [];
    foreach ($events as $ev) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status = 'attended'");
        $stmt->execute([$ev['id']]);
        $attended = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM waitlist WHERE event_id = ? AND status = 'waiting'");
        $stmt->execute([$ev['id']]);
        $waitCount = (int) $stmt->fetchColumn();
        $rows[] = [$ev['title'], formatDate($ev['starts_at']), eventStatusName($ev['status']), $ev['capacity'] === null ? 'без ограничения' : $ev['capacity'], registeredCount($pdo, $ev['id']), $attended, $waitCount];
    }
    logAction($pdo, $user['id'], 'export_report', 'events', null);
    if ($format === 'xlsx') {
        sendXlsx('prestige-report.xlsx', 'Сводка', $headers, $rows);
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="prestige-report.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
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

$headers = ['Фамилия', 'Имя', 'Отчество', 'Email', 'Телефон', 'Статус', 'Дата записи', 'Токен'];
$table = [];
foreach ($rows as $row) {
    $table[] = [$row['last_name'], $row['first_name'], $row['middle_name'], $row['email'], $row['phone'], regStatusName($row['status']), formatDate($row['registered_at']), $row['qr_token']];
}

logAction($pdo, $user['id'], 'export_participants', 'events', $eventId);
if ($format === 'xlsx') {
    sendXlsx('participants-event-' . $eventId . '.xlsx', 'Участники', $headers, $table);
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="participants-event-' . $eventId . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, $headers, ';');
foreach ($table as $row) {
    fputcsv($out, $row, ';');
}
fclose($out);
