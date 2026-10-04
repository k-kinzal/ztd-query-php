<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\RelationFact;

/**
 * Checks, derives and writes the table list of a table maintenance or cache statement.
 *
 * Rule: MYSQL-MAINTAINED-TABLES-001. A list holds at least one table; each
 * resolves by MYSQL-SERVER-TABLES-001 without an alias. The list is written
 * comma separated in order. Terminates: one pass over the list.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/table-maintenance-statements.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class MaintainedTables
{
    /**
     * Checks a table list.
     *
     * @param list<MaintainedTable> $tables
     * @return list<MaintainedTable>
     */
    public function checked(array $tables): array
    {
        return Check::listOf($tables, MaintainedTable::class, 'The statement names at least one table.', 1);
    }

    /**
     * Records the resolution of each table and reports a table named twice.
     *
     * @param list<MaintainedTable> $tables
     * @return list<RelationFact> The facts of the tables in order
     */
    public function derive(Derivation $derivation, array $tables): array
    {
        $named = [];
        foreach ($tables as $table) {
            $named[] = [$table, $table->name, null];
        }
        return (new TableNames())->record($derivation, $named);
    }

    /**
     * Writes the keywords of the statement, the optional NO_WRITE_TO_BINLOG and the table list.
     *
     * @param list<string> $keywords The words before the table list, TABLE included
     * @param list<MaintainedTable> $tables
     */
    public function render(Output $out, array $keywords, bool $noWriteToBinlog, array $tables): void
    {
        $out->keyword($keywords[0]);
        if ($noWriteToBinlog) {
            $out->keyword('NO_WRITE_TO_BINLOG');
        }
        $out->keyword(...array_slice($keywords, 1))->list($tables);
    }
}
