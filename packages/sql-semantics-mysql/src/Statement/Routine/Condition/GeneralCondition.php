<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A class of conditions in a handler declaration: SQLWARNING, NOT FOUND or SQLEXCEPTION.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-handler.html.
 *
 * @visibility SqlSemantics
 */
final class GeneralCondition implements Condition
{
    use Snapshot;

    /**
     * @param ConditionClass $class The class of conditions
     */
    public function __construct(public readonly ConditionClass $class)
    {
    }

    /**
     * Writes the keywords of the class.
     */
    public function render(Output $out): void
    {
        match ($this->class) {
            ConditionClass::SqlWarning => $out->keyword('SQLWARNING'),
            ConditionClass::NotFound => $out->keyword('NOT', 'FOUND'),
            ConditionClass::SqlException => $out->keyword('SQLEXCEPTION'),
        };
    }
}
