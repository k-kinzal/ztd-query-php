<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * One assignment of the SET clause of SIGNAL or RESIGNAL: a condition information item and its value.
 *
 * The grammar accepts a literal, a variable or a name as the value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/signal.html#signal-condition-information-items.
 *
 * @visibility SqlSemantics
 */
final class SignalItem implements \SqlSemantics\Platform\MySql\Model\Statement\Node
{
    use Snapshot;

    /**
     * @param ConditionItemName $name The item that is set; not RETURNED_SQLSTATE
     * @param Scalar $value The value
     */
    public function __construct(public readonly ConditionItemName $name, public readonly Scalar $value)
    {
        Check::input($name !== ConditionItemName::ReturnedSqlstate, 'RETURNED_SQLSTATE is set by the condition value, not by an item.');
    }

    /**
     * Writes the item, the equals sign and the value.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->name->value)->symbol('=')->node($this->value);
    }
}
