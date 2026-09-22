<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Sql;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
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
        return new Expression(ExpressionKind::Literal, new TypeDescriptor($dialect, $type), $value === null ? Nullability::AlwaysNull : Nullability::NotNull, new Token(0, 'literal', $text, 0), symbol: $text);
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
        return new Expression(ExpressionKind::UnresolvedColumn, new TypeDescriptor($dialect, 'unknown'), Nullability::Unknown, new Node('reference', 0, []), reference: $name, sql: Build::identifier($name, $dialect));
    }

    /**
     * Constructs a binary operation and protects both operand boundaries.
     * @throws InvalidStructure
     * Type and NULL facts are resolved when the expression is attached to a statement.
     */
    public static function binary(string $operator, Expression $left, Expression $right): Expression
    {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['AND', 'OR', 'IS', 'IS NOT', 'IS DISTINCT FROM', 'IS NOT DISTINCT FROM', 'LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE', 'GLOB', 'MATCH', 'REGEXP', 'DIV', 'MOD'], true) && (preg_match('~^[+*/<>=!@#%^&|?\x7e-]+$~', $operator) !== 1 || str_contains($operator, '--') || str_contains($operator, '/*') || str_contains($operator, '*/'))) {
            throw new InvalidStructure('A binary operator must be one SQL operator.');
        }
        $sql = Build::parentheses(new Tree('binary', [Build::parentheses($left->sql), Build::keyword($operator), Build::parentheses($right->sql)]));
        return new Expression(ExpressionKind::Operator, new TypeDescriptor($left->type->dialect, 'unknown'), Nullability::Unknown, new Node('expression', 0, []), [$left, $right], symbol: $operator, sql: $sql);
    }
}
