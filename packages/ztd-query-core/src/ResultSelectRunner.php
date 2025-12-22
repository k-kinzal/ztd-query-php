<?php

declare(strict_types=1);

namespace ZtdQuery;

use ZtdQuery\Connection\StatementInterface;

/**
 * Executes result-select queries and returns rows.
 */
final class ResultSelectRunner
{
    /**
     * Execute SQL using the provided executor and return result rows.
     *
     * @param callable(string): (StatementInterface|false) $executor
     * @return array<int, array<string, mixed>>
     */
    public function run(string $sql, callable $executor): array
    {
        $statement = $executor($sql);
        if ($statement === false) {
            return [];
        }

        return $statement->fetchAll();
    }

    /**
     * Execute a prepared statement and return result rows.
     *
     * @param array<int|string, mixed>|null $params
     * @return array<int, array<string, mixed>>
     */
    public function runStatement(StatementInterface $statement, ?array $params = null): array
    {
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
