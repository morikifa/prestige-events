<?php
require __DIR__ . '/db.php';
require __DIR__ . '/core.php';

$user = currentUser($pdo);
$p = $_GET['p'] ?? 'login';
$message = trim($_GET['msg'] ?? '');
$error = '';

if ($p === 'logout') {
    if ($user) {
        logAction($pdo, $user['id'], 'logout', 'users', $user['id']);
    }
    $_SESSION = [];
    session_destroy();
    redirect('index.php');
}

if ($p === 'login' && $user) {
    redirect('account.php?p=cabinet');
}
if ($p === 'register' && $user) {
    redirect('account.php?p=cabinet');
}

$values = ['last_name' => '', 'first_name' => '', 'middle_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $login = trim($_POST['login'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if ($login !== '' && strpos($login, '@') === false) {
            $login .= '@prestige.local';
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$login]);
        $found = $stmt->fetch();
        if (!$found || !password_verify($password, $found['password_hash'])) {
            $error = 'Неверный логин или пароль.';
        } elseif ($found['status'] === 'blocked') {
            $error = 'Учётная запись заблокирована. Обратитесь к администратору.';
        } elseif ($found['status'] === 'pending') {
            $error = 'Учётная запись ожидает подтверждения.';
        } else {
            $_SESSION['user_id'] = $found['id'];
            logAction($pdo, $found['id'], 'login', 'users', $found['id']);
            $stmt = $pdo->prepare('SELECT code FROM roles WHERE id = ?');
            $stmt->execute([$found['role_id']]);
            $role = $stmt->fetchColumn();
            if ($role === 'admin') {
                redirect('admin.php');
            }
            if ($role === 'organizer') {
                redirect('office.php');
            }
            redirect('account.php?p=cabinet');
        }
    }

    if ($action === 'register') {
        foreach ($values as $key => $value) {
            $values[$key] = trim($_POST[$key] ?? '');
        }
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');
        if ($values['last_name'] === '' || $values['first_name'] === '' || $values['email'] === '') {
            $error = 'Заполните фамилию, имя и email.';
        } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Укажите корректный email.';
        } elseif (mb_strlen($password) < 4) {
            $error = 'Пароль должен быть не короче 4 символов.';
        } elseif ($password !== $password2) {
            $error = 'Пароли не совпадают.';
        } elseif (empty($_POST['agree'])) {
            $error = 'Нужно согласие на обработку персональных данных.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$values['email']]);
            if ($stmt->fetch()) {
                $error = 'Пользователь с таким email уже зарегистрирован.';
            } else {
                $roleId = (int) $pdo->query("SELECT id FROM roles WHERE code = 'participant'")->fetchColumn();
                $ins = $pdo->prepare('INSERT INTO users (role_id, last_name, first_name, middle_name, email, phone, password_hash, status, email_verified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([$roleId, $values['last_name'], $values['first_name'], $values['middle_name'] !== '' ? $values['middle_name'] : null, $values['email'], $values['phone'] !== '' ? $values['phone'] : null, password_hash($password, PASSWORD_DEFAULT), 'active', nowText(), nowText(), nowText()]);
                $newId = insertedId($pdo);
                $_SESSION['user_id'] = $newId;
                notify($pdo, $newId, null, 'welcome', 'Добро пожаловать в Prestige Events', 'Учётная запись участника создана. Теперь можно записываться на мероприятия из афиши.', true);
                logAction($pdo, $newId, 'register_account', 'users', $newId);
                redirect('account.php?p=cabinet');
            }
        }
    }

    if ($action === 'reset') {
        $email = trim($_POST['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Укажите корректный email учётной записи.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $found = $stmt->fetch();
            if ($found) {
                $admins = $pdo->query("SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin' AND u.status = 'active'")->fetchAll();
                foreach ($admins as $admin) {
                    notify($pdo, $admin['id'], null, 'password_reset', 'Запрос на восстановление пароля', 'Пользователь ' . fullName($found) . ' (' . $found['email'] . ') запросил восстановление пароля. Задайте новый пароль в разделе «Пользователи».');
                }
                queueEmail($pdo, $found['id'], null, 'password_reset', 'Восстановление пароля Prestige Events', 'Запрос на восстановление пароля получен. Администратор задаст временный пароль и сообщит его лично.');
                logAction($pdo, $found['id'], 'password_reset_request', 'users', $found['id']);
            }
            $message = 'Если учётная запись с таким email есть, запрос отправлен администратору. Он задаст временный пароль в разделе «Пользователи» и сообщит его лично.';
        }
    }

    if ($action === 'profile' || $action === 'password' || $action === 'cancel' || $action === 'leave' || $action === 'read_one' || $action === 'read_all') {
        needLogin($user);
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
                    redirect('account.php?p=cabinet&msg=' . urlencode('Профиль сохранён в базе данных.'));
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
                redirect('account.php?p=cabinet&msg=' . urlencode('Пароль изменён.'));
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
            redirect('account.php?p=cabinet&msg=' . urlencode('Запись отменена.'));
        }
        if ($action === 'leave') {
            $waitId = (int) ($_POST['wait_id'] ?? 0);
            $del = $pdo->prepare('DELETE FROM waitlist WHERE id = ? AND user_id = ?');
            $del->execute([$waitId, $user['id']]);
            logAction($pdo, $user['id'], 'waitlist_leave', 'waitlist', $waitId);
            redirect('account.php?p=cabinet&msg=' . urlencode('Вы вышли из листа ожидания.'));
        }
        if ($action === 'read_one') {
            $upd = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            $upd->execute([(int) ($_POST['note_id'] ?? 0), $user['id']]);
            redirect('account.php?p=cabinet&msg=' . urlencode('Уведомление отмечено прочитанным.'));
        }
        if ($action === 'read_all') {
            $upd = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
            $upd->execute([$user['id']]);
            redirect('account.php?p=cabinet&msg=' . urlencode('Все уведомления отмечены прочитанными.'));
        }
        $user = currentUser($pdo);
        $p = 'cabinet';
    }
}

