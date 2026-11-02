<?php
require __DIR__ . '/config.php';

try {
    if ($dbType === 'sqlsrv') {
        $pdo = new PDO('sqlsrv:Server=' . $dbHost . ';Database=' . $dbName . ';TrustServerCertificate=true', $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } else {
        if (!is_dir(__DIR__ . '/../data')) {
            mkdir(__DIR__ . '/../data', 0777, true);
        }
        $dbFile = __DIR__ . '/../data/prestige.db';
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetch();
        if (!$check) {
            $sql = file_get_contents(__DIR__ . '/../database/sqlite.sql');
            $pdo->exec($sql);
        }
    }
} catch (PDOException $error) {
    die('Ошибка подключения к базе данных: ' . htmlspecialchars($error->getMessage()));
}
