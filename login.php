<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
if ($user) {
    redirect('cabinet.php');
}
$pageTitle = 'Вход';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
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
            redirect('organizer.php');
        }
        redirect('cabinet.php');
    }
}

require __DIR__ . '/includes/header.php';
?>
<h2 class="page-title">Вход</h2>
<p class="lead">Войдите по логину и паролю из таблицы ниже или по полному email вашей учётной записи. Роль определяется по базе данных и меняется только администратором.</p>

<?php if ($error): ?>
<div class="message error"><?= e($error) ?></div>
<?php endif; ?>

<div class="two-col">
<form class="narrow" method="post">
<?= csrfField() ?>
<div class="field" style="margin-bottom:16px">
<label for="login">Логин</label>
<input id="login" type="text" name="login" required autocomplete="username" placeholder="admin">
</div>
<div class="field" style="margin-bottom:16px">
<label for="password">Пароль</label>
<input id="password" type="password" name="password" required autocomplete="current-password" placeholder="1234">
</div>
<button class="btn" type="submit">Войти</button>
<p style="margin-top:16px"><a href="register.php">Регистрация участника</a> · <a href="forgot.php">Восстановление пароля</a></p>
</form>
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
<?php require __DIR__ . '/includes/footer.php'; ?>
