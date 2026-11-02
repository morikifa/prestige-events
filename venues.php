<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['admin']);
$pageTitle = 'Площадки';
$editId = (int) ($_GET['edit'] ?? 0);
$message = trim($_GET['msg'] ?? '');
$error = '';
$edit = ['name' => '', 'address' => '', 'capacity' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $del = $pdo->prepare('DELETE FROM venues WHERE id = ?');
        $del->execute([(int) ($_POST['item_id'] ?? 0)]);
        logAction($pdo, $user['id'], 'venue_delete', 'venues', (int) ($_POST['item_id'] ?? 0));
        redirect('venues.php?msg=' . urlencode('Площадка удалена. У связанных мероприятий площадка очищена (ON DELETE SET NULL).'));
    }
    if ($action === 'save') {
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
                logAction($pdo, $user['id'], 'venue_create', 'venues', (int) $pdo->lastInsertId());
            }
            redirect('venues.php?msg=' . urlencode('Площадка сохранена в базе данных.'));
        }
        $edit = ['name' => $name, 'address' => $address, 'capacity' => $capText];
        $editId = $saveId;
    }
}

if ($editId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM venues WHERE id = ?');
    $stmt->execute([$editId]);
    $found = $stmt->fetch();
    if ($found) {
        $edit = $found;
    }
}

$rows = $pdo->query('SELECT * FROM venues ORDER BY name ASC')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<p><a href="admin.php">← Администратор</a></p>
<h2 class="page-title">Площадки</h2>
<p class="lead">Справочник venues. Вместимость площадки проверяется при сохранении мероприятия.</p>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

<table>
<tr><th>ID</th><th>Название</th><th>Адрес</th><th>Вместимость</th><th>Мероприятий</th><th></th></tr>
<?php foreach ($rows as $row): ?>
<?php $stmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE venue_id = ?'); $stmt->execute([$row['id']]); $cnt = (int) $stmt->fetchColumn(); ?>
<tr>
<td><?= (int) $row['id'] ?></td>
<td><strong><?= e($row['name']) ?></strong></td>
<td><?= e($row['address']) ?></td>
<td><?= e($row['capacity'] ?? '') ?></td>
<td><?= $cnt ?></td>
<td><div class="actions">
<a class="btn btn-light btn-small" href="venues.php?edit=<?= (int) $row['id'] ?>">Изменить</a>
<form method="post" data-confirm="Удалить площадку?"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="item_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-small" type="submit">Удалить</button></form>
</div></td>
</tr>
<?php endforeach; ?>
</table>

<h3 class="section"><?= $editId > 0 ? 'Изменить площадку' : 'Добавить площадку' ?></h3>
<form class="form-box" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="save">
<input type="hidden" name="item_id" value="<?= $editId ?>">
<div class="form-grid">
<div><label>Название *</label><input type="text" name="name" required maxlength="150" value="<?= e($edit['name']) ?>"></div>
<div><label>Вместимость</label><input type="number" name="capacity" min="1" value="<?= e($edit['capacity'] ?? '') ?>"></div>
<div class="full"><label>Адрес *</label><input type="text" name="address" required maxlength="255" value="<?= e($edit['address']) ?>"></div>
</div>
<p style="margin-top:14px"><button class="btn" type="submit">Сохранить</button> <a class="btn btn-light" href="venues.php">Сбросить</a></p>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
