<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowTargets;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\TableOrView;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;

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
 * @visibility SqlSemantics
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
