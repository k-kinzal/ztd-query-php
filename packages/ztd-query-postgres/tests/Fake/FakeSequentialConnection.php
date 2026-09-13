<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;

/**
 * Provides queued statement results to test schema reflection without a database.
 */
final class FakeSequentialConnection implements ConnectionInterface
{
    /**
     * @var list<StatementInterface>
     */
    private array $statements;

    private int $index = 0;

    /**
     * @param list<StatementInterface> $statements
     */
    public function __construct(array $statements)
    {
        $this->statements = $statements;
    }

    /**
     * Returns the next prepared statement from this deterministic connection fake.
     */
    public function query(string $sql): StatementInterface|false
    {
        return $this->statements[$this->index++] ?? false;
    }
}
