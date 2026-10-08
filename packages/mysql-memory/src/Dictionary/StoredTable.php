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
     * @var array<string, list<int|string|null>> The statistics the data dictionary cached for the table when they were first read, by kind
     *
     * INFORMATION_SCHEMA keeps the statistics of a table it reads for information_schema_stats_expiry
     * seconds, a day by default, so SHOW TABLE STATUS and SHOW INDEX report what they read first.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_information_schema_stats_expiry.
     */
    public array $statistics = [];

    /**
     * @param TableDefinition $definition The definition
     * @param Heap $data The rows
     * @param array<string, string> $histograms The columns that have histogram statistics, by lowercase name
     */
    public function __construct(public TableDefinition $definition, public Heap $data, public array $histograms = [])
    {
    }
}
