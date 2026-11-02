<?php
$dbType = 'sqlite';
$dbHost = 'localhost';
$dbName = 'prestige_events';
$dbUser = 'sa';
$dbPass = '';

try {
    if ($dbType === 'sqlsrv') {
        $pdo = new PDO('sqlsrv:Server=' . $dbHost . ';Database=' . $dbName . ';TrustServerCertificate=true', $dbUser, $dbPass);
    } else {
        if (!is_dir(__DIR__ . '/data')) {
            mkdir(__DIR__ . '/data', 0777, true);
        }
        $pdo = new PDO('sqlite:' . __DIR__ . '/data/prestige.db');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetch();
        if (!$check) {
            $pdo->exec(file_get_contents(__DIR__ . '/database/sqlite.sql'));
        }
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $error) {
    die('Ошибка подключения к базе данных: ' . htmlspecialchars($error->getMessage()));
}
