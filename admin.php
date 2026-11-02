<?php
require __DIR__ . '/db.php';
require __DIR__ . '/core.php';

$user = currentUser($pdo);
needRole($user, ['admin']);
$p = $_GET['p'] ?? 'home';
$message = trim($_GET['msg'] ?? '');
$error = trim($_GET['err'] ?? '');
$search = trim($_GET['q'] ?? '');
$systemRoles = ['guest', 'participant', 'organizer', 'admin'];
$backupDir = __DIR__ . '/data/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

if ($p === 'backup' && isset($_GET['download']) && $GLOBALS['dbType'] === 'sqlite') {
    $name = basename((string) $_GET['download']);
    $file = $backupDir . '/' . $name;
    if (is_file($file) && substr($name, -3) === '.db') {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        readfile($file);
        exit;
    }
    die('Файл копии не найден.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'moderate') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['draft', 'moderation', 'published', 'cancelled', 'completed'], true)) {
            $upd = $pdo->prepare('UPDATE events SET status = ?, updated_at = ? WHERE id = ?');
            $upd->execute([$status, nowText(), $eventId]);
            logAction($pdo, $user['id'], 'event_moderation_' . $status, 'events', $eventId);
        }
        redirect('admin.php?msg=' . urlencode('Статус мероприятия изменён администратором.'));
    }

    if ($action === 'user_save' || $action === 'user_delete') {
        if ($action === 'user_delete') {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            if ($targetId === (int) $user['id']) {
                redirect('admin.php?p=users&err=' . urlencode('Нельзя удалить собственную учётную запись.'));
            }
            try {
                $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $del->execute([$targetId]);
                logAction($pdo, $user['id'], 'user_delete', 'users', $targetId);
                redirect('admin.php?p=users&msg=' . urlencode('Пользователь удалён. Связанные записи и уведомления удалены по внешним ключам, журнал действий сохранён.'));
            } catch (PDOException $ex) {
                redirect('admin.php?p=users&err=' . urlencode('Нельзя удалить пользователя: он является организатором мероприятий. Сначала измените или удалите его мероприятия.'));
            }
        }
        $editId = (int) ($_POST['edit_id'] ?? 0);
        $edit = [
            'last_name' => trim($_POST['last_name'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'middle_name' => trim($_POST['middle_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'role_id' => (int) ($_POST['role_id'] ?? 0),
            'status' => $_POST['status'] ?? 'active'
        ];
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
                    redirect('admin.php?p=users&msg=' . urlencode('Пользователь обновлён в базе данных.'));
                } else {
                    $ins = $pdo->prepare('INSERT INTO users (role_id, last_name, first_name, middle_name, email, phone, password_hash, status, email_verified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $ins->execute([$edit['role_id'], $edit['last_name'], $edit['first_name'], $edit['middle_name'] !== '' ? $edit['middle_name'] : null, $edit['email'], $edit['phone'] !== '' ? $edit['phone'] : null, password_hash($password, PASSWORD_DEFAULT), $edit['status'], nowText(), nowText(), nowText()]);
                    logAction($pdo, $user['id'], 'user_create', 'users', insertedId($pdo));
                    redirect('admin.php?p=users&msg=' . urlencode('Пользователь добавлен в базу данных.'));
                }
            }
        }
        $p = 'users';
        $userEdit = $edit;
        $userEditId = $editId;
    }

    if ($action === 'role_save' || $action === 'role_delete') {
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
                    redirect('admin.php?p=users&msg=' . urlencode('Роль удалена из базы данных.') . '#roles');
                } catch (PDOException $ex) {
                    $error = 'Роль используется пользователями. Сначала назначьте им другую роль.';
                }
            }
        } else {
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
                    redirect('admin.php?p=users#roles');
                }
                if (in_array($oldCode, $systemRoles, true) && $code !== $oldCode) {
                    $error = 'Код системной роли менять нельзя: от него зависит разграничение прав.';
                } else {
                    try {
                        $upd = $pdo->prepare('UPDATE roles SET code = ?, name = ?, description = ? WHERE id = ?');
                        $upd->execute([$code, $name, $description !== '' ? $description : null, $roleEditId]);
                        logAction($pdo, $user['id'], 'role_update', 'roles', $roleEditId);
                        redirect('admin.php?p=users&msg=' . urlencode('Роль обновлена в базе данных.') . '#roles');
                    } catch (PDOException $ex) {
                        $error = 'Роль с таким кодом уже есть.';
                    }
                }
            } else {
                try {
                    $ins = $pdo->prepare('INSERT INTO roles (code, name, description) VALUES (?, ?, ?)');
                    $ins->execute([$code, $name, $description !== '' ? $description : null]);
                    logAction($pdo, $user['id'], 'role_create', 'roles', insertedId($pdo));
                    redirect('admin.php?p=users&msg=' . urlencode('Роль добавлена в базу данных.') . '#roles');
                } catch (PDOException $ex) {
                    $error = 'Роль с таким кодом уже есть.';
                }
            }
        }
        $p = 'users';
    }

    if ($action === 'cat_save' || $action === 'cat_delete') {
        if ($action === 'cat_delete') {
            try {
                $del = $pdo->prepare('DELETE FROM categories WHERE id = ?');
                $del->execute([(int) ($_POST['item_id'] ?? 0)]);
                logAction($pdo, $user['id'], 'category_delete', 'categories', (int) ($_POST['item_id'] ?? 0));
                redirect('admin.php?p=dirs&msg=' . urlencode('Категория удалена.'));
            } catch (PDOException $ex) {
                redirect('admin.php?p=dirs&err=' . urlencode('Нельзя удалить категорию, в которой есть мероприятия. Сначала измените категорию мероприятий.'));
            }
        }
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $saveId = (int) ($_POST['item_id'] ?? 0);
        if ($name === '') {
            $error = 'Название категории не может быть пустым.';
        } else {
            try {
                if ($saveId > 0) {
                    $upd = $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
                    $upd->execute([$name, $description !== '' ? $description : null, $saveId]);
                    logAction($pdo, $user['id'], 'category_update', 'categories', $saveId);
                } else {
                    $ins = $pdo->prepare('INSERT INTO categories (name, description, created_at) VALUES (?, ?, ?)');
                    $ins->execute([$name, $description !== '' ? $description : null, nowText()]);
                    logAction($pdo, $user['id'], 'category_create', 'categories', insertedId($pdo));
                }
                redirect('admin.php?p=dirs&msg=' . urlencode('Категория сохранена в базе данных.'));
            } catch (PDOException $ex) {
                $error = 'Категория с таким названием уже есть.';
            }
        }
        $p = 'dirs';
    }

    if ($action === 'venue_save' || $action === 'venue_delete') {
        if ($action === 'venue_delete') {
            $del = $pdo->prepare('DELETE FROM venues WHERE id = ?');
            $del->execute([(int) ($_POST['item_id'] ?? 0)]);
            logAction($pdo, $user['id'], 'venue_delete', 'venues', (int) ($_POST['item_id'] ?? 0));
            redirect('admin.php?p=dirs&msg=' . urlencode('Площадка удалена. У связанных мероприятий площадка очищена (ON DELETE SET NULL).'));
        }
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $capText = trim($_POST['capacity'] ?? '');
        $capacity = $capText !== '' ? (int) $capText : null;
        $saveId = (int) ($_POST['item_id'] ?? 0);
        if ($name === '' || $address === '') {
            $error = 'Заполните название и адрес площадки.';
        } elseif ($capacity !== null && $capacity <= 0) {
            $error = 'Вместимость должна быть больше нуля или пустой.';
        } else {
            if ($saveId > 0) {
                $upd = $pdo->prepare('UPDATE venues SET name = ?, address = ?, capacity = ? WHERE id = ?');
                $upd->execute([$name, $address, $capacity, $saveId]);
                logAction($pdo, $user['id'], 'venue_update', 'venues', $saveId);
            } else {
                $ins = $pdo->prepare('INSERT INTO venues (name, address, capacity, created_at) VALUES (?, ?, ?, ?)');
                $ins->execute([$name, $address, $capacity, nowText()]);
                logAction($pdo, $user['id'], 'venue_create', 'venues', insertedId($pdo));
            }
            redirect('admin.php?p=dirs&msg=' . urlencode('Площадка сохранена в базе данных.'));
        }
        $p = 'dirs';
    }

    if ($action === 'settings_save') {
        $values = $_POST['values'] ?? [];
        if (is_array($values)) {
            $upd = $pdo->prepare('UPDATE settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?');
            foreach ($values as $key => $value) {
                $upd->execute([trim((string) $value), nowText(), $key]);
            }
            logAction($pdo, $user['id'], 'settings_update', 'settings', null);
        }
        redirect('admin.php?p=settings&msg=' . urlencode('Настройки сохранены в таблице settings. Название платформы в шапке обновилось.') . '#settings');
    }

    if ($action === 'setting_add') {
        $key = trim($_POST['new_key'] ?? '');
        $value = trim($_POST['new_value'] ?? '');
        $description = trim($_POST['new_description'] ?? '');
        if ($key === '' || $value === '' || !preg_match('/^[a-z0-9_]+$/', $key)) {
            redirect('admin.php?p=settings&msg=' . urlencode('Ключ — латинские буквы, цифры и подчёркивание, значение непустое.') . '#settings');
        }
        $stmt = $pdo->prepare('SELECT setting_key FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        if ($stmt->fetch()) {
            redirect('admin.php?p=settings&msg=' . urlencode('Такой параметр уже есть.') . '#settings');
        }
        $ins = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, description, updated_at) VALUES (?, ?, ?, ?)');
        $ins->execute([$key, $value, $description !== '' ? $description : null, nowText()]);
        logAction($pdo, $user['id'], 'setting_create', 'settings', null);
        redirect('admin.php?p=settings&msg=' . urlencode('Параметр добавлен в таблицу settings.') . '#settings');
    }

    if ($action === 'setting_delete') {
        $del = $pdo->prepare('DELETE FROM settings WHERE setting_key = ?');
        $del->execute([trim($_POST['setting_key'] ?? '')]);
        logAction($pdo, $user['id'], 'setting_delete', 'settings', null);
        redirect('admin.php?p=settings&msg=' . urlencode('Параметр удалён из таблицы settings.') . '#settings');
    }

    if ($action === 'mail_send' || $action === 'mail_mark') {
        $noteId = (int) ($_POST['note_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT n.*, u.email FROM notifications n INNER JOIN users u ON u.id = n.user_id WHERE n.id = ? AND n.channel = 'email'");
        $stmt->execute([$noteId]);
        $note = $stmt->fetch();
        $sent = false;
        if ($note && $action === 'mail_mark') {
            $sent = true;
        } elseif ($note && function_exists('mail')) {
            $headers = 'From: Prestige Events <no-reply@prestige.local>' . "\r\n" . 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
            $sent = @mail($note['email'], '=?UTF-8?B?' . base64_encode($note['subject']) . '?=', $note['message'], $headers);
        }
        if ($note) {
            $upd = $pdo->prepare('UPDATE notifications SET status = ?, sent_at = ? WHERE id = ?');
            $upd->execute([$sent ? 'sent' : 'failed', $sent ? nowText() : null, $noteId]);
            logAction($pdo, $user['id'], $sent ? 'email_sent' : 'email_failed', 'notifications', $noteId);
        }
        redirect('admin.php?p=settings&msg=' . urlencode($sent ? 'Письмо отправлено или отмечено отправленным.' : 'Отправить не удалось: настройте SMTP в php.ini или нажмите «Отметить».') . '#emails');
    }

    if ($action === 'mail_delete') {
        $noteId = (int) ($_POST['note_id'] ?? 0);
        $del = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND channel = 'email'");
        $del->execute([$noteId]);
        logAction($pdo, $user['id'], 'email_delete', 'notifications', $noteId);
        redirect('admin.php?p=settings&msg=' . urlencode('Письмо удалено из таблицы notifications.') . '#emails');
    }

    if ($action === 'backup_create') {
        if ($GLOBALS['dbType'] === 'sqlite') {
            $source = __DIR__ . '/data/prestige.db';
            $target = $backupDir . '/backup_' . date('Ymd_His') . '.db';
            if (copy($source, $target)) {
                logAction($pdo, $user['id'], 'backup_create', 'database', null);
                redirect('admin.php?p=backup&msg=' . urlencode('Резервная копия создана: ' . basename($target)));
            }
            $error = 'Не удалось создать копию. Проверьте права папки data.';
        } else {
            try {
                $file = 'C:\\Backup\\prestige_events_' . date('Ymd_His') . '.bak';
                $pdo->exec("BACKUP DATABASE [" . $GLOBALS['dbName'] . "] TO DISK = '" . $file . "'");
                logAction($pdo, $user['id'], 'backup_create', 'database', null);
                redirect('admin.php?p=backup&msg=' . urlencode('Команда BACKUP DATABASE выполнена. Файл на сервере SQL Server: ' . $file));
            } catch (PDOException $ex) {
                $error = 'SQL Server не выполнил BACKUP DATABASE: ' . $ex->getMessage() . ' Создайте папку C:\\Backup и выдайте права службе SQL Server или сделайте копию через SSMS.';
            }
        }
        $p = 'backup';
    }

    if ($action === 'backup_delete' && $GLOBALS['dbType'] === 'sqlite') {
        $name = basename((string) ($_POST['file'] ?? ''));
        $file = $backupDir . '/' . $name;
        if (is_file($file)) {
            unlink($file);
        }
        redirect('admin.php?p=backup&msg=' . urlencode('Файл копии удалён.'));
    }

    if ($action === 'backup_restore') {
        if ($GLOBALS['dbType'] !== 'sqlite') {
            $error = 'Для SQL Server восстановление выполняется в SSMS: база → Tasks → Restore → Database. Пошагово описано в README проекта.';
        } elseif (!isset($_FILES['dbfile']) || $_FILES['dbfile']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Выберите файл резервной копии .db.';
        } elseif (substr($_FILES['dbfile']['name'], -3) !== '.db') {
            $error = 'Нужен файл с расширением .db.';
        } else {
            $source = __DIR__ . '/data/prestige.db';
            copy($source, $backupDir . '/before_restore_' . date('Ymd_His') . '.db');
            if (move_uploaded_file($_FILES['dbfile']['tmp_name'], $source)) {
                redirect('admin.php?p=backup&msg=' . urlencode('База восстановлена из файла. Прежняя база сохранена как before_restore. Войдите заново.'));
            }
            $error = 'Не удалось заменить файл базы данных.';
        }
        $p = 'backup';
    }
}

$settingsRows = $pdo->query('SELECT * FROM settings ORDER BY setting_key ASC')->fetchAll();
$moderation = $pdo->query("SELECT e.*, u.last_name, u.first_name FROM events e INNER JOIN users u ON u.id = e.organizer_id WHERE e.status IN ('moderation', 'draft') ORDER BY e.starts_at ASC")->fetchAll();

if ($p === 'home') {
    $usersCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $eventsCount = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
    $regsCount = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE status IN ('registered', 'confirmed', 'attended')")->fetchColumn();
    $waitCount = (int) $pdo->query("SELECT COUNT(*) FROM waitlist WHERE status = 'waiting'")->fetchColumn();
    pageStart('Администратор', $pdo, $user);
?>
<h2 class="page-title">Админ</h2>
<p class="lead">Полный доступ к базе: пользователи и роли, справочники, модерация, настройки, журнал, отчёты и резервная копия.</p>
<?php showMessage($message, $error); ?>
<div class="cards">
<div class="card"><strong><?= $usersCount ?></strong><span>Пользователи</span></div>
<div class="card"><strong><?= $eventsCount ?></strong><span>Мероприятия</span></div>
<div class="card"><strong><?= $regsCount ?></strong><span>Активные записи</span></div>
<div class="card"><strong><?= $waitCount ?></strong><span>Лист ожидания</span></div>
</div>
<div class="links-grid">
<a href="admin.php?p=users">Пользователи и роли<span>Добавление, редактирование, удаление, смена роли и пароля.</span></a>
<a href="admin.php?p=dirs">Категории и площадки<span>Справочники categories и venues: добавить / изменить / удалить.</span></a>
<a href="office.php">Все мероприятия и сводка<span>Редактирование, программа, участники, посещаемость и выгрузки.</span></a>
<a href="admin.php?p=settings">Настройки и почта<span>Таблица settings, очередь писем и журнал действий.</span></a>
<a href="admin.php?p=backup">Резервная копия<span>Создать копию БД, скачать и восстановить.</span></a>
<a href="office.php?p=edit">Новое мероприятие<span>Создать запись в таблице events.</span></a>
</div>
<h3 class="section">Модерация</h3>
<?php if (!$moderation): ?>
<p class="empty">Мероприятий в черновиках и на модерации нет.</p>
<?php else: ?>
<table>
<tr><th>Мероприятие</th><th>Дата</th><th>Организатор</th><th>Статус в БД</th><th></th></tr>
<?php foreach ($moderation as $ev): ?>
<tr>
<td><a href="index.php?p=event&id=<?= (int) $ev['id'] ?>"><?= e($ev['title']) ?></a></td>
<td><?= formatDate($ev['starts_at']) ?></td>
<td><?= e($ev['last_name'] . ' ' . $ev['first_name']) ?></td>
<td><?= e(eventStatusName($ev['status'])) ?></td>
<td><div class="actions">
<form method="post"><?= csrfField() ?><input type="hidden" name="action" value="moderate"><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><input type="hidden" name="status" value="published"><button class="btn btn-small" type="submit">Опубликовать</button></form>
<form method="post"><?= csrfField() ?><input type="hidden" name="action" value="moderate"><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><input type="hidden" name="status" value="cancelled"><button class="btn btn-light btn-small" type="submit">Отменить</button></form>
<a class="btn btn-light btn-small" href="office.php?p=edit&id=<?= (int) $ev['id'] ?>">Открыть форму</a>
</div></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php
    pageEnd();
    exit;
}

if ($p === 'users') {
    $userEditId = $userEditId ?? (int) ($_GET['edit'] ?? 0);
    $userEdit = $userEdit ?? ['last_name' => '', 'first_name' => '', 'middle_name' => '', 'email' => '', 'phone' => '', 'role_id' => '', 'status' => 'active'];
    if ($userEditId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userEditId]);
        $found = $stmt->fetch();
        if (!$found) {
            redirect('admin.php?p=users');
        }
        $userEdit = $found;
    }
    $roles = $pdo->query('SELECT * FROM roles ORDER BY id ASC')->fetchAll();
    $roleEditId = $roleEditId ?? (int) ($_GET['role_edit'] ?? 0);
    $roleEdit = $roleEdit ?? ['code' => '', 'name' => '', 'description' => ''];
    if ($roleEditId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = ?');
        $stmt->execute([$roleEditId]);
        $roleEdit = $stmt->fetch() ?: $roleEdit;
    }
    $rows = $pdo->query('SELECT u.*, r.name AS role_name, r.code AS role_code FROM users u INNER JOIN roles r ON r.id = u.role_id ORDER BY u.id ASC')->fetchAll();
    pageStart('Пользователи', $pdo, $user);
