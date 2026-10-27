<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ConditionFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * SIGNAL: raises a condition.
 *
 * The statement is valid on its own and inside a stored program, where a
 * condition name and the names in the item values are resolved in the scope
 * of the statement. The facts follow MYSQL-PROGRAM-CONDITIONS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/signal.html.
 *
 * @visibility public
 * @example Reading the statement
 *     $signal = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SIGNAL SQLSTATE VALUE '45000' SET MESSAGE_TEXT = 'no'");
 *     [$signal->condition->state->value, $signal->toString()] // => ['45000', "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'no'"]
 */
final class Signal extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, ProgramStatement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<SignalItem> The condition information items in written order
     */
    public readonly array $items;

    /**
     * @param ConditionName|SqlState $condition The condition raised: an SQLSTATE value or a condition name
     * @param list<SignalItem> $items The condition information items of the SET clause; empty without SET
     */
    public function __construct(public readonly ConditionName|SqlState $condition, array $items = [], ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->items = Check::listOf($items, SignalItem::class, 'The SET clause holds condition information items.');
        $repeated = (new ConditionFacts())->repeated($this->items);
        Check::input($repeated === null, "Duplicate condition information item '" . ($repeated?->name->value ?? '') . "'");
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the statement outside a stored program, where no condition name and no local variable is in scope.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $this->deriveProgram($derivation, new ProgramScope($derivation->environment()));
    }

    /**
     * Derives the condition and the items in the scope of the statement.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        (new ConditionFacts())->signal($this->condition, $this->items, $derivation, $scope);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SIGNAL')->node($this->condition);
        if ($this->items !== []) {
            $out->keyword('SET')->list($this->items);
        }
    }
}
