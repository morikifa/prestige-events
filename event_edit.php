<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['organizer', 'admin']);
$pageTitle = 'Мероприятие';
$id = (int) ($_GET['id'] ?? 0);
$error = '';
$message = trim($_GET['msg'] ?? '');
$programEditId = (int) ($_GET['program_edit'] ?? 0);
$programItems = [];
$programItem = ['title' => '', 'speaker' => '', 'place' => '', 'starts_at' => '', 'ends_at' => '', 'sort_order' => 0];

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

if ($id > 0) {
    $programItem['starts_at'] = $event['starts_at'];
    $programItem['ends_at'] = $event['ends_at'];
    if ($programEditId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM event_program WHERE id = ? AND event_id = ?');
        $stmt->execute([$programEditId, $id]);
        $foundProgramItem = $stmt->fetch();
        if ($foundProgramItem) {
            $programItem = $foundProgramItem;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? 'event_save';
    if ($action === 'program_delete' && $id > 0) {
        $del = $pdo->prepare('DELETE FROM event_program WHERE id = ? AND event_id = ?');
        $del->execute([(int) ($_POST['item_id'] ?? 0), $id]);
        logAction($pdo, $user['id'], 'program_delete', 'event_program', (int) ($_POST['item_id'] ?? 0));
        redirect('event_edit.php?id=' . $id . '&msg=' . urlencode('Пункт программы удалён.') . '#program');
    }
    if ($action === 'program_save' && $id > 0) {
        $programItem['title'] = trim($_POST['program_title'] ?? '');
        $programItem['speaker'] = trim($_POST['program_speaker'] ?? '');
        $programItem['place'] = trim($_POST['program_place'] ?? '');
        $programItem['starts_at'] = saveDate($_POST['program_starts_at'] ?? '');
        $programItem['ends_at'] = saveDate($_POST['program_ends_at'] ?? '');
        $programItem['sort_order'] = (int) ($_POST['program_sort_order'] ?? 0);
        $programEditId = (int) ($_POST['program_id'] ?? 0);
        if ($programItem['title'] === '' || !$programItem['starts_at'] || !$programItem['ends_at']) {
            $error = 'Заполните название и время пункта программы.';
        } elseif ($programItem['ends_at'] < $programItem['starts_at']) {
            $error = 'Окончание пункта программы не может быть раньше начала.';
        } elseif ($programItem['starts_at'] < $event['starts_at'] || $programItem['ends_at'] > $event['ends_at']) {
            $error = 'Пункт программы должен укладываться во время мероприятия.';
        } else {
            if ($programEditId > 0) {
                $upd = $pdo->prepare('UPDATE event_program SET title = ?, speaker = ?, place = ?, starts_at = ?, ends_at = ?, sort_order = ? WHERE id = ? AND event_id = ?');
                $upd->execute([$programItem['title'], $programItem['speaker'] !== '' ? $programItem['speaker'] : null, $programItem['place'] !== '' ? $programItem['place'] : null, $programItem['starts_at'], $programItem['ends_at'], $programItem['sort_order'], $programEditId, $id]);
                logAction($pdo, $user['id'], 'program_update', 'event_program', $programEditId);
            } else {
                $ins = $pdo->prepare('INSERT INTO event_program (event_id, title, speaker, place, starts_at, ends_at, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([$id, $programItem['title'], $programItem['speaker'] !== '' ? $programItem['speaker'] : null, $programItem['place'] !== '' ? $programItem['place'] : null, $programItem['starts_at'], $programItem['ends_at'], $programItem['sort_order']]);
                logAction($pdo, $user['id'], 'program_create', 'event_program', insertedId($pdo));
            }
            redirect('event_edit.php?id=' . $id . '&msg=' . urlencode('Программа сохранена в базе данных.') . '#program');
        }
    }
    if ($action === 'event_save') {
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
            $newId = insertedId($pdo);
            logAction($pdo, $user['id'], 'event_create', 'events', $newId);
            redirect('event_edit.php?id=' . $newId . '&msg=' . urlencode('Мероприятие добавлено в базу. Теперь можно добавить программу ниже.') . '#program');
        }
    }
    }
}

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM event_program WHERE event_id = ? ORDER BY sort_order ASC, starts_at ASC');
    $stmt->execute([$id]);
    $programItems = $stmt->fetchAll();
}

require __DIR__ . '/includes/header.php';
?>
<p><a href="organizer.php">← Организатор</a></p>
<h2 class="page-title"><?= $id > 0 ? 'Изменить' : 'Новое' ?></h2>
<p class="lead">Форма пишет прямо в таблицу events: <?= $id > 0 ? 'обновляется существующая запись.' : 'создаётся новая запись, адрес (slug) строится из названия автоматически.' ?></p>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

<form class="form-box" id="eventForm" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="event_save">
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

<?php if ($id > 0): ?>
<h3 class="section" id="program">Программа мероприятия</h3>
<p class="lead">Пункты программы хранятся в таблице event_program и должны укладываться во время мероприятия: <?= formatDate($event['starts_at']) ?> — <?= formatDate($event['ends_at']) ?>.</p>

<table>
<tr><th>Порядок</th><th>Время</th><th>Название</th><th>Докладчик / место</th><th></th></tr>
<?php foreach ($programItems as $row): ?>
<tr>
<td><?= (int) $row['sort_order'] ?></td>
<td><?= formatDate($row['starts_at']) ?> — <?= date('H:i', strtotime($row['ends_at'])) ?></td>
<td><?= e($row['title']) ?></td>
<td><?= e($row['speaker'] ?? '') ?><br><span class="grey"><?= e($row['place'] ?? '') ?></span></td>
<td>
<div class="actions">
<a class="btn btn-light btn-small" href="event_edit.php?id=<?= $id ?>&program_edit=<?= (int) $row['id'] ?>#program">Изменить</a>
<form method="post" data-confirm="Удалить пункт программы?">
<?= csrfField() ?>
<input type="hidden" name="action" value="program_delete">
<input type="hidden" name="item_id" value="<?= (int) $row['id'] ?>">
<button class="btn btn-small" type="submit">Удалить</button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php if (!$programItems): ?><p class="empty">Программа пока пустая. Добавьте первый пункт формой ниже.</p><?php endif; ?>

<h3 class="section"><?= $programEditId > 0 ? 'Изменить пункт программы' : 'Добавить пункт программы' ?></h3>
<form class="form-box" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="program_save">
<input type="hidden" name="program_id" value="<?= $programEditId ?>">
<div class="form-grid">
<div class="full"><label>Название *</label><input type="text" name="program_title" required maxlength="200" value="<?= e($programItem['title']) ?>"></div>
<div><label>Докладчик</label><input type="text" name="program_speaker" maxlength="150" value="<?= e($programItem['speaker'] ?? '') ?>"></div>
<div><label>Место</label><input type="text" name="program_place" maxlength="100" value="<?= e($programItem['place'] ?? '') ?>"></div>
<div><label>Начало *</label><input type="datetime-local" name="program_starts_at" required value="<?= inputDate($programItem['starts_at']) ?>"></div>
<div><label>Окончание *</label><input type="datetime-local" name="program_ends_at" required value="<?= inputDate($programItem['ends_at']) ?>"></div>
<div><label>Порядок сортировки</label><input type="number" name="program_sort_order" value="<?= e($programItem['sort_order']) ?>"></div>
</div>
<p style="margin-top:14px"><button class="btn" type="submit">Сохранить пункт</button> <a class="btn btn-light" href="event_edit.php?id=<?= $id ?>#program">Сбросить форму</a></p>
</form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
