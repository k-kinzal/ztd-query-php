<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\View;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ViewFacts;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\RelationKind;

/**
 * A request to create a view, or to replace one with OR REPLACE.
 *
 * Rule: MYSQL-CREATE-VIEW-001. The statement provides one declaration made
 * from the output of the query (MYSQL-VIEW-FACTS-001), a view; it returns no
 * rows. OR REPLACE refuses a name declared as a base table
 * (MYSQL-RELATION-KIND-001).
 * IF NOT EXISTS (MySQL 9.1 and later, part of the definition) does not change
 * what it declares.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-view.html,
 * https://dev.mysql.com/doc/refman/9.1/en/create-view.html. Status: Implemented.
 *
 * @example Refusing legacy column names with a modern query body
 *     new \SqlSemantics\Platform\MySql\Statement\View\CreateView(new \SqlSemantics\Platform\MySql\Statement\View\ViewDefinition(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('v')), new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')])]), [new \SqlSemantics\Statement\Identifier\Name('')])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @visibility public
 * @example Declaring a view with a column list
 *     $view = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE OR REPLACE VIEW v (one, two) AS SELECT 1, 2');
 *     [$view->orReplace, $view->declarations()[0]->columns[1]->name->value] // => [true, 'two']
 */
final class CreateView extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param ViewDefinition $definition The view definition
     * @param bool $orReplace Whether OR REPLACE is written
     */
    public function __construct(public readonly ViewDefinition $definition, public readonly bool $orReplace = false, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        \SqlSemantics\Diagnostic\Check::input(!$orReplace || !$definition->ifNotExists, 'CREATE VIEW cannot combine OR REPLACE with IF NOT EXISTS.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the query and provides the declaration of the view.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->orReplace) {
            $name = $this->definition->name;
            (new RelationKinds())->require($derivation, $name, $derivation->table($name, $derivation->environment()), RelationKind::View);
        }
        $derivation->declare((new ViewFacts())->derive($this->definition, $derivation));
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->orReplace) {
            $out->keyword('OR', 'REPLACE');
        }
        $this->definition->renderHead($out);
        $out->keyword('VIEW')->node($this->definition);
    }
}
