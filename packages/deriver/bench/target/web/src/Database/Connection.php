<?php

declare(strict_types=1);

namespace Harbor\Database;

use PDO;
use Throwable;

final class Connection
{
    public function __construct(public readonly PDO $pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }

    public static function open(string $path): self
    {
        $connection = new self(new PDO('sqlite:' . $path));
        $connection->pdo->exec('PRAGMA journal_mode = WAL');
        return $connection;
    }

    public function execute(string $sql, array $parameters = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->rowCount();
    }

    public function rows(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function one(string $sql, array $parameters = []): ?array
    {
        return $this->rows($sql, $parameters)[0] ?? null;
    }

    public function transaction(callable $operation): mixed
    {
        // Acquire the SQLite write reservation before checking mutable permissions.
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $result = $operation();
            $this->pdo->exec('COMMIT');
            return $result;
        } catch (Throwable $error) {
            $this->pdo->exec('ROLLBACK');
            throw $error;
        }
    }
}
