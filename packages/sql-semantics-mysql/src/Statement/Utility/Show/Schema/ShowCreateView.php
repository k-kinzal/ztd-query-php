<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowTargets;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW CREATE VIEW: the statement that creates a view.
 *
 * Rule: MYSQL-SHOW-CREATE-VIEW-001. The view resolves by MYSQL-SHOW-TARGET-001; a declared base
 * table is refused (MYSQL-RELATION-KIND-001). The columns are those of the
 * layout of MYSQL-SHOW-ROWS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-view.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW CREATE VIEW v');
 *     [$show->field(0)->name?->value, $show->toString()] // => ['View', 'SHOW CREATE VIEW v']
 */
final class ShowCreateView implements Statement
{
    use Snapshot;

    /**
     * @param InspectedTable $view The view
     */
    public function __construct(public readonly InspectedTable $view)
    {
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new RelationKinds())->require($derivation, $this->view->name, (new ShowTargets())->derive($derivation, $this->view)->table, RelationKind::View);
        (new ShowFacts())->rows($derivation, Report::CreateView);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'CREATE', 'VIEW')->node($this->view);
    }
}
