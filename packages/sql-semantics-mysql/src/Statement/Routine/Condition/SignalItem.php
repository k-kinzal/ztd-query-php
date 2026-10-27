<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\LiteralExpression;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;

/**
 * One assignment of the SET clause of SIGNAL or RESIGNAL: a condition information item and its value.
 *
 * The grammar accepts a literal, a variable or a name as the value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/signal.html#signal-condition-information-items.
 *
 * @visibility public
 * @example Reading the items of a SIGNAL
 *     $signal = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'no', MYSQL_ERRNO = 1001");
 *     $signal->items[1]->name // => \SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName::MysqlErrno
 */
final class SignalItem implements \SqlSemantics\Statement\Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param ConditionItemName $name The item that is set; not RETURNED_SQLSTATE
     * @param LiteralExpression|ColumnUse|SystemVariable|UserVariable $value The value
     */
    public function __construct(public readonly ConditionItemName $name, public readonly LiteralExpression|ColumnUse|SystemVariable|UserVariable $value, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($name !== ConditionItemName::ReturnedSqlstate, 'RETURNED_SQLSTATE is set by the condition value, not by an item.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Writes the item, the equals sign and the value.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->name->value)->symbol('=')->node($this->value);
    }
}
