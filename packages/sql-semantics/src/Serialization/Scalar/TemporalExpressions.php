<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Model\Scalar\Temporal\DateShift;
use SqlSemantics\Model\Scalar\Temporal\Extract;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes temporal operands and interval fields in their distinct SQL roles.
 * @visibility SqlSemantics
 */
final class TemporalExpressions
{
    /**
     * Writes a field extraction, a date shift, TIMESTAMPADD or TIMESTAMPDIFF, a period overlap test or a time zone conversion from its operands.
     */
    public static function write(Extract|DateShift|\SqlSemantics\Model\Scalar\Temporal\PeriodOverlap|\SqlSemantics\Model\Scalar\Temporal\ZoneConversion|\SqlSemantics\Model\Scalar\Temporal\TemporalFormat|\SqlSemantics\Model\Scalar\Temporal\TimestampAdd|\SqlSemantics\Model\Scalar\Temporal\TimestampDiff $value): Tree
    {
        if ($value instanceof \SqlSemantics\Model\Scalar\Temporal\TimestampAdd || $value instanceof \SqlSemantics\Model\Scalar\Temporal\TimestampDiff) {
            return new Tree('timestamp-arithmetic', [Build::keyword($value->spelling()), Build::parentheses(Build::separated([Build::keyword($value->unit->value), ...array_map(Expressions::write(...), $value->inputs())]))]);
        }
        if ($value instanceof \SqlSemantics\Model\Scalar\Temporal\TemporalFormat) {
            return new Tree('get-format', [Build::keyword('GET_FORMAT'), Build::parentheses(Build::separated([Build::keyword($value->temporalKind->value), Expressions::write($value->standard)]))]);
        }
        if ($value instanceof \SqlSemantics\Model\Scalar\Temporal\ZoneConversion) {
            return Build::parentheses(new Tree('zone', [Expressions::write($value->value), Build::keyword($value->spelling()), ...($value->zone === null ? [] : [Expressions::write($value->zone)])]));
        }
        if ($value instanceof \SqlSemantics\Model\Scalar\Temporal\PeriodOverlap) {
            $row = static fn (\SqlSemantics\Model\Expression $start, \SqlSemantics\Model\Expression $end): Tree => Build::parentheses(Build::separated([Expressions::write($start), Expressions::write($end)]));
            return Build::parentheses(new Tree('overlaps', [$row($value->leftStart, $value->leftEnd), Build::keyword('OVERLAPS'), $row($value->rightStart, $value->rightEnd)]));
        }
        if ($value instanceof DateShift) {
            $interval = new Tree('interval', [Build::keyword('INTERVAL'), Expressions::write($value->quantity), Build::keyword($value->unit->value)]);
            if ($value->operandOrder === \SqlSemantics\Model\Scalar\Temporal\IntervalOperandOrder::IntervalFirst) {
                return Build::parentheses(new Tree('interval-addition', [$interval, Build::keyword('+'), Expressions::write($value->value)]));
            }
            return new Tree('date-shift', [Build::keyword($value->direction->value), Build::parentheses(Build::separated([Expressions::write($value->value), $interval]))]);
        }
        return new Tree('extract', [Build::keyword('EXTRACT'), Build::parentheses(new Tree('extraction', [Build::keyword($value->field->value), Build::keyword('FROM'), Expressions::write($value->value)]))]);
    }
}
