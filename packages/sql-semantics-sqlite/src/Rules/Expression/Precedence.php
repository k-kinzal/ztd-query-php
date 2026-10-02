<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Expression;

use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Between;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTest;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternMatch;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InList;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InTable;
use SqlSemantics\Statement\Scalar;

/**
 * Decides whether an operand keeps its place when an operator expression is written without parentheses.
 *
 * Rule: SQLITE-PRECEDENCE-001. The binding levels are those of the SQLite
 * grammar, weakest first: OR; AND; NOT; the equality group (=, ==, <>, !=,
 * IS, LIKE, GLOB, REGEXP, MATCH, BETWEEN, IN, ISNULL, NOTNULL, NOT NULL);
 * the ordering comparisons; ESCAPE; the bitwise group; + and -; *, / and %;
 * || and the JSON extraction operators; COLLATE; the prefix operators ~, +
 * and -. Binary operators group to the left. An operand written on the left
 * of an operator keeps its place when no expression on its right edge binds
 * weaker than the operator; an operand written on the right keeps its place
 * when every expression on its left edge binds tighter. A prefix operator on
 * the right edge takes every tighter operator that follows it. Terminates:
 * each walk follows one edge of a finite tree.
 * Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class Precedence
{
    /**
     * The level of OR.
     */
    public const DISJUNCTION = 1;

    /**
     * The level of AND.
     */
    public const CONJUNCTION = 2;

    /**
     * The level of the prefix NOT.
     */
    public const NEGATION = 3;

    /**
     * The level of the equality group.
     */
    public const EQUALITY = 4;

    /**
     * The level of the ordering comparisons.
     */
    public const COMPARISON = 5;

    /**
     * The level of ESCAPE.
     */
    public const ESCAPE = 6;

    /**
     * The level of the bitwise operators.
     */
    public const BITWISE = 7;

    /**
     * The level of binary plus and minus.
     */
    public const ADDITIVE = 8;

    /**
     * The level of multiplication, division and remainder.
     */
    public const MULTIPLICATIVE = 9;

    /**
     * The level of concatenation and JSON extraction.
     */
    public const CONCATENATION = 10;

    /**
     * The level of COLLATE.
     */
    public const COLLATION = 11;

    /**
     * The level of the prefix operators other than NOT.
     */
    public const PREFIX = 12;

    /**
     * The level of an expression that is closed on the asked edge.
     */
    public const CLOSED = 99;

    /**
     * Answers the level of an operator expression with its first and last written operand; null marks an edge that is not an operand.
     *
     * @return array{int, Scalar|null, Scalar|null}|null Null for an expression that is closed on both edges
     */
    public function edges(Scalar $expression): ?array
    {
        if ($expression instanceof Binary) {
            return [$expression->operator->level(), $expression->left, $expression->right];
        }
        if ($expression instanceof Unary) {
            return [$expression->operator === UnaryOperator::Not ? self::NEGATION : self::PREFIX, null, $expression->operand];
        }
        if ($expression instanceof Collate) {
            return [self::COLLATION, $expression->operand, null];
        }
        if ($expression instanceof PatternMatch) {
            return [self::EQUALITY, $expression->left, $expression->escape ?? $expression->right];
        }
        if ($expression instanceof Between) {
            return [self::EQUALITY, $expression->operand, $expression->high];
        }
        if ($expression instanceof NullTest || $expression instanceof InList || $expression instanceof InQuery || $expression instanceof InTable) {
            return [self::EQUALITY, $expression->operand, null];
        }

        return null;
    }

    /**
     * Answers the weakest level among the expressions on the left edge of an operand.
     */
    public function opening(Scalar $operand): int
    {
        $weakest = self::CLOSED;
        for ($edges = $this->edges($operand); $edges !== null && $edges[1] !== null; $edges = $this->edges($edges[1])) {
            $weakest = min($weakest, $edges[0]);
        }

        return $weakest;
    }

    /**
     * Answers the weakest level among the expressions on the right edge of an operand.
     */
    public function closing(Scalar $operand): int
    {
        $weakest = self::CLOSED;
        for ($edges = $this->edges($operand); $edges !== null && $edges[2] !== null; $edges = $this->edges($edges[2])) {
            $weakest = min($weakest, $edges[0]);
        }

        return $weakest;
    }

    /**
     * Tells whether a pattern match without ESCAPE lies on the right edge of an operand, where it would take a following ESCAPE.
     */
    public function takesEscape(Scalar $operand): bool
    {
        for ($node = $operand; $node !== null; $node = $this->edges($node)[2] ?? null) {
            if ($node instanceof PatternMatch && $node->escape === null) {
                return true;
            }
        }

        return false;
    }
}