?>
<p><a href="admin.php">← Администратор</a></p>
<h2 class="page-title">Пользователи</h2>
<p class="lead">Таблица users. Только здесь администратор может менять роль, статус и задавать пароль. Обычный пользователь свою роль изменить не может.</p>
<?php showMessage($message, $error); ?>
<p class="actions"><a class="btn" href="admin.php?p=users#user-form">Новый пользователь</a> <a class="btn btn-light" href="admin.php?p=users#roles">Роли и права</a></p>
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
<td><div class="actions">
<a class="btn btn-light btn-small" href="admin.php?p=users&edit=<?= (int) $row['id'] ?>#user-form">Изменить</a>
<?php if ((int) $row['id'] !== (int) $user['id']): ?>
<form method="post" data-confirm="Удалить пользователя из базы?"><?= csrfField() ?><input type="hidden" name="action" value="user_delete"><input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-small" type="submit">Удалить</button></form>
<?php endif; ?>
</div></td>
</tr>
<?php endforeach; ?>
</table>
<h3 class="section" id="user-form"><?= $userEditId > 0 ? 'Изменить пользователя' : 'Новый пользователь' ?></h3>
<p class="lead">Роль и статус пишутся в таблицу users (role_id, status). Пароль хранится только в виде хэша password_hash.</p>
<form class="form-box" method="post">
<?= csrfField() ?><input type="hidden" name="action" value="user_save"><input type="hidden" name="edit_id" value="<?= $userEditId ?>">
<div class="form-grid">
<div><label>Фамилия *</label><input type="text" name="last_name" required maxlength="64" value="<?= e($userEdit['last_name']) ?>"></div>
<div><label>Имя *</label><input type="text" name="first_name" required maxlength="64" value="<?= e($userEdit['first_name']) ?>"></div>
<div><label>Отчество</label><input type="text" name="middle_name" maxlength="64" value="<?= e($userEdit['middle_name'] ?? '') ?>"></div>
<div><label>Телефон</label><input type="text" name="phone" maxlength="20" value="<?= e($userEdit['phone'] ?? '') ?>"></div>
<div class="full"><label>Email (логин) *</label><input type="email" name="email" required maxlength="128" value="<?= e($userEdit['email']) ?>"></div>
<div><label>Роль</label><select name="role_id"><?php foreach ($roles as $role): ?><?php if ($role['code'] === 'guest') { continue; } ?><option value="<?= (int) $role['id'] ?>" <?= (string) $userEdit['role_id'] === (string) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?> (<?= e($role['code']) ?>)</option><?php endforeach; ?></select></div>
<div><label>Статус</label><select name="status"><?php foreach (['active', 'blocked', 'pending'] as $st): ?><option value="<?= $st ?>" <?= $userEdit['status'] === $st ? 'selected' : '' ?>><?= e(userStatusName($st)) ?></option><?php endforeach; ?></select></div>
<div class="full"><label><?= $userEditId > 0 ? 'Новый пароль (пусто — не менять)' : 'Пароль *' ?></label><input type="text" name="password" placeholder="Например: 1234"></div>
</div>
<p style="margin-top:16px"><button class="btn" type="submit">Сохранить в базу</button> <a class="btn btn-light" href="admin.php?p=users">Сбросить форму</a></p>
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
<a class="btn btn-light btn-small" href="admin.php?p=users&role_edit=<?= (int) $role['id'] ?>#role-form">Изменить</a>
<?php if (!in_array($role['code'], $systemRoles, true)): ?>
<form method="post" data-confirm="Удалить роль из базы?"><?= csrfField() ?><input type="hidden" name="action" value="role_delete"><input type="hidden" name="item_id" value="<?= (int) $role['id'] ?>"><button class="btn btn-small" type="submit">Удалить</button></form>
<?php endif; ?>
</div></td>
</tr>
<?php endforeach; ?>
</table>
<h3 class="section" id="role-form"><?= $roleEditId > 0 ? 'Изменить роль' : 'Добавить роль' ?></h3>
<form class="form-box" method="post">
<?= csrfField() ?><input type="hidden" name="action" value="role_save"><input type="hidden" name="item_id" value="<?= $roleEditId ?>">
<div class="form-grid">
<div><label>Код *</label><input type="text" name="code" required maxlength="32" value="<?= e($roleEdit['code']) ?>"></div>
<div><label>Название *</label><input type="text" name="name" required maxlength="64" value="<?= e($roleEdit['name']) ?>"></div>
<div class="full"><label>Описание</label><input type="text" name="description" maxlength="255" value="<?= e($roleEdit['description'] ?? '') ?>"></div>
</div>
<p style="margin-top:14px"><button class="btn" type="submit">Сохранить в базу</button> <a class="btn btn-light" href="admin.php?p=users#roles">Сбросить</a></p>
</form>
<?php
    pageEnd();
    exit;
}

