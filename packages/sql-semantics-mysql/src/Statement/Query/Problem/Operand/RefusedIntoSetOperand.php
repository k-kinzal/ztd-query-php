<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A completed SELECT redirects its result before a following set operator.
 *
 * This input diagnostic retains the offending prefix, never an invalid set query.
 *
 * @visibility public
 * @example Refusing a destination before a set operator
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 INTO @x UNION SELECT 2') // throws \SqlSemantics\Construction\RejectedInput
 */
final class RefusedIntoSetOperand implements Diagnostic
{
    use Snapshot;

    /**
     * @param Select $operand The complete leading operand with its destination
     * @param SetOperator $operator The following set operation
     */
    public function __construct(public readonly Select $operand, public readonly SetOperator $operator)
    {
        Check::input($operand->into !== null, 'A refused set operand retains its result destination.');
    }

    /**
     * Describes the result destination's incompatible role.
     */
    public function message(): string
    {
        return 'Incorrect usage of ' . $this->operator->value . ' and INTO';
    }
}
