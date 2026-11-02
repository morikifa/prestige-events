<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['organizer', 'admin']);
$pageTitle = 'Организатор';
$message = trim($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    if ($event && canEditEvent($user, $event)) {
        if ($action === 'delete') {
            $del = $pdo->prepare('DELETE FROM events WHERE id = ?');
            $del->execute([$eventId]);
            logAction($pdo, $user['id'], 'event_delete', 'events', $eventId);
            redirect('organizer.php?msg=' . urlencode('Мероприятие удалено из базы вместе с программой и записями.'));
        }
        if ($action === 'status') {
            $status = $_POST['status'] ?? '';
            $allowed = ['draft', 'moderation', 'published', 'cancelled', 'completed'];
            if (in_array($status, $allowed, true)) {
                $upd = $pdo->prepare('UPDATE events SET status = ?, updated_at = ? WHERE id = ?');
                $upd->execute([$status, nowText(), $eventId]);
                if ($status === 'cancelled') {
                    $parts = $pdo->prepare("SELECT user_id FROM registrations WHERE event_id = ? AND status IN ('registered', 'confirmed')");
                    $parts->execute([$eventId]);
                    foreach ($parts->fetchAll() as $part) {
                        notify($pdo, $part['user_id'], $eventId, 'event_cancelled', 'Мероприятие отменено', 'Мероприятие «' . $event['title'] . '» отменено организатором.', true);
                    }
                }
                logAction($pdo, $user['id'], 'event_status_' . $status, 'events', $eventId);
            }
            redirect('organizer.php?msg=' . urlencode('Статус мероприятия изменён.'));
        }
    }
    redirect('organizer.php');
}

if ($user['role_code'] === 'admin') {
    $events = $pdo->query('SELECT e.*, c.name AS category_name FROM events e INNER JOIN categories c ON c.id = e.category_id ORDER BY e.starts_at DESC')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT e.*, c.name AS category_name FROM events e INNER JOIN categories c ON c.id = e.category_id WHERE e.organizer_id = ? ORDER BY e.starts_at DESC');
    $stmt->execute([$user['id']]);
    $events = $stmt->fetchAll();
}

$totalParts = 0;
$totalAttended = 0;
foreach ($events as $ev) {
    $totalParts += registeredCount($pdo, $ev['id']);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status = 'attended'");
    $stmt->execute([$ev['id']]);
    $totalAttended += (int) $stmt->fetchColumn();
}

require __DIR__ . '/includes/header.php';
?>
<h2 class="page-title">Организатор</h2>
<p class="lead"><?= $user['role_code'] === 'admin' ? 'Все мероприятия платформы.' : 'Ваши мероприятия: создание, редактирование, программа, участники, отметка посещения и выгрузки.' ?></p>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>

<div class="cards">
<div class="card"><strong><?= count($events) ?></strong><span>Мероприятий</span></div>
<div class="card"><strong><?= $totalParts ?></strong><span>Записей участников</span></div>
<div class="card"><strong><?= $user['role_code'] === 'admin' ? 'Все' : 'Мои' ?></strong><span>Область доступа</span></div>
<div class="card"><strong><?= $totalAttended ?></strong><span>Отмечено посещений</span></div>
</div>

<p class="actions"><a class="btn" href="event_edit.php">Новое мероприятие</a> <a class="btn btn-light" href="export.php?mode=summary&format=xlsx">Отчёт XLSX</a> <a class="btn btn-light" href="export.php?mode=summary">Сводка CSV</a></p>

<h3 class="section">Мероприятия и сводка</h3>
<p class="grey">Колонки «Посетило», «Ожидание» и «Заполнено» считаются из таблиц registrations и waitlist при открытии страницы. Для администратора здесь показаны все мероприятия, для организатора — только свои.</p>
<?php if (!$events): ?>
<p class="empty">Мероприятий пока нет. Создайте первое кнопкой выше.</p>
<?php else: ?>
<table>
<tr><th>Название</th><th>Дата</th><th>Категория</th><th>Записано</th><th>Посетило</th><th>Ожидание</th><th>Заполнено</th><th>Статус в БД</th><th>Действия</th></tr>
<?php foreach ($events as $ev): ?>
<?php
$regCount = registeredCount($pdo, $ev['id']);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status = 'attended'");
$stmt->execute([$ev['id']]);
$attendedCount = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM waitlist WHERE event_id = ? AND status = 'waiting'");
$stmt->execute([$ev['id']]);
$waitingCount = (int) $stmt->fetchColumn();
$fill = '—';
if ($ev['capacity']) {
    $fill = round($regCount * 100 / (int) $ev['capacity']) . '%';
}
?>
<tr>
<td><a href="event.php?id=<?= (int) $ev['id'] ?>"><?= e($ev['title']) ?></a><br><span class="grey"><?= e($ev['slug']) ?></span></td>
<td><?= formatDate($ev['starts_at']) ?></td>
<td><?= e($ev['category_name']) ?></td>
<td><?= $regCount ?><?= $ev['capacity'] !== null ? ' / ' . e($ev['capacity']) : '' ?></td>
<td><?= $attendedCount ?></td>
<td><?= $waitingCount ?></td>
<td><?= e($fill) ?></td>
<td>
<form method="post" class="inline-form">
<?= csrfField() ?>
<input type="hidden" name="action" value="status">
<input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>">
<select name="status" style="width:auto;display:inline-block">
<?php foreach (['draft', 'moderation', 'published', 'cancelled', 'completed'] as $st): ?>
<option value="<?= $st ?>" <?= $ev['status'] === $st ? 'selected' : '' ?>><?= e(eventStatusName($st)) ?></option>
<?php endforeach; ?>
</select>
<button class="btn btn-light btn-small" type="submit">OK</button>
</form>
</td>
<td>
<div class="actions">
<a class="btn btn-light btn-small" href="event_edit.php?id=<?= (int) $ev['id'] ?>">Изменить и программа</a>
<a class="btn btn-light btn-small" href="participants.php?event_id=<?= (int) $ev['id'] ?>">Участники</a>
<form method="post" data-confirm="Удалить мероприятие из базы? Программа и записи удалятся по внешним ключам.">
<?= csrfField() ?>
<input type="hidden" name="action" value="delete">
<input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>">
<button class="btn btn-small" type="submit">Удалить</button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