if ($p === 'dirs') {
    $catEditId = (int) ($_GET['cat_edit'] ?? 0);
    $catEdit = ['name' => '', 'description' => ''];
    if ($catEditId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$catEditId]);
        $catEdit = $stmt->fetch() ?: $catEdit;
    }
    $venueEditId = (int) ($_GET['venue_edit'] ?? 0);
    $venueEdit = ['name' => '', 'address' => '', 'capacity' => ''];
    if ($venueEditId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM venues WHERE id = ?');
        $stmt->execute([$venueEditId]);
        $venueEdit = $stmt->fetch() ?: $venueEdit;
    }
    $categories = $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
    $venues = $pdo->query('SELECT * FROM venues ORDER BY name ASC')->fetchAll();
    pageStart('Справочники', $pdo, $user);
?>
<p><a href="admin.php">← Администратор</a></p>
<h2 class="page-title">Справочники</h2>
<p class="lead">Категории и площадки. Изменения сразу появляются в фильтрах афиши и в форме мероприятия.</p>
<?php showMessage($message, $error); ?>
<h3 class="section">Категории</h3>
<table>
<tr><th>ID</th><th>Название</th><th>Описание</th><th>Мероприятий</th><th></th></tr>
<?php foreach ($categories as $row): ?>
<?php $stmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE category_id = ?'); $stmt->execute([$row['id']]); $cnt = (int) $stmt->fetchColumn(); ?>
<tr>
<td><?= (int) $row['id'] ?></td>
<td><strong><?= e($row['name']) ?></strong></td>
<td><?= e($row['description'] ?? '') ?></td>
<td><?= $cnt ?></td>
<td><div class="actions">
<a class="btn btn-light btn-small" href="admin.php?p=dirs&cat_edit=<?= (int) $row['id'] ?>#cat-form">Изменить</a>
<form method="post" data-confirm="Удалить категорию?"><?= csrfField() ?><input type="hidden" name="action" value="cat_delete"><input type="hidden" name="item_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-small" type="submit">Удалить</button></form>
</div></td>
</tr>
<?php endforeach; ?>
</table>
<h3 class="section" id="cat-form"><?= $catEditId > 0 ? 'Изменить категорию' : 'Добавить категорию' ?></h3>
<form class="form-box" method="post">
<?= csrfField() ?><input type="hidden" name="action" value="cat_save"><input type="hidden" name="item_id" value="<?= $catEditId ?>">
<div style="margin-bottom:14px"><label>Название *</label><input type="text" name="name" required maxlength="100" value="<?= e($catEdit['name']) ?>"></div>
<div style="margin-bottom:14px"><label>Описание</label><textarea name="description"><?= e($catEdit['description'] ?? '') ?></textarea></div>
<button class="btn" type="submit">Сохранить</button> <a class="btn btn-light" href="admin.php?p=dirs">Сбросить</a>
</form>
<h3 class="section">Площадки</h3>
<table>
<tr><th>ID</th><th>Название</th><th>Адрес</th><th>Вместимость</th><th>Мероприятий</th><th></th></tr>
<?php foreach ($venues as $row): ?>
<?php $stmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE venue_id = ?'); $stmt->execute([$row['id']]); $cnt = (int) $stmt->fetchColumn(); ?>
<tr>
<td><?= (int) $row['id'] ?></td>
<td><strong><?= e($row['name']) ?></strong></td>
<td><?= e($row['address']) ?></td>
<td><?= e($row['capacity'] ?? '') ?></td>
<td><?= $cnt ?></td>
<td><div class="actions">
<a class="btn btn-light btn-small" href="admin.php?p=dirs&venue_edit=<?= (int) $row['id'] ?>#venue-form">Изменить</a>
<form method="post" data-confirm="Удалить площадку?"><?= csrfField() ?><input type="hidden" name="action" value="venue_delete"><input type="hidden" name="item_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-small" type="submit">Удалить</button></form>
</div></td>
</tr>
<?php endforeach; ?>
</table>
<h3 class="section" id="venue-form"><?= $venueEditId > 0 ? 'Изменить площадку' : 'Добавить площадку' ?></h3>
<form class="form-box" method="post">
<?= csrfField() ?><input type="hidden" name="action" value="venue_save"><input type="hidden" name="item_id" value="<?= $venueEditId ?>">
<div class="form-grid">
<div><label>Название *</label><input type="text" name="name" required maxlength="150" value="<?= e($venueEdit['name']) ?>"></div>
<div><label>Вместимость</label><input type="number" name="capacity" min="1" value="<?= e($venueEdit['capacity'] ?? '') ?>"></div>
<div class="full"><label>Адрес *</label><input type="text" name="address" required maxlength="255" value="<?= e($venueEdit['address']) ?>"></div>
</div>
<p style="margin-top:14px"><button class="btn" type="submit">Сохранить</button> <a class="btn btn-light" href="admin.php?p=dirs">Сбросить</a></p>
</form>
<?php
    pageEnd();
    exit;
}

