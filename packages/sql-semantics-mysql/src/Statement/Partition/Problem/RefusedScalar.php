<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Problem;

use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A partition function containing an operand with no scalar interpretation.
 *
 * Its partition role precedes column binding and the general operand-width
 * diagnostic. The complete operand remains available for earlier conditions.
 * Observed through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility public
 * @example Retaining a row in its partition role
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     (new \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar($row))->message() // => 'This partition function is not allowed'
 */
final class RefusedScalar implements Diagnostic
{
    use Snapshot;

    /**
     * @param RowOperand|DeferredOperand $operand The complete refused partition function
     * @param OperandCondition $condition The partition restriction checked before scalar binding
     * @param int $offset The input boundary used only to format a contextual syntax refusal
     */
    public function __construct(public readonly RowOperand|DeferredOperand $operand, public readonly OperandCondition $condition = OperandCondition::Function, public readonly int $offset = 0)
    {
        \SqlSemantics\Diagnostic\Check::input($offset >= 0, 'A partition input boundary is nonnegative.');
    }

    /**
     * Describes the partition-function restriction.
     */
    public function message(): string
    {
        return 'This partition function is not allowed';
    }
}
