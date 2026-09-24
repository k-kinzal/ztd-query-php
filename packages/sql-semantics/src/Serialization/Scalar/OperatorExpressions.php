<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Model\Scalar\Operator;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**

 * Writes fixed-arity operations with explicit precedence. @visibility SqlSemantics

 */
final class OperatorExpressions
{
    /**
     * Writes operator operands with boundaries that preserve their precedence.
     */
    public static function write(Operator\CollatedExpression|Operator\BinaryExpression|Operator\UnaryExpression|Operator\CastExpression|Operator\ArrayCast|Operator\TimeZoneCast|Operator\CharacterSetConversion $value): Tree
    {
        if ($value instanceof Operator\ArrayCast || $value instanceof Operator\TimeZoneCast || $value instanceof Operator\CharacterSetConversion) {
            return self::conversion($value);
        }
        if ($value instanceof Operator\CollatedExpression) {
            return Build::parentheses(new Tree('collate', [Expressions::write($value->operand), Build::keyword('COLLATE'), Build::identifier($value->collation->parts, $value->type->dialect)]));
        }
        if ($value instanceof Operator\BinaryExpression) {
            return Build::parentheses(new Tree('binary', [Expressions::write($value->left), Build::keyword($value->operator->value), Expressions::write($value->right)]));
        }
        if ($value instanceof Operator\CastExpression) {
            return $value->mode === Operator\CastMode::Implicit ? Expressions::write($value->operand) : new Tree('cast', [Build::keyword('CAST'), Build::parentheses(new Tree('cast-value', [Expressions::write($value->operand), Build::keyword('AS'), CastTargets::write($value->type)]))]);
        }
        $parts = [Build::keyword($value->operator->value), Expressions::write($value->operand)];
        return Build::parentheses(new Tree('unary', $value->operator->postfix() ? array_reverse($parts) : $parts));
    }

    /**
     * Writes an operation through an explicitly named PostgreSQL operator, keeping its OPERATOR(path.symbol) spelling.
     */
    public static function named(Operator\Qualified\QualifiedInfixOperation|Operator\Qualified\QualifiedPrefixOperation $value): Tree
    {
        $operator = self::reference($value->operator);
        return Build::parentheses($value instanceof Operator\Qualified\QualifiedInfixOperation ? new Tree('binary', [Expressions::write($value->left), $operator, Expressions::write($value->right)]) : new Tree('unary', [$operator, Expressions::write($value->operand)]));
    }

    /**
     * Writes OPERATOR(path.symbol) with quoted qualifier names.
     */
    public static function reference(Operator\Qualified\QualifiedOperator $operator): Tree
    {
        $path = $operator->qualifier === [] ? [] : [Build::identifier($operator->qualifier, \SqlSemantics\Dialect::PostgreSql), new \SqlSemantics\Model\Sql\Atom('punctuation', '.')];
        return new Tree('operator-reference', [Build::keyword('OPERATOR'), Build::parentheses(new Tree('operator-name', [...$path, new \SqlSemantics\Model\Sql\Atom('operator', $operator->symbol)]))]);
    }

    /**
     * Writes the operator and quantifier of an ANY, SOME or ALL comparison.
     */
    public static function quantified(\SqlSemantics\Model\Scalar\Query\ComparisonOperator|\SqlSemantics\Model\Scalar\Conditional\PatternOperator|Operator\Qualified\QualifiedOperator $operator, bool $negated, \SqlSemantics\Model\Scalar\Query\Quantifier $quantifier): Tree
    {
        return new Tree('quantified-operator', [$operator instanceof Operator\Qualified\QualifiedOperator ? self::reference($operator) : Build::keyword(($negated ? 'NOT ' : '') . $operator->value), Build::keyword($quantifier->value)]);
    }

    /**
     * Writes the operator and quantifier of an array comparison; a classified operator stays one keyword with its quantifier.
     */
    public static function array(\SqlSemantics\Model\Scalar\Conditional\ArrayComparison $value): Tree
    {
        return $value->operator instanceof Operator\Qualified\QualifiedOperator ? self::quantified($value->operator, false, $value->quantifier) : Build::keyword($value->spelling());
    }

    /**
     * Writes MySQL's array cast, time zone cast and character set conversion.
     */
    public static function conversion(Operator\ArrayCast|Operator\TimeZoneCast|Operator\CharacterSetConversion $value): Tree
    {
        $inner = match (true) {
            $value instanceof Operator\ArrayCast => [Expressions::write($value->operand), Build::keyword('AS'), CastTargets::write($value->element), Build::keyword('ARRAY')],
            $value instanceof Operator\TimeZoneCast => [Expressions::write($value->operand), Build::keyword('AT TIME ZONE'), ...($value->interval ? [Build::keyword('INTERVAL')] : []), Expressions::write($value->zone), Build::keyword('AS DATETIME'), ...($value->precision === null ? [] : [Build::parentheses(Build::keyword((string) $value->precision))])],
            $value instanceof Operator\CharacterSetConversion => [Expressions::write($value->operand), Build::keyword('USING'), Build::identifier([$value->characterSet], \SqlSemantics\Dialect::MySql)],
        };
        return new Tree('conversion', [Build::keyword($value->spelling()), Build::parentheses(new Tree('conversion-operands', $inner))]);
    }
}
