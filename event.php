<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT e.*, c.name AS category_name, v.name AS venue_name, v.address AS venue_address FROM events e INNER JOIN categories c ON c.id = e.category_id LEFT JOIN venues v ON v.id = e.venue_id WHERE e.id = ?');
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    $pageTitle = 'Мероприятие не найдено';
    require __DIR__ . '/includes/header.php';
    echo '<h2 class="page-title">Не найдено</h2><p class="lead">Такого мероприятия нет. <a href="index.php">Вернуться в афишу</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($event['status'] !== 'published' && !canEditEvent($user, $event)) {
    $pageTitle = 'Мероприятие не опубликовано';
    require __DIR__ . '/includes/header.php';
    echo '<h2 class="page-title">Не опубликовано</h2><p class="lead">Мероприятие пока доступно только организатору и администратору. <a href="index.php">Вернуться в афишу</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$message = trim($_GET['msg'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    requireLogin($user);
    $action = $_POST['action'] ?? '';
    $now = nowText();
    $registrationClosed = false;
    if ($event['registration_opens_at'] && $now < $event['registration_opens_at']) {
        $registrationClosed = true;
    }
    if ($event['registration_closes_at'] && $now > $event['registration_closes_at']) {
        $registrationClosed = true;
    }
    if ($event['status'] !== 'published') {
        $registrationClosed = true;
    }

    if ($action === 'register') {
        if ($registrationClosed) {
            $error = 'Запись на это мероприятие сейчас закрыта.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM registrations WHERE event_id = ? AND user_id = ?');
            $stmt->execute([$id, $user['id']]);
            $old = $stmt->fetch();
            if ($old && $old['status'] !== 'cancelled') {
                $error = 'Вы уже записаны на это мероприятие.';
            } else {
                $free = freePlaces($pdo, $event);
                if ($free !== null && $free <= 0) {
                    $stmt = $pdo->prepare('SELECT * FROM waitlist WHERE event_id = ? AND user_id = ?');
                    $stmt->execute([$id, $user['id']]);
                    $inWait = $stmt->fetch();
                    if ($inWait && $inWait['status'] === 'waiting') {
                        $error = 'Вы уже находитесь в листе ожидания.';
                    } else {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM waitlist WHERE event_id = ? AND status = 'waiting'");
                        $stmt->execute([$id]);
                        $waitCount = (int) $stmt->fetchColumn();
                        $maxWait = (int) getSetting($pdo, 'max_waitlist_size', '50');
                        if ($waitCount >= $maxWait) {
                            $error = 'Лист ожидания заполнен.';
                        } else {
                            if ($inWait) {
                                $upd = $pdo->prepare("UPDATE waitlist SET status = 'waiting', position = ?, updated_at = ? WHERE id = ?");
                                $upd->execute([$waitCount + 1, $now, $inWait['id']]);
                            } else {
                                $ins = $pdo->prepare('INSERT INTO waitlist (event_id, user_id, position, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
                                $ins->execute([$id, $user['id'], $waitCount + 1, 'waiting', $now, $now]);
                            }
                            notify($pdo, $user['id'], $id, 'waitlist', 'Лист ожидания', 'Вы добавлены в лист ожидания на мероприятие «' . $event['title'] . '». Позиция: ' . ($waitCount + 1));
                            logAction($pdo, $user['id'], 'waitlist_add', 'events', $id);
                            redirect('event.php?id=' . $id . '&msg=' . urlencode('Мест нет. Вы добавлены в лист ожидания.'));
                        }
                    }
                } else {
                    $token = makeToken();
                    if ($old) {
                        $upd = $pdo->prepare("UPDATE registrations SET status = 'registered', qr_token = ?, registered_at = ?, cancelled_at = NULL, attended_at = NULL WHERE id = ?");
                        $upd->execute([$token, $now, $old['id']]);
                    } else {
                        $ins = $pdo->prepare('INSERT INTO registrations (event_id, user_id, status, qr_token, registered_at) VALUES (?, ?, ?, ?, ?)');
                        $ins->execute([$id, $user['id'], 'registered', $token, $now]);
                    }
                    notify($pdo, $user['id'], $id, 'registration', 'Запись подтверждена', 'Вы записаны на мероприятие «' . $event['title'] . '». Токен билета: ' . $token);
                    logAction($pdo, $user['id'], 'register', 'events', $id);
                    redirect('event.php?id=' . $id . '&msg=' . urlencode('Вы записаны. Билет с токеном ниже и в личном кабинете.'));
                }
            }
        }
    }

    if ($action === 'cancel') {
        $upd = $pdo->prepare("UPDATE registrations SET status = 'cancelled', cancelled_at = ? WHERE event_id = ? AND user_id = ? AND status IN ('registered', 'confirmed')");
        $upd->execute([$now, $id, $user['id']]);
        logAction($pdo, $user['id'], 'register_cancel', 'events', $id);
        promoteWaitlist($pdo, $id);
        redirect('event.php?id=' . $id . '&msg=' . urlencode('Запись отменена.'));
    }

    if ($action === 'leave_waitlist') {
        $del = $pdo->prepare('DELETE FROM waitlist WHERE event_id = ? AND user_id = ?');
        $del->execute([$id, $user['id']]);
        logAction($pdo, $user['id'], 'waitlist_leave', 'events', $id);
        redirect('event.php?id=' . $id . '&msg=' . urlencode('Вы вышли из листа ожидания.'));
    }
}

