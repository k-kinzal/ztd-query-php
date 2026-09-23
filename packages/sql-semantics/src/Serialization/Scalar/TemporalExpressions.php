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
     * Writes a field extraction from its classified unit and value expression.
     */
    public static function write(Extract|DateShift $value): Tree
    {
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
