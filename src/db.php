<?php

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', 'localhost'),
            env('DB_PORT', '3306'),
            env('DB_NAME', 'marketplace'),
        );

        $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // keep NOW() in MySQL on the same clock as date() in PHP
        $pdo->prepare('SET time_zone = ?')->execute([(new DateTime())->format('P')]);
    }

    return $pdo;
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function db_value(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();

    return $value === false ? null : $value;
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
}

function db_insert(string $sql, array $params = []): int
{
    db_exec($sql, $params);

    return (int) db()->lastInsertId();
}

function db_transaction(callable $callback): mixed
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $returned = $callback($pdo);
        $pdo->commit();

        return $returned;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
