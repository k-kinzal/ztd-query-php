<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Model\Scalar\Operator;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\TypeDeclaration;

/**

 * Writes fixed-arity operations with explicit precedence. @visibility SqlSemantics

 */
final class OperatorExpressions
{
    public static function write(Operator\CollatedExpression|Operator\BinaryExpression|Operator\UnaryExpression|Operator\CastExpression $value): Tree
    {
        if ($value instanceof Operator\CollatedExpression) {
            return Build::parentheses(new Tree('collate', [Expressions::write($value->operand), Build::keyword('COLLATE'), Build::identifier($value->collation->parts, $value->type->dialect)]));
        }
        if ($value instanceof Operator\BinaryExpression) {
            return Build::parentheses(new Tree('binary', [Expressions::write($value->left), Build::keyword($value->operator->value), Expressions::write($value->right)]));
        }
        if ($value instanceof Operator\CastExpression) {
            return $value->mode === Operator\CastMode::Implicit ? Expressions::write($value->operand) : new Tree('cast', [Build::keyword('CAST'), Build::parentheses(new Tree('cast-value', [Expressions::write($value->operand), Build::keyword('AS'), TypeDeclaration::write($value->type)]))]);
        }
        $parts = [Build::keyword($value->operator->value), Expressions::write($value->operand)];
        return Build::parentheses(new Tree('unary', in_array($value->operator, [Operator\UnaryOperator::IsNull, Operator\UnaryOperator::IsNotNull], true) ? array_reverse($parts) : $parts));
    }
}
