<?php

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $settings = config('db');
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    if (($settings['driver'] ?? 'mysql') === 'sqlite') {
        $path = $settings['sqlite_path'];
        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $settings['host'],
        $settings['port'],
        $settings['name'],
        $settings['charset']
    );

    $pdo = new PDO($dsn, $settings['user'], $settings['password'], $options + [
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function db_all(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute(array_values($params));
    return $statement->fetchAll();
}

function db_one(string $sql, array $params = [])
{
    $statement = db()->prepare($sql);
    $statement->execute(array_values($params));
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function db_val(string $sql, array $params = [], $default = null)
{
    $statement = db()->prepare($sql);
    $statement->execute(array_values($params));
    $value = $statement->fetchColumn();
    return $value === false ? $default : $value;
}

function db_exec(string $sql, array $params = []): int
{
    $statement = db()->prepare($sql);
    $statement->execute(array_values($params));
    return $statement->rowCount();
}

function db_last_id(): int
{
    return (int) db()->lastInsertId();
}

function db_transaction(callable $callback)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $callback();
        $pdo->commit();
        return $result;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}
