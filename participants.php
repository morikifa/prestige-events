<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['organizer', 'admin']);
$pageTitle = 'Участники';
$eventId = (int) ($_GET['event_id'] ?? 0);
$message = trim($_GET['msg'] ?? '');
$error = '';

$stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event || !canEditEvent($user, $event)) {
    redirect('organizer.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'status') {
        $regId = (int) ($_POST['reg_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['registered', 'confirmed', 'attended', 'cancelled'], true)) {
            $attendedAt = $status === 'attended' ? nowText() : null;
            $cancelledAt = $status === 'cancelled' ? nowText() : null;
            if ($status === 'attended') {
                $upd = $pdo->prepare('UPDATE registrations SET status = ?, attended_at = ? WHERE id = ? AND event_id = ?');
                $upd->execute([$status, $attendedAt, $regId, $eventId]);
            } elseif ($status === 'cancelled') {
                $upd = $pdo->prepare('UPDATE registrations SET status = ?, cancelled_at = ? WHERE id = ? AND event_id = ?');
                $upd->execute([$status, $cancelledAt, $regId, $eventId]);
                promoteWaitlist($pdo, $eventId);
            } else {
                $upd = $pdo->prepare('UPDATE registrations SET status = ? WHERE id = ? AND event_id = ?');
                $upd->execute([$status, $regId, $eventId]);
            }
            logAction($pdo, $user['id'], 'registration_status_' . $status, 'registrations', $regId);
        }
        redirect('participants.php?event_id=' . $eventId . '&msg=' . urlencode('Статус участника обновлён.'));
    }
    if ($action === 'delete') {
        $regId = (int) ($_POST['reg_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM registrations WHERE id = ? AND event_id = ?');
        $del->execute([$regId, $eventId]);
        logAction($pdo, $user['id'], 'registration_delete', 'registrations', $regId);
        promoteWaitlist($pdo, $eventId);
        redirect('participants.php?event_id=' . $eventId . '&msg=' . urlencode('Запись участника удалена из базы.'));
    }
    if ($action === 'checkin') {
        $token = trim($_POST['token'] ?? '');
        $stmt = $pdo->prepare('SELECT reg.*, u.last_name, u.first_name, u.middle_name FROM registrations reg INNER JOIN users u ON u.id = reg.user_id WHERE reg.qr_token = ? AND reg.event_id = ?');
        $stmt->execute([$token, $eventId]);
        $reg = $stmt->fetch();
        if (!$reg) {
            $error = 'Билет с таким токеном для этого мероприятия не найден.';
        } elseif ($reg['status'] === 'cancelled') {
            $error = 'Запись по этому токену отменена.';
        } elseif ($reg['status'] === 'attended') {
            $error = 'Участник уже отмечен: ' . fullName($reg) . ', время ' . formatDate($reg['attended_at']) . '.';
        } else {
            $upd = $pdo->prepare("UPDATE registrations SET status = 'attended', attended_at = ? WHERE id = ?");
            $upd->execute([nowText(), $reg['id']]);
            logAction($pdo, $user['id'], 'checkin', 'registrations', (int) $reg['id']);
            redirect('participants.php?event_id=' . $eventId . '&msg=' . urlencode('Посещение отмечено: ' . fullName($reg)));
        }
    }
    if ($action === 'notify') {
        $subject = trim($_POST['subject'] ?? '');
        $text = trim($_POST['text'] ?? '');
        if ($subject === '' || $text === '') {
            $error = 'Заполните тему и текст рассылки.';
        } else {
            $stmt = $pdo->prepare("SELECT user_id FROM registrations WHERE event_id = ? AND status IN ('registered', 'confirmed')");
            $stmt->execute([$eventId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                notify($pdo, $row['user_id'], $eventId, 'organizer_message', $subject, $text);
            }
            logAction($pdo, $user['id'], 'notify_participants', 'events', $eventId);
            redirect('participants.php?event_id=' . $eventId . '&msg=' . urlencode('Уведомление отправлено участникам: ' . count($rows)));
        }
    }
    if ($action === 'wait_delete') {
        $del = $pdo->prepare('DELETE FROM waitlist WHERE id = ? AND event_id = ?');
        $del->execute([(int) ($_POST['wait_id'] ?? 0), $eventId]);
        redirect('participants.php?event_id=' . $eventId . '&msg=' . urlencode('Запись листа ожидания удалена.'));
    }
    if ($action === 'wait_add') {
        $free = freePlaces($pdo, $event);
        if ($free !== null && $free <= 0) {
            $error = 'Свободных мест нет, сначала освободите место.';
        } else {
            promoteWaitlist($pdo, $eventId);
            redirect('participants.php?event_id=' . $eventId . '&msg=' . urlencode('Первый участник листа ожидания записан на мероприятие.'));
        }
    }
}

$stmt = $pdo->prepare('SELECT reg.*, u.last_name, u.first_name, u.middle_name, u.email, u.phone FROM registrations reg INNER JOIN users u ON u.id = reg.user_id WHERE reg.event_id = ? ORDER BY reg.registered_at ASC');
$stmt->execute([$eventId]);
$rows = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT w.*, u.last_name, u.first_name, u.middle_name, u.email FROM waitlist w INNER JOIN users u ON u.id = w.user_id WHERE w.event_id = ? ORDER BY w.position ASC');
$stmt->execute([$eventId]);
$waitRows = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<p><a href="organizer.php">← Организатор</a> · <a href="event.php?id=<?= $eventId ?>">Карточка</a></p>
<h2 class="page-title">Участники</h2>
<p class="lead"><?= e($event['title']) ?> · <?= formatDate($event['starts_at']) ?> · записано: <?= registeredCount($pdo, $eventId) ?><?= $event['capacity'] !== null ? ' из ' . e($event['capacity']) : '' ?></p>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

<p class="actions">
<a class="btn btn-light" href="export.php?event_id=<?= $eventId ?>&format=csv">Список CSV</a>
<a class="btn btn-light" href="export.php?event_id=<?= $eventId ?>&format=xls">Список Excel (XLS)</a>
<a class="btn btn-light" href="program.php?event_id=<?= $eventId ?>">Программа</a>
</p>

<h3 class="section">Отметка посещения по токену</h3>
<form method="post" class="narrow">
<?= csrfField() ?>
<input type="hidden" name="action" value="checkin">
<label>Токен из QR-кода билета</label>
<input type="text" name="token" required placeholder="Вставьте токен участника">
<p style="margin-top:12px"><button class="btn" type="submit">Отметить посещение</button></p>
<p class="grey">Тот же результат даёт переход по ссылке из QR-кода на страницу checkin.php под учётной записью организатора.</p>
</form>

<h3 class="section">Записи в таблице registrations</h3>
<table>
<tr><th>Участник</th><th>Контакты</th><th>Статус</th><th>Токен</th><th></th></tr>
<?php foreach ($rows as $row): ?>
<tr>
<td><?= e(fullName($row)) ?></td>
<td><?= e($row['email']) ?><br><?= e($row['phone'] ?? '') ?></td>
<td>
<form method="post" class="inline-form">
<?= csrfField() ?>
<input type="hidden" name="action" value="status">
<input type="hidden" name="reg_id" value="<?= (int) $row['id'] ?>">
<select name="status" style="width:auto;display:inline-block">
<?php foreach (['registered', 'confirmed', 'attended', 'cancelled'] as $st): ?>
<option value="<?= $st ?>" <?= $row['status'] === $st ? 'selected' : '' ?>><?= e(regStatusName($st)) ?></option>
<?php endforeach; ?>
</select>
<button class="btn btn-light btn-small" type="submit">OK</button>
</form>
<?php if ($row['attended_at']): ?><br><span class="grey"><?= formatDate($row['attended_at']) ?></span><?php endif; ?>
</td>
<td style="font-family:Consolas,monospace;font-size:12px"><?= e($row['qr_token']) ?></td>
<td>
<form method="post" data-confirm="Удалить запись участника из базы?">
<?= csrfField() ?>
<input type="hidden" name="action" value="delete">
<input type="hidden" name="reg_id" value="<?= (int) $row['id'] ?>">
<button class="btn btn-small" type="submit">Удалить</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php if (!$rows): ?><p class="empty">Участников пока нет.</p><?php endif; ?>

<h3 class="section">Лист ожидания</h3>
<table>
<tr><th>Позиция</th><th>Участник</th><th>Статус</th><th></th></tr>
<?php foreach ($waitRows as $wait): ?>
<tr>
<td><?= (int) $wait['position'] ?></td>
<td><?= e(fullName($wait)) ?><br><span class="grey"><?= e($wait['email']) ?></span></td>
<td><?= e(regStatusName($wait['status'])) ?></td>
<td>
<form method="post" data-confirm="Удалить из листа ожидания?">
<?= csrfField() ?>
<input type="hidden" name="action" value="wait_delete">
<input type="hidden" name="wait_id" value="<?= (int) $wait['id'] ?>">
<button class="btn btn-light btn-small" type="submit">Удалить</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php if (!$waitRows): ?><p class="empty">Лист ожидания пуст.</p><?php endif; ?>
<form method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="wait_add">
<button class="btn btn-light" type="submit">Записать первого из ожидания, если есть место</button>
</form>

<h3 class="section">Рассылка участникам</h3>
<form class="form-box" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="notify">
<div style="margin-bottom:14px"><label>Тема</label><input type="text" name="subject" maxlength="200" placeholder="Например: Изменение аудитории"></div>
<div style="margin-bottom:14px"><label>Текст уведомления в личный кабинет</label><textarea name="text"></textarea></div>
<button class="btn" type="submit">Отправить записанным участникам</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
