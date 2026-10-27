<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Program;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * SHOW CREATE TRIGGER: the statement that creates a trigger.
 *
 * Rule: MYSQL-SHOW-CREATETRIGGER-001. Stored programs are not part of a context, so the name is not
 * resolved. The columns are those of the layout of MYSQL-SHOW-ROWS-001.
 * Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-trigger.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW CREATE TRIGGER shop.p');
 *     [$show->name->schema?->value, $show->toString()] // => ['shop', 'SHOW CREATE TRIGGER shop.p']
 */
final class ShowCreateTrigger extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param QualifiedName $name The name with its optional database
     */
    public function __construct(public readonly QualifiedName $name, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        \SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames::check($name);
        \SqlSemantics\Diagnostic\Check::input($name->catalog === null, 'A stored-program name has at most a database qualifier.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, Report::CreateTrigger);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'CREATE', 'TRIGGER');
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Routine);
    }
}
