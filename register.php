<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
if ($user) {
    redirect('cabinet.php');
}
$pageTitle = 'Регистрация';
$error = '';
$values = ['last_name' => '', 'first_name' => '', 'middle_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
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
            $stmt = $pdo->query("SELECT id FROM roles WHERE code = 'participant'");
            $roleId = (int) $stmt->fetchColumn();
            $ins = $pdo->prepare('INSERT INTO users (role_id, last_name, first_name, middle_name, email, phone, password_hash, status, email_verified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$roleId, $values['last_name'], $values['first_name'], $values['middle_name'] !== '' ? $values['middle_name'] : null, $values['email'], $values['phone'] !== '' ? $values['phone'] : null, password_hash($password, PASSWORD_DEFAULT), 'active', nowText(), nowText(), nowText()]);
            $newId = (int) $pdo->lastInsertId();
            $_SESSION['user_id'] = $newId;
            notify($pdo, $newId, null, 'welcome', 'Добро пожаловать в Prestige Events', 'Учётная запись участника создана. Теперь можно записываться на мероприятия из афиши.');
            logAction($pdo, $newId, 'register_account', 'users', $newId);
            redirect('cabinet.php');
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<h2 class="page-title">Регистрация</h2>
<p class="lead">Новая учётная запись всегда создаётся с ролью «Участник». Роль организатора или администратора назначает только администратор в разделе пользователей.</p>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

<form class="form-box" id="registerForm" method="post">
<?= csrfField() ?>
<div class="form-grid">
<div>
<label for="last_name">Фамилия *</label>
<input id="last_name" type="text" name="last_name" required value="<?= e($values['last_name']) ?>">
</div>
<div>
<label for="first_name">Имя *</label>
<input id="first_name" type="text" name="first_name" required value="<?= e($values['first_name']) ?>">
</div>
<div>
<label for="middle_name">Отчество</label>
<input id="middle_name" type="text" name="middle_name" value="<?= e($values['middle_name']) ?>">
</div>
<div>
<label for="phone">Телефон</label>
<input id="phone" type="text" name="phone" value="<?= e($values['phone']) ?>" placeholder="+7 900 000-00-00">
</div>
<div class="full">
<label for="email">Email *</label>
<input id="email" type="email" name="email" required value="<?= e($values['email']) ?>">
</div>
<div>
<label for="password">Пароль *</label>
<input id="password" type="password" name="password" required>
</div>
<div>
<label for="password2">Пароль ещё раз *</label>
<input id="password2" type="password" name="password2" required>
</div>
</div>
<div class="check-line">
<input id="agree" type="checkbox" name="agree" value="1">
<label for="agree" style="text-transform:none;letter-spacing:0;color:#161616;font-size:14px">Согласен на обработку персональных данных в соответствии с Федеральным законом № 152-ФЗ</label>
</div>
<button class="btn" type="submit">Создать учётную запись</button>
<p style="margin-top:14px">Уже есть учётная запись? <a href="login.php">Войти</a></p>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
