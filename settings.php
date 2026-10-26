<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['admin']);
$pageTitle = 'Настройки';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $values = $_POST['values'] ?? [];
    if (is_array($values)) {
        $upd = $pdo->prepare('UPDATE settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?');
        foreach ($values as $key => $value) {
            $upd->execute([trim((string) $value), nowText(), $key]);
        }
        logAction($pdo, $user['id'], 'settings_update', 'settings', null);
    }
    redirect('settings.php?msg=' . urlencode('Настройки сохранены в таблице settings. Название платформы в шапке обновилось.'));
}
$message = trim($_GET['msg'] ?? '');
$rows = $pdo->query('SELECT * FROM settings ORDER BY setting_key ASC')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<p><a href="admin.php">← Администратор</a></p>
<h2 class="page-title">Настройки</h2>
<p class="lead">Таблица settings (ключ — значение). platform_title показывается в шапке, max_waitlist_size ограничивает лист ожидания.</p>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>

<form class="form-box" method="post">
<?= csrfField() ?>
<?php foreach ($rows as $row): ?>
<div style="margin-bottom:16px">
<label><?= e($row['setting_key']) ?> — <?= e($row['description'] ?? '') ?></label>
<input type="text" name="values[<?= e($row['setting_key']) ?>]" value="<?= e($row['setting_value']) ?>">
<p class="grey">Обновлено: <?= formatDate($row['updated_at']) ?></p>
</div>
<?php endforeach; ?>
<button class="btn" type="submit">Сохранить настройки</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
