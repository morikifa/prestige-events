<?php

$root = dirname(__DIR__);
$config = require $root . '/app/config.php';
$path = $config['db']['sqlite_path'];

$schema = file_get_contents($root . '/database/schema.sql');
$seed = file_get_contents($root . '/database/seed.sql');

$schema = preg_replace('/^CREATE DATABASE.*?;$/ms', '', $schema);
$schema = preg_replace('/^USE .*?;$/m', '', $schema);
$schema = preg_replace('/^SET NAMES.*?;$/m', '', $schema);
$schema = preg_replace('/^SET FOREIGN_KEY_CHECKS.*?;$/m', '', $schema);
$schema = preg_replace('/^DROP TABLE IF EXISTS.*?;$/m', '', $schema);

$indexes = [];

$schema = preg_replace_callback(
    '/CREATE TABLE (\w+) \((.*?)\n\) ENGINE[^;]*;/s',
    static function (array $match) use (&$indexes): string {
        $table = $match[1];
        $definitions = [];
        $current = '';

        foreach (preg_split('/\n/', $match[2]) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $current = $current === '' ? $line : $current . ' ' . $line;
            if (str_ends_with($line, ',')) {
                $definitions[] = rtrim($current, ',');
                $current = '';
            }
        }
        if ($current !== '') {
            $definitions[] = rtrim($current, ',');
        }

        $kept = [];
        foreach ($definitions as $definition) {
            if (preg_match('/^(UNIQUE )?KEY (\w+) \(([^)]+)\)/i', $definition, $keyMatch)) {
                $unique = trim($keyMatch[1]) !== '' ? 'UNIQUE ' : '';
                $indexes[] = 'CREATE ' . $unique . 'INDEX ' . $keyMatch[2] . ' ON ' . $table . ' (' . $keyMatch[3] . ');';
                continue;
            }

            if (preg_match('/^PRIMARY KEY \((\w+)\)/i', $definition, $primaryMatch)) {
                $column = $primaryMatch[1];
                $kept[] = $column === 'id'
                    ? 'PRIMARY KEY (' . $column . ' AUTOINCREMENT)'
                    : 'PRIMARY KEY (' . $column . ')';
                continue;
            }

            $definition = preg_replace('/\s+AUTO_INCREMENT/', '', $definition);
            $definition = preg_replace('/\bTINYINT\(\d+\)/i', 'INTEGER', $definition);
            $definition = preg_replace('/\bBIGINT\b/i', 'INTEGER', $definition);
            $definition = preg_replace('/\bINT\b/i', 'INTEGER', $definition);
            $definition = preg_replace('/\bDATETIME\b/i', 'TEXT', $definition);
            $kept[] = $definition;
        }

        return 'CREATE TABLE ' . $table . " (\n    " . implode(",\n    ", $kept) . "\n);";
    },
    $schema
);

$seed = preg_replace('/^USE .*?;$/m', '', $seed);
$seed = preg_replace('/^SET NAMES.*?;$/m', '', $seed);

$statements = array_merge(
    demo_split_statements($schema),
    demo_split_statements(implode("\n", $indexes)),
    demo_split_statements($seed)
);

if (file_exists($path)) {
    unlink($path);
}
if (!is_dir(dirname($path))) {
    mkdir(dirname($path), 0775, true);
}

$pdo = new PDO('sqlite:' . $path);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$executed = 0;
foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement === '') {
        continue;
    }
    $pdo->exec($statement);
    $executed++;
}

echo 'Демо-база создана: ' . $path . PHP_EOL;
echo 'Выполнено запросов: ' . $executed . PHP_EOL;
echo 'Запуск сайта: PRESTIGE_DB_DRIVER=sqlite php -S 127.0.0.1:8000 -t public' . PHP_EOL;

function demo_split_statements(string $sql): array
{
    $statements = [];
    $buffer = '';

    foreach (preg_split('/\n/', $sql) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with($trimmed, ';')) {
            $statements[] = $buffer;
            $buffer = '';
        }
    }

    if (trim($buffer) !== '') {
        $statements[] = $buffer;
    }

    return $statements;
}