$stmt = $pdo->prepare('SELECT * FROM event_program WHERE event_id = ? ORDER BY sort_order ASC, starts_at ASC');
$stmt->execute([$id]);
$program = $stmt->fetchAll();

$myReg = null;
$myWait = null;
if ($user) {
    $stmt = $pdo->prepare('SELECT * FROM registrations WHERE event_id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
    $myReg = $stmt->fetch();
    $stmt = $pdo->prepare('SELECT * FROM waitlist WHERE event_id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
    $myWait = $stmt->fetch();
}

$free = freePlaces($pdo, $event);
$pageTitle = $event['title'];
require __DIR__ . '/includes/header.php';
?>
<p><a href="index.php">← Афиша</a></p>
<h2 class="page-title"><?= e($event['title']) ?></h2>
<p class="lead"><?= e($event['category_name']) ?> · <?= e(eventStatusName($event['status'])) ?></p>

<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

<div class="two-col">
<div>
<h3 class="section">Описание</h3>
<p><?= nl2br(e($event['description'] ?? '')) ?></p>

<h3 class="section">Программа</h3>
<?php if (!$program): ?>
<p class="empty">Программа пока не добавлена.</p>
<?php endif; ?>
<?php foreach ($program as $item): ?>
<div class="program-row">
<div><?= formatDate($item['starts_at']) ?><br><span class="grey">до <?= date('H:i', strtotime($item['ends_at'])) ?></span></div>
<div><strong><?= e($item['title']) ?></strong><?php if ($item['speaker']): ?><br><?= e($item['speaker']) ?><?php endif; ?><?php if ($item['place']): ?><br><span class="grey"><?= e($item['place']) ?></span><?php endif; ?></div>
</div>
<?php endforeach; ?>
</div>

<aside class="side-box">
<h3>Запись</h3>
<p><strong>Начало:</strong> <?= formatDate($event['starts_at']) ?></p>
<p><strong>Окончание:</strong> <?= formatDate($event['ends_at']) ?></p>
<p><strong>Площадка:</strong> <?= e($event['venue_name'] ?? 'Уточняется') ?><?php if (!empty($event['venue_address'])): ?><br><span class="grey"><?= e($event['venue_address']) ?></span><?php endif; ?></p>
<p><strong>Мест:</strong> <?= $event['capacity'] !== null ? e($event['capacity']) : 'без ограничения' ?></p>
<p><strong>Свободно:</strong> <?= $free === null ? 'без ограничения' : $free ?></p>
<?php if ($event['registration_closes_at']): ?>
<p><strong>Запись до:</strong> <?= formatDate($event['registration_closes_at']) ?></p>
<?php endif; ?>

<?php if (!$user): ?>
<p>Для записи войдите в платформу.</p>
<a class="btn" href="login.php">Войти</a>
<?php elseif ($myReg && $myReg['status'] !== 'cancelled'): ?>
<p><span class="badge"><?= e(regStatusName($myReg['status'])) ?></span></p>
<p>Ваш билет:</p>
<div class="ticket-token"><?= e($myReg['qr_token']) ?></div>
<img class="qr-img" alt="QR-код билета" src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode(checkinUrl($myReg['qr_token'])) ?>">
<p class="grey">Организатор открывает ссылку из QR-кода или вводит этот токен вручную.</p>
<?php if ($myReg['status'] !== 'attended'): ?>
<form method="post" data-confirm="Отменить запись на мероприятие?">
<?= csrfField() ?>
<input type="hidden" name="action" value="cancel">
<button class="btn btn-light" type="submit">Отменить запись</button>
</form>
<?php endif; ?>
<?php elseif ($myWait && $myWait['status'] === 'waiting'): ?>
<p><span class="badge">Лист ожидания</span></p>
<p>Ваша позиция: <?= (int) $myWait['position'] ?>. Когда место освободится, запись появится автоматически, а уведомление придёт в кабинет.</p>
<form method="post" data-confirm="Выйти из листа ожидания?">
<?= csrfField() ?>
<input type="hidden" name="action" value="leave_waitlist">
<button class="btn btn-light" type="submit">Выйти из листа ожидания</button>
</form>
<?php else: ?>
<form method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="register">
<?php if ($free !== null && $free <= 0): ?>
<button class="btn" type="submit">В лист ожидания</button>
<?php else: ?>
<button class="btn" type="submit">Записаться</button>
<?php endif; ?>
</form>
<?php endif; ?>

<?php if (canEditEvent($user, $event)): ?>
<p style="margin-top:20px"><a class="btn btn-light btn-small" href="event_edit.php?id=<?= (int) $event['id'] ?>">Редактировать</a> <a class="btn btn-light btn-small" href="participants.php?event_id=<?= (int) $event['id'] ?>">Участники</a></p>
<?php endif; ?>
</aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
