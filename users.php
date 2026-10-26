<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['admin']);
$pageTitle = 'Пользователи';
$message = trim($_GET['msg'] ?? '');
$error = trim($_GET['err'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
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

$rows = $pdo->query('SELECT u.*, r.name AS role_name, r.code AS role_code FROM users u INNER JOIN roles r ON r.id = u.role_id ORDER BY u.id ASC')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<p><a href="admin.php">← Администратор</a></p>
<h2 class="page-title">Пользователи</h2>
<p class="lead">Таблица users. Только здесь администратор может менять роль, статус и задавать пароль. Обычный пользователь свою роль изменить не может.</p>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

<p><a class="btn" href="user_edit.php">Новый пользователь</a></p>
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
<a class="btn btn-light btn-small" href="user_edit.php?id=<?= (int) $row['id'] ?>">Изменить</a>
<?php if ((int) $row['id'] !== (int) $user['id']): ?>
<form method="post" data-confirm="Удалить пользователя из базы?">
<?= csrfField() ?>
<input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
<button class="btn btn-small" type="submit">Удалить</button>
</form>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