if ($p === 'login') {
    pageStart('Вход', $pdo, $user);
?>
<h2 class="page-title">Вход</h2>
<p class="lead">Войдите по логину и паролю из таблицы ниже или по полному email вашей учётной записи. Роль определяется по базе данных и меняется только администратором.</p>
<?php showMessage($message, $error); ?>
<div class="two-col">
<div>
<form class="narrow" method="post">
<?= csrfField() ?><input type="hidden" name="action" value="login">
<div class="field" style="margin-bottom:16px"><label for="login">Логин</label><input id="login" type="text" name="login" required autocomplete="username" placeholder="admin"></div>
<div class="field" style="margin-bottom:16px"><label for="password">Пароль</label><input id="password" type="password" name="password" required autocomplete="current-password" placeholder="1234"></div>
<button class="btn" type="submit">Войти</button>
<p style="margin-top:16px"><a href="account.php?p=register">Регистрация участника</a></p>
</form>
<form class="narrow" method="post">
<?= csrfField() ?><input type="hidden" name="action" value="reset">
<h3 class="section">Забыли пароль?</h3>
<p class="grey">Почтового сервера на учебном стенде нет. Запрос получит администратор и задаст временный пароль в разделе «Пользователи».</p>
<div class="field" style="margin:16px 0"><label for="email">Email учётной записи</label><input id="email" type="email" name="email" required></div>
<button class="btn btn-light" type="submit">Отправить запрос</button>
</form>
</div>
<div class="side-box">
<h3>Учётные записи из базы</h3>
<table>
<tr><th>Логин</th><th>Пароль</th><th>Роль</th></tr>
<tr><td>admin</td><td>1234</td><td>Администратор</td></tr>
<tr><td>organizer</td><td>5678</td><td>Организатор</td></tr>
<tr><td>student</td><td>1111</td><td>Участник</td></tr>
</table>
<p>Полные email: admin@prestige.local, organizer@prestige.local, student@prestige.local.</p>
<p>Гость без входа может только смотреть афишу и программы.</p>
</div>
</div>
<?php
    pageEnd();
    exit;
}

if ($p === 'register') {
    pageStart('Регистрация', $pdo, $user);
?>
<h2 class="page-title">Регистрация</h2>
<p class="lead">Новая учётная запись всегда создаётся с ролью «Участник». Роль организатора или администратора назначает только администратор в разделе пользователей.</p>
<?php showMessage($message, $error); ?>
<form class="form-box" id="registerForm" method="post">
<?= csrfField() ?><input type="hidden" name="action" value="register">
<div class="form-grid">
<div><label for="last_name">Фамилия *</label><input id="last_name" type="text" name="last_name" required value="<?= e($values['last_name']) ?>"></div>
<div><label for="first_name">Имя *</label><input id="first_name" type="text" name="first_name" required value="<?= e($values['first_name']) ?>"></div>
<div><label for="middle_name">Отчество</label><input id="middle_name" type="text" name="middle_name" value="<?= e($values['middle_name']) ?>"></div>
<div><label for="phone">Телефон</label><input id="phone" type="text" name="phone" value="<?= e($values['phone']) ?>" placeholder="+7 900 000-00-00"></div>
<div class="full"><label for="email">Email *</label><input id="email" type="email" name="email" required value="<?= e($values['email']) ?>"></div>
<div><label for="password">Пароль *</label><input id="password" type="password" name="password" required></div>
<div><label for="password2">Пароль ещё раз *</label><input id="password2" type="password" name="password2" required></div>
</div>
<div class="check-line">
<input id="agree" type="checkbox" name="agree" value="1">
<label for="agree" style="text-transform:none;letter-spacing:0;color:#161616;font-size:14px">Согласен на обработку персональных данных в соответствии с Федеральным законом № 152-ФЗ</label>
</div>
<button class="btn" type="submit">Создать учётную запись</button>
<p style="margin-top:14px">Уже есть учётная запись? <a href="account.php?p=login">Войти</a></p>
</form>
<?php
    pageEnd();
    exit;
}

