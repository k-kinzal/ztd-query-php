<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ConditionFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

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
 *     [$signal->statement->condition->state->value, $signal->toString()] // => ['45000', "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'no'"]
 */
final class Signal implements Statement, ProgramStatement
{
    use Snapshot;

    /**
     * @var list<SignalItem> The condition information items in written order
     */
    public readonly array $items;

    /**
     * @param ConditionName|SqlState $condition The condition raised: an SQLSTATE value or a condition name
     * @param list<SignalItem> $items The condition information items of the SET clause; empty without SET
     */
    public function __construct(public readonly ConditionName|SqlState $condition, array $items = [])
    {
        $this->items = Check::listOf($items, SignalItem::class, 'The SET clause holds condition information items.');
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
