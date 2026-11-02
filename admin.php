<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['admin']);
$pageTitle = 'Администратор';
$message = trim($_GET['msg'] ?? '');
$search = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? 'moderate';
    if ($action === 'settings') {
        $values = $_POST['values'] ?? [];
        if (is_array($values)) {
            $upd = $pdo->prepare('UPDATE settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?');
            foreach ($values as $key => $value) {
                $upd->execute([trim((string) $value), nowText(), $key]);
            }
            logAction($pdo, $user['id'], 'settings_update', 'settings', null);
        }
        redirect('admin.php?msg=' . urlencode('Настройки сохранены в таблице settings. Название платформы в шапке обновилось.') . '#settings');
    }
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
$settingsRows = $pdo->query('SELECT * FROM settings ORDER BY setting_key ASC')->fetchAll();

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
<a href="organizer.php">Все мероприятия и сводка<span>Редактирование, программа, участники, посещаемость и выгрузка CSV по любым мероприятиям.</span></a>
<a href="admin.php#settings">Настройки платформы<span>Таблица settings: название, лимит листа ожидания и др.</span></a>
<a href="admin.php#logs">Журнал действий<span>Таблица action_log: кто, когда, что изменил.</span></a>
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
<form method="post"><?= csrfField() ?><input type="hidden" name="action" value="moderate"><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><input type="hidden" name="status" value="published"><button class="btn btn-small" type="submit">Опубликовать</button></form>
<form method="post"><?= csrfField() ?><input type="hidden" name="action" value="moderate"><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><input type="hidden" name="status" value="cancelled"><button class="btn btn-light btn-small" type="submit">Отменить</button></form>
<a class="btn btn-light btn-small" href="event_edit.php?id=<?= (int) $ev['id'] ?>">Открыть форму</a>
</div>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h3 class="section" id="settings">Настройки платформы</h3>
<p class="lead">Таблица settings (ключ — значение). platform_title показывается в шапке, max_waitlist_size ограничивает лист ожидания.</p>
<form class="form-box" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="settings">
<?php foreach ($settingsRows as $row): ?>
<div style="margin-bottom:16px">
<label><?= e($row['setting_key']) ?> — <?= e($row['description'] ?? '') ?></label>
<input type="text" name="values[<?= e($row['setting_key']) ?>]" value="<?= e($row['setting_value']) ?>">
<p class="grey">Обновлено: <?= formatDate($row['updated_at']) ?></p>
</div>
<?php endforeach; ?>
<button class="btn" type="submit">Сохранить настройки</button>
</form>

<h3 class="section" id="logs">Журнал действий</h3>
<p class="lead">Таблица action_log: вход, создание, изменение, удаление, записи на мероприятия и отметки посещения. При удалении пользователя история сохраняется, а user_id становится NULL.</p>
<form method="get" action="admin.php" class="filters" style="grid-template-columns:1fr auto auto">
<div><label>Поиск по действию или сущности</label><input type="text" name="q" value="<?= e($search) ?>" placeholder="Например: event"></div>
<button class="btn" type="submit">Найти</button>
<a class="btn btn-light" href="admin.php#logs">Сбросить</a>
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
<?php require __DIR__ . '/includes/footer.php'; ?>