needLogin($user);
$stmt = $pdo->prepare('SELECT reg.*, e.title, e.starts_at FROM registrations reg INNER JOIN events e ON e.id = reg.event_id WHERE reg.user_id = ? ORDER BY e.starts_at DESC');
$stmt->execute([$user['id']]);
$registrations = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT w.*, e.title, e.starts_at FROM waitlist w INNER JOIN events e ON e.id = w.event_id WHERE w.user_id = ? ORDER BY w.created_at DESC');
$stmt->execute([$user['id']]);
$waitItems = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC');
$stmt->execute([$user['id']]);
$notes = array_slice($stmt->fetchAll(), 0, 30);

pageStart('Личный кабинет', $pdo, $user);
?>
<h2 class="page-title">Кабинет</h2>
<p class="lead"><?= e(fullName($user)) ?> · <?= e($user['role_name']) ?> · <?= e($user['email']) ?></p>
<?php showMessage($message, $error); ?>
<h3 class="section">Мои мероприятия</h3>
<?php if (!$registrations): ?>
<p class="empty">Вы пока не записаны ни на одно мероприятие. Откройте <a href="index.php">афишу</a>.</p>
<?php else: ?>
<table>
<tr><th>Мероприятие</th><th>Дата</th><th>Статус</th><th>Билет</th><th></th></tr>
<?php foreach ($registrations as $reg): ?>
<tr>
<td><a href="index.php?p=event&id=<?= (int) $reg['event_id'] ?>"><?= e($reg['title']) ?></a></td>
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
<form method="post" data-confirm="Отменить эту запись?"><?= csrfField() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="reg_id" value="<?= (int) $reg['id'] ?>"><button class="btn btn-light btn-small" type="submit">Отменить</button></form>
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
<td><a href="index.php?p=event&id=<?= (int) $wait['event_id'] ?>"><?= e($wait['title']) ?></a></td>
<td><?= formatDate($wait['starts_at']) ?></td>
<td><?= (int) $wait['position'] ?></td>
<td><?= e(regStatusName($wait['status'])) ?></td>
<td>
<?php if ($wait['status'] === 'waiting'): ?>
<form method="post" data-confirm="Выйти из листа ожидания?"><?= csrfField() ?><input type="hidden" name="action" value="leave"><input type="hidden" name="wait_id" value="<?= (int) $wait['id'] ?>"><button class="btn btn-light btn-small" type="submit">Выйти</button></form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<h3 class="section" id="notifications">Уведомления</h3>
<form method="post" style="margin-bottom:14px"><?= csrfField() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-light btn-small" type="submit">Отметить все прочитанными</button></form>
<?php if (!$notes): ?><p class="empty">Уведомлений нет.</p><?php endif; ?>
<?php foreach ($notes as $note): ?>
<div class="notice-item <?= (int) $note['is_read'] === 0 ? 'unread' : '' ?>">
<strong><?= e($note['subject']) ?></strong> <span class="grey"><?= formatDate($note['created_at']) ?> · <?= e($note['type']) ?> · <?= e($note['channel']) ?></span>
<p><?= nl2br(e($note['message'])) ?></p>
<?php if ((int) $note['is_read'] === 0): ?>
<form method="post"><?= csrfField() ?><input type="hidden" name="action" value="read_one"><input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>"><button class="btn btn-light btn-small" type="submit">Прочитано</button></form>
<?php endif; ?>
</div>
<?php endforeach; ?>
<h3 class="section">Профиль в базе данных</h3>
<form class="form-box" method="post">
<?= csrfField() ?><input type="hidden" name="action" value="profile">
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
<?= csrfField() ?><input type="hidden" name="action" value="password">
<div class="form-grid">
<div><label>Текущий пароль</label><input type="password" name="old_password" required></div>
<div><label>Новый пароль</label><input type="password" name="new_password" required></div>
</div>
<p style="margin-top:14px"><button class="btn btn-light" type="submit">Сменить пароль</button></p>
</form>
<p class="grey">Роль в профиле поменять нельзя. Это делает только администратор.</p>
<?php pageEnd(); ?>