if ($p === 'settings') {
    $emailRows = array_slice($pdo->query("SELECT n.*, u.email FROM notifications n INNER JOIN users u ON u.id = n.user_id WHERE n.channel = 'email' ORDER BY n.created_at DESC, n.id DESC")->fetchAll(), 0, 30);
    $logSql = 'SELECT l.*, u.last_name, u.first_name, u.email FROM action_log l LEFT JOIN users u ON u.id = l.user_id';
    $logParams = [];
    if ($search !== '') {
        $logSql .= ' WHERE l.action LIKE ? OR l.entity LIKE ?';
        $logParams[] = '%' . $search . '%';
        $logParams[] = '%' . $search . '%';
    }
    $logSql .= ' ORDER BY l.created_at DESC, l.id DESC';
    $stmt = $pdo->prepare($logSql);
    $stmt->execute($logParams);
    $logRows = array_slice($stmt->fetchAll(), 0, 100);
    pageStart('Настройки и журнал', $pdo, $user);
?>
<p><a href="admin.php">← Администратор</a></p>
<h2 class="page-title">Настройки</h2>
<?php showMessage($message, $error); ?>
<h3 class="section" id="settings">Настройки платформы</h3>
<p class="lead">Таблица settings (ключ — значение). platform_title показывается в шапке, max_waitlist_size ограничивает лист ожидания.</p>
<form class="form-box" method="post">
<?= csrfField() ?><input type="hidden" name="action" value="settings_save">
<?php foreach ($settingsRows as $row): ?>
<div style="margin-bottom:16px">
<label><?= e($row['setting_key']) ?> — <?= e($row['description'] ?? '') ?></label>
<input type="text" name="values[<?= e($row['setting_key']) ?>]" value="<?= e($row['setting_value']) ?>">
<p class="grey">Обновлено: <?= formatDate($row['updated_at']) ?></p>
</div>
<?php endforeach; ?>
<button class="btn" type="submit">Сохранить настройки</button>
</form>
<div class="two-col">
<div>
<h3 class="section">Добавить параметр</h3>
<form method="post" class="form-box"><?= csrfField() ?><input type="hidden" name="action" value="setting_add">
<div class="form-grid">
<div><label>Ключ</label><input type="text" name="new_key" required placeholder="new_setting"></div>
<div><label>Значение</label><input type="text" name="new_value" required></div>
<div class="full"><label>Описание</label><input type="text" name="new_description"></div>
</div>
<p style="margin-top:14px"><button class="btn btn-light" type="submit">Добавить в settings</button></p>
</form>
</div>
<div>
<h3 class="section">Удалить параметр</h3>
<form method="post" class="form-box" data-confirm="Удалить параметр из базы?"><?= csrfField() ?><input type="hidden" name="action" value="setting_delete">
<label>Ключ</label><select name="setting_key"><?php foreach ($settingsRows as $row): ?><option value="<?= e($row['setting_key']) ?>"><?= e($row['setting_key']) ?></option><?php endforeach; ?></select>
<p style="margin-top:14px"><button class="btn btn-light" type="submit">Удалить</button></p>
</form>
</div>
</div>
<h3 class="section" id="emails">Почтовые уведомления</h3>
<p class="lead">Канал email таблицы notifications: подтверждения записи, лист ожидания и рассылки организатора. «Отправить» вызывает mail(), «Отметить» фиксирует отправку вручную, если почтовый сервер не настроен.</p>
<table>
<tr><th>Время</th><th>Кому</th><th>Тема</th><th>Статус</th><th></th></tr>
<?php foreach ($emailRows as $note): ?>
<tr>
<td><?= formatDate($note['created_at']) ?></td>
<td><?= e($note['email']) ?></td>
<td><?= e($note['subject']) ?></td>
<td><span class="badge"><?= e($note['status']) ?></span></td>
<td><div class="actions">
<form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="action" value="mail_send"><input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>"><button class="btn btn-light btn-small" type="submit">Отправить</button></form>
<form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="action" value="mail_mark"><input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>"><button class="btn btn-light btn-small" type="submit">Отметить</button></form>
<form method="post" data-confirm="Удалить письмо из базы?"><?= csrfField() ?><input type="hidden" name="action" value="mail_delete"><input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>"><button class="btn btn-small" type="submit">Удалить</button></form>
</div></td>
</tr>
<?php endforeach; ?>
</table>
<?php if (!$emailRows): ?><p class="empty">Писем в очереди нет.</p><?php endif; ?>
<p class="grey">Показаны последние 30 писем. Для реальной отправки на сервере нужен SMTP в php.ini.</p>
<h3 class="section" id="logs">Журнал действий</h3>
<p class="lead">Таблица action_log: вход, создание, изменение, удаление, записи на мероприятия и отметки посещения. При удалении пользователя история сохраняется.</p>
<form method="get" action="admin.php" class="filters" style="grid-template-columns:1fr auto auto">
<input type="hidden" name="p" value="settings">
<div><label>Поиск по действию или сущности</label><input type="text" name="q" value="<?= e($search) ?>" placeholder="Например: event"></div>
<button class="btn" type="submit">Найти</button>
<a class="btn btn-light" href="admin.php?p=settings#logs">Сбросить</a>
</form>
<table>
<tr><th>Время</th><th>Пользователь</th><th>Действие</th><th>Сущность</th><th>ID</th><th>IP</th></tr>
<?php foreach ($logRows as $row): ?>
<tr>
<td><?= formatDate($row['created_at']) ?></td>
<td><?php if ($row['email']): ?><?= e(trim(($row['last_name'] ?? '') . ' ' . ($row['first_name'] ?? ''))) ?><br><span class="grey"><?= e($row['email']) ?></span><?php else: ?>—<?php endif; ?></td>
<td><?= e($row['action']) ?></td>
<td><?= e($row['entity']) ?></td>
<td><?= e($row['entity_id'] ?? '') ?></td>
<td><?= e($row['ip_address'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php if (!$logRows): ?><p class="empty">Записей в журнале нет.</p><?php endif; ?>
<p class="grey">Показаны последние 100 записей.</p>
<?php
    pageEnd();
    exit;
}

$files = [];
if ($GLOBALS['dbType'] === 'sqlite') {
    $list = glob($backupDir . '/*.db');
    if ($list) {
        rsort($list);
        $files = $list;
    }
}
pageStart('Резервная копия', $pdo, $user);
?>
<p><a href="admin.php">← Администратор</a></p>
<h2 class="page-title">Копия БД</h2>
<p class="lead">Режим базы сейчас: <strong><?= e($GLOBALS['dbType']) ?></strong>. В SQLite-режиме копия — это файл data/prestige.db. В SQL Server-режиме кнопка выполняет BACKUP DATABASE, а восстановление делается в SSMS. По ТЗ копии хранят не менее 30 суток на отдельном носителе — скачивайте файлы из списка.</p>
<?php showMessage($message, $error); ?>
<form method="post"><?= csrfField() ?><input type="hidden" name="action" value="backup_create"><button class="btn" type="submit">Создать резервную копию сейчас</button></form>
<?php if ($GLOBALS['dbType'] === 'sqlite'): ?>
<h3 class="section">Файлы копий в data/backups</h3>
<table>
<tr><th>Файл</th><th>Размер</th><th></th></tr>
<?php foreach ($files as $file): ?>
<tr>
<td><?= e(basename($file)) ?></td>
<td><?= round(filesize($file) / 1024) ?> КБ</td>
<td><div class="actions">
<a class="btn btn-light btn-small" href="admin.php?p=backup&download=<?= urlencode(basename($file)) ?>">Скачать</a>
<form method="post" data-confirm="Удалить файл копии?"><?= csrfField() ?><input type="hidden" name="action" value="backup_delete"><input type="hidden" name="file" value="<?= e(basename($file)) ?>"><button class="btn btn-small" type="submit">Удалить</button></form>
</div></td>
</tr>
<?php endforeach; ?>
</table>
<?php if (!$files): ?><p class="empty">Копий пока нет. Нажмите кнопку выше.</p><?php endif; ?>
<h3 class="section">Восстановление из файла .db</h3>
<form class="form-box" method="post" enctype="multipart/form-data" data-confirm="Заменить текущую базу загруженным файлом? Текущая база будет сохранена как before_restore.">
<?= csrfField() ?><input type="hidden" name="action" value="backup_restore">
<div style="margin-bottom:14px"><label>Файл резервной копии SQLite</label><input type="file" name="dbfile" accept=".db"></div>
<button class="btn btn-light" type="submit">Восстановить базу из файла</button>
</form>
<?php else: ?>
<h3 class="section">Восстановление SQL Server</h3>
<p>SSMS → правый клик по базам данных → Restore Database → Device → выбрать .bak файл → OK. Подробно — раздел «Резервная копия SQL Server» в README проекта.</p>
<?php endif; ?>
<?php pageEnd(); ?>
