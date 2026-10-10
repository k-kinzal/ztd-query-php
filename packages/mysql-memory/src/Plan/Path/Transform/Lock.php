<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Transform;

use MySqlMemory\Concurrency\LockMode;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Source\TableScan;
use Override;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction;

/**
 * Passes the rows of a query block that meet its WHERE condition, locking the row each of its locked tables gives them, as a locking read does.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html.
 *
 * @visibility MySqlMemory
 */
final class Lock implements AccessPath
{
    /**
     * @param AccessPath $input The rows of the FROM clause of the block
     * @param Filter|null $filter The WHERE condition of the block over those rows, or null for none
     * @param list<array{TableScan, int, LockMode, LockedRowAction|null}> $targets The scans of the tables locked: the offset of their columns in a row, the mode they are locked in, and what a row another transaction holds does: NOWAIT, SKIP LOCKED, or null to wait
     */
    public function __construct(public readonly AccessPath $input, public readonly ?Filter $filter, public readonly array $targets)
    {
    }

    /**
     * Answers the width of the input.
     */
    #[Override]
    public function width(): int
    {
        return $this->input->width();
    }
}
