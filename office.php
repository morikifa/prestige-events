<?php
require __DIR__ . '/db.php';
require __DIR__ . '/core.php';

$user = currentUser($pdo);
needRole($user, ['organizer', 'admin']);
$p = $_GET['p'] ?? 'events';
$id = (int) ($_GET['id'] ?? 0);
$eventId = (int) ($_GET['event_id'] ?? 0);
$tokenFromLink = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$message = trim($_GET['msg'] ?? '');
$error = '';
$formSubmitted = null;

if ($p === 'export') {
    $format = $_GET['format'] ?? 'csv';
    $mode = $_GET['mode'] ?? '';
    if ($mode === 'summary') {
        if ($user['role_code'] === 'admin') {
            $events = $pdo->query('SELECT * FROM events ORDER BY starts_at ASC')->fetchAll();
        } else {
            $stmt = $pdo->prepare('SELECT * FROM events WHERE organizer_id = ? ORDER BY starts_at ASC');
            $stmt->execute([$user['id']]);
            $events = $stmt->fetchAll();
        }
        $headers = ['Мероприятие', 'Дата', 'Статус', 'Мест', 'Записано', 'Посетило', 'Лист ожидания'];
        $rows = [];
        foreach ($events as $ev) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status = 'attended'");
            $stmt->execute([$ev['id']]);
            $attended = (int) $stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM waitlist WHERE event_id = ? AND status = 'waiting'");
            $stmt->execute([$ev['id']]);
            $waiting = (int) $stmt->fetchColumn();
            $rows[] = [$ev['title'], formatDate($ev['starts_at']), eventStatusName($ev['status']), $ev['capacity'] === null ? 'без ограничения' : $ev['capacity'], registeredCount($pdo, $ev['id']), $attended, $waiting];
        }
        logAction($pdo, $user['id'], 'export_report', 'events', null);
        if ($format === 'xlsx') {
            sendXlsx('prestige-report.xlsx', 'Сводка', $headers, $rows);
        }
        sendCsv('prestige-report.csv', $headers, $rows);
    }
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    if (!$event || !canEditEvent($user, $event)) {
        die('Мероприятие не найдено.');
    }
    $stmt = $pdo->prepare('SELECT reg.*, u.last_name, u.first_name, u.middle_name, u.email, u.phone FROM registrations reg INNER JOIN users u ON u.id = reg.user_id WHERE reg.event_id = ? ORDER BY reg.registered_at ASC');
    $stmt->execute([$eventId]);
    $headers = ['Фамилия', 'Имя', 'Отчество', 'Email', 'Телефон', 'Статус', 'Дата записи', 'Токен'];
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [$row['last_name'], $row['first_name'], $row['middle_name'], $row['email'], $row['phone'], regStatusName($row['status']), formatDate($row['registered_at']), $row['qr_token']];
    }
    logAction($pdo, $user['id'], 'export_participants', 'events', $eventId);
    if ($format === 'xlsx') {
        sendXlsx('participants-event-' . $eventId . '.xlsx', 'Участники', $headers, $rows);
    }
    sendCsv('participants-event-' . $eventId . '.csv', $headers, $rows);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'event_delete' || $action === 'event_status') {
        $targetId = (int) ($_POST['event_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$targetId]);
        $event = $stmt->fetch();
        if ($event && canEditEvent($user, $event)) {
            if ($action === 'event_delete') {
                $del = $pdo->prepare('DELETE FROM events WHERE id = ?');
                $del->execute([$targetId]);
                logAction($pdo, $user['id'], 'event_delete', 'events', $targetId);
                redirect('office.php?msg=' . urlencode('Мероприятие удалено из базы вместе с программой и записями.'));
            }
            $status = $_POST['status'] ?? '';
            if (in_array($status, ['draft', 'moderation', 'published', 'cancelled', 'completed'], true)) {
                $upd = $pdo->prepare('UPDATE events SET status = ?, updated_at = ? WHERE id = ?');
                $upd->execute([$status, nowText(), $targetId]);
                if ($status === 'cancelled') {
                    $parts = $pdo->prepare("SELECT user_id FROM registrations WHERE event_id = ? AND status IN ('registered', 'confirmed')");
                    $parts->execute([$targetId]);
                    foreach ($parts->fetchAll() as $part) {
                        notify($pdo, $part['user_id'], $targetId, 'event_cancelled', 'Мероприятие отменено', 'Мероприятие «' . $event['title'] . '» отменено организатором.', true);
                    }
                }
                logAction($pdo, $user['id'], 'event_status_' . $status, 'events', $targetId);
            }
        }
        redirect('office.php?msg=' . urlencode('Статус мероприятия изменён.'));
    }

    if ($action === 'event_save' || $action === 'program_save' || $action === 'program_delete') {
        $id = (int) ($_POST['event_id'] ?? 0);
        $event = null;
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
            $stmt->execute([$id]);
            $event = $stmt->fetch();
            if (!$event || !canEditEvent($user, $event)) {
                redirect('office.php');
            }
        }
        if ($action === 'program_delete' && $event) {
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $del = $pdo->prepare('DELETE FROM event_program WHERE id = ? AND event_id = ?');
            $del->execute([$itemId, $id]);
            logAction($pdo, $user['id'], 'program_delete', 'event_program', $itemId);
            redirect('office.php?p=edit&id=' . $id . '&msg=' . urlencode('Пункт программы удалён.') . '#program');
        }
        if ($action === 'program_save' && $event) {
            $title = trim($_POST['program_title'] ?? '');
            $speaker = trim($_POST['program_speaker'] ?? '');
            $place = trim($_POST['program_place'] ?? '');
            $starts = saveDate($_POST['program_starts_at'] ?? '');
            $ends = saveDate($_POST['program_ends_at'] ?? '');
            $sort = (int) ($_POST['program_sort_order'] ?? 0);
            $itemId = (int) ($_POST['program_id'] ?? 0);
            if ($title === '' || !$starts || !$ends) {
                $error = 'Заполните название и время пункта программы.';
            } elseif ($ends < $starts) {
                $error = 'Окончание пункта программы не может быть раньше начала.';
            } elseif ($starts < $event['starts_at'] || $ends > $event['ends_at']) {
                $error = 'Пункт программы должен укладываться во время мероприятия.';
            } else {
                if ($itemId > 0) {
                    $upd = $pdo->prepare('UPDATE event_program SET title = ?, speaker = ?, place = ?, starts_at = ?, ends_at = ?, sort_order = ? WHERE id = ? AND event_id = ?');
                    $upd->execute([$title, $speaker !== '' ? $speaker : null, $place !== '' ? $place : null, $starts, $ends, $sort, $itemId, $id]);
                    logAction($pdo, $user['id'], 'program_update', 'event_program', $itemId);
                } else {
                    $ins = $pdo->prepare('INSERT INTO event_program (event_id, title, speaker, place, starts_at, ends_at, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $ins->execute([$id, $title, $speaker !== '' ? $speaker : null, $place !== '' ? $place : null, $starts, $ends, $sort]);
                    logAction($pdo, $user['id'], 'program_create', 'event_program', insertedId($pdo));
                }
                redirect('office.php?p=edit&id=' . $id . '&msg=' . urlencode('Программа сохранена в базе данных.') . '#program');
            }
            $p = 'edit';
        }
        if ($action === 'event_save') {
            $form = [
                'title' => trim($_POST['title'] ?? ''),
                'category_id' => (int) ($_POST['category_id'] ?? 0),
                'venue_id' => (int) ($_POST['venue_id'] ?? 0) ?: null,
                'description' => trim($_POST['description'] ?? ''),
                'capacity' => trim($_POST['capacity'] ?? '') !== '' ? (int) $_POST['capacity'] : null,
                'starts_at' => saveDate($_POST['starts_at'] ?? ''),
                'ends_at' => saveDate($_POST['ends_at'] ?? ''),
                'registration_opens_at' => saveDate($_POST['registration_opens_at'] ?? ''),
                'registration_closes_at' => saveDate($_POST['registration_closes_at'] ?? ''),
                'status' => $_POST['status'] ?? 'draft'
            ];
            if ($form['title'] === '' || $form['category_id'] <= 0 || !$form['starts_at'] || !$form['ends_at']) {
                $error = 'Заполните название, категорию, дату начала и окончания.';
            } elseif ($form['ends_at'] < $form['starts_at']) {
                $error = 'Дата окончания не может быть раньше даты начала.';
            } elseif ($form['registration_opens_at'] && $form['registration_closes_at'] && $form['registration_closes_at'] < $form['registration_opens_at']) {
                $error = 'Дата закрытия записи не может быть раньше даты открытия.';
            } elseif ($form['capacity'] !== null && $form['capacity'] <= 0) {
                $error = 'Количество мест должно быть больше нуля или пустым.';
            } else {
                if ($form['venue_id'] && $form['capacity'] !== null) {
                    $stmt = $pdo->prepare('SELECT capacity FROM venues WHERE id = ?');
                    $stmt->execute([$form['venue_id']]);
                    $venueCap = $stmt->fetchColumn();
                    if ($venueCap !== false && $venueCap !== null && $form['capacity'] > (int) $venueCap) {
                        $error = 'Мест в мероприятии больше, чем вместимость выбранной площадки (' . (int) $venueCap . ').';
                    }
                }
            }
            $formSubmitted = $form;
            if ($error === '') {
                $slug = makeSlug($form['title']);
                $stmt = $pdo->prepare('SELECT id FROM events WHERE slug = ?');
                $stmt->execute([$slug]);
                $owner = $stmt->fetch();
                if ($owner && (int) $owner['id'] !== $id) {
                    $slug .= '-' . date('His');
                }
                if ($id > 0) {
                    $upd = $pdo->prepare('UPDATE events SET title = ?, slug = ?, category_id = ?, venue_id = ?, description = ?, capacity = ?, registration_opens_at = ?, registration_closes_at = ?, starts_at = ?, ends_at = ?, status = ?, updated_at = ? WHERE id = ?');
                    $upd->execute([$form['title'], $slug, $form['category_id'], $form['venue_id'], $form['description'] !== '' ? $form['description'] : null, $form['capacity'], $form['registration_opens_at'], $form['registration_closes_at'], $form['starts_at'], $form['ends_at'], $form['status'], nowText(), $id]);
                    logAction($pdo, $user['id'], 'event_update', 'events', $id);
                    redirect('office.php?msg=' . urlencode('Мероприятие обновлено в базе данных.'));
                } else {
                    $ins = $pdo->prepare('INSERT INTO events (title, slug, category_id, venue_id, organizer_id, description, capacity, registration_opens_at, registration_closes_at, starts_at, ends_at, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $ins->execute([$form['title'], $slug, $form['category_id'], $form['venue_id'], $user['id'], $form['description'] !== '' ? $form['description'] : null, $form['capacity'], $form['registration_opens_at'], $form['registration_closes_at'], $form['starts_at'], $form['ends_at'], $form['status'], nowText(), nowText()]);
                    $newId = insertedId($pdo);
                    logAction($pdo, $user['id'], 'event_create', 'events', $newId);
                    redirect('office.php?p=edit&id=' . $newId . '&msg=' . urlencode('Мероприятие добавлено в базу. Теперь можно добавить программу ниже.') . '#program');
                }
            }
            $p = 'edit';
        }
    }

    if (in_array($action, ['reg_status', 'reg_delete', 'checkin', 'notify', 'wait_delete', 'wait_add'], true)) {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        if ($eventId === 0 && $tokenFromLink !== '') {
            $stmt = $pdo->prepare('SELECT event_id FROM registrations WHERE qr_token = ?');
            $stmt->execute([$tokenFromLink]);
            $eventId = (int) $stmt->fetchColumn();
        }
        $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        if (!$event || !canEditEvent($user, $event)) {
            redirect('office.php');
        }
        if ($action === 'reg_status') {
            $regId = (int) ($_POST['reg_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if (in_array($status, ['registered', 'confirmed', 'attended', 'cancelled'], true)) {
                if ($status === 'attended') {
                    $upd = $pdo->prepare('UPDATE registrations SET status = ?, attended_at = ? WHERE id = ? AND event_id = ?');
                    $upd->execute([$status, nowText(), $regId, $eventId]);
                } elseif ($status === 'cancelled') {
                    $upd = $pdo->prepare('UPDATE registrations SET status = ?, cancelled_at = ? WHERE id = ? AND event_id = ?');
                    $upd->execute([$status, nowText(), $regId, $eventId]);
                    promoteWaitlist($pdo, $eventId);
                } else {
                    $upd = $pdo->prepare('UPDATE registrations SET status = ? WHERE id = ? AND event_id = ?');
                    $upd->execute([$status, $regId, $eventId]);
                }
                logAction($pdo, $user['id'], 'registration_status_' . $status, 'registrations', $regId);
            }
            redirect('office.php?p=people&event_id=' . $eventId . '&msg=' . urlencode('Статус участника обновлён.'));
        }
        if ($action === 'reg_delete') {
            $regId = (int) ($_POST['reg_id'] ?? 0);
            $del = $pdo->prepare('DELETE FROM registrations WHERE id = ? AND event_id = ?');
            $del->execute([$regId, $eventId]);
            logAction($pdo, $user['id'], 'registration_delete', 'registrations', $regId);
            promoteWaitlist($pdo, $eventId);
            redirect('office.php?p=people&event_id=' . $eventId . '&msg=' . urlencode('Запись участника удалена из базы.'));
        }
        if ($action === 'checkin') {
            $token = trim($_POST['token'] ?? '');
            $stmt = $pdo->prepare('SELECT reg.*, u.last_name, u.first_name, u.middle_name FROM registrations reg INNER JOIN users u ON u.id = reg.user_id WHERE reg.qr_token = ? AND reg.event_id = ?');
            $stmt->execute([$token, $eventId]);
            $reg = $stmt->fetch();
            if (!$reg) {
                $error = 'Билет с таким токеном для этого мероприятия не найден.';
            } elseif ($reg['status'] === 'cancelled') {
                $error = 'Запись по этому токену отменена.';
            } elseif ($reg['status'] === 'attended') {
                $error = 'Участник уже отмечен: ' . fullName($reg) . ', время ' . formatDate($reg['attended_at']) . '.';
            } else {
                $upd = $pdo->prepare("UPDATE registrations SET status = 'attended', attended_at = ? WHERE id = ?");
                $upd->execute([nowText(), $reg['id']]);
                logAction($pdo, $user['id'], 'checkin', 'registrations', (int) $reg['id']);
                redirect('office.php?p=people&event_id=' . $eventId . '&msg=' . urlencode('Посещение отмечено: ' . fullName($reg)));
            }
        }
        if ($action === 'notify') {
            $subject = trim($_POST['subject'] ?? '');
            $text = trim($_POST['text'] ?? '');
            if ($subject === '' || $text === '') {
                $error = 'Заполните тему и текст рассылки.';
            } else {
                $stmt = $pdo->prepare("SELECT user_id FROM registrations WHERE event_id = ? AND status IN ('registered', 'confirmed', 'attended')");
                $stmt->execute([$eventId]);
                $rows = $stmt->fetchAll();
                foreach ($rows as $row) {
                    notify($pdo, $row['user_id'], $eventId, 'organizer_message', $subject, $text, true);
                }
                logAction($pdo, $user['id'], 'notify_participants', 'events', $eventId);
                redirect('office.php?p=people&event_id=' . $eventId . '&msg=' . urlencode('Уведомление отправлено участникам: ' . count($rows)));
            }
        }
        if ($action === 'wait_delete') {
            $del = $pdo->prepare('DELETE FROM waitlist WHERE id = ? AND event_id = ?');
            $del->execute([(int) ($_POST['wait_id'] ?? 0), $eventId]);
            redirect('office.php?p=people&event_id=' . $eventId . '&msg=' . urlencode('Запись листа ожидания удалена.'));
        }
        if ($action === 'wait_add') {
            $free = freePlaces($pdo, $event);
            if ($free !== null && $free <= 0) {
                $error = 'Свободных мест нет, сначала освободите место.';
            } else {
                promoteWaitlist($pdo, $eventId);
                redirect('office.php?p=people&event_id=' . $eventId . '&msg=' . urlencode('Первый участник листа ожидания записан на мероприятие.'));
            }
        }
        $p = 'people';
    }
}

if ($p === 'edit') {
    $form = ['title' => '', 'category_id' => '', 'venue_id' => '', 'description' => '', 'capacity' => '', 'registration_opens_at' => '', 'registration_closes_at' => '', 'starts_at' => '', 'ends_at' => '', 'status' => 'draft'];
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$id]);
        $event = $stmt->fetch();
        if (!$event || !canEditEvent($user, $event)) {
            redirect('office.php');
        }
        $form = $event;
    }
    if ($formSubmitted !== null) {
        $form = $formSubmitted;
    }
    $categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
    $venues = $pdo->query('SELECT * FROM venues ORDER BY name')->fetchAll();
    $programEditId = (int) ($_GET['program_edit'] ?? 0);
    $programItem = ['title' => '', 'speaker' => '', 'place' => '', 'starts_at' => $form['starts_at'], 'ends_at' => $form['ends_at'], 'sort_order' => 0];
    $programItems = [];
    if ($id > 0) {
        if ($programEditId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM event_program WHERE id = ? AND event_id = ?');
            $stmt->execute([$programEditId, $id]);
            $found = $stmt->fetch();
            if ($found) {
                $programItem = $found;
            }
        }
        $stmt = $pdo->prepare('SELECT * FROM event_program WHERE event_id = ? ORDER BY sort_order ASC, starts_at ASC');
        $stmt->execute([$id]);
        $programItems = $stmt->fetchAll();
    }
    pageStart('Мероприятие', $pdo, $user);
?>
<p><a href="office.php">← Организатор</a></p>
<h2 class="page-title"><?= $id > 0 ? 'Изменить' : 'Новое' ?></h2>
<p class="lead">Форма пишет прямо в таблицу events: <?= $id > 0 ? 'обновляется существующая запись.' : 'создаётся новая запись, адрес (slug) строится из названия автоматически.' ?></p>
<?php showMessage($message, $error); ?>
<form class="form-box" id="eventForm" method="post">
<?= csrfField() ?><input type="hidden" name="action" value="event_save"><input type="hidden" name="event_id" value="<?= $id ?>">
<div class="form-grid">
<div class="full"><label>Название *</label><input type="text" name="title" required maxlength="200" value="<?= e($form['title']) ?>"></div>
<div><label>Категория *</label><select name="category_id" required><option value="">Выберите категорию</option><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= (string) $form['category_id'] === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Площадка</label><select name="venue_id"><option value="0">Без площадки</option><?php foreach ($venues as $venue): ?><option value="<?= (int) $venue['id'] ?>" <?= (string) $form['venue_id'] === (string) $venue['id'] ? 'selected' : '' ?>><?= e($venue['name']) ?><?= $venue['capacity'] ? ' (до ' . e($venue['capacity']) . ')' : '' ?></option><?php endforeach; ?></select></div>
<div><label>Количество мест (пусто — без ограничения)</label><input type="number" name="capacity" min="1" value="<?= e($form['capacity'] ?? '') ?>"></div>
<div><label>Статус</label><select name="status"><?php foreach (['draft', 'moderation', 'published', 'cancelled', 'completed'] as $st): ?><option value="<?= $st ?>" <?= $form['status'] === $st ? 'selected' : '' ?>><?= e(eventStatusName($st)) ?></option><?php endforeach; ?></select></div>
<div><label>Начало *</label><input id="starts_at" type="datetime-local" name="starts_at" required value="<?= inputDate($form['starts_at']) ?>"></div>
<div><label>Окончание *</label><input id="ends_at" type="datetime-local" name="ends_at" required value="<?= inputDate($form['ends_at']) ?>"></div>
<div><label>Открытие записи</label><input type="datetime-local" name="registration_opens_at" value="<?= inputDate($form['registration_opens_at']) ?>"></div>
<div><label>Закрытие записи</label><input type="datetime-local" name="registration_closes_at" value="<?= inputDate($form['registration_closes_at']) ?>"></div>
<div class="full"><label>Описание</label><textarea name="description"><?= e($form['description'] ?? '') ?></textarea></div>
</div>
<p style="margin-top:16px"><button class="btn" type="submit">Сохранить в базу</button> <a class="btn btn-light" href="office.php">Отмена</a></p>
</form>
<?php if ($id > 0): ?>
<h3 class="section" id="program">Программа мероприятия</h3>
<p class="lead">Пункты программы хранятся в таблице event_program и должны укладываться во время мероприятия: <?= formatDate($form['starts_at']) ?> — <?= formatDate($form['ends_at']) ?>.</p>
<table>
<tr><th>Порядок</th><th>Время</th><th>Название</th><th>Докладчик / место</th><th></th></tr>
<?php foreach ($programItems as $row): ?>
<tr>
<td><?= (int) $row['sort_order'] ?></td>
<td><?= formatDate($row['starts_at']) ?> — <?= date('H:i', strtotime($row['ends_at'])) ?></td>
<td><?= e($row['title']) ?></td>
<td><?= e($row['speaker'] ?? '') ?><br><span class="grey"><?= e($row['place'] ?? '') ?></span></td>
<td><div class="actions">
<a class="btn btn-light btn-small" href="office.php?p=edit&id=<?= $id ?>&program_edit=<?= (int) $row['id'] ?>#program">Изменить</a>
<form method="post" data-confirm="Удалить пункт программы?"><?= csrfField() ?><input type="hidden" name="action" value="program_delete"><input type="hidden" name="event_id" value="<?= $id ?>"><input type="hidden" name="item_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-small" type="submit">Удалить</button></form>
</div></td>
</tr>
<?php endforeach; ?>
</table>
<?php if (!$programItems): ?><p class="empty">Программа пока пустая. Добавьте первый пункт формой ниже.</p><?php endif; ?>
<h3 class="section"><?= $programEditId > 0 ? 'Изменить пункт программы' : 'Добавить пункт программы' ?></h3>
<form class="form-box" method="post">
<?= csrfField() ?><input type="hidden" name="action" value="program_save"><input type="hidden" name="event_id" value="<?= $id ?>"><input type="hidden" name="program_id" value="<?= $programEditId ?>">
<div class="form-grid">
<div class="full"><label>Название *</label><input type="text" name="program_title" required maxlength="200" value="<?= e($programItem['title']) ?>"></div>
<div><label>Докладчик</label><input type="text" name="program_speaker" maxlength="150" value="<?= e($programItem['speaker'] ?? '') ?>"></div>
<div><label>Место</label><input type="text" name="program_place" maxlength="100" value="<?= e($programItem['place'] ?? '') ?>"></div>
<div><label>Начало *</label><input type="datetime-local" name="program_starts_at" required value="<?= inputDate($programItem['starts_at']) ?>"></div>
<div><label>Окончание *</label><input type="datetime-local" name="program_ends_at" required value="<?= inputDate($programItem['ends_at']) ?>"></div>
<div><label>Порядок сортировки</label><input type="number" name="program_sort_order" value="<?= e($programItem['sort_order']) ?>"></div>
</div>
<p style="margin-top:14px"><button class="btn" type="submit">Сохранить пункт</button> <a class="btn btn-light" href="office.php?p=edit&id=<?= $id ?>#program">Сбросить форму</a></p>
</form>
<?php endif; ?>
<?php
    pageEnd();
    exit;
}

