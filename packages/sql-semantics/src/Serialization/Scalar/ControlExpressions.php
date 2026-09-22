<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Model\Scalar\Control\RaiseError;
use SqlSemantics\Model\Scalar\Control\RaiseIgnore;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes explicit trigger control flow and its message operand.
 * @visibility SqlSemantics
 */
final class ControlExpressions
{
    /**
     * Serializes the requested effect without evaluating it.
     */
    public static function write(RaiseError|RaiseIgnore $expression): Tree
    {
        $operands = $expression instanceof RaiseError
            ? [Build::keyword($expression->action->value), Expressions::write($expression->message)]
            : [Build::keyword('IGNORE')];
        return new Tree('raise', [Build::keyword('RAISE'), Build::parentheses(Build::separated($operands))]);
    }
}
