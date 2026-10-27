<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\View;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableTargets;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ViewFacts;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\RelationKind;

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
 * @visibility public
 * @example Reading the new query of a view
 *     $view = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER VIEW v AS SELECT 1 AS a');
 *     [$view->definition->name->name->value, $view->declarations()] // => ['v', []]
 */
final class AlterView extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\TableUse
{
    use \SqlSemantics\Statement\Semantics\RelationSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param ViewDefinition $definition The new view definition
     */
    public function __construct(public readonly ViewDefinition $definition, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?\SqlSemantics\Statement\Fact\RelationFact $relationInformation = null)
    {
        if ($relationInformation !== null) {
            $this->initializeRelationInformation($relationInformation);
        }
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        \SqlSemantics\Diagnostic\Check::input(!$definition->ifNotExists, 'ALTER VIEW has no IF NOT EXISTS clause.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
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
