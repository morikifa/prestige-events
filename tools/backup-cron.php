<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Скрипт запускается только из командной строки.';
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';
require PRESTIGE_APP . '/lib/backup.php';

try {
    $filename = backup_create();
    echo 'Резервная копия создана: ' . $filename . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Ошибка создания копии: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
