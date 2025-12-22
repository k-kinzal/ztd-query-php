<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;

/**
 * Fake ConnectionInterface that returns pre-configured FakeStatements.
 *
 * Queries are recorded for inspection. Results can be pre-loaded per SQL string
 * or a default result set can be provided. Specific queries can be configured to
 * fail by returning false, enabling tests for error-handling branches.
 */
final class FakeConnection implements ConnectionInterface
{
    /**
     * Recorded queries.
     *
     * @var array<int, string>
     */
    public array $queries = [];

    /**
     * Pre-configured results keyed by SQL.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $results;

    /**
     * Default rows returned when no specific result is configured.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $defaultRows;

    /**
     * SQL patterns that should return false (simulating query failure).
     *
     * @var array<int, string>
     */
    private array $failPatterns = [];

    /**
     * @param array<string, array<int, array<string, mixed>>> $results SQL => rows mapping.
     * @param array<int, array<string, mixed>> $defaultRows Default rows for unconfigured queries.
     */
    public function __construct(array $results = [], array $defaultRows = [])
    {
        $this->results = $results;
        $this->defaultRows = $defaultRows;
    }

    public function query(string $sql): StatementInterface|false
    {
        $this->queries[] = $sql;

        foreach ($this->failPatterns as $pattern) {
            if ($sql === $pattern) {
                return false;
            }
        }

        $rows = $this->results[$sql] ?? $this->defaultRows;

        return new FakeStatement($rows);
    }

    /**
     * Pre-load a result for a specific SQL query.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function addResult(string $sql, array $rows): void
    {
        $this->results[$sql] = $rows;
    }

    /**
     * Configure a query to return false (simulate failure).
     */
    public function failOnQuery(string $sql): void
    {
        $this->failPatterns[] = $sql;
    }
}
