<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowTargets;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\TableOrView;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW CREATE TABLE: the statement that creates a table, or a view.
 *
 * Rule: MYSQL-SHOW-CREATE-TABLE-001. The table resolves by MYSQL-SHOW-TARGET-001. For a base table
 * the server returns `Table` and `Create Table`; the statement also
 * accepts a view and then returns the four columns of SHOW CREATE VIEW
 * (MYSQL-SHOW-ROWS-001). A declared table answers the columns of its kind
 * (MYSQL-RELATION-KIND-001); for any other name the shape is open and
 * depends on whether the name is a table or a view (TableOrView).
 * Terminates: no nested part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW CREATE TABLE shop.t');
 *     [$show->shape()?->missing[0]->describe(), $show->toString()] // => ['whether shop.t is a base table or a view', 'SHOW CREATE TABLE shop.t']
 * @example Reading the columns for a declared view
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $view = $semantics->analyze('CREATE VIEW v AS SELECT 1 AS a');
 *     $semantics->analyze('SHOW CREATE TABLE v', [$view])->field(1)->name?->value // => 'Create View'
 */
final class ShowCreateTable implements Statement
{
    use Snapshot;

    /**
     * @param InspectedTable $table The table or view
     */
    public function __construct(public readonly InspectedTable $table)
    {
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $facts = new ShowFacts();
        $kind = (new RelationKinds())->kind((new ShowTargets())->derive($derivation, $this->table)->table);
        if ($kind === null) {
            $derivation->output($facts->query($facts->open(new TableOrView($this->table->name))->shape, $derivation->context->columnNames));

            return;
        }
        $facts->rows($derivation, $kind === RelationKind::View ? Report::CreateView : Report::CreateTable);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'CREATE', 'TABLE')->node($this->table);
    }
}
