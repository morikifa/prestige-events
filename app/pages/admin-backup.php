<?php

require PRESTIGE_APP . '/lib/backup.php';

if (is_post()) {
    $action = (string) input('action');

    if ($action === 'create') {
        $filename = backup_create();
        log_action('backup_created', 'platform', null, ['file' => $filename]);
        flash('success', 'Резервная копия создана: ' . $filename . '.');
    }

    if ($action === 'delete') {
        $path = backup_path((string) input('file'));
        if ($path) {
            unlink($path);
            log_action('backup_deleted', 'platform', null, ['file' => basename($path)]);
            flash('success', 'Файл резервной копии удалён.');
        } else {
            flash('error', 'Файл не найден.');
        }
    }

    if ($action === 'restore') {
        $result = backup_restore((string) input('file'));
        if (isset($result['error'])) {
            flash('error', $result['error']);
        } else {
            log_action('backup_restored', 'platform', null, ['file' => (string) input('file')]);
            flash('success', 'Данные восстановлены из копии. Выполнено запросов: ' . (int) $result['statements'] . '.');
        }
    }

    redirect('admin/backup');
}

$download = (string) input('download', '');
if ($download !== '') {
    $path = backup_path($download);
    if (!$path) {
        abort(404, 'Файл резервной копии не найден.');
    }
    log_action('backup_downloaded', 'platform', null, ['file' => basename($path)]);
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

render('admin-backup', [
    'title' => 'Резервное копирование',
    'navActive' => 'admin',
    'files' => backup_files(),
    'directory' => backup_directory(),
]);
