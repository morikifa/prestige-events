<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['admin']);
$pageTitle = 'Администратор';
$message = trim($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['draft', 'moderation', 'published', 'cancelled', 'completed'], true)) {
        $upd = $pdo->prepare('UPDATE events SET status = ?, updated_at = ? WHERE id = ?');
        $upd->execute([$status, nowText(), $eventId]);
        logAction($pdo, $user['id'], 'event_moderation_' . $status, 'events', $eventId);
    }
    redirect('admin.php?msg=' . urlencode('Статус мероприятия изменён администратором.'));
}

$usersCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$eventsCount = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
$regsCount = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE status IN ('registered', 'confirmed', 'attended')")->fetchColumn();
$waitCount = (int) $pdo->query("SELECT COUNT(*) FROM waitlist WHERE status = 'waiting'")->fetchColumn();
$moderation = $pdo->query("SELECT e.*, u.last_name, u.first_name FROM events e INNER JOIN users u ON u.id = e.organizer_id WHERE e.status IN ('moderation', 'draft') ORDER BY e.starts_at ASC")->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<h2 class="page-title">Админ</h2>
<p class="lead">Полный доступ к базе: пользователи и роли, справочники, модерация, настройки, журнал, отчёты и резервная копия.</p>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>

<div class="cards">
<div class="card"><strong><?= $usersCount ?></strong><span>Пользователи</span></div>
<div class="card"><strong><?= $eventsCount ?></strong><span>Мероприятия</span></div>
<div class="card"><strong><?= $regsCount ?></strong><span>Активные записи</span></div>
<div class="card"><strong><?= $waitCount ?></strong><span>Лист ожидания</span></div>
</div>

<div class="links-grid">
<a href="users.php">Пользователи и роли<span>Добавление, редактирование, удаление, смена роли и пароля — таблица users.</span></a>
<a href="categories.php">Категории<span>Справочник categories: добавить / изменить / удалить.</span></a>
<a href="venues.php">Площадки<span>Справочник venues: адрес и вместимость.</span></a>
<a href="organizer.php">Все мероприятия<span>Редактирование, программа и участники любого мероприятия.</span></a>
<a href="reports.php">Сводные отчёты<span>По всем мероприятиям платформы + выгрузка CSV.</span></a>
<a href="settings.php">Настройки платформы<span>Таблица settings: название, лимит листа ожидания и др.</span></a>
<a href="logs.php">Журнал действий<span>Таблица action_log: кто, когда, что изменил.</span></a>
<a href="backup.php">Резервная копия<span>Создать копию БД, скачать и восстановить (SQLite-режим).</span></a>
<a href="event_edit.php">Новое мероприятие<span>Создать запись в таблице events.</span></a>
</div>

<h3 class="section">Модерация</h3>
<?php if (!$moderation): ?>
<p class="empty">Мероприятий в черновиках и на модерации нет.</p>
<?php else: ?>
<table>
<tr><th>Мероприятие</th><th>Дата</th><th>Организатор</th><th>Статус в БД</th><th></th></tr>
<?php foreach ($moderation as $ev): ?>
<tr>
<td><a href="event.php?id=<?= (int) $ev['id'] ?>"><?= e($ev['title']) ?></a></td>
<td><?= formatDate($ev['starts_at']) ?></td>
<td><?= e($ev['last_name'] . ' ' . $ev['first_name']) ?></td>
<td><?= e(eventStatusName($ev['status'])) ?></td>
<td>
<div class="actions">
<form method="post"><?= csrfField() ?><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><input type="hidden" name="status" value="published"><button class="btn btn-small" type="submit">Опубликовать</button></form>
<form method="post"><?= csrfField() ?><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><input type="hidden" name="status" value="cancelled"><button class="btn btn-light btn-small" type="submit">Отменить</button></form>
<a class="btn btn-light btn-small" href="event_edit.php?id=<?= (int) $ev['id'] ?>">Открыть форму</a>
</div>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
