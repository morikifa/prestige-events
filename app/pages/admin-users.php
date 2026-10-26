<?php

$user = current_user();
$roles = db_all('SELECT id, code, name FROM roles ORDER BY id');
$errors = [];

$editId = (int) input('edit', 0);
$editUser = $editId > 0 ? db_one('SELECT * FROM users WHERE id = ?', [$editId]) : null;

$values = [
    'last_name' => $editUser['last_name'] ?? '',
    'first_name' => $editUser['first_name'] ?? '',
    'middle_name' => $editUser['middle_name'] ?? '',
    'email' => $editUser['email'] ?? '',
    'phone' => $editUser['phone'] ?? '',
    'role_id' => $editUser['role_id'] ?? '',
    'status' => $editUser['status'] ?? 'active',
];

if (is_post()) {
    $action = (string) input('action');
    $targetId = (int) input('user_id', 0);

    if ($action === 'delete') {
        if ($targetId === (int) $user['id']) {
            flash('error', 'Нельзя удалить собственную учётную запись.');
        } else {
            db_exec('DELETE FROM users WHERE id = ?', [$targetId]);
            log_action('user_deleted', 'user', $targetId);
            flash('success', 'Пользователь удалён.');
        }
        redirect('admin/users');
    }

    if ($action === 'status') {
        $status = (string) input('status');
        if (!in_array($status, ['active', 'blocked', 'pending'], true)) {
            flash('error', 'Неизвестный статус.');
        } elseif ($targetId === (int) $user['id'] && $status !== 'active') {
            flash('error', 'Нельзя заблокировать собственную учётную запись.');
        } else {
            db_exec('UPDATE users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$status, $targetId]);
            log_action('user_status_changed', 'user', $targetId, ['status' => $status]);
            flash('success', 'Статус пользователя обновлён.');
        }
        redirect('admin/users');
    }

    if ($action === 'save') {
        foreach (array_keys($values) as $key) {
            $values[$key] = (string) input($key, '');
        }
        $password = (string) ($_POST['password'] ?? '');

        if (trim($values['last_name']) === '' || trim($values['first_name']) === '') {
            $errors[] = 'Укажите фамилию и имя.';
        }
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Укажите корректный адрес электронной почты.';
        } else {
            $sql = 'SELECT COUNT(*) FROM users WHERE LOWER(email) = ?';
            $params = [mb_strtolower($values['email'], 'UTF-8')];
            if ($targetId > 0) {
                $sql .= ' AND id <> ?';
                $params[] = $targetId;
            }
            if ((int) db_val($sql, $params, 0) > 0) {
                $errors[] = 'Пользователь с такой почтой уже существует.';
            }
        }
        if (!in_array($values['status'], ['active', 'blocked', 'pending'], true)) {
            $errors[] = 'Неизвестный статус пользователя.';
        }
        if ((int) $values['role_id'] <= 0) {
            $errors[] = 'Выберите роль.';
        }
        if ($targetId === 0 && mb_strlen($password, 'UTF-8') < 8) {
            $errors[] = 'Пароль нового пользователя должен быть не короче 8 символов.';
        }
        if ($targetId === (int) $user['id'] && (int) $values['role_id'] !== (int) $user['role_id']) {
            $errors[] = 'Нельзя изменить собственную роль.';
        }
        if ($targetId === (int) $user['id'] && $values['status'] !== 'active') {
            $errors[] = 'Нельзя изменить статус собственной учётной записи.';
        }

        if (!$errors) {
            $params = [
                (int) $values['role_id'],
                trim($values['last_name']),
                trim($values['first_name']),
                trim($values['middle_name']) !== '' ? trim($values['middle_name']) : null,
                mb_strtolower($values['email'], 'UTF-8'),
                trim($values['phone']) !== '' ? trim($values['phone']) : null,
                $values['status'],
            ];

            if ($targetId > 0) {
                $updateParams = $params;
                $updateParams[] = $targetId;
                if ($password !== '') {
                    $updateParams = $params;
                    $updateParams[] = password_hash($password, PASSWORD_DEFAULT);
                    $updateParams[] = $targetId;
                    db_exec(
                        'UPDATE users SET role_id = ?, last_name = ?, first_name = ?, middle_name = ?, email = ?, phone = ?, status = ?,
                                password_hash = ?, updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?',
                        $updateParams
                    );
                } else {
                    db_exec(
                        'UPDATE users SET role_id = ?, last_name = ?, first_name = ?, middle_name = ?, email = ?, phone = ?, status = ?,
                                updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?',
                        $updateParams
                    );
                }
                log_action('user_updated', 'user', $targetId, ['role_id' => (int) $values['role_id']]);
                flash('success', 'Пользователь сохранён.');
            } else {
                db_exec(
                    'INSERT INTO users (role_id, last_name, first_name, middle_name, email, phone, status, password_hash)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    array_merge($params, [password_hash($password, PASSWORD_DEFAULT)])
                );
                log_action('user_created', 'user', db_last_id(), ['role_id' => (int) $values['role_id']]);
                flash('success', 'Пользователь создан.');
            }

            redirect('admin/users');
        }
    }
}

$roleFilter = (int) input('role', 0);
$statusFilter = (string) input('status', '');
$search = (string) input('search', '');

$sql = 'SELECT u.*, r.code AS role_code, r.name AS role_name
        FROM users u JOIN roles r ON r.id = u.role_id
        WHERE 1 = 1';
$params = [];

if ($roleFilter > 0) {
    $sql .= ' AND u.role_id = ?';
    $params[] = $roleFilter;
}
if ($statusFilter !== '') {
    $sql .= ' AND u.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= ' AND (u.last_name LIKE ? OR u.first_name LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY u.last_name, u.first_name';

$users = db_all($sql, $params);

render('admin-users', [
    'title' => 'Пользователи',
    'navActive' => 'admin',
    'users' => $users,
    'roles' => $roles,
    'values' => $values,
    'errors' => $errors,
    'editUser' => $editUser,
    'roleFilter' => $roleFilter,
    'statusFilter' => $statusFilter,
    'search' => $search,
    'currentUser' => $user,
]);
