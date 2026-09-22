<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Operator;

use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Conditional;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;

/**

 * Selects a semantic operator form with its exact operand arity. @visibility SqlSemantics

 */
final class Operations
{
    /**
     * @param list<Expression> $operands
     * @throws InvalidStructure
     */
    public static function make(ExpressionFacts $facts, Node $source, string $operator, array $operands): Expression
    {
        if (count($operands) === 2 && in_array($operator, ['MEMBER', 'MEMBER OF'], true)) {
            return new Conditional\JsonMembership($facts, $source, $operands[0], $operands[1]);
        }
        if (count($operands) === 1 && ($unary = UnaryOperator::tryFrom($operator)) !== null) {
            return new UnaryExpression($facts, $source, $unary, $operands[0]);
        }
        $pattern = str_ends_with($operator, ' ESCAPE') ? substr($operator, 0, -7) : $operator;
        $pattern = str_starts_with($pattern, 'NOT ') ? substr($pattern, 4) : $pattern;
        $pattern = $pattern === 'RLIKE' ? 'REGEXP' : $pattern;
        $patternOperator = Conditional\PatternOperator::tryFrom($pattern);
        if ($patternOperator !== null && count($operands) === (str_ends_with($operator, ' ESCAPE') ? 3 : 2)) {
            return new Conditional\PatternMatch($facts, $source, $operands[0], $operands[1], $operands[2] ?? null, str_starts_with($operator, 'NOT '), $patternOperator);
        }
        if (count($operands) === 2 && ($binary = BinaryOperator::tryFrom($operator)) !== null) {
            return new BinaryExpression($facts, $source, $binary, $operands[0], $operands[1]);
        }
        if (count($operands) === 3 && in_array($operator, ['BETWEEN', 'NOT BETWEEN', 'BETWEEN SYMMETRIC', 'NOT BETWEEN SYMMETRIC', 'BETWEEN ASYMMETRIC', 'NOT BETWEEN ASYMMETRIC'], true)) {
            return new Conditional\Between($facts, $source, $operands[0], $operands[1], $operands[2], str_starts_with($operator, 'NOT '), str_ends_with($operator, ' SYMMETRIC'));
        }
        if ($operands !== [] && in_array($operator, ['IN', 'NOT IN'], true)) {
            return new Conditional\InList($facts, $source, $operands[0], array_slice($operands, 1), $operator === 'NOT IN');
        }
        throw new InvalidStructure('Unclassified operator or invalid operand arity: ' . $operator . ' (' . count($operands) . ').');
    }
}