if ($p === 'people') {
    if ($eventId === 0 && $tokenFromLink !== '') {
        $stmt = $pdo->prepare('SELECT event_id FROM registrations WHERE qr_token = ?');
        $stmt->execute([$tokenFromLink]);
        $eventId = (int) $stmt->fetchColumn();
    }
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    if (!$event || !canEditEvent($user, $event)) {
        redirect('office.php');
    }
    $stmt = $pdo->prepare('SELECT reg.*, u.last_name, u.first_name, u.middle_name, u.email, u.phone FROM registrations reg INNER JOIN users u ON u.id = reg.user_id WHERE reg.event_id = ? ORDER BY reg.registered_at ASC');
    $stmt->execute([$eventId]);
    $rows = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT w.*, u.last_name, u.first_name, u.middle_name, u.email FROM waitlist w INNER JOIN users u ON u.id = w.user_id WHERE w.event_id = ? ORDER BY w.position ASC');
    $stmt->execute([$eventId]);
    $waitRows = $stmt->fetchAll();
    pageStart('Участники', $pdo, $user);
?>
<p><a href="office.php">← Организатор</a> · <a href="index.php?p=event&id=<?= $eventId ?>">Карточка</a></p>
<h2 class="page-title">Участники</h2>
<p class="lead"><?= e($event['title']) ?> · <?= formatDate($event['starts_at']) ?> · записано: <?= registeredCount($pdo, $eventId) ?><?= $event['capacity'] !== null ? ' из ' . e($event['capacity']) : '' ?></p>
<?php showMessage($message, $error); ?>
<p class="actions">
<a class="btn btn-light" href="office.php?p=export&event_id=<?= $eventId ?>&format=csv">Список CSV</a>
<a class="btn btn-light" href="office.php?p=export&event_id=<?= $eventId ?>&format=xlsx">Список XLSX</a>
<a class="btn btn-light" href="office.php?p=edit&id=<?= $eventId ?>#program">Программа</a>
</p>
<h3 class="section">Отметка посещения по токену</h3>
<form method="post" class="narrow">
<?= csrfField() ?><input type="hidden" name="action" value="checkin"><input type="hidden" name="event_id" value="<?= $eventId ?>">
<label>Токен из QR-кода билета</label>
<input type="text" name="token" required placeholder="Вставьте токен участника" value="<?= e($tokenFromLink) ?>">
<p style="margin-top:12px"><button class="btn" type="submit">Отметить посещение</button></p>
<p class="grey">Ссылка из QR-кода открывает эту страницу и подставляет токен. Организатору остаётся проверить участника в таблице ниже.</p>
</form>
<h3 class="section">Записи в таблице registrations</h3>
<table>
<tr><th>Участник</th><th>Контакты</th><th>Статус</th><th>Токен</th><th></th></tr>
<?php foreach ($rows as $row): ?>
<tr>
<td><?= e(fullName($row)) ?></td>
<td><?= e($row['email']) ?><br><?= e($row['phone'] ?? '') ?></td>
<td>
<form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="action" value="reg_status"><input type="hidden" name="event_id" value="<?= $eventId ?>"><input type="hidden" name="reg_id" value="<?= (int) $row['id'] ?>">
<select name="status" style="width:auto;display:inline-block"><?php foreach (['registered', 'confirmed', 'attended', 'cancelled'] as $st): ?><option value="<?= $st ?>" <?= $row['status'] === $st ? 'selected' : '' ?>><?= e(regStatusName($st)) ?></option><?php endforeach; ?></select>
<button class="btn btn-light btn-small" type="submit">OK</button>
</form>
<?php if ($row['attended_at']): ?><br><span class="grey"><?= formatDate($row['attended_at']) ?></span><?php endif; ?>
</td>
<td style="font-family:Consolas,monospace;font-size:12px"><?= e($row['qr_token']) ?></td>
<td><form method="post" data-confirm="Удалить запись участника из базы?"><?= csrfField() ?><input type="hidden" name="action" value="reg_delete"><input type="hidden" name="event_id" value="<?= $eventId ?>"><input type="hidden" name="reg_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-small" type="submit">Удалить</button></form></td>
</tr>
<?php endforeach; ?>
</table>
<?php if (!$rows): ?><p class="empty">Участников пока нет.</p><?php endif; ?>
<h3 class="section">Лист ожидания</h3>
<table>
<tr><th>Позиция</th><th>Участник</th><th>Статус</th><th></th></tr>
<?php foreach ($waitRows as $wait): ?>
<tr>
<td><?= (int) $wait['position'] ?></td>
<td><?= e(fullName($wait)) ?><br><span class="grey"><?= e($wait['email']) ?></span></td>
<td><?= e(regStatusName($wait['status'])) ?></td>
<td><form method="post" data-confirm="Удалить из листа ожидания?"><?= csrfField() ?><input type="hidden" name="action" value="wait_delete"><input type="hidden" name="event_id" value="<?= $eventId ?>"><input type="hidden" name="wait_id" value="<?= (int) $wait['id'] ?>"><button class="btn btn-light btn-small" type="submit">Удалить</button></form></td>
</tr>
<?php endforeach; ?>
</table>
<?php if (!$waitRows): ?><p class="empty">Лист ожидания пуст.</p><?php endif; ?>
<form method="post"><?= csrfField() ?><input type="hidden" name="action" value="wait_add"><input type="hidden" name="event_id" value="<?= $eventId ?>"><button class="btn btn-light" type="submit">Записать первого из ожидания, если есть место</button></form>
<h3 class="section">Рассылка участникам</h3>
<form class="form-box" method="post">
<?= csrfField() ?><input type="hidden" name="action" value="notify"><input type="hidden" name="event_id" value="<?= $eventId ?>">
<div style="margin-bottom:14px"><label>Тема</label><input type="text" name="subject" maxlength="200" placeholder="Например: Изменение аудитории"></div>
<div style="margin-bottom:14px"><label>Текст уведомления в личный кабинет</label><textarea name="text"></textarea></div>
<button class="btn" type="submit">Отправить записанным участникам</button>
</form>
<?php
    pageEnd();
    exit;
}

