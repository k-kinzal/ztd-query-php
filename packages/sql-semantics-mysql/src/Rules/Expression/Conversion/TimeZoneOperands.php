<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Conversion;

use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\LiteralExpression;
use SqlSemantics\Statement\Scalar;

/**
 * Distinguishes intrinsic literal roles in AT TIME ZONE from catalog-dependent operands.
 *
 * A TIMESTAMP literal is accepted although its output field is DATETIME.
 * Other non-null literals cannot denote a TIMESTAMP input. Parentheses retain
 * that role. Names and compound expressions still require their resolved facts.
 * Verified through standalone and stored SQL on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TimeZoneOperands
{
    /**
     * Answers an operand's intrinsic TIMESTAMP input role, independently of name resolution.
     * Casts, arithmetic, interval arithmetic and clocks have a fixed result
     * family even when their operands are unresolved or NULL. They cannot
     * produce a TIMESTAMP field. Other compositions retain their dependencies.
     */
    public function input(Scalar $operand): ?bool
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast
            || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic
            || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalAddition
            || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalArithmetic
            || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Call\ClockCall) {
            return false;
        }

        return $this->literal($operand);
    }

    /**
     * Answers whether a literal has the TIMESTAMP or NULL role; null means it is not a literal.
     */
    public function literal(Scalar $operand): ?bool
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        return $operand instanceof LiteralExpression ? $operand instanceof NullLiteral || ($operand instanceof TemporalLiteral && $operand->form === TemporalForm::Timestamp) : null;
    }
}
