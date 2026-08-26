<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\ResultColumnTypeResolver;

/**
 * A statement that answers from rows held in memory.
 *
 * Nothing here talks to a driver, so a test can say exactly what a statement
 * hands back and then check what the code around it made of that.
 *
 * @phpstan-import-type Row from StatementInterface
 */
final class FakeStatement implements StatementInterface
{
    /**
     * @var list<Row> Rows this statement answers with
     */
    private array $rows;

    private bool $executed = false;

    /**
     * @var list<ResultColumn> Columns this statement reports
     */
    private array $columns;

    /**
     * Builds a statement that answers with these rows.
     *
     * @param list<Row> $rows Rows to answer with
     * @param list<ResultColumn> $columns Columns to report
     */
    public function __construct(array $rows = [], array $columns = [])
    {
        $this->rows = $rows;
        $this->columns = $columns;
    }

    /**
     * Records that the statement was run.
     *
     * @param array<int|string, int|float|string|bool|null>|null $params Ignored
     *
     * @return bool Always true, because nothing here can fail
     */
    public function execute(?array $params = null): bool
    {
        $this->executed = true;

        return true;
    }

    /**
     * Answers the rows this statement was built with.
     *
     * @return list<Row> The rows
     */
    public function fetchAll(): array
    {
        return $this->rows;
    }

    /**
     * Answers the columns this statement was built with.
     *
     * @param ResultColumnTypeResolver $typeResolver Ignored, because the columns are already decided
     *
     * @return list<ResultColumn> The columns
     */
    public function resultColumns(ResultColumnTypeResolver $typeResolver): array
    {
        return $this->columns;
    }

    /**
     * Answers how many rows this statement was built with.
     *
     * @return int The number of rows
     */
    public function rowCount(): int
    {
        return count($this->rows);
    }

    /**
     * Reports whether the statement was run.
     *
     * @return bool True once execute() has been called
     */
    public function isExecuted(): bool
    {
        return $this->executed;
    }
}
