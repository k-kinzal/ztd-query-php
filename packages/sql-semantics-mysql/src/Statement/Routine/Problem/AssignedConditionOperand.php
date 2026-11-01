<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Problem;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An assignment in a SIGNAL item or GET DIAGNOSTICS condition-number position.
 *
 * MySQL 5.6 and 5.7 parse the complete assignment before refusing this role.
 * Earlier grammar conditions in the assigned expression still take precedence.
 * Source: https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_yacc.yy.
 *
 * @visibility public
 * @example Describing an assignment used as a condition number
 *     $get = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze('GET DIAGNOSTICS CONDITION @n := 1 @m = MESSAGE_TEXT');
 *     $get->diagnostics()[0] instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\AssignedConditionOperand // => true
 */
final class AssignedConditionOperand implements Diagnostic
{
    use Snapshot;

    /**
     * @param VariableAssignment|DeferredAssignment $operand The complete refused assignment
     */
    public function __construct(public readonly VariableAssignment|DeferredAssignment $operand)
    {
    }

    /**
     * Describes the forbidden role without discarding the assigned computation.
     */
    public function message(): string
    {
        return 'A condition operand reads a variable; it cannot assign one.';
    }
}
