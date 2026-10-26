<?php

function backup_directory(): string
{
    $directory = config('upload.backup_dir');
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
    return $directory;
}

function backup_tables(): array
{
    return [
        'roles',
        'categories',
        'venues',
        'settings',
        'users',
        'events',
        'event_program',
        'registrations',
        'waitlist',
        'notifications',
        'action_log',
    ];
}

function backup_sql_value($value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    return db()->quote((string) $value);
}

function backup_create(): string
{
    $directory = backup_directory();
    $filename = 'prestige-' . date('Ymd-His') . '.sql';
    $lines = [];

    $lines[] = '-- Prestige Events: резервная копия данных';
    $lines[] = '-- Дата: ' . date('Y-m-d H:i:s');
    $lines[] = '-- Драйвер: ' . config('db.driver');
    $lines[] = '';

    if (config('db.driver') === 'mysql') {
        $lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
    } else {
        $lines[] = 'PRAGMA foreign_keys = OFF;';
    }
    $lines[] = '';

    foreach (backup_tables() as $table) {
        $rows = db_all('SELECT * FROM ' . $table);
        $lines[] = 'DELETE FROM ' . $table . ';';
        foreach ($rows as $row) {
            $columns = array_keys($row);
            $values = array_map('backup_sql_value', array_values($row));
            $lines[] = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');';
        }
        $lines[] = '';
    }

    if (config('db.driver') === 'mysql') {
        $lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
    } else {
        $lines[] = 'PRAGMA foreign_keys = ON;';
    }

    file_put_contents($directory . '/' . $filename, implode("\n", $lines));

    return $filename;
}

function backup_files(): array
{
    $directory = backup_directory();
    $files = [];

    foreach (glob($directory . '/*.sql') ?: [] as $path) {
        $files[] = [
            'name' => basename($path),
            'size' => filesize($path),
            'created_at' => date('Y-m-d H:i:s', filemtime($path)),
        ];
    }

    usort($files, static fn($a, $b) => strcmp($b['name'], $a['name']));

    return $files;
}

function backup_path(string $filename): ?string
{
    $filename = basename($filename);
    if (!preg_match('/^[A-Za-z0-9._-]+\.sql$/', $filename)) {
        return null;
    }
    $path = backup_directory() . '/' . $filename;
    return is_file($path) ? $path : null;
}

function backup_restore(string $filename): array
{
    $path = backup_path($filename);
    if (!$path) {
        return ['error' => 'Файл резервной копии не найден.'];
    }

    $content = (string) file_get_contents($path);
    $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $content)));

    $applied = 0;
    foreach ($statements as $statement) {
        if ($statement === '' || str_starts_with($statement, '--')) {
            continue;
        }
        try {
            db()->exec($statement);
            $applied++;
        } catch (PDOException $exception) {
            return ['error' => 'Ошибка восстановления: ' . $exception->getMessage()];
        }
    }

    return ['ok' => true, 'statements' => $applied];
}
