<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One assignment of the SET clause of SIGNAL or RESIGNAL: a condition information item and its value.
 *
 * The grammar accepts a literal, a variable or a name as the value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/signal.html#signal-condition-information-items.
 *
 * @visibility public
 * @example Reading the items of a SIGNAL
 *     $signal = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'no', MYSQL_ERRNO = 1001");
 *     $signal->statement->items[1]->name // => \SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName::MysqlErrno
 */
final class SignalItem implements \SqlSemantics\Statement\Node
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
