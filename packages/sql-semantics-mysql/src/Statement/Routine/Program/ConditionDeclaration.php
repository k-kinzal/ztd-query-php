<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;

/**
 * DECLARE ... CONDITION: a name for an error code or an SQLSTATE value.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-condition.html.
 *
 * @visibility SqlSemantics
 */
final class ConditionDeclaration implements Declaration
{
    use Snapshot;

    /**
     * @param Name $name The condition name
     * @param ErrorCode|SqlState $value The error code or SQLSTATE value the name stands for
     */
    public function __construct(public readonly Name $name, public readonly ErrorCode|SqlState $value)
    {
    }

    /**
     * Writes the declaration.
     */
    public function render(Output $out): void
    {
        $out->keyword('DECLARE')->name($this->name, NameUse::Identifier)->keyword('CONDITION', 'FOR')->node($this->value);
    }
}
