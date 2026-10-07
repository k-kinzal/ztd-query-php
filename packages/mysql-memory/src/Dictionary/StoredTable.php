<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use MySqlMemory\Storage\Heap;

/**
 * A table of a database: its definition and its rows.
 *
 * @visibility MySqlMemory
 */
final class StoredTable
{
    /**
     * @param TableDefinition $definition The definition
     * @param Heap $data The rows
     */
    public function __construct(public TableDefinition $definition, public Heap $data)
    {
    }
}
