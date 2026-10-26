<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['organizer', 'admin']);
$pageTitle = 'Мероприятие';
$id = (int) ($_GET['id'] ?? 0);
$error = '';

$event = ['title' => '', 'category_id' => '', 'venue_id' => '', 'description' => '', 'capacity' => '', 'registration_opens_at' => '', 'registration_closes_at' => '', 'starts_at' => '', 'ends_at' => '', 'status' => 'draft'];
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found || !canEditEvent($user, $found)) {
        redirect('organizer.php');
    }
    $event = $found;
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$venues = $pdo->query('SELECT * FROM venues ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $event['title'] = trim($_POST['title'] ?? '');
    $event['category_id'] = (int) ($_POST['category_id'] ?? 0);
    $venueValue = (int) ($_POST['venue_id'] ?? 0);
    $event['venue_id'] = $venueValue > 0 ? $venueValue : null;
    $event['description'] = trim($_POST['description'] ?? '');
    $capacityValue = trim($_POST['capacity'] ?? '');
    $event['capacity'] = $capacityValue !== '' ? (int) $capacityValue : null;
    $event['starts_at'] = saveDate($_POST['starts_at'] ?? '');
    $event['ends_at'] = saveDate($_POST['ends_at'] ?? '');
    $event['registration_opens_at'] = saveDate($_POST['registration_opens_at'] ?? '');
    $event['registration_closes_at'] = saveDate($_POST['registration_closes_at'] ?? '');
    $event['status'] = $_POST['status'] ?? 'draft';

    if ($event['title'] === '' || $event['category_id'] <= 0 || !$event['starts_at'] || !$event['ends_at']) {
        $error = 'Заполните название, категорию, дату начала и окончания.';
    } elseif ($event['ends_at'] < $event['starts_at']) {
        $error = 'Дата окончания не может быть раньше даты начала.';
    } elseif ($event['registration_opens_at'] && $event['registration_closes_at'] && $event['registration_closes_at'] < $event['registration_opens_at']) {
        $error = 'Дата закрытия записи не может быть раньше даты открытия.';
    } elseif ($event['capacity'] !== null && $event['capacity'] <= 0) {
        $error = 'Количество мест должно быть больше нуля или пустым.';
    } else {
        if ($event['venue_id'] && $event['capacity'] !== null) {
            $stmt = $pdo->prepare('SELECT capacity FROM venues WHERE id = ?');
            $stmt->execute([$event['venue_id']]);
            $venueCap = $stmt->fetchColumn();
            if ($venueCap !== false && $venueCap !== null && $event['capacity'] > (int) $venueCap) {
                $error = 'Мест в мероприятии больше, чем вместимость выбранной площадки (' . (int) $venueCap . ').';
            }
        }
    }

    if ($error === '') {
        $slug = makeSlug($event['title']);
        $stmt = $pdo->prepare('SELECT id FROM events WHERE slug = ?');
        $stmt->execute([$slug]);
        $slugOwner = $stmt->fetch();
        if ($slugOwner && (int) $slugOwner['id'] !== $id) {
            $slug .= '-' . date('His');
        }
        if ($id > 0) {
            $upd = $pdo->prepare('UPDATE events SET title = ?, slug = ?, category_id = ?, venue_id = ?, description = ?, capacity = ?, registration_opens_at = ?, registration_closes_at = ?, starts_at = ?, ends_at = ?, status = ?, updated_at = ? WHERE id = ?');
            $upd->execute([$event['title'], $slug, $event['category_id'], $event['venue_id'], $event['description'] !== '' ? $event['description'] : null, $event['capacity'], $event['registration_opens_at'], $event['registration_closes_at'], $event['starts_at'], $event['ends_at'], $event['status'], nowText(), $id]);
            logAction($pdo, $user['id'], 'event_update', 'events', $id);
            redirect('organizer.php?msg=' . urlencode('Мероприятие обновлено в базе данных.'));
        } else {
            $ins = $pdo->prepare('INSERT INTO events (title, slug, category_id, venue_id, organizer_id, description, capacity, registration_opens_at, registration_closes_at, starts_at, ends_at, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$event['title'], $slug, $event['category_id'], $event['venue_id'], $user['id'], $event['description'] !== '' ? $event['description'] : null, $event['capacity'], $event['registration_opens_at'], $event['registration_closes_at'], $event['starts_at'], $event['ends_at'], $event['status'], nowText(), nowText()]);
            $newId = (int) $pdo->lastInsertId();
            logAction($pdo, $user['id'], 'event_create', 'events', $newId);
            redirect('program.php?event_id=' . $newId . '&msg=' . urlencode('Мероприятие добавлено в базу. Теперь можно добавить программу.'));
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<p><a href="organizer.php">← Организатор</a></p>
<h2 class="page-title"><?= $id > 0 ? 'Изменить' : 'Новое' ?></h2>
<p class="lead">Форма пишет прямо в таблицу events: <?= $id > 0 ? 'обновляется существующая запись.' : 'создаётся новая запись, адрес (slug) строится из названия автоматически.' ?></p>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

<form class="form-box" id="eventForm" method="post">
<?= csrfField() ?>
<div class="form-grid">
<div class="full"><label>Название *</label><input type="text" name="title" required maxlength="200" value="<?= e($event['title']) ?>"></div>
<div>
<label>Категория *</label>
<select name="category_id" required>
<option value="">Выберите категорию</option>
<?php foreach ($categories as $cat): ?>
<option value="<?= (int) $cat['id'] ?>" <?= (string) $event['category_id'] === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label>Площадка</label>
<select name="venue_id">
<option value="0">Без площадки</option>
<?php foreach ($venues as $venue): ?>
<option value="<?= (int) $venue['id'] ?>" <?= (string) $event['venue_id'] === (string) $venue['id'] ? 'selected' : '' ?>><?= e($venue['name']) ?><?= $venue['capacity'] ? ' (до ' . e($venue['capacity']) . ')' : '' ?></option>
<?php endforeach; ?>
</select>
</div>
<div><label>Количество мест (пусто — без ограничения)</label><input type="number" name="capacity" min="1" value="<?= e($event['capacity'] ?? '') ?>"></div>
<div>
<label>Статус</label>
<select name="status">
<?php foreach (['draft', 'moderation', 'published', 'cancelled', 'completed'] as $st): ?>
<option value="<?= $st ?>" <?= $event['status'] === $st ? 'selected' : '' ?>><?= e(eventStatusName($st)) ?></option>
<?php endforeach; ?>
</select>
</div>
<div><label>Начало *</label><input id="starts_at" type="datetime-local" name="starts_at" required value="<?= inputDate($event['starts_at']) ?>"></div>
<div><label>Окончание *</label><input id="ends_at" type="datetime-local" name="ends_at" required value="<?= inputDate($event['ends_at']) ?>"></div>
<div><label>Открытие записи</label><input type="datetime-local" name="registration_opens_at" value="<?= inputDate($event['registration_opens_at']) ?>"></div>
<div><label>Закрытие записи</label><input type="datetime-local" name="registration_closes_at" value="<?= inputDate($event['registration_closes_at']) ?>"></div>
<div class="full"><label>Описание</label><textarea name="description"><?= e($event['description'] ?? '') ?></textarea></div>
</div>
<p style="margin-top:16px"><button class="btn" type="submit">Сохранить в базу</button> <a class="btn btn-light" href="organizer.php">Отмена</a></p>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