if ($user['role_code'] === 'admin') {
    $events = $pdo->query('SELECT e.*, c.name AS category_name FROM events e INNER JOIN categories c ON c.id = e.category_id ORDER BY e.starts_at DESC')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT e.*, c.name AS category_name FROM events e INNER JOIN categories c ON c.id = e.category_id WHERE e.organizer_id = ? ORDER BY e.starts_at DESC');
    $stmt->execute([$user['id']]);
    $events = $stmt->fetchAll();
}

pageStart('Организатор', $pdo, $user);
?>
<h2 class="page-title">Организатор</h2>
<p class="lead"><?= $user['role_code'] === 'admin' ? 'Все мероприятия платформы.' : 'Ваши мероприятия: создание, редактирование, программа, участники, отметка посещения и выгрузки.' ?></p>
<?php showMessage($message, $error); ?>
<div class="cards">
<div class="card"><strong><?= count($events) ?></strong><span>Мероприятий</span></div>
<div class="card"><strong><?= array_sum(array_map(function ($ev) use ($pdo) { return registeredCount($pdo, $ev['id']); }, $events)) ?></strong><span>Записей участников</span></div>
<div class="card"><strong><?= $user['role_code'] === 'admin' ? 'Все' : 'Мои' ?></strong><span>Область доступа</span></div>
<div class="card"><strong><?= (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'attended'")->fetchColumn() ?></strong><span>Отмечено посещений</span></div>
</div>
<p class="actions"><a class="btn" href="office.php?p=edit">Новое мероприятие</a> <a class="btn btn-light" href="office.php?p=export&mode=summary&format=xlsx">Отчёт XLSX</a> <a class="btn btn-light" href="office.php?p=export&mode=summary">Сводка CSV</a></p>
<h3 class="section">Мероприятия и сводка</h3>
<p class="grey">Колонки «Посетило», «Ожидание» и «Заполнено» считаются из таблиц registrations и waitlist при открытии страницы.</p>
<?php if (!$events): ?>
<p class="empty">Мероприятий пока нет. Создайте первое кнопкой выше.</p>
<?php else: ?>
<table>
<tr><th>Название</th><th>Дата</th><th>Категория</th><th>Записано</th><th>Посетило</th><th>Ожидание</th><th>Заполнено</th><th>Статус в БД</th><th>Действия</th></tr>
<?php foreach ($events as $ev): ?>
<?php
$regCount = registeredCount($pdo, $ev['id']);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status = 'attended'");
$stmt->execute([$ev['id']]);
$attendedCount = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM waitlist WHERE event_id = ? AND status = 'waiting'");
$stmt->execute([$ev['id']]);
$waitingCount = (int) $stmt->fetchColumn();
$fill = $ev['capacity'] ? round($regCount * 100 / (int) $ev['capacity']) . '%' : '—';
?>
<tr>
<td><a href="index.php?p=event&id=<?= (int) $ev['id'] ?>"><?= e($ev['title']) ?></a><br><span class="grey"><?= e($ev['slug']) ?></span></td>
<td><?= formatDate($ev['starts_at']) ?></td>
<td><?= e($ev['category_name']) ?></td>
<td><?= $regCount ?><?= $ev['capacity'] !== null ? ' / ' . e($ev['capacity']) : '' ?></td>
<td><?= $attendedCount ?></td>
<td><?= $waitingCount ?></td>
<td><?= e($fill) ?></td>
<td>
<form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="action" value="event_status"><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>">
<select name="status" style="width:auto;display:inline-block"><?php foreach (['draft', 'moderation', 'published', 'cancelled', 'completed'] as $st): ?><option value="<?= $st ?>" <?= $ev['status'] === $st ? 'selected' : '' ?>><?= e(eventStatusName($st)) ?></option><?php endforeach; ?></select>
<button class="btn btn-light btn-small" type="submit">OK</button>
</form>
</td>
<td><div class="actions">
<a class="btn btn-light btn-small" href="office.php?p=edit&id=<?= (int) $ev['id'] ?>">Изменить и программа</a>
<a class="btn btn-light btn-small" href="office.php?p=people&event_id=<?= (int) $ev['id'] ?>">Участники</a>
<form method="post" data-confirm="Удалить мероприятие из базы? Программа и записи удалятся по внешним ключам."><?= csrfField() ?><input type="hidden" name="action" value="event_delete"><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><button class="btn btn-small" type="submit">Удалить</button></form>
</div></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php pageEnd(); ?>
