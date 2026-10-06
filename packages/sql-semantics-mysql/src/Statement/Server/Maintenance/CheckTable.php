<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Platform\MySql\Rules\Server\MaintainedTables;
use SqlSemantics\Platform\MySql\Statement\Server\CheckOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CHECK TABLE t, … [option …]`: a request to check tables for errors.
 *
 * Mirrors PT_check_table_stmt (Sql_cmd_check_table). Rule: MYSQL-CHECK-TABLE-001. Each table resolves by
 * MYSQL-SERVER-TABLES-001 and its resolution is the relation fact of its
 * MaintainedTable; a table named twice is NonUniqueTable. The options are kept in written order; the server merges them into flags. TABLE and
 * TABLES are synonyms; TABLE is written. The server returns one row per
 * table and message; its columns are MYSQL-ADMIN-ROWS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/check-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('check tables t for upgrade quick');
 *     [$statement->toString(), $statement->statement->options[0] === \SqlSemantics\Platform\MySql\Statement\Server\CheckOption::ForUpgrade] // => ['CHECK TABLE t FOR UPGRADE QUICK', true]
 */
final class CheckTable implements Statement
{
    use Snapshot;

    /**
     * @var list<MaintainedTable> The tables in written order; at least one
     */
    public readonly array $tables;

    /**
     * @var list<CheckOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param list<MaintainedTable> $tables The tables in written order; at least one
     * @param list<CheckOption> $options The options in written order
     * @throws InvalidConstruction When the table list is empty or an option list holds a foreign value
     */
    public function __construct(array $tables, array $options = [])
    {
        $this->tables = (new MaintainedTables())->checked($tables);
        $this->options = Check::listOf($options, CheckOption::class, 'CHECK TABLE takes a list of check options.');
    }

    /**
     * Records the resolution of each table, reports a table named twice, and records the result rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new MaintainedTables())->derive($derivation, $this->tables);
        (new AdminRows())->admin($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        (new MaintainedTables())->render($out, ['CHECK', 'TABLE'], false, $this->tables);
        foreach ($this->options as $option) {
            $out->keyword(...explode(' ', $option->value));
        }
    }
}
