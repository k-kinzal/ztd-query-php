<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;

/**
 * One assignment of GET DIAGNOSTICS: a target and the information item it receives.
 *
 * The target is a user variable, or the name of a parameter or local
 * variable of the stored program that holds the statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/get-diagnostics.html.
 *
 * @visibility SqlSemantics
 */
final class InformationItem implements Node
{
    use Snapshot;

    /**
     * @param Name|UserVariable $target The variable that receives the item: a user variable or the name of a parameter or local variable
     * @param ConditionItemName|StatementItemName $item The information item read
     */
    public function __construct(public readonly Name|UserVariable $target, public readonly ConditionItemName|StatementItemName $item)
    {
    }

    /**
     * Writes the target, the equals sign and the item.
     */
    public function render(Output $out): void
    {
        if ($this->target instanceof Name) {
            $out->name($this->target, NameUse::Identifier);
        } else {
            $out->node($this->target);
        }
        $out->symbol('=')->keyword($this->item->value);
    }
}
