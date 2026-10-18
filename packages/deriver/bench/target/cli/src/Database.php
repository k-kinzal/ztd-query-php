<?php

declare(strict_types=1);

namespace HarborOps;

use PDO;
use RuntimeException;
use Throwable;

final class Database
{
    public function __construct(public readonly PDO $pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
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
    public function migrate(string $file): void
    {
        $this->execute('CREATE TABLE IF NOT EXISTS migrations(version INTEGER PRIMARY KEY)');
        $this->transaction(function () use ($file): void {
            if ($this->one('SELECT version FROM migrations WHERE version = 1')) {
                return;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Cannot read the schema migration.');
            }
            $this->pdo->exec($sql);
            $this->execute('INSERT INTO migrations VALUES (1)');
        });
    }
}
