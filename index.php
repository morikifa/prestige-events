<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
$pageTitle = 'Афиша';
$search = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category_id'] ?? 0);
$venueId = (int) ($_GET['venue_id'] ?? 0);
$date = trim($_GET['date'] ?? '');

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$venues = $pdo->query('SELECT * FROM venues ORDER BY name')->fetchAll();

$sql = "SELECT e.*, c.name AS category_name, v.name AS venue_name, v.address AS venue_address FROM events e INNER JOIN categories c ON c.id = e.category_id LEFT JOIN venues v ON v.id = e.venue_id WHERE e.status = 'published' AND e.starts_at >= ?";
$params = [nowText()];
if ($search !== '') {
    $sql .= ' AND e.title LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($categoryId > 0) {
    $sql .= ' AND e.category_id = ?';
    $params[] = $categoryId;
}
if ($venueId > 0) {
    $sql .= ' AND e.venue_id = ?';
    $params[] = $venueId;
}
if ($date !== '') {
    $sql .= ' AND e.starts_at >= ? AND e.starts_at <= ?';
    $params[] = $date . ' 00:00:00';
    $params[] = $date . ' 23:59:59';
}
$sql .= ' ORDER BY e.starts_at ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
<h1>Prestige <span class="line2">Events</span></h1>
<div class="hero-sub">
<p>Афиша мероприятий колледжа: конференции, семинары, олимпиады и хакатоны. Запись через личный кабинет, билет с QR-кодом.</p>
<div class="small">Найдено мероприятий: <?= count($events) ?></div>
</div>
</section>

<form class="filters" method="get">
<div>
<label for="q">Поиск по названию</label>
<input id="q" type="text" name="q" value="<?= e($search) ?>" placeholder="Например: SQL">
</div>
<div>
<label for="category_id">Категория</label>
<select id="category_id" name="category_id">
<option value="0">Все категории</option>
<?php foreach ($categories as $cat): ?>
<option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label for="venue_id">Площадка</label>
<select id="venue_id" name="venue_id">
<option value="0">Все площадки</option>
<?php foreach ($venues as $venue): ?>
<option value="<?= (int) $venue['id'] ?>" <?= $venueId === (int) $venue['id'] ? 'selected' : '' ?>><?= e($venue['name']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label for="date">Дата</label>
<input id="date" type="date" name="date" value="<?= e($date) ?>">
</div>
<button class="btn" type="submit">Найти</button>
<a class="btn btn-light" href="index.php">Сбросить</a>
</form>

<?php if (!$events): ?>
<p class="empty">По этим условиям мероприятий нет. Измените фильтры или сбросьте поиск.</p>
<?php endif; ?>

<?php foreach ($events as $event): ?>
<?php $free = freePlaces($pdo, $event); ?>
<article class="event-row">
<div class="event-date"><strong><?= date('d', strtotime($event['starts_at'])) ?></strong><?= date('m.Y', strtotime($event['starts_at'])) ?><br><?= date('H:i', strtotime($event['starts_at'])) ?></div>
<div>
<h3><a href="event.php?id=<?= (int) $event['id'] ?>"><?= e($event['title']) ?></a></h3>
<p><?= e(mb_substr((string) $event['description'], 0, 160)) ?><?= mb_strlen((string) $event['description']) > 160 ? '…' : '' ?></p>
</div>
<div>
<span class="badge"><?= e($event['category_name']) ?></span>
<p class="grey"><?= e($event['venue_name'] ?? 'Место уточняется') ?></p>
</div>
<div>
<p><?php if ($free === null): ?>Без ограничения<?php elseif ($free > 0): ?>Свободно мест: <?= $free ?><?php else: ?>Мест нет · лист ожидания<?php endif; ?></p>
<a class="btn btn-light btn-small" href="event.php?id=<?= (int) $event['id'] ?>">Открыть</a>
</div>
</article>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
