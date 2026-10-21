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
 * that role. MIN/MAX preserve an argument's field family, not the special
 * TIMESTAMP literal role. Unresolved names still require their resolved facts.
 * Numeric and JSON aggregates, EXTRACT and GET_FORMAT cannot produce TIMESTAMP.
 * TIMESTAMPADD produces date/time fields, not TIMESTAMP. A direct parameter is
 * refused during native PREPARE, before any bound value can change its role.
 * Verified through standalone and stored SQL on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TimeZoneOperands
{
    /**
     * The conversion grammar's supported zone values, independently of the zone catalog.
     */
    public function zone(\SqlSemantics\Platform\MySql\Statement\Literal\Text $zone): bool
    {
        return \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\TimeZone::tryFrom($zone->value) !== null;
    }

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
            || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampCall
            || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Literal\Parameter
            || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Call\ClockCall
            || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Call\Extract
            || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Call\Temporal\GetFormat) {
            return false;
        }

        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate) {
            return match ($operand->function) {
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Minimum,
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Maximum => $this->field($operand->arguments[0]),
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Count,
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::BitAnd,
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::BitOr,
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::BitXor,
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Sum,
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Average,
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::StandardDeviation,
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Variance,
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::SampleStandardDeviation,
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::SampleVariance,
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::JsonArray,
                \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Collect => false,
            };
        }

        return $this->literal($operand) ?? (new TimeZoneFacts())->input($operand);
    }

    /**
     * Retains an aggregate argument's field family; TIMESTAMP literals have DATETIME fields.
     */
    public function field(Scalar $operand): ?bool
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        return $operand instanceof TemporalLiteral ? false : $this->input($operand);
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
