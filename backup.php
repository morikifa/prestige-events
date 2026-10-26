<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
requireRole($user, ['admin']);
$pageTitle = 'Резервная копия';

$backupDir = __DIR__ . '/data/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

if (isset($_GET['download']) && $dbType === 'sqlite') {
    $name = basename((string) $_GET['download']);
    $file = $backupDir . '/' . $name;
    if (is_file($file) && substr($name, -3) === '.db') {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        readfile($file);
        exit;
    }
    die('Файл копии не найден.');
}

$message = trim($_GET['msg'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        if ($dbType === 'sqlite') {
            $source = __DIR__ . '/data/prestige.db';
            $target = $backupDir . '/backup_' . date('Ymd_His') . '.db';
            if (copy($source, $target)) {
                logAction($pdo, $user['id'], 'backup_create', 'database', null);
                redirect('backup.php?msg=' . urlencode('Резервная копия создана: ' . basename($target)));
            }
            $error = 'Не удалось создать копию. Проверьте права папки data.';
        } else {
            try {
                $file = 'C:\\Backup\\prestige_events_' . date('Ymd_His') . '.bak';
                $pdo->exec("BACKUP DATABASE [" . $dbName . "] TO DISK = '" . $file . "'");
                logAction($pdo, $user['id'], 'backup_create', 'database', null);
                redirect('backup.php?msg=' . urlencode('Команда BACKUP DATABASE выполнена. Файл на сервере SQL Server: ' . $file));
            } catch (PDOException $ex) {
                $error = 'SQL Server не выполнил BACKUP DATABASE: ' . $ex->getMessage() . ' Создайте папку C:\\Backup и выдайте права службе SQL Server или сделайте копию через SSMS (Tasks → Back Up).';
            }
        }
    }
    if ($action === 'restore') {
        if ($dbType !== 'sqlite') {
            $error = 'Для SQL Server восстановление выполняется в SSMS: база → Tasks → Restore → Database. Пошагово описано в README проекта.';
        } elseif (!isset($_FILES['dbfile']) || $_FILES['dbfile']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Выберите файл резервной копии .db.';
        } else {
            $name = $_FILES['dbfile']['name'];
            if (substr($name, -3) !== '.db') {
                $error = 'Нужен файл с расширением .db.';
            } else {
                $source = __DIR__ . '/data/prestige.db';
                copy($source, $backupDir . '/before_restore_' . date('Ymd_His') . '.db');
                if (move_uploaded_file($_FILES['dbfile']['tmp_name'], $source)) {
                    redirect('backup.php?msg=' . urlencode('База восстановлена из файла. Прежняя база сохранена как before_restore в папке backups. Войдите заново.'));
                }
                $error = 'Не удалось заменить файл базы данных.';
            }
        }
    }
    if ($action === 'delete' && $dbType === 'sqlite') {
        $name = basename((string) ($_POST['file'] ?? ''));
        $file = $backupDir . '/' . $name;
        if (is_file($file)) {
            unlink($file);
        }
        redirect('backup.php?msg=' . urlencode('Файл копии удалён.'));
    }
}

$files = [];
if ($dbType === 'sqlite') {
    $list = glob($backupDir . '/*.db');
    if ($list) {
        rsort($list);
        $files = $list;
    }
}

require __DIR__ . '/includes/header.php';
?>
<p><a href="admin.php">← Администратор</a></p>
<h2 class="page-title">Копия БД</h2>
<p class="lead">Режим базы сейчас: <strong><?= e($dbType) ?></strong>. В SQLite-режиме копия — это файл data/prestige.db. В SQL Server-режиме кнопка выполняет BACKUP DATABASE, а восстановление делается в SSMS (инструкция в README). По ТЗ копии хранят не менее 30 суток на отдельном носителе — скачивайте файлы из списка.</p>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

<form method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="create">
<button class="btn" type="submit">Создать резервную копию сейчас</button>
</form>

<?php if ($dbType === 'sqlite'): ?>
<h3 class="section">Файлы копий в data/backups</h3>
<table>
<tr><th>Файл</th><th>Размер</th><th></th></tr>
<?php foreach ($files as $file): ?>
<tr>
<td><?= e(basename($file)) ?></td>
<td><?= round(filesize($file) / 1024) ?> КБ</td>
<td><div class="actions">
<a class="btn btn-light btn-small" href="backup.php?download=<?= urlencode(basename($file)) ?>">Скачать</a>
<form method="post" data-confirm="Удалить файл копии?"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="file" value="<?= e(basename($file)) ?>"><button class="btn btn-small" type="submit">Удалить</button></form>
</div></td>
</tr>
<?php endforeach; ?>
</table>
<?php if (!$files): ?><p class="empty">Копий пока нет. Нажмите кнопку выше.</p><?php endif; ?>

<h3 class="section">Восстановление из файла .db</h3>
<form class="form-box" method="post" enctype="multipart/form-data" data-confirm="Заменить текущую базу загруженным файлом? Текущая база будет сохранена как before_restore.">
<?= csrfField() ?>
<input type="hidden" name="action" value="restore">
<div style="margin-bottom:14px"><label>Файл резервной копии SQLite</label><input type="file" name="dbfile" accept=".db"></div>
<button class="btn btn-light" type="submit">Восстановить базу из файла</button>
</form>
<?php else: ?>
<h3 class="section">Восстановление SQL Server</h3>
<p>SSMS → правый клик по базам данных → Restore Database → Device → выбрать .bak файл → OK. Подробно — раздел «Резервная копия SQL Server» в README проекта.</p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
