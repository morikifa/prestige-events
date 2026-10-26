<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['organizer', 'admin']);
$pageTitle = 'Отчёты';

if ($user['role_code'] === 'admin') {
    $events = $pdo->query('SELECT e.*, c.name AS category_name FROM events e INNER JOIN categories c ON c.id = e.category_id ORDER BY e.starts_at DESC')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT e.*, c.name AS category_name FROM events e INNER JOIN categories c ON c.id = e.category_id WHERE e.organizer_id = ? ORDER BY e.starts_at DESC');
    $stmt->execute([$user['id']]);
    $events = $stmt->fetchAll();
}

$totalReg = 0;
$totalAttended = 0;
foreach ($events as $ev) {
    $totalReg += registeredCount($pdo, $ev['id']);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status = 'attended'");
    $stmt->execute([$ev['id']]);
    $totalAttended += (int) $stmt->fetchColumn();
}

require __DIR__ . '/includes/header.php';
?>
<h2 class="page-title">Отчёты</h2>
<p class="lead"><?= $user['role_code'] === 'admin' ? 'Сводный отчёт по всем мероприятиям платформы.' : 'Отчёт по вашим мероприятиям.' ?> Числа считаются из registrations и waitlist на момент открытия страницы.</p>

<div class="cards">
<div class="card"><strong><?= count($events) ?></strong><span>Мероприятий в отчёте</span></div>
<div class="card"><strong><?= $totalReg ?></strong><span>Всего записей</span></div>
<div class="card"><strong><?= $totalAttended ?></strong><span>Отмечено посещений</span></div>
<div class="card"><strong><?= $totalReg > 0 ? round($totalAttended * 100 / $totalReg) : 0 ?>%</strong><span>Явка от записанных</span></div>
</div>

<p><a class="btn" href="export.php?mode=summary">Скачать сводный отчёт CSV</a></p>

<table style="margin-top:20px">
<tr><th>Мероприятие</th><th>Дата</th><th>Статус</th><th>Мест</th><th>Записано</th><th>Посетило</th><th>Ожидание</th><th>Заполняемость</th><th>Списки</th></tr>
<?php foreach ($events as $ev): ?>
<?php
$regCount = registeredCount($pdo, $ev['id']);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status = 'attended'");
$stmt->execute([$ev['id']]);
$attCount = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM waitlist WHERE event_id = ? AND status = 'waiting'");
$stmt->execute([$ev['id']]);
$waitCount = (int) $stmt->fetchColumn();
$fill = '—';
if ($ev['capacity']) {
    $fill = round($regCount * 100 / (int) $ev['capacity']) . '%';
}
?>
<tr>
<td><a href="event.php?id=<?= (int) $ev['id'] ?>"><?= e($ev['title']) ?></a><br><span class="grey"><?= e($ev['category_name']) ?></span></td>
<td><?= formatDate($ev['starts_at']) ?></td>
<td><?= e(eventStatusName($ev['status'])) ?></td>
<td><?= e($ev['capacity'] ?? '—') ?></td>
<td><?= $regCount ?></td>
<td><?= $attCount ?></td>
<td><?= $waitCount ?></td>
<td><?= e($fill) ?></td>
<td><a href="export.php?event_id=<?= (int) $ev['id'] ?>&format=csv">CSV</a> · <a href="export.php?event_id=<?= (int) $ev['id'] ?>&format=xls">Excel</a> · <a href="participants.php?event_id=<?= (int) $ev['id'] ?>">Открыть</a></td>
</tr>
<?php endforeach; ?>
</table>
<?php if (!$events): ?><p class="empty">Мероприятий для отчёта нет.</p><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
