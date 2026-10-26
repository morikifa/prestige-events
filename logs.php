<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['admin']);
$pageTitle = 'Журнал действий';
$search = trim($_GET['q'] ?? '');

$sql = 'SELECT l.*, u.last_name, u.first_name, u.email FROM action_log l LEFT JOIN users u ON u.id = l.user_id';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE l.action LIKE ? OR l.entity LIKE ?';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY l.created_at DESC, l.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = array_slice($stmt->fetchAll(), 0, 300);

require __DIR__ . '/includes/header.php';
?>
<p><a href="admin.php">← Администратор</a></p>
<h2 class="page-title">Журнал</h2>
<p class="lead">Таблица action_log. Сюда пишутся вход, создание, изменение и удаление записей, записи на мероприятия и отметки посещения. При удалении пользователя его история в журнале сохраняется (user_id становится NULL).</p>

<form method="get" class="filters" style="grid-template-columns:1fr auto auto">
<div><label>Поиск по действию или сущности</label><input type="text" name="q" value="<?= e($search) ?>" placeholder="Например: event"></div>
<button class="btn" type="submit">Найти</button>
<a class="btn btn-light" href="logs.php">Сбросить</a>
</form>

<table>
<tr><th>Время</th><th>Пользователь</th><th>Действие</th><th>Сущность</th><th>ID</th><th>IP</th></tr>
<?php foreach ($rows as $row): ?>
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
<?php if (!$rows): ?><p class="empty">Записей в журнале нет.</p><?php endif; ?>
<p class="grey">Показаны последние 300 записей.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>
