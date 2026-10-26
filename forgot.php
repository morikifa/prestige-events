<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
$pageTitle = 'Восстановление пароля';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
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
            $ins = $pdo->prepare('INSERT INTO notifications (user_id, event_id, type, channel, subject, message, status, is_read, created_at) VALUES (?, NULL, ?, ?, ?, ?, ?, 0, ?)');
            $ins->execute([$found['id'], 'password_reset', 'email', 'Восстановление пароля Prestige Events', 'Запрос на восстановление пароля получен. Администратор задаст временный пароль и сообщит его лично.', 'pending', nowText()]);
            logAction($pdo, $found['id'], 'password_reset_request', 'users', $found['id']);
        }
        $message = 'Если учётная запись с таким email есть, запрос отправлен администратору. Он задаст временный пароль в разделе «Пользователи» и сообщит его лично.';
    }
}

require __DIR__ . '/includes/header.php';
?>
<h2 class="page-title">Пароль</h2>
<p class="lead">На учебной платформе нет почтового сервера, поэтому восстановление идёт через администратора: запрос фиксируется в журнале и в уведомлениях, администратор задаёт временный пароль в базе через раздел «Пользователи».</p>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>
<form class="narrow" method="post">
<?= csrfField() ?>
<div style="margin-bottom:16px">
<label for="email">Email учётной записи</label>
<input id="email" type="email" name="email" required>
</div>
<button class="btn" type="submit">Отправить запрос</button>
</form>
<p style="margin-top:16px"><a href="login.php">Вернуться ко входу</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
