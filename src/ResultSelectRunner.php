<?php

declare(strict_types=1);

namespace ZtdQuery;

use PDO;
use PDOStatement;

/**
 * Executes result-select queries and returns rows.
 */
final class ResultSelectRunner
{
    /**
     * Execute SQL using the provided executor and return result rows.
     *
     * @param callable(string): (PDOStatement|false) $executor
     * @return array<int, array<string, mixed>>
     */
    public function run(string $sql, callable $executor): array
    {
        $statement = $executor($sql);
        if ($statement === false) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        /** @var array<int, array<string, mixed>> $rows */
        return $rows;
    }

    /**
     * Execute a prepared statement and return result rows.
     *
     * @param array<int|string, mixed>|null $params
     * @return array<int, array<string, mixed>>
     */
    public function runStatement(PDOStatement $statement, ?array $params = null): array
    {
        $statement->execute($params ?? []);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        /** @var array<int, array<string, mixed>> $rows */
        return $rows;
    }
}
