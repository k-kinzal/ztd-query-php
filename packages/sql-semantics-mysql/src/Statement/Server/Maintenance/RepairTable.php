<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Platform\MySql\Rules\Server\MaintainedTables;
use SqlSemantics\Platform\MySql\Statement\Server\RepairOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `REPAIR [NO_WRITE_TO_BINLOG | LOCAL] TABLE t, … [QUICK] [EXTENDED] [USE_FRM]`: a request to repair possibly corrupted tables.
 *
 * Mirrors PT_repair_table_stmt (Sql_cmd_repair_table). Rule: MYSQL-REPAIR-TABLE-001. Each table resolves by
 * MYSQL-SERVER-TABLES-001 and its resolution is the relation fact of its
 * MaintainedTable; a table named twice is NonUniqueTable. The options are kept in written order; the server merges them into flags. LOCAL is a synonym of NO_WRITE_TO_BINLOG, which is written. TABLE and
 * TABLES are synonyms; TABLE is written. The server returns one row per
 * table and message; its columns are MYSQL-ADMIN-ROWS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/repair-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('repair table t quick use_frm');
 *     [$statement->toString(), $statement->statement->options[1] === \SqlSemantics\Platform\MySql\Statement\Server\RepairOption::UseFrm] // => ['REPAIR TABLE t QUICK USE_FRM', true]
 */
final class RepairTable implements Statement
{
    use Snapshot;

    /**
     * @var list<MaintainedTable> The tables in written order; at least one
     */
    public readonly array $tables;

    /**
     * @var list<RepairOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param bool $noWriteToBinlog Whether NO_WRITE_TO_BINLOG or its synonym LOCAL is written
     * @param list<MaintainedTable> $tables The tables in written order; at least one
     * @param list<RepairOption> $options The options in written order
     * @throws InvalidConstruction When the table list is empty or an option list holds a foreign value
     */
    public function __construct(public readonly bool $noWriteToBinlog, array $tables, array $options = [])
    {
        $this->tables = (new MaintainedTables())->checked($tables);
        $this->options = Check::listOf($options, RepairOption::class, 'REPAIR TABLE takes a list of repair options.');
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
        (new MaintainedTables())->render($out, ['REPAIR', 'TABLE'], $this->noWriteToBinlog, $this->tables);
        foreach ($this->options as $option) {
            $out->keyword($option->value);
        }
    }
}
