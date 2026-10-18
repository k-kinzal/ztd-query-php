<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call\Window;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge;
use SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment;
use SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Scalar;

/**
 * Checks the written argument positions shared by ordinary and stored window calls.
 *
 * A routine variable occurs only in a stable-integer position. Row-valued
 * arguments retain their shape in a stored instruction, outside Scalar.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Arguments
{
    /**
     * @param list<Scalar|RoutineVariable|RowOperand|DeferredOperand> $arguments Written arguments
     * @return list<Scalar|RoutineVariable|RowOperand|DeferredOperand> Checked arguments
     */
    public function checked(WindowFunctionKind $kind, array $arguments, ?NullTreatment $nulls, ?CountingEdge $edge): array
    {
        $values = [];
        foreach (Check::listOf($arguments, Operand::class, 'Window function arguments are values.') as $position => $argument) {
            Check::input($argument instanceof Scalar || $argument instanceof RowOperand || $argument instanceof DeferredOperand || ($argument instanceof RoutineVariable && $position === $kind->counted()), 'A routine variable is accepted only as a window count or offset.');
            $values[] = $argument;
        }
        $arguments = $values;
        [$minimum, $maximum] = $kind->arity();
        Check::input(count($arguments) >= $minimum && count($arguments) <= $maximum, 'The grammar does not accept this number of arguments for ' . $kind->value . '.');
        Check::input($nulls === null || $kind->treatsNulls(), $kind->value . ' takes no NULL treatment.');
        Check::input($edge === null || $kind === WindowFunctionKind::NthValue, 'Only NTH_VALUE counts from an edge.');
        $counted = $arguments[$kind->counted() ?? -1] ?? null;
        Check::input($counted === null || $counted instanceof NumberLiteral || $counted instanceof Parameter || $counted instanceof UserVariable || $counted instanceof RoutineVariable, 'The count is an integer, a parameter marker or a variable.');
        Check::input(!$counted instanceof NumberLiteral || $counted->form === \SqlSemantics\Platform\MySql\Statement\Literal\NumberForm::Integer, 'A window count or offset literal is an unsigned 64-bit integer token.');
        Check::input($kind !== WindowFunctionKind::NthValue || (!$arguments[1] instanceof RoutineVariable && (new Precedence())->admits($arguments[1], Precedence::SIMPLE_EXPR)), 'The row number of NTH_VALUE needs a grouping.');
        return $arguments;
    }
    /**
     * Answers a written option that no supported release implements.
     */
    public function limitation(?NullTreatment $nulls, ?CountingEdge $edge): ?\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit
    {
        return match (true) {
            $nulls === NullTreatment::Ignore => \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit::IgnoreNulls,
            $edge === CountingEdge::Last => \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit::FromLast,
            default => null,
        };
    }

}
