<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireLogin($user);
$pageTitle = 'Личный кабинет';
$message = trim($_GET['msg'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $last = trim($_POST['last_name'] ?? '');
        $first = trim($_POST['first_name'] ?? '');
        $middle = trim($_POST['middle_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($last === '' || $first === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Проверьте фамилию, имя и email.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetch()) {
                $error = 'Email уже занят другим пользователем.';
            } else {
                $upd = $pdo->prepare('UPDATE users SET last_name = ?, first_name = ?, middle_name = ?, email = ?, phone = ?, updated_at = ? WHERE id = ?');
                $upd->execute([$last, $first, $middle !== '' ? $middle : null, $email, $phone !== '' ? $phone : null, nowText(), $user['id']]);
                logAction($pdo, $user['id'], 'profile_update', 'users', $user['id']);
                redirect('cabinet.php?msg=' . urlencode('Профиль сохранён в базе данных.'));
            }
        }
    }
    if ($action === 'password') {
        $oldPass = (string) ($_POST['old_password'] ?? '');
        $newPass = (string) ($_POST['new_password'] ?? '');
        if (!password_verify($oldPass, $user['password_hash'])) {
            $error = 'Текущий пароль указан неверно.';
        } elseif (mb_strlen($newPass) < 4) {
            $error = 'Новый пароль должен быть не короче 4 символов.';
        } else {
            $upd = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?');
            $upd->execute([password_hash($newPass, PASSWORD_DEFAULT), nowText(), $user['id']]);
            logAction($pdo, $user['id'], 'password_change', 'users', $user['id']);
            redirect('cabinet.php?msg=' . urlencode('Пароль изменён.'));
        }
    }
    if ($action === 'cancel') {
        $regId = (int) ($_POST['reg_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = ? AND user_id = ?');
        $stmt->execute([$regId, $user['id']]);
        $reg = $stmt->fetch();
        if ($reg) {
            $upd = $pdo->prepare("UPDATE registrations SET status = 'cancelled', cancelled_at = ? WHERE id = ?");
            $upd->execute([nowText(), $regId]);
            logAction($pdo, $user['id'], 'register_cancel', 'registrations', $regId);
            promoteWaitlist($pdo, (int) $reg['event_id']);
        }
        redirect('cabinet.php?msg=' . urlencode('Запись отменена.'));
    }
    if ($action === 'leave_waitlist') {
        $waitId = (int) ($_POST['wait_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM waitlist WHERE id = ? AND user_id = ?');
        $del->execute([$waitId, $user['id']]);
        logAction($pdo, $user['id'], 'waitlist_leave', 'waitlist', $waitId);
        redirect('cabinet.php?msg=' . urlencode('Вы вышли из листа ожидания.'));
    }
    if ($action === 'read_one') {
        $noteId = (int) ($_POST['note_id'] ?? 0);
        $upd = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $upd->execute([$noteId, $user['id']]);
        redirect('cabinet.php?msg=' . urlencode('Уведомление отмечено прочитанным.'));
    }
    if ($action === 'read_all') {
        $upd = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $upd->execute([$user['id']]);
        redirect('cabinet.php?msg=' . urlencode('Все уведомления отмечены прочитанными.'));
    }
    $user = currentUser($pdo);
}

$stmt = $pdo->prepare('SELECT reg.*, e.title, e.starts_at, e.ends_at FROM registrations reg INNER JOIN events e ON e.id = reg.event_id WHERE reg.user_id = ? ORDER BY e.starts_at DESC');
$stmt->execute([$user['id']]);
$registrations = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT w.*, e.title, e.starts_at FROM waitlist w INNER JOIN events e ON e.id = w.event_id WHERE w.user_id = ? ORDER BY w.created_at DESC');
$stmt->execute([$user['id']]);
$waitItems = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC');
$stmt->execute([$user['id']]);
$notes = array_slice($stmt->fetchAll(), 0, 30);

require __DIR__ . '/includes/header.php';
?>
<h2 class="page-title">Кабинет</h2>
<p class="lead"><?= e(fullName($user)) ?> · <?= e($user['role_name']) ?> · <?= e($user['email']) ?></p>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

<h3 class="section">Мои мероприятия</h3>
<?php if (!$registrations): ?>
<p class="empty">Вы пока не записаны ни на одно мероприятие. Откройте <a href="index.php">афишу</a>.</p>
<?php else: ?>
<table>
<tr><th>Мероприятие</th><th>Дата</th><th>Статус</th><th>Билет</th><th></th></tr>
<?php foreach ($registrations as $reg): ?>
<tr>
<td><a href="event.php?id=<?= (int) $reg['event_id'] ?>"><?= e($reg['title']) ?></a></td>
<td><?= formatDate($reg['starts_at']) ?></td>
<td><span class="badge"><?= e(regStatusName($reg['status'])) ?></span><?php if ($reg['attended_at']): ?><br><span class="grey">Отмечено: <?= formatDate($reg['attended_at']) ?></span><?php endif; ?></td>
<td>
<?php if ($reg['status'] !== 'cancelled'): ?>
<img class="qr-img" style="width:90px;height:90px" alt="QR" src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=<?= urlencode(checkinUrl($reg['qr_token'])) ?>">
<div class="ticket-token"><?= e($reg['qr_token']) ?></div>
<?php endif; ?>
</td>
<td>
<?php if (in_array($reg['status'], ['registered', 'confirmed'], true)): ?>
<form method="post" data-confirm="Отменить эту запись?">
<?= csrfField() ?>
<input type="hidden" name="action" value="cancel">
<input type="hidden" name="reg_id" value="<?= (int) $reg['id'] ?>">
<button class="btn btn-light btn-small" type="submit">Отменить</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h3 class="section">Лист ожидания</h3>
<?php if (!$waitItems): ?>
<p class="empty">В листе ожидания пусто.</p>
<?php else: ?>
<table>
<tr><th>Мероприятие</th><th>Дата</th><th>Позиция</th><th>Статус</th><th></th></tr>
<?php foreach ($waitItems as $wait): ?>
<tr>
<td><a href="event.php?id=<?= (int) $wait['event_id'] ?>"><?= e($wait['title']) ?></a></td>
<td><?= formatDate($wait['starts_at']) ?></td>
<td><?= (int) $wait['position'] ?></td>
<td><?= e(regStatusName($wait['status'])) ?></td>
<td>
<?php if ($wait['status'] === 'waiting'): ?>
<form method="post" data-confirm="Выйти из листа ожидания?">
<?= csrfField() ?>
<input type="hidden" name="action" value="leave_waitlist">
<input type="hidden" name="wait_id" value="<?= (int) $wait['id'] ?>">
<button class="btn btn-light btn-small" type="submit">Выйти</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h3 class="section" id="notifications">Уведомления</h3>
<form method="post" style="margin-bottom:14px">
<?= csrfField() ?>
<input type="hidden" name="action" value="read_all">
<button class="btn btn-light btn-small" type="submit">Отметить все прочитанными</button>
</form>
<?php if (!$notes): ?>
<p class="empty">Уведомлений нет.</p>
<?php endif; ?>
<?php foreach ($notes as $note): ?>
<div class="notice-item <?= (int) $note['is_read'] === 0 ? 'unread' : '' ?>">
<strong><?= e($note['subject']) ?></strong> <span class="grey"><?= formatDate($note['created_at']) ?> · <?= e($note['type']) ?> · <?= e($note['channel']) ?></span>
<p><?= nl2br(e($note['message'])) ?></p>
<?php if ((int) $note['is_read'] === 0): ?>
<form method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="read_one">
<input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>">
<button class="btn btn-light btn-small" type="submit">Прочитано</button>
</form>
<?php endif; ?>
</div>
<?php endforeach; ?>

<h3 class="section">Профиль в базе данных</h3>
<form class="form-box" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="profile">
<div class="form-grid">
<div><label>Фамилия</label><input type="text" name="last_name" required value="<?= e($user['last_name']) ?>"></div>
<div><label>Имя</label><input type="text" name="first_name" required value="<?= e($user['first_name']) ?>"></div>
<div><label>Отчество</label><input type="text" name="middle_name" value="<?= e($user['middle_name'] ?? '') ?>"></div>
<div><label>Телефон</label><input type="text" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
<div class="full"><label>Email</label><input type="email" name="email" required value="<?= e($user['email']) ?>"></div>
</div>
<p style="margin-top:14px"><button class="btn" type="submit">Сохранить профиль</button></p>
</form>

<h3 class="section">Смена пароля</h3>
<form class="form-box" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="password">
<div class="form-grid">
<div><label>Текущий пароль</label><input type="password" name="old_password" required></div>
<div><label>Новый пароль</label><input type="password" name="new_password" required></div>
</div>
<p style="margin-top:14px"><button class="btn btn-light" type="submit">Сменить пароль</button></p>
</form>
<p class="grey">Роль в профиле поменять нельзя. Это делает только администратор.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>
