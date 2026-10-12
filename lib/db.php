<?php
// One PDO connection for the request. MySQL in production, SQLite for local testing.
// Keep SQL portable: plain SELECT/INSERT/UPDATE/DELETE, timestamps written from PHP.

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = config('db');
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['path'], null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $c['host'], $c['name'], $c['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
    }
    return $pdo;
}

function db_driver(): string
{
    return config('db.driver', 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q1(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function qv(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function insert(string $table, array $row): int
{
    $cols = array_keys($row);
    $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(',', $cols), implode(',', array_fill(0, count($cols), '?')));
    q($sql, array_values($row));
    return (int) db()->lastInsertId();
}

function now(): string
{
    return gmdate('Y-m-d H:i:s');
}
