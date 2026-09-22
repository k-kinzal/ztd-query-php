<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Sql;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Constructs scalar expression structure before statement-level name and type binding.
 *
 * @visibility SqlSemantics
 */
final class ExpressionFactory
{
    /**
     * Encodes one scalar value without needing any original SQL.
     */
    public static function literal(string|int|float|bool|null $value, Dialect $dialect): Expression
    {
        [$text, $type] = Literal::encode($value, $dialect);
        return new \SqlSemantics\Model\Scalar\Value\Literal(new \SqlSemantics\Model\Scalar\ExpressionFacts(TypeDescriptor::builtin($dialect, $type), $value === null ? Nullability::AlwaysNull : Nullability::NotNull, []), new Token(0, 'literal', $text, 0), \SqlSemantics\Model\Scalar\Value\LiteralClassification::of($text, $dialect), $text);
    }

    /**
     * @param list<string> $name Resolved identifier parts, without SQL quotes
     * @throws InvalidStructure
     */
    public static function reference(array $name, Dialect $dialect): Expression
    {
        Collections::strings($name);
        if ($name === [] || in_array('', $name, true)) {
            throw new InvalidStructure('A column reference requires nonempty identifier parts.');
        }
        return new \SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference(new \SqlSemantics\Model\Scalar\ExpressionFacts(TypeDescriptor::builtin($dialect, 'unknown'), Nullability::Unknown, []), new Node('reference', 0, []), $name);
    }

    /**
     * Constructs a binary operation and protects both operand boundaries.
     * @throws InvalidStructure
     * Type and NULL facts are resolved when the expression is attached to a statement.
     */
    public static function binary(string $operator, Expression $left, Expression $right): Expression
    {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['MEMBER', 'MEMBER OF', 'AND', 'OR', 'IS', 'IS NOT', 'IS DISTINCT FROM', 'IS NOT DISTINCT FROM', 'LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE', 'GLOB', 'MATCH', 'REGEXP', 'DIV', 'MOD'], true) && (preg_match('~^[+*/<>=!@#%^&|?\x7e-]+$~', $operator) !== 1 || str_contains($operator, '--') || str_contains($operator, '/*') || str_contains($operator, '*/'))) {
            throw new InvalidStructure('A binary operator must be one SQL operator.');
        }
        return \SqlSemantics\Model\Scalar\Operator\Operations::make(new \SqlSemantics\Model\Scalar\ExpressionFacts(TypeDescriptor::builtin($left->type->dialect, 'unknown'), Nullability::Unknown, []), new Node('expression', 0, []), $operator, [$left, $right]);
    }
}
