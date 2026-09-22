<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Model\Scalar\Temporal\Extract;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes extraction fields and temporal operands in their distinct SQL roles.
 * @visibility SqlSemantics
 */
final class TemporalExpressions
{
    /**
     * Writes a field extraction from its classified unit and value expression.
     */
    public static function write(Extract $value): Tree
    {
        return new Tree('extract', [Build::keyword('EXTRACT'), Build::parentheses(new Tree('extraction', [Build::keyword($value->field->value), Build::keyword('FROM'), Expressions::write($value->value)]))]);
    }
}
