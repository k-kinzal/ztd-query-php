<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A condition named by DECLARE ... CONDITION, used in a handler or raised by SIGNAL.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-condition.html.
 *
 * @visibility SqlSemantics
 * @example Holding a condition name
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('gone')))->name->value // => 'gone'
 */
final class ConditionName implements Condition
{
    use Snapshot;

    /**
     * @param Name $name The condition name
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Writes the name.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Identifier);
    }
}
