<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Connection\StatementInterface;

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

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
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

    public function rowCount(): int
    {
        return count($this->rows);
    }

    public function isExecuted(): bool
    {
        return $this->executed;
    }
}
