<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression;

use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\CastSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Collation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\DefaultRequest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\AtLocal;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\AtTimeZone;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Between;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\DistinctTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Negation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\PatternMatch;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\BooleanTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\DocumentTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\JsonTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NormalizationTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\Overlaps;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\InList;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\InSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\QuantifiedArray;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\QuantifiedSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\UniquePredicate;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Statement\Scalar;

/**
 * Decides whether an operand keeps its place when an operator expression is written without parentheses.
 *
 * Rule: PG-PRECEDENCE-001. The levels are the precedence declarations of the
 * PostgreSQL grammar (gram.y), weakest first: OR; AND; NOT (right); IS,
 * ISNULL and NOTNULL; the six comparison operators; BETWEEN, IN, LIKE,
 * ILIKE, SIMILAR and the NOT before them; ESCAPE; UNBOUNDED; IDENT and the
 * other words with declared precedence; other operators and OPERATOR(...);
 * + and -; *, / and %; ^; AT; COLLATE; the prefix + and - (right); the
 * subscript, parenthesis, :: and . levels. Each operator form has a left
 * operand followed by a token of some level, and a last operand that closes
 * its rule at the rule's level. An operand written before a token of level
 * L keeps its place when every rule on its right edge is above L, or at L
 * and L groups to the left; an operand written after the token of a rule of
 * level L keeps its place when every token on its left edge is above L, or
 * at L and L groups to the right. Precedence decides only between a rule
 * and a token that can continue its last operand, which every operator
 * token can; ESCAPE continues only a pattern match without one, so an
 * operand before ESCAPE keeps its place unless such a match ends it; WITH
 * and WITHOUT continue only an IS JSON test without a uniqueness clause. An
 * expression that is not an operator
 * form is a `c_expr`: it is closed on both edges. Terminates: each walk
 * follows one edge of a finite tree.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-PRECEDENCE,
 * https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Precedence
{
    /**
     * The level of OR.
     */
    public const OR = 1;

    /**
     * The level of AND.
     */
    public const AND = 2;

    /**
     * The level of the prefix NOT.
     */
    public const NOT = 3;

    /**
     * The level of IS, ISNULL and NOTNULL.
     */
    public const IS = 4;

    /**
     * The level of the comparison operators.
     */
    public const COMPARISON = 5;

    /**
     * The level of BETWEEN, IN, LIKE, ILIKE, SIMILAR and the NOT before them.
     */
    public const PATTERN = 6;

    /**
     * The level of ESCAPE.
     */
    public const ESCAPE = 7;

    /**
     * The level of UNBOUNDED and NESTED.
     */
    public const UNBOUNDED = 8;

    /**
     * The level of IDENT and the other words with declared precedence, such as PRECEDING and FOLLOWING.
     */
    public const IDENT = 9;

    /**
     * The level of the other operators and OPERATOR(...).
     */
    public const OPERATOR = 10;

    /**
     * The level of binary + and -.
     */
    public const ADDITIVE = 11;

    /**
     * The level of *, / and %.
     */
    public const MULTIPLICATIVE = 12;

    /**
     * The level of ^.
     */
    public const EXPONENT = 13;

    /**
     * The level of AT TIME ZONE and AT LOCAL.
     */
    public const AT = 14;

    /**
     * The level of COLLATE.
     */
    public const COLLATE = 15;

    /**
     * The level of the prefix + and -.
     */
    public const UNARY = 16;

    /**
     * The level of ::.
     */
    public const TYPECAST = 19;

    /**
     * The level of an edge that holds no operand.
     */
    public const CLOSED = 99;

    /**
     * The levels whose operators group to the left.
     */
    private const LEFT = [self::OR, self::AND, self::OPERATOR, self::ADDITIVE, self::MULTIPLICATIVE, self::EXPONENT, self::AT, self::COLLATE, self::TYPECAST];

    /**
     * The levels whose operators group to the right.
     */
    private const RIGHT = [self::NOT, self::UNARY];

    /**
     * Answers the level of an operator name written between or before operands.
     */
    public function operator(OperatorName $operator, bool $prefix = false): int
    {
        if ($operator->explicit || $operator->qualifiers !== []) {
            return self::OPERATOR;
        }

        return match ($operator->name->value) {
            '+', '-' => $prefix ? self::UNARY : self::ADDITIVE,
            '*', '/', '%' => $prefix ? self::OPERATOR : self::MULTIPLICATIVE,
            '^' => $prefix ? self::OPERATOR : self::EXPONENT,
            '<', '>', '=', '<=', '>=', '<>' => $prefix ? self::OPERATOR : self::COMPARISON,
            default => self::OPERATOR,
        };
    }

    /**
     * Answers the edges of an operator form: the left operand, the level of the token after it, the last operand and the level of the rule it closes.
     *
     * @return array{Scalar|null, int, Scalar|null, int}|null Null for an expression that is not an operator form
     */
    public function edges(Scalar $expression): ?array
    {
        return $this->operators($expression) ?? $this->predicates($expression);
    }

    /**
     * Answers the edges of the operator forms of PG-OPERATOR-001.
     *
     * @return array{Scalar|null, int, Scalar|null, int}|null
     */
    public function operators(Scalar $expression): ?array
    {
        if ($expression instanceof BinaryOperation) {
            $level = $this->operator($expression->operator);

            return [$expression->left, $level, $expression->right, $level];
        }
        if ($expression instanceof UnaryOperation) {
            return [null, self::CLOSED, $expression->operand, $this->operator($expression->operator, true)];
        }
        if ($expression instanceof BooleanOperation) {
            $level = $expression->operator === BooleanOperator::And ? self::AND : self::OR;

            return [$expression->left, $level, $expression->right, $level];
        }
        if ($expression instanceof Negation) {
            return [null, self::CLOSED, $expression->operand, self::NOT];
        }
        if ($expression instanceof Cast) {
            return $expression->spelling === CastSpelling::Operator ? [$expression->operand, self::TYPECAST, null, self::CLOSED] : null;
        }
        if ($expression instanceof Collation) {
            return [$expression->operand, self::COLLATE, null, self::CLOSED];
        }
        if ($expression instanceof AtTimeZone) {
            return [$expression->operand, self::AT, $expression->zone, self::AT];
        }
        if ($expression instanceof AtLocal) {
            return [$expression->operand, self::AT, null, self::CLOSED];
        }
        if ($expression instanceof DistinctTest) {
            return [$expression->left, self::IS, $expression->right, self::IS];
        }
        if ($expression instanceof Between) {
            return [$expression->operand, self::PATTERN, $expression->high, self::PATTERN];
        }
        if ($expression instanceof PatternMatch) {
            return [$expression->left, self::PATTERN, $expression->escape ?? $expression->right, self::PATTERN];
        }

        return null;
    }

    /**
     * Answers the edges of the predicate and sublink forms of PG-PREDICATE-001 and PG-SUBLINK-001.
     *
     * @return array{Scalar|null, int, Scalar|null, int}|null
     */
    public function predicates(Scalar $expression): ?array
    {
        if ($expression instanceof NullTest || $expression instanceof BooleanTest || $expression instanceof DocumentTest || $expression instanceof NormalizationTest || $expression instanceof JsonTest) {
            return [$expression->operand, self::IS, null, self::CLOSED];
        }
        if ($expression instanceof InList || $expression instanceof InSubquery) {
            return [$expression->operand, self::PATTERN, null, self::CLOSED];
        }
        if ($expression instanceof QuantifiedSubquery || $expression instanceof QuantifiedArray) {
            return [$expression->operand, $expression->comparator instanceof OperatorName ? $this->operator($expression->comparator) : self::PATTERN, null, self::CLOSED];
        }
        if ($expression instanceof Overlaps || $expression instanceof UniquePredicate || $expression instanceof DefaultRequest) {
            return [null, self::CLOSED, null, self::CLOSED];
        }

        return null;
    }

    /**
     * Tells whether an expression is a `c_expr`: closed on both edges, so it keeps its place in every operand slot.
     */
    public function primary(Scalar $expression): bool
    {
        return $this->edges($expression) === null;
    }

    /**
     * Tells whether an expression can be written where the grammar expects a `b_expr`, which has no boolean, IS, pattern or subquery operators.
     */
    public function restricted(Scalar $expression): bool
    {
        $pending = [$expression];
        while ($pending !== []) {
            $current = array_pop($pending);
            $edges = $this->edges($current);
            if ($edges === null) {
                continue;
            }
            $form = $current instanceof BinaryOperation || $current instanceof UnaryOperation || $current instanceof Cast
                || $current instanceof DistinctTest || $current instanceof DocumentTest;
            if (!$form) {
                return false;
            }
            foreach ([$edges[0], $edges[2]] as $operand) {
                if ($operand !== null) {
                    $pending[] = $operand;
                }
            }
        }

        return true;
    }

    /**
     * Answers the weakest token level on the left edge of an expression.
     */
    public function opening(Scalar $expression): int
    {
        $weakest = self::CLOSED;
        for ($edges = $this->edges($expression); $edges !== null && $edges[0] !== null; $edges = $this->edges($edges[0])) {
            $weakest = min($weakest, $edges[1]);
        }

        return $weakest;
    }

    /**
     * Answers the weakest rule level on the right edge of an expression.
     */
    public function closing(Scalar $expression): int
    {
        $weakest = self::CLOSED;
        for ($edges = $this->edges($expression); $edges !== null && $edges[2] !== null; $edges = $this->edges($edges[2])) {
            $weakest = min($weakest, $edges[3]);
        }

        return $weakest;
    }

    /**
     * Tells whether an operand keeps its place when a token of a level follows it.
     */
    public function before(Scalar $operand, int $level): bool
    {
        $closing = $this->closing($operand);

        return $closing > $level || ($closing === $level && in_array($level, self::LEFT, true));
    }

    /**
     * Tells whether a pattern match without ESCAPE lies on the right edge of an operand, where it would take a following ESCAPE.
     *
     * ESCAPE continues only a pattern match, so no other rule on the edge
     * competes for it: the parser reduces them and gives ESCAPE to the
     * nearest open pattern match.
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

    /**
     * Tells whether an IS JSON test without a key uniqueness clause lies on the right edge of an operand, where it would take a following WITH or WITHOUT.
     *
     * The grammar gives WITH and WITHOUT a higher precedence than the empty
     * json_key_uniqueness_constraint_opt of `a_expr IS JSON`, so the nearest
     * open test takes a WITH UNIQUE, WITH WRAPPER, WITH CHECK OPTION or
     * WITH DATA that follows the operand, and the last three then fail to
     * parse.
     */
    public function takesUniqueness(Scalar $operand): bool
    {
        for ($node = $operand; $node !== null; $node = $this->edges($node)[2] ?? null) {
            if ($node instanceof JsonTest && $node->uniqueness === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether an operand keeps its place as the last operand of a rule of a level.
     */
    public function after(Scalar $operand, int $level): bool
    {
        $opening = $this->opening($operand);

        return $opening > $level || ($opening === $level && in_array($level, self::RIGHT, true));
    }
}
