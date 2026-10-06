<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\KeyCache;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Platform\MySql\Rules\Server\TableNames;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `LOAD INDEX INTO CACHE t [INDEX (…)] [IGNORE LEAVES], …`: a request to preload the indexes of MyISAM tables into their key caches.
 *
 * Mirrors PT_load_index_stmt and PT_load_index_partitions_stmt. Rule:
 * MYSQL-LOAD-INDEX-001. Each table resolves by MYSQL-SERVER-TABLES-001 and
 * its resolution is the relation fact of its PreloadedTable; a table named
 * twice is NonUniqueTable. A table with a partition selection is the only
 * table of the statement. The server returns one row per table, which
 * describes server state no context holds.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-index.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Preloading indexes
 *     $load = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('load index into cache t partition (all) ignore leaves');
 *     [$load->toString(), $load->statement->tables[0]->ignoreLeaves] // => ['LOAD INDEX INTO CACHE t PARTITION (ALL) IGNORE LEAVES', true]
 */
final class LoadIndex implements Statement
{
    use Snapshot;

    /**
     * @var list<PreloadedTable> The tables in written order; at least one
     */
    public readonly array $tables;

    /**
     * @param list<PreloadedTable> $tables The tables in written order; at least one
     * @throws InvalidConstruction When the list is empty, or a table with partitions is not the only one
     */
    public function __construct(array $tables)
    {
        $this->tables = Check::listOf($tables, PreloadedTable::class, 'LOAD INDEX INTO CACHE names at least one table.', 1);
        foreach ($this->tables as $table) {
            Check::input($table->partitions === null || count($this->tables) === 1, 'A table with partitions is the only table of LOAD INDEX INTO CACHE.');
        }
    }

    /**
     * Records the resolution of each table, reports a table named twice, and records the result rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $tables = [];
        foreach ($this->tables as $table) {
            $tables[] = [$table, $table->table, null];
        }
        (new TableNames())->record($derivation, $tables);
        (new AdminRows())->admin($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('LOAD', 'INDEX', 'INTO', 'CACHE')->list($this->tables);
    }
}
