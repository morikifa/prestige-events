<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['organizer', 'admin']);
$pageTitle = 'Программа мероприятия';
$eventId = (int) ($_GET['event_id'] ?? 0);
$editId = (int) ($_GET['edit'] ?? 0);
$message = trim($_GET['msg'] ?? '');
$error = '';

$stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event || !canEditEvent($user, $event)) {
    redirect('organizer.php');
}

$item = ['title' => '', 'speaker' => '', 'place' => '', 'starts_at' => $event['starts_at'], 'ends_at' => $event['ends_at'], 'sort_order' => 0];
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM event_program WHERE id = ? AND event_id = ?');
    $stmt->execute([$editId, $eventId]);
    $found = $stmt->fetch();
    if ($found) {
        $item = $found;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $del = $pdo->prepare('DELETE FROM event_program WHERE id = ? AND event_id = ?');
        $del->execute([(int) ($_POST['item_id'] ?? 0), $eventId]);
        logAction($pdo, $user['id'], 'program_delete', 'event_program', (int) ($_POST['item_id'] ?? 0));
        redirect('program.php?event_id=' . $eventId . '&msg=' . urlencode('Пункт программы удалён.'));
    }
    if ($action === 'save') {
        $item['title'] = trim($_POST['title'] ?? '');
        $item['speaker'] = trim($_POST['speaker'] ?? '');
        $item['place'] = trim($_POST['place'] ?? '');
        $item['starts_at'] = saveDate($_POST['starts_at'] ?? '');
        $item['ends_at'] = saveDate($_POST['ends_at'] ?? '');
        $item['sort_order'] = (int) ($_POST['sort_order'] ?? 0);
        $saveId = (int) ($_POST['item_id'] ?? 0);
        if ($item['title'] === '' || !$item['starts_at'] || !$item['ends_at']) {
            $error = 'Заполните название и время пункта программы.';
        } elseif ($item['ends_at'] < $item['starts_at']) {
            $error = 'Окончание пункта не может быть раньше начала.';
        } elseif ($item['starts_at'] < $event['starts_at'] || $item['ends_at'] > $event['ends_at']) {
            $error = 'Пункт программы должен укладываться во время мероприятия: ' . formatDate($event['starts_at']) . ' — ' . formatDate($event['ends_at']) . '.';
        } else {
            if ($saveId > 0) {
                $upd = $pdo->prepare('UPDATE event_program SET title = ?, speaker = ?, place = ?, starts_at = ?, ends_at = ?, sort_order = ? WHERE id = ? AND event_id = ?');
                $upd->execute([$item['title'], $item['speaker'] !== '' ? $item['speaker'] : null, $item['place'] !== '' ? $item['place'] : null, $item['starts_at'], $item['ends_at'], $item['sort_order'], $saveId, $eventId]);
                logAction($pdo, $user['id'], 'program_update', 'event_program', $saveId);
            } else {
                $ins = $pdo->prepare('INSERT INTO event_program (event_id, title, speaker, place, starts_at, ends_at, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([$eventId, $item['title'], $item['speaker'] !== '' ? $item['speaker'] : null, $item['place'] !== '' ? $item['place'] : null, $item['starts_at'], $item['ends_at'], $item['sort_order']]);
                logAction($pdo, $user['id'], 'program_create', 'event_program', (int) $pdo->lastInsertId());
            }
            redirect('program.php?event_id=' . $eventId . '&msg=' . urlencode('Программа сохранена в базе данных.'));
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM event_program WHERE event_id = ? ORDER BY sort_order ASC, starts_at ASC');
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<p><a href="organizer.php">← Организатор</a> · <a href="event.php?id=<?= $eventId ?>">Карточка мероприятия</a></p>
<h2 class="page-title">Программа</h2>
<p class="lead"><?= e($event['title']) ?> · <?= formatDate($event['starts_at']) ?> — <?= formatDate($event['ends_at']) ?></p>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

<table>
<tr><th>Порядок</th><th>Время</th><th>Название</th><th>Докладчик / место</th><th></th></tr>
<?php foreach ($items as $row): ?>
<tr>
<td><?= (int) $row['sort_order'] ?></td>
<td><?= formatDate($row['starts_at']) ?> — <?= date('H:i', strtotime($row['ends_at'])) ?></td>
<td><?= e($row['title']) ?></td>
<td><?= e($row['speaker'] ?? '') ?><br><span class="grey"><?= e($row['place'] ?? '') ?></span></td>
<td>
<div class="actions">
<a class="btn btn-light btn-small" href="program.php?event_id=<?= $eventId ?>&edit=<?= (int) $row['id'] ?>">Изменить</a>
<form method="post" data-confirm="Удалить пункт программы?">
<?= csrfField() ?>
<input type="hidden" name="action" value="delete">
<input type="hidden" name="item_id" value="<?= (int) $row['id'] ?>">
<button class="btn btn-small" type="submit">Удалить</button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php if (!$items): ?><p class="empty">Программа пока пустая. Добавьте первый пункт формой ниже.</p><?php endif; ?>

<h3 class="section"><?= $editId > 0 ? 'Изменить пункт' : 'Добавить пункт' ?></h3>
<form class="form-box" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="save">
<input type="hidden" name="item_id" value="<?= $editId ?>">
<div class="form-grid">
<div class="full"><label>Название *</label><input type="text" name="title" required maxlength="200" value="<?= e($item['title']) ?>"></div>
<div><label>Докладчик</label><input type="text" name="speaker" maxlength="150" value="<?= e($item['speaker'] ?? '') ?>"></div>
<div><label>Место</label><input type="text" name="place" maxlength="100" value="<?= e($item['place'] ?? '') ?>"></div>
<div><label>Начало *</label><input type="datetime-local" name="starts_at" required value="<?= inputDate($item['starts_at']) ?>"></div>
<div><label>Окончание *</label><input type="datetime-local" name="ends_at" required value="<?= inputDate($item['ends_at']) ?>"></div>
<div><label>Порядок сортировки</label><input type="number" name="sort_order" value="<?= e($item['sort_order']) ?>"></div>
</div>
<p style="margin-top:14px"><button class="btn" type="submit">Сохранить</button> <a class="btn btn-light" href="program.php?event_id=<?= $eventId ?>">Сбросить форму</a></p>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
