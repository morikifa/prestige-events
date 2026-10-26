<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['admin']);
$pageTitle = 'Категории';
$editId = (int) ($_GET['edit'] ?? 0);
$message = trim($_GET['msg'] ?? '');
$error = trim($_GET['err'] ?? '');
$edit = ['name' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        try {
            $del = $pdo->prepare('DELETE FROM categories WHERE id = ?');
            $del->execute([(int) ($_POST['item_id'] ?? 0)]);
            logAction($pdo, $user['id'], 'category_delete', 'categories', (int) ($_POST['item_id'] ?? 0));
            redirect('categories.php?msg=' . urlencode('Категория удалена.'));
        } catch (PDOException $ex) {
            redirect('categories.php?err=' . urlencode('Нельзя удалить категорию, в которой есть мероприятия. Сначала измените категорию мероприятий.'));
        }
    }
    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $saveId = (int) ($_POST['item_id'] ?? 0);
        if ($name === '') {
            $error = 'Название категории не может быть пустым.';
            $edit = ['name' => $name, 'description' => $description];
            $editId = $saveId;
        } else {
            try {
                if ($saveId > 0) {
                    $upd = $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
                    $upd->execute([$name, $description !== '' ? $description : null, $saveId]);
                    logAction($pdo, $user['id'], 'category_update', 'categories', $saveId);
                } else {
                    $ins = $pdo->prepare('INSERT INTO categories (name, description, created_at) VALUES (?, ?, ?)');
                    $ins->execute([$name, $description !== '' ? $description : null, nowText()]);
                    logAction($pdo, $user['id'], 'category_create', 'categories', (int) $pdo->lastInsertId());
                }
                redirect('categories.php?msg=' . urlencode('Категория сохранена в базе данных.'));
            } catch (PDOException $ex) {
                $error = 'Категория с таким названием уже есть.';
                $edit = ['name' => $name, 'description' => $description];
                $editId = $saveId;
            }
        }
    }
}

if ($editId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$editId]);
    $found = $stmt->fetch();
    if ($found) {
        $edit = $found;
    }
}

$rows = $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<p><a href="admin.php">← Администратор</a></p>
<h2 class="page-title">Категории</h2>
<p class="lead">Справочник categories. Добавление, редактирование и удаление сразу меняют фильтры афиши и форму мероприятия.</p>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

<table>
<tr><th>ID</th><th>Название</th><th>Описание</th><th>Мероприятий</th><th></th></tr>
<?php foreach ($rows as $row): ?>
<?php $stmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE category_id = ?'); $stmt->execute([$row['id']]); $cnt = (int) $stmt->fetchColumn(); ?>
<tr>
<td><?= (int) $row['id'] ?></td>
<td><strong><?= e($row['name']) ?></strong></td>
<td><?= e($row['description'] ?? '') ?></td>
<td><?= $cnt ?></td>
<td><div class="actions">
<a class="btn btn-light btn-small" href="categories.php?edit=<?= (int) $row['id'] ?>">Изменить</a>
<form method="post" data-confirm="Удалить категорию?"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="item_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-small" type="submit">Удалить</button></form>
</div></td>
</tr>
<?php endforeach; ?>
</table>

<h3 class="section"><?= $editId > 0 ? 'Изменить категорию' : 'Добавить категорию' ?></h3>
<form class="form-box" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="save">
<input type="hidden" name="item_id" value="<?= $editId ?>">
<div style="margin-bottom:14px"><label>Название *</label><input type="text" name="name" required maxlength="100" value="<?= e($edit['name']) ?>"></div>
<div style="margin-bottom:14px"><label>Описание</label><textarea name="description"><?= e($edit['description'] ?? '') ?></textarea></div>
<button class="btn" type="submit">Сохранить</button> <a class="btn btn-light" href="categories.php">Сбросить</a>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
