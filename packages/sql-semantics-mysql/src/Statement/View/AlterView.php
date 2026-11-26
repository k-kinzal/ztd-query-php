<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\View;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableTargets;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ViewFacts;

/**
 * A request to change the definition of an existing view.
 *
 * Rule: MYSQL-ALTER-VIEW-001. The view name is a table use: the statement
 * node records its resolution as a target; a declared base table is refused
 * (MYSQL-RELATION-KIND-001). The new query is derived and
 * checked as for CREATE VIEW (MYSQL-VIEW-FACTS-001), but an ALTER provides no
 * declaration: it requests a change and does not change a context.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-view.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterView implements Statement
{
    use Snapshot;

    /**
     * @param ViewDefinition $definition The new view definition
     */
    public function __construct(public readonly ViewDefinition $definition)
    {
    }

    /**
     * Resolves the view and derives its new query.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $fact = $derivation->target($this, (new TableTargets())->existing($derivation, $this->definition->name));
        (new RelationKinds())->require($derivation, $this->definition->name, $fact->table, RelationKind::View);
        (new ViewFacts())->derive($this->definition, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER');
        $this->definition->renderHead($out);
        $out->keyword('VIEW')->node($this->definition);
    }
}
