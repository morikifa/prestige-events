<?php
require __DIR__ . '/db.php';
require __DIR__ . '/core.php';

$user = currentUser($pdo);
$p = $_GET['p'] ?? 'afisha';
$id = (int) ($_GET['id'] ?? 0);
$message = trim($_GET['msg'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    needLogin($user);
    $id = (int) ($_POST['event_id'] ?? 0);
    $event = eventById($pdo, $id);
    if (!$event) {
        redirect('index.php');
    }
    $action = $_POST['action'] ?? '';
    $now = nowText();
    $closed = $event['status'] !== 'published'
        || ($event['registration_opens_at'] && $now < $event['registration_opens_at'])
        || ($event['registration_closes_at'] && $now > $event['registration_closes_at']);

    if ($action === 'register') {
        if ($closed) {
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
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM waitlist WHERE event_id = ? AND status = 'waiting'");
                    $stmt->execute([$id]);
                    $waitCount = (int) $stmt->fetchColumn();
                    if ($inWait && $inWait['status'] === 'waiting') {
                        $error = 'Вы уже находитесь в листе ожидания.';
                    } elseif ($waitCount >= (int) getSetting($pdo, 'max_waitlist_size', '50')) {
                        $error = 'Лист ожидания заполнен.';
                    } else {
                        if ($inWait) {
                            $upd = $pdo->prepare("UPDATE waitlist SET status = 'waiting', position = ?, updated_at = ? WHERE id = ?");
                            $upd->execute([$waitCount + 1, $now, $inWait['id']]);
                        } else {
                            $ins = $pdo->prepare('INSERT INTO waitlist (event_id, user_id, position, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
                            $ins->execute([$id, $user['id'], $waitCount + 1, 'waiting', $now, $now]);
                        }
                        notify($pdo, $user['id'], $id, 'waitlist', 'Лист ожидания', 'Вы добавлены в лист ожидания на мероприятие «' . $event['title'] . '». Позиция: ' . ($waitCount + 1), true);
                        logAction($pdo, $user['id'], 'waitlist_add', 'events', $id);
                        redirect('index.php?p=event&id=' . $id . '&msg=' . urlencode('Мест нет. Вы добавлены в лист ожидания.'));
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
                    notify($pdo, $user['id'], $id, 'registration', 'Запись подтверждена', 'Вы записаны на мероприятие «' . $event['title'] . '». Токен билета: ' . $token, true);
                    logAction($pdo, $user['id'], 'register', 'events', $id);
                    redirect('index.php?p=event&id=' . $id . '&msg=' . urlencode('Вы записаны. Билет с токеном ниже и в личном кабинете.'));
                }
            }
        }
    } elseif ($action === 'cancel') {
        $upd = $pdo->prepare("UPDATE registrations SET status = 'cancelled', cancelled_at = ? WHERE event_id = ? AND user_id = ? AND status IN ('registered', 'confirmed')");
        $upd->execute([$now, $id, $user['id']]);
        logAction($pdo, $user['id'], 'register_cancel', 'events', $id);
        promoteWaitlist($pdo, $id);
        redirect('index.php?p=event&id=' . $id . '&msg=' . urlencode('Запись отменена.'));
    } elseif ($action === 'leave_waitlist') {
        $del = $pdo->prepare('DELETE FROM waitlist WHERE event_id = ? AND user_id = ?');
        $del->execute([$id, $user['id']]);
        logAction($pdo, $user['id'], 'waitlist_leave', 'events', $id);
        redirect('index.php?p=event&id=' . $id . '&msg=' . urlencode('Вы вышли из листа ожидания.'));
    }
    $p = 'event';
}

if ($p === 'event') {
    $event = eventById($pdo, $id);
    if (!$event) {
        pageStart('Не найдено', $pdo, $user);
        echo '<h2 class="page-title">Не найдено</h2><p class="lead">Такого мероприятия нет. <a href="index.php">Вернуться в афишу</a></p>';
        pageEnd();
        exit;
    }
    if ($event['status'] !== 'published' && !canEditEvent($user, $event)) {
        pageStart('Не опубликовано', $pdo, $user);
        echo '<h2 class="page-title">Не опубликовано</h2><p class="lead">Мероприятие пока доступно только организатору и администратору. <a href="index.php">Вернуться в афишу</a></p>';
        pageEnd();
        exit;
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
    pageStart($event['title'], $pdo, $user);
?>
<p><a href="index.php">← Афиша</a></p>
<h2 class="page-title"><?= e($event['title']) ?></h2>
<p class="lead"><?= e($event['category_name']) ?> · <?= e(eventStatusName($event['status'])) ?></p>
<?php showMessage($message, $error); ?>
<div class="two-col">
<div>
<h3 class="section">Описание</h3>
<p><?= nl2br(e($event['description'] ?? '')) ?></p>
<h3 class="section">Программа</h3>
<?php if (!$program): ?><p class="empty">Программа пока не добавлена.</p><?php endif; ?>
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
<p><strong>Свободно:</strong> <span id="freePlaces"><?= $free === null ? 'без ограничения' : $free ?></span> <button type="button" class="btn btn-light btn-small" id="refreshFree" data-event="<?= (int) $event['id'] ?>">Обновить</button></p>
<?php if ($event['registration_closes_at']): ?><p><strong>Запись до:</strong> <?= formatDate($event['registration_closes_at']) ?></p><?php endif; ?>
<?php if (!$user): ?>
<p>Для записи войдите в платформу.</p>
<a class="btn" href="account.php?p=login">Войти</a>
<?php elseif ($myReg && $myReg['status'] !== 'cancelled'): ?>
<p><span class="badge"><?= e(regStatusName($myReg['status'])) ?></span></p>
<p>Ваш билет:</p>
<div class="ticket-token"><?= e($myReg['qr_token']) ?></div>
<img class="qr-img" alt="QR-код билета" src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode(checkinUrl($myReg['qr_token'])) ?>">
<p class="grey">Организатор открывает ссылку из QR-кода или вводит этот токен вручную.</p>
<?php if ($myReg['status'] !== 'attended'): ?>
<form method="post" data-confirm="Отменить запись на мероприятие?">
<?= csrfField() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
<button class="btn btn-light" type="submit">Отменить запись</button>
</form>
<?php endif; ?>
<?php elseif ($myWait && $myWait['status'] === 'waiting'): ?>
<p><span class="badge">Лист ожидания</span></p>
<p>Ваша позиция: <?= (int) $myWait['position'] ?>. Когда место освободится, запись появится автоматически, а уведомление придёт в кабинет.</p>
<form method="post" data-confirm="Выйти из листа ожидания?">
<?= csrfField() ?><input type="hidden" name="action" value="leave_waitlist"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
<button class="btn btn-light" type="submit">Выйти из листа ожидания</button>
</form>
<?php else: ?>
<form method="post">
<?= csrfField() ?><input type="hidden" name="action" value="register"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
<?php if ($free !== null && $free <= 0): ?>
<button class="btn" type="submit">В лист ожидания</button>
<?php else: ?>
<button class="btn" type="submit">Записаться</button>
<?php endif; ?>
</form>
<?php endif; ?>
<?php if (canEditEvent($user, $event)): ?>
<p style="margin-top:20px"><a class="btn btn-light btn-small" href="office.php?p=edit&id=<?= (int) $event['id'] ?>">Редактировать</a> <a class="btn btn-light btn-small" href="office.php?p=people&event_id=<?= (int) $event['id'] ?>">Участники</a></p>
<?php endif; ?>
</aside>
</div>
<?php
    pageEnd();
    exit;
}

$search = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category_id'] ?? 0);
$venueId = (int) ($_GET['venue_id'] ?? 0);
$date = trim($_GET['date'] ?? '');
$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$venues = $pdo->query('SELECT * FROM venues ORDER BY name')->fetchAll();
$sql = "SELECT e.*, c.name AS category_name, v.name AS venue_name FROM events e INNER JOIN categories c ON c.id = e.category_id LEFT JOIN venues v ON v.id = e.venue_id WHERE e.status = 'published' AND e.starts_at >= ?";
$params = [nowText()];
if ($search !== '') {
    $sql .= ' AND e.title LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($categoryId > 0) {
    $sql .= ' AND e.category_id = ?';
    $params[] = $categoryId;
}
if ($venueId > 0) {
    $sql .= ' AND e.venue_id = ?';
    $params[] = $venueId;
}
if ($date !== '') {
    $sql .= ' AND e.starts_at >= ? AND e.starts_at <= ?';
    $params[] = $date . ' 00:00:00';
    $params[] = $date . ' 23:59:59';
}
$sql .= ' ORDER BY e.starts_at ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

pageStart('Афиша', $pdo, $user);
showMessage($message, $error);
?>
<section class="hero">
<h1>Prestige <span class="line2">Events</span></h1>
<div class="hero-sub">
<p>Афиша мероприятий колледжа: конференции, семинары, олимпиады и хакатоны. Запись через личный кабинет, билет с QR-кодом.</p>
<div class="small">Найдено мероприятий: <?= count($events) ?></div>
</div>
</section>
<form class="filters" method="get">
<div><label for="q">Поиск по названию</label><input id="q" type="text" name="q" value="<?= e($search) ?>" placeholder="Например: SQL"></div>
<div><label for="category_id">Категория</label><select id="category_id" name="category_id"><option value="0">Все категории</option><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?></select></div>
<div><label for="venue_id">Площадка</label><select id="venue_id" name="venue_id"><option value="0">Все площадки</option><?php foreach ($venues as $venue): ?><option value="<?= (int) $venue['id'] ?>" <?= $venueId === (int) $venue['id'] ? 'selected' : '' ?>><?= e($venue['name']) ?></option><?php endforeach; ?></select></div>
<div><label for="date">Дата</label><input id="date" type="date" name="date" value="<?= e($date) ?>"></div>
<button class="btn" type="submit">Найти</button>
<a class="btn btn-light" href="index.php">Сбросить</a>
</form>
<?php if (!$events): ?><p class="empty">По этим условиям мероприятий нет. Измените фильтры или сбросьте поиск.</p><?php endif; ?>
<?php foreach ($events as $event): ?>
<?php $free = freePlaces($pdo, $event); ?>
<article class="event-row">
<div class="event-date"><strong><?= date('d', strtotime($event['starts_at'])) ?></strong><?= date('m.Y', strtotime($event['starts_at'])) ?><br><?= date('H:i', strtotime($event['starts_at'])) ?></div>
<div>
<h3><a href="index.php?p=event&id=<?= (int) $event['id'] ?>"><?= e($event['title']) ?></a></h3>
<p><?= e(mb_substr((string) $event['description'], 0, 160)) ?><?= mb_strlen((string) $event['description']) > 160 ? '…' : '' ?></p>
</div>
<div><span class="badge"><?= e($event['category_name']) ?></span><p class="grey"><?= e($event['venue_name'] ?? 'Место уточняется') ?></p></div>
<div>
<p><?php if ($free === null): ?>Без ограничения<?php elseif ($free > 0): ?>Свободно мест: <?= $free ?><?php else: ?>Мест нет · лист ожидания<?php endif; ?></p>
<a class="btn btn-light btn-small" href="index.php?p=event&id=<?= (int) $event['id'] ?>">Открыть</a>
</div>
</article>
<?php endforeach; ?>
<?php pageEnd(); ?>
