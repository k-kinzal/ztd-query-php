<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableChange\Targets;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `DROP [TEMPORARY] TABLE [IF EXISTS] t, … [RESTRICT | CASCADE]`: a request to remove tables.
 *
 * Mirrors PT_drop_table_stmt. Rule: MYSQL-DROP-TABLE-001. Each table name
 * resolves by MYSQL-CHANGE-TARGET-001 and its resolution is the relation fact
 * of its TargetTable; under IF EXISTS a name a complete context does not
 * declare resolves to AbsentTable. A table named twice is the diagnostic
 * RepeatedTable. A declared view is not a table DROP TABLE finds: without
 * IF EXISTS it is refused (MYSQL-RELATION-KIND-001). TEMPORARY restricts the request to temporary tables, which a
 * declaration context does not distinguish. RESTRICT and CASCADE have no
 * effect and are kept as written. The statement removes no declaration and
 * provides none. TABLE and TABLES are synonyms.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Resolving the dropped table to its declaration
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $drop = $semantics->analyze('drop tables if exists t, u cascade', []);
 *     [$drop->toString(), $drop->statement->behavior] // => ['DROP TABLE IF EXISTS t, u CASCADE', \SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior::Cascade]
 */
final class DropTable implements Statement
{
    use Snapshot;

    /**
     * @var list<TargetTable> The tables in order; at least one
     */
    public readonly array $tables;

    /**
     * @param bool $temporary Whether TEMPORARY is written
     * @param bool $ifExists Whether IF EXISTS is written
     * @param list<TargetTable> $tables The tables in order; at least one
     * @param DropBehavior|null $behavior RESTRICT or CASCADE, when written
     */
    public function __construct(public readonly bool $temporary, public readonly bool $ifExists, array $tables, public readonly ?DropBehavior $behavior = null)
    {
        Check::input($tables !== [], 'DROP TABLE names at least one table.');
        $this->tables = Check::listOf($tables, TargetTable::class, 'DROP TABLE names a list of tables.');
    }

    /**
     * Records the resolution of each table and reports a table named twice.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $targets = new Targets();
        foreach ($this->tables as $index => $table) {
            $fact = $derivation->target($table, $targets->optional($derivation, $table->name, $this->ifExists));
            if (!$this->ifExists) {
                (new RelationKinds())->refuseView($derivation, $table->name, $fact->table, KindRefusal::UnknownTable);
            }
            foreach (array_slice($this->tables, 0, $index) as $earlier) {
                if ($targets->same($derivation->context, $earlier->name, $table->name)) {
                    $derivation->report(new RepeatedTable($table->name));
                    break;
                }
            }
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP');
        if ($this->temporary) {
            $out->keyword('TEMPORARY');
        }
        $out->keyword('TABLE');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->list($this->tables);
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
