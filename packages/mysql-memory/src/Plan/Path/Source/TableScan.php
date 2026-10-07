<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Source;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Plan\Path\AccessPath;
use Override;

/**
 * Reads every row of a stored table, in the order of its clustered index.
 *
 * InnoDB stores rows in the order of the primary key, else of the first unique key over NOT
 * NULL columns, else of insertion; a full scan returns them in that order.
 *
 * @visibility MySqlMemory
 */
final class TableScan implements AccessPath
{
    /**
     * @param StoredTable $table The table read
     */
    public function __construct(public readonly StoredTable $table)
    {
    }

    /**
     * Answers the number of columns of the table.
     */
    #[Override]
    public function width(): int
    {
        return count($this->table->definition->columns);
    }
}
