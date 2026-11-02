<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['admin']);
$pageTitle = 'Пользователи';
$message = trim($_GET['msg'] ?? '');
$error = trim($_GET['err'] ?? '');
$editId = (int) ($_GET['edit'] ?? 0);
$edit = ['last_name' => '', 'first_name' => '', 'middle_name' => '', 'email' => '', 'phone' => '', 'role_id' => '', 'status' => 'active'];

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$editId]);
    $found = $stmt->fetch();
    if (!$found) {
        redirect('users.php');
    }
    $edit = $found;
}

$roles = $pdo->query('SELECT * FROM roles ORDER BY id ASC')->fetchAll();
$systemRoles = ['guest', 'participant', 'organizer', 'admin'];
$roleEditId = (int) ($_GET['role_edit'] ?? 0);
$roleEdit = ['code' => '', 'name' => '', 'description' => ''];
if ($roleEditId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = ?');
    $stmt->execute([$roleEditId]);
    $foundRole = $stmt->fetch();
    if ($foundRole) {
        $roleEdit = $foundRole;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'user_delete') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($targetId === (int) $user['id']) {
            redirect('users.php?err=' . urlencode('Нельзя удалить собственную учётную запись.'));
        }
        try {
            $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $del->execute([$targetId]);
            logAction($pdo, $user['id'], 'user_delete', 'users', $targetId);
            redirect('users.php?msg=' . urlencode('Пользователь удалён. Связанные записи и уведомления удалены по внешним ключам, журнал действий сохранён.'));
        } catch (PDOException $ex) {
            redirect('users.php?err=' . urlencode('Нельзя удалить пользователя: он является организатором мероприятий. Сначала измените или удалите его мероприятия.'));
        }
    }
    if ($action === 'role_save') {
        $roleEditId = (int) ($_POST['item_id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $roleEdit = ['code' => $code, 'name' => $name, 'description' => $description];
        if ($code === '' || $name === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
            $error = 'Код роли — латинские буквы, цифры и подчёркивание с буквы в начале; название обязательно.';
        } elseif ($roleEditId > 0) {
            $stmt = $pdo->prepare('SELECT code FROM roles WHERE id = ?');
            $stmt->execute([$roleEditId]);
            $oldCode = $stmt->fetchColumn();
            if (!$oldCode) {
                redirect('users.php#roles');
            }
            if (in_array($oldCode, $systemRoles, true) && $code !== $oldCode) {
                $error = 'Код системной роли менять нельзя: от него зависит разграничение прав.';
            } else {
                try {
                    $upd = $pdo->prepare('UPDATE roles SET code = ?, name = ?, description = ? WHERE id = ?');
                    $upd->execute([$code, $name, $description !== '' ? $description : null, $roleEditId]);
                    logAction($pdo, $user['id'], 'role_update', 'roles', $roleEditId);
                    redirect('users.php?msg=' . urlencode('Роль обновлена в базе данных.') . '#roles');
                } catch (PDOException $ex) {
                    $error = 'Роль с таким кодом уже есть.';
                }
            }
        } else {
            try {
                $ins = $pdo->prepare('INSERT INTO roles (code, name, description) VALUES (?, ?, ?)');
                $ins->execute([$code, $name, $description !== '' ? $description : null]);
                logAction($pdo, $user['id'], 'role_create', 'roles', insertedId($pdo));
                redirect('users.php?msg=' . urlencode('Роль добавлена в базу данных.') . '#roles');
            } catch (PDOException $ex) {
                $error = 'Роль с таким кодом уже есть.';
            }
        }
    }
    if ($action === 'role_delete') {
        $delId = (int) ($_POST['item_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT code FROM roles WHERE id = ?');
        $stmt->execute([$delId]);
        $code = (string) $stmt->fetchColumn();
        if (in_array($code, $systemRoles, true)) {
            $error = 'Системные роли удалять нельзя: на них завязаны права доступа.';
        } else {
            try {
                $del = $pdo->prepare('DELETE FROM roles WHERE id = ?');
                $del->execute([$delId]);
                logAction($pdo, $user['id'], 'role_delete', 'roles', $delId);
                redirect('users.php?msg=' . urlencode('Роль удалена из базы данных.') . '#roles');
            } catch (PDOException $ex) {
                $error = 'Роль используется пользователями. Сначала назначьте им другую роль.';
            }
        }
    }
    if ($action === 'user_save') {
        $editId = (int) ($_POST['edit_id'] ?? 0);
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
        } elseif ($editId === 0 && mb_strlen($password) < 4) {
            $error = 'Для нового пользователя задайте пароль не короче 4 символов.';
        } elseif ($editId > 0 && $password !== '' && mb_strlen($password) < 4) {
            $error = 'Новый пароль должен быть не короче 4 символов.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $stmt->execute([$edit['email'], $editId]);
            if ($stmt->fetch()) {
                $error = 'Email уже занят.';
            } else {
                if ($editId > 0) {
                    if ($password !== '') {
                        $upd = $pdo->prepare('UPDATE users SET role_id = ?, last_name = ?, first_name = ?, middle_name = ?, email = ?, phone = ?, status = ?, password_hash = ?, updated_at = ? WHERE id = ?');
                        $upd->execute([$edit['role_id'], $edit['last_name'], $edit['first_name'], $edit['middle_name'] !== '' ? $edit['middle_name'] : null, $edit['email'], $edit['phone'] !== '' ? $edit['phone'] : null, $edit['status'], password_hash($password, PASSWORD_DEFAULT), nowText(), $editId]);
                    } else {
                        $upd = $pdo->prepare('UPDATE users SET role_id = ?, last_name = ?, first_name = ?, middle_name = ?, email = ?, phone = ?, status = ?, updated_at = ? WHERE id = ?');
                        $upd->execute([$edit['role_id'], $edit['last_name'], $edit['first_name'], $edit['middle_name'] !== '' ? $edit['middle_name'] : null, $edit['email'], $edit['phone'] !== '' ? $edit['phone'] : null, $edit['status'], nowText(), $editId]);
                    }
                    logAction($pdo, $user['id'], 'user_update', 'users', $editId);
                    redirect('users.php?msg=' . urlencode('Пользователь обновлён в базе данных.'));
                } else {
                    $ins = $pdo->prepare('INSERT INTO users (role_id, last_name, first_name, middle_name, email, phone, password_hash, status, email_verified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $ins->execute([$edit['role_id'], $edit['last_name'], $edit['first_name'], $edit['middle_name'] !== '' ? $edit['middle_name'] : null, $edit['email'], $edit['phone'] !== '' ? $edit['phone'] : null, password_hash($password, PASSWORD_DEFAULT), $edit['status'], nowText(), nowText(), nowText()]);
                    logAction($pdo, $user['id'], 'user_create', 'users', insertedId($pdo));
                    redirect('users.php?msg=' . urlencode('Пользователь добавлен в базу данных.'));
                }
            }
        }
    }
}

$rows = $pdo->query('SELECT u.*, r.name AS role_name, r.code AS role_code FROM users u INNER JOIN roles r ON r.id = u.role_id ORDER BY u.id ASC')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<p><a href="admin.php">← Администратор</a></p>
<h2 class="page-title">Пользователи</h2>
<p class="lead">Таблица users. Только здесь администратор может менять роль, статус и задавать пароль. Обычный пользователь свою роль изменить не может.</p>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

<p class="actions"><a class="btn" href="users.php#user-form">Новый пользователь</a> <a class="btn btn-light" href="users.php#roles">Роли и права</a></p>
<table style="margin-top:18px">
<tr><th>ID</th><th>ФИО</th><th>Логин / Email</th><th>Телефон</th><th>Роль в БД</th><th>Статус</th><th></th></tr>
<?php foreach ($rows as $row): ?>
<tr>
<td><?= (int) $row['id'] ?></td>
<td><?= e(fullName($row)) ?></td>
<td><?= e($row['email']) ?></td>
<td><?= e($row['phone'] ?? '') ?></td>
<td><span class="badge"><?= e($row['role_name']) ?></span><br><span class="grey"><?= e($row['role_code']) ?></span></td>
<td><?= e(userStatusName($row['status'])) ?></td>
<td>
<div class="actions">
<a class="btn btn-light btn-small" href="users.php?edit=<?= (int) $row['id'] ?>#user-form">Изменить</a>
<?php if ((int) $row['id'] !== (int) $user['id']): ?>
<form method="post" data-confirm="Удалить пользователя из базы?">
<?= csrfField() ?>
<input type="hidden" name="action" value="user_delete">
<input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
<button class="btn btn-small" type="submit">Удалить</button>
</form>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</table>

<h3 class="section" id="user-form"><?= $editId > 0 ? 'Изменить пользователя' : 'Новый пользователь' ?></h3>
<p class="lead">Роль и статус пишутся в таблицу users (role_id, status). Пароль хранится только в виде хэша password_hash.</p>
<form class="form-box" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="user_save">
<input type="hidden" name="edit_id" value="<?= $editId ?>">
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
<div class="full"><label><?= $editId > 0 ? 'Новый пароль (пусто — не менять)' : 'Пароль *' ?></label><input type="text" name="password" placeholder="Например: 1234"></div>
</div>
<p style="margin-top:16px"><button class="btn" type="submit">Сохранить в базу</button> <a class="btn btn-light" href="users.php">Сбросить форму</a></p>
</form>
<h3 class="section" id="roles">Роли</h3>
<p class="lead">Таблица roles. Четыре системные роли задают права доступа, их код менять нельзя. Названия и описания правятся, свои роли можно добавить и удалить.</p>
<table>
<tr><th>ID</th><th>Код</th><th>Название</th><th>Описание</th><th>Пользователей</th><th></th></tr>
<?php foreach ($roles as $role): ?>
<?php $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?'); $stmt->execute([$role['id']]); $roleCount = (int) $stmt->fetchColumn(); ?>
<tr>
<td><?= (int) $role['id'] ?></td>
<td><span class="badge"><?= e($role['code']) ?></span></td>
<td><strong><?= e($role['name']) ?></strong></td>
<td><?= e($role['description'] ?? '') ?></td>
<td><?= $roleCount ?></td>
<td><div class="actions">
<a class="btn btn-light btn-small" href="users.php?role_edit=<?= (int) $role['id'] ?>#role-form">Изменить</a>
<?php if (!in_array($role['code'], $systemRoles, true)): ?>
<form method="post" data-confirm="Удалить роль из базы?"><?= csrfField() ?><input type="hidden" name="action" value="role_delete"><input type="hidden" name="item_id" value="<?= (int) $role['id'] ?>"><button class="btn btn-small" type="submit">Удалить</button></form>
<?php endif; ?>
</div></td>
</tr>
<?php endforeach; ?>
</table>

<h3 class="section" id="role-form"><?= $roleEditId > 0 ? 'Изменить роль' : 'Добавить роль' ?></h3>
<form class="form-box" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="role_save">
<input type="hidden" name="item_id" value="<?= $roleEditId ?>">
<div class="form-grid">
<div><label>Код *</label><input type="text" name="code" required maxlength="32" value="<?= e($roleEdit['code']) ?>"></div>
<div><label>Название *</label><input type="text" name="name" required maxlength="64" value="<?= e($roleEdit['name']) ?>"></div>
<div class="full"><label>Описание</label><input type="text" name="description" maxlength="255" value="<?= e($roleEdit['description'] ?? '') ?>"></div>
</div>
<p style="margin-top:14px"><button class="btn" type="submit">Сохранить в базу</button> <a class="btn btn-light" href="users.php#roles">Сбросить</a></p>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
