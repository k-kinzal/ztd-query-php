<?php

declare(strict_types=1);

namespace ZtdQuery;

use ZtdQuery\Connection\ResultSet;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\ResultColumnTypeResolver;

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
        return $this->runResultSet($sql, $executor)->rows;
    }

    /**
     * @param callable(string): (StatementInterface|false) $executor
     */
    public function runResultSet(
        string $sql,
        callable $executor,
        ?ResultColumnTypeResolver $typeResolver = null,
    ): ResultSet {
        $statement = $executor($sql);
        if ($statement === false) {
            return new ResultSet([], []);
        }

        return $this->readResultSet($statement, $typeResolver);
    }

    /**
     * Execute a prepared statement and return result rows.
     *
     * @param array<int|string, mixed>|null $params
     * @return array<int, array<string, mixed>>
     */
    public function runStatement(
        StatementInterface $statement,
        ?array $params = null,
        ?ResultColumnTypeResolver $typeResolver = null,
    ): array {
        $statement->execute($params);

        return $this->readResultSet($statement, $typeResolver)->rows;
    }

    public function readResultSet(
        StatementInterface $statement,
        ?ResultColumnTypeResolver $typeResolver = null,
    ): ResultSet {
        $columns = $statement->resultColumns($typeResolver);
        $rows = $statement->fetchAll();

        return new ResultSet($rows, $columns);
    }
}
