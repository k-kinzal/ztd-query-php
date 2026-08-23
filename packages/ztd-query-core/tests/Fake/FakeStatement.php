<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\ResultColumnTypeResolver;

/**
 * Fake StatementInterface backed by in-memory row data.
 */
final class FakeStatement implements StatementInterface
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $rows;

    private bool $executed = false;

    /** @var list<ResultColumn> */
    private array $columns;

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param list<ResultColumn> $columns
     */
    public function __construct(array $rows = [], array $columns = [])
    {
        $this->rows = $rows;
        $this->columns = $columns;
    }

    public function execute(?array $params = null): bool
    {
        $this->executed = true;

        return true;
    }

    public function fetchAll(): array
    {
        return $this->rows;
    }

    public function resultColumns(ResultColumnTypeResolver $typeResolver): array
    {
        return $this->columns;
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    public function isExecuted(): bool
    {
        return $this->executed;
    }
}
