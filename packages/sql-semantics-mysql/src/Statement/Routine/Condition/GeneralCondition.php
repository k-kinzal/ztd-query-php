<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A class of conditions in a handler declaration: SQLWARNING, NOT FOUND or SQLEXCEPTION.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-handler.html.
 *
 * @visibility public
 * @example Reading the class a handler names
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN END; END');
 *     $create->statement->body->declarations[0]->conditions[0]->class // => \SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionClass::SqlException
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
