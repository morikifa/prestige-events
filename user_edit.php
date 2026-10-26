<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['admin']);
$pageTitle = 'Пользователь';
$id = (int) ($_GET['id'] ?? 0);
$error = '';

$edit = ['last_name' => '', 'first_name' => '', 'middle_name' => '', 'email' => '', 'phone' => '', 'role_id' => '', 'status' => 'active'];
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        redirect('users.php');
    }
    $edit = $found;
}

$roles = $pdo->query('SELECT * FROM roles ORDER BY id ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $edit['last_name'] = trim($_POST['last_name'] ?? '');
    $edit['first_name'] = trim($_POST['first_name'] ?? '');
    $edit['middle_name'] = trim($_POST['middle_name'] ?? '');
    $edit['email'] = trim($_POST['email'] ?? '');
    $edit['phone'] = trim($_POST['phone'] ?? '');
    $edit['role_id'] = (int) ($_POST['role_id'] ?? 0);
    $edit['status'] = $_POST['status'] ?? 'active';
    $password = (string) ($_POST['password'] ?? '');

    if ($edit['last_name'] === '' || $edit['first_name'] === '' || !filter_var($edit['email'], FILTER_VALIDATE_EMAIL) || $edit['role_id'] <= 0) {
        $error = 'Заполните фамилию, имя, корректный email и выберите роль.';
    } elseif ($id === 0 && mb_strlen($password) < 4) {
        $error = 'Для нового пользователя задайте пароль не короче 4 символов.';
    } elseif ($id > 0 && $password !== '' && mb_strlen($password) < 4) {
        $error = 'Новый пароль должен быть не короче 4 символов.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $stmt->execute([$edit['email'], $id]);
        if ($stmt->fetch()) {
            $error = 'Email уже занят.';
        } else {
            if ($id > 0) {
                if ($password !== '') {
                    $upd = $pdo->prepare('UPDATE users SET role_id = ?, last_name = ?, first_name = ?, middle_name = ?, email = ?, phone = ?, status = ?, password_hash = ?, updated_at = ? WHERE id = ?');
                    $upd->execute([$edit['role_id'], $edit['last_name'], $edit['first_name'], $edit['middle_name'] !== '' ? $edit['middle_name'] : null, $edit['email'], $edit['phone'] !== '' ? $edit['phone'] : null, $edit['status'], password_hash($password, PASSWORD_DEFAULT), nowText(), $id]);
                } else {
                    $upd = $pdo->prepare('UPDATE users SET role_id = ?, last_name = ?, first_name = ?, middle_name = ?, email = ?, phone = ?, status = ?, updated_at = ? WHERE id = ?');
                    $upd->execute([$edit['role_id'], $edit['last_name'], $edit['first_name'], $edit['middle_name'] !== '' ? $edit['middle_name'] : null, $edit['email'], $edit['phone'] !== '' ? $edit['phone'] : null, $edit['status'], nowText(), $id]);
                }
                logAction($pdo, $user['id'], 'user_update', 'users', $id);
                redirect('users.php?msg=' . urlencode('Пользователь обновлён в базе данных.'));
            } else {
                $ins = $pdo->prepare('INSERT INTO users (role_id, last_name, first_name, middle_name, email, phone, password_hash, status, email_verified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([$edit['role_id'], $edit['last_name'], $edit['first_name'], $edit['middle_name'] !== '' ? $edit['middle_name'] : null, $edit['email'], $edit['phone'] !== '' ? $edit['phone'] : null, password_hash($password, PASSWORD_DEFAULT), $edit['status'], nowText(), nowText(), nowText()]);
                logAction($pdo, $user['id'], 'user_create', 'users', (int) $pdo->lastInsertId());
                redirect('users.php?msg=' . urlencode('Пользователь добавлен в базу данных.'));
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<p><a href="users.php">← Пользователи</a></p>
<h2 class="page-title"><?= $id > 0 ? 'Изменить' : 'Новый' ?></h2>
<p class="lead">Роль и статус пишутся в таблицу users (role_id, status). Пароль хранится только в виде хэша password_hash.</p>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

<form class="form-box" method="post">
<?= csrfField() ?>
<div class="form-grid">
<div><label>Фамилия *</label><input type="text" name="last_name" required maxlength="64" value="<?= e($edit['last_name']) ?>"></div>
<div><label>Имя *</label><input type="text" name="first_name" required maxlength="64" value="<?= e($edit['first_name']) ?>"></div>
<div><label>Отчество</label><input type="text" name="middle_name" maxlength="64" value="<?= e($edit['middle_name'] ?? '') ?>"></div>
<div><label>Телефон</label><input type="text" name="phone" maxlength="20" value="<?= e($edit['phone'] ?? '') ?>"></div>
<div class="full"><label>Email (логин) *</label><input type="email" name="email" required maxlength="128" value="<?= e($edit['email']) ?>"></div>
<div>
<label>Роль</label>
<select name="role_id">
<?php foreach ($roles as $role): ?>
<?php if ($role['code'] === 'guest') { continue; } ?>
<option value="<?= (int) $role['id'] ?>" <?= (string) $edit['role_id'] === (string) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?> (<?= e($role['code']) ?>)</option>
<?php endforeach; ?>
</select>
</div>
<div>
<label>Статус</label>
<select name="status">
<?php foreach (['active', 'blocked', 'pending'] as $st): ?>
<option value="<?= $st ?>" <?= $edit['status'] === $st ? 'selected' : '' ?>><?= e(userStatusName($st)) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="full"><label><?= $id > 0 ? 'Новый пароль (пусто — не менять)' : 'Пароль *' ?></label><input type="text" name="password" placeholder="Например: 1234"></div>
</div>
<p style="margin-top:16px"><button class="btn" type="submit">Сохранить в базу</button> <a class="btn btn-light" href="users.php">Отмена</a></p>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
