<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call\Window;

use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal as L;
use SqlSemantics\Statement\Operand;

/**
 * Constrains literal window counts without evaluating database or session state.
 *
 * NTILE and NTH_VALUE require positive signed 64-bit integers. LEAD and LAG
 * also admit zero. Parameters, variables and computations retain their own
 * resolution and evaluation responsibilities. Native SQL observations cover
 * these literal boundaries on MySQL 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Counts
{
    /**
     * @param list<Operand> $arguments Complete invocation operands
     */
    public function invalid(WindowFunctionKind $kind, array $arguments): bool
    {
        $position = $kind->counted() ?? ($kind === WindowFunctionKind::NthValue ? 1 : -1);
        $operand = $arguments[$position] ?? null;
        if ($operand === null) {
            return false;
        }
        while ($operand instanceof Grouped || ($operand instanceof Unary && $operand->operator === UnaryOperator::Plus)) {
            $operand = $operand->operand;
        }
        $positive = $kind === WindowFunctionKind::Tile || $kind === WindowFunctionKind::NthValue;
        if ($operand instanceof Unary && $operand->operator === UnaryOperator::Minus && $operand->operand instanceof L\NumberLiteral) {
            return true;
        }
        return match (true) {
            $operand instanceof L\NumberLiteral => $operand->form !== L\NumberForm::Integer || $operand->beyondSigned() || ($positive && ltrim($operand->text, '0') === ''),
            $operand instanceof L\BooleanLiteral => $positive && !$operand->value,
            $operand instanceof L\NullLiteral, $operand instanceof L\StringLiteral, $operand instanceof L\RadixLiteral, $operand instanceof L\TemporalLiteral => true,
            default => false,
        };
    }
}
