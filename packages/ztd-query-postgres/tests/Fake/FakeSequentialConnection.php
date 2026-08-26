<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;

/**
 * The fake sequential connection, as connection interface.
 */
final class FakeSequentialConnection implements ConnectionInterface
{
    /** @var list<StatementInterface> */
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
     * Query.
     *
     * @param string $sql
     * @return StatementInterface|false
     */
    public function query(string $sql): StatementInterface|false
    {
        return $this->statements[$this->index++] ?? false;
    }
}
