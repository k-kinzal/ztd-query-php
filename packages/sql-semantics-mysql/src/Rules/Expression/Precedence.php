<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\BinaryCast;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalAddition;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalArithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\MemberOf;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Regexp;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\SoundsLike;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Scalar;

/**
 * Decides whether an operand keeps its place when an expression is written without grouping parentheses.
 *
 * Rule: MYSQL-PRECEDENCE-001. The MySQL grammar stratifies expressions into
 * expr (OR, XOR, AND, NOT, IS TRUE|FALSE|UNKNOWN), bool_pri (IS NULL, the
 * comparisons and the quantified comparisons), predicate (IN, BETWEEN, LIKE,
 * REGEXP, SOUNDS LIKE, MEMBER OF), bit_expr (`|`, `&`, `<<` `>>`, `+` `-`,
 * `*` `/` `%` DIV MOD, `^`, and `± INTERVAL`) and simple_expr (concatenation
 * under PIPES_AS_CONCAT, the prefix operators, BINARY, COLLATE and the closed
 * primaries), and resolves the operators inside a level by the precedence
 * declarations of `sql_yacc.yy`. Every expression has an opening level, the
 * weakest level on its left edge, and a closing level, the weakest level on
 * its right edge, on one scale whose constants this class declares. A prefix
 * form has the opening level of its nonterminal; a postfix form closes at
 * its own level; a closed form (parentheses, a call, a literal, a name) is
 * closed on both edges. Two prefix forms extend their right operand further
 * than their nonterminal suggests: `INTERVAL e unit + expr` takes everything
 * above AND, and a user variable assignment `@v := expr` takes everything.
 * An operator written after an operand takes the whole operand as its left
 * operand unless it binds into the operand's right edge: the parser, which
 * stands at the innermost form, binds it into the right operand of the
 * innermost form on that edge whose right operand bound is weaker than the
 * operator, provided that right operand can be the operator's left operand. A left operand therefore keeps its place
 * when its top level admits the operator's left operand and the operator
 * does not bind into it; for left association an operator binds into a
 * form of its own level only from the right. A right operand keeps its
 * place when its opening level is stronger than the operator. Terminates:
 * each walk follows one edge of a finite tree.
 * The scale was checked against the parser of every release with
 * `SELECT <expression>` probes of each pair of forms.
 * Shared rule: this class is the one precedence model of the package. A
 * structure class of any family whose grammar slot takes an expression of
 * a level (the substring of POSITION before IN, the document of JSON_VALUE,
 * the row number of NTH_VALUE) checks its operand in its constructor with
 * admits() for the slot level and, when an operator keyword follows the
 * slot, absorbs() for that keyword's level, using the level constants
 * declared here; no family keeps its own table of levels.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/operator-precedence.html,
 * https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_high_not_precedence.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Precedence
{
    /**
     * The slot level of an expr: every expression.
     */
    public const EXPR = 0;

    /**
     * The level of a user variable assignment's right edge.
     */
    public const ASSIGNMENT = 0;

    /**
     * The level of OR.
     */
    public const DISJUNCTION = 1;

    /**
     * The level of XOR.
     */
    public const EXCLUSION = 2;

    /**
     * The level of AND.
     */
    public const CONJUNCTION = 3;

    /**
     * The level of the low-precedence prefix NOT, and the right edge of `INTERVAL e unit + expr`.
     */
    public const NEGATION = 4;

    /**
     * The level of IS TRUE, IS FALSE and IS UNKNOWN.
     */
    public const TRUTH = 5;

    /**
     * The level of bool_pri: IS NULL and the comparisons.
     */
    public const BOOL_PRI = 6;

    /**
     * The level of predicate: IN, BETWEEN, LIKE, REGEXP, SOUNDS LIKE and MEMBER OF.
     */
    public const PREDICATE = 7;

    /**
     * The level of bit_expr and of its weakest operator `|`.
     */
    public const BIT_EXPR = 8;

    /**
     * The level of `&`.
     */
    public const BIT_AND = 9;

    /**
     * The level of `<<` and `>>`.
     */
    public const SHIFT = 10;

    /**
     * The level of binary `+` and `-`, also with an INTERVAL operand.
     */
    public const ADDITIVE = 11;

    /**
     * The level of `*`, `/`, `%`, DIV and MOD.
     */
    public const MULTIPLICATIVE = 12;

    /**
     * The level of `^`.
     */
    public const BIT_XOR = 13;

    /**
     * The level of simple_expr and of its weakest operator, `||` under PIPES_AS_CONCAT.
     */
    public const SIMPLE_EXPR = 14;

    /**
     * The level of the right edge of the prefix operators `-`, `+`, `~` and `!`.
     */
    public const PREFIX = 15;

    /**
     * The opening level of a prefix form of simple_expr, and the right edge of BINARY.
     */
    public const PRIMARY = 16;

    /**
     * The level of COLLATE.
     */
    public const COLLATION = 17;

    /**
     * The level of an edge that is closed.
     */
    public const CLOSED = 99;

    /**
     * Answers the edges of an operator form: its level, the bound of its right operand, the operand on the left edge and the operand on the right edge.
     *
     * An operator written after the form binds into its right operand when
     * the operator is stronger than the bound: the bound is the form's own
     * level for a binary operator, the level of the keyword for a prefix
     * form, and the level just below the nonterminal of the right operand
     * otherwise (the upper bound of BETWEEN is a predicate, the pattern of
     * LIKE a simple_expr).
     *
     * @return array{int, int, Scalar|null, Scalar|null}|null Null for a form that is closed on both edges
     */
    public function edges(Scalar $expression): ?array
    {
        return match (true) {
            $expression instanceof Logical => [$expression->operator->level(), $expression->operator->level(), $expression->left, $expression->right],
            $expression instanceof Arithmetic => [$expression->operator->level(), $expression->operator->level(), $expression->left, $expression->right],
            $expression instanceof Comparison => [self::BOOL_PRI, self::BOOL_PRI, $expression->left, $expression->right],
            $expression instanceof Concatenation => [self::SIMPLE_EXPR, self::SIMPLE_EXPR, $expression->left, $expression->right],
            $expression instanceof Not => [self::NEGATION, self::NEGATION, null, $expression->operand],
            $expression instanceof Unary => [self::PRIMARY, self::PREFIX, null, $expression->operand],
            $expression instanceof BinaryCast => [self::PRIMARY, self::PRIMARY, null, $expression->operand],
            $expression instanceof IntervalAddition => [self::PRIMARY, self::NEGATION, null, $expression->operand],
            $expression instanceof VariableAssignment => [self::PRIMARY, self::ASSIGNMENT, null, $expression->value],
            default => $this->postfix($expression),
        };
    }

    /**
     * Answers the edges of a form whose operator follows its first operand.
     *
     * @return array{int, int, Scalar|null, Scalar|null}|null Null for a form that is closed on both edges
     */
    public function postfix(Scalar $expression): ?array
    {
        return match (true) {
            $expression instanceof TruthTest => [self::TRUTH, self::TRUTH, $expression->operand, null],
            $expression instanceof NullTest => [self::BOOL_PRI, self::BOOL_PRI, $expression->operand, null],
            $expression instanceof QuantifiedComparison => [self::BOOL_PRI, self::BOOL_PRI, $expression->operand, null],
            $expression instanceof InList, $expression instanceof InQuery, $expression instanceof MemberOf => [self::PREDICATE, self::PREDICATE, $expression->operand, null],
            $expression instanceof Between => [self::PREDICATE, self::BOOL_PRI, $expression->operand, $expression->high],
            $expression instanceof Like => [self::PREDICATE, self::BIT_XOR, $expression->operand, $expression->escape ?? $expression->pattern],
            $expression instanceof Regexp, $expression instanceof SoundsLike => [self::PREDICATE, self::PREDICATE, $expression->operand, $expression->pattern],
            $expression instanceof IntervalArithmetic => [self::ADDITIVE, self::ADDITIVE, $expression->operand, null],
            $expression instanceof Collated => [self::COLLATION, self::COLLATION, $expression->operand, null],
            default => null,
        };
    }

    /**
     * Answers the weakest level on the left edge of an expression.
     */
    public function opening(Scalar $expression): int
    {
        $weakest = self::CLOSED;
        for ($edges = $this->edges($expression); $edges !== null; $edges = $edges[2] === null ? null : $this->edges($edges[2])) {
            $weakest = min($weakest, $edges[0]);
        }

        return $weakest;
    }

    /**
     * Answers the expression written first in an expression: the end of its left edge.
     */
    public function leading(Scalar $expression): Scalar
    {
        for ($edges = $this->edges($expression); $edges !== null && $edges[2] !== null; $edges = $this->edges($expression)) {
            $expression = $edges[2];
        }

        return $expression;
    }

    /**
     * Answers the level at which an expression stands as an operand: its operator level, the level of its prefix keyword, or closed.
     *
     * A trailing interval `x ± INTERVAL n unit` ends with the interval unit and
     * takes the precedence of INTERVAL, so every bit_expr operator written
     * after it applies to the whole of it: it stands at the strongest bit_expr level.
     */
    public function top(Scalar $expression): int
    {
        if ($expression instanceof IntervalArithmetic) {
            return self::BIT_XOR;
        }

        return $this->edges($expression)[0] ?? self::CLOSED;
    }

    /**
     * Tells whether an operator written after an expression would bind into the expression's right edge instead of taking the whole expression.
     *
     * The operator binds with the given precedence and takes a left operand
     * of at least the given level.
     */
    public function absorbs(Scalar $expression, int $precedence, int $minimum): bool
    {
        for ($edges = $this->edges($expression); $edges !== null && $edges[3] !== null; $edges = $this->edges($edges[3])) {
            if ($precedence > $edges[1] && $this->top($edges[3]) >= $minimum) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether an expression keeps its place as the left operand of an operator of a precedence that takes a left operand of at least a level.
     */
    public function fits(Scalar $left, int $precedence, int $minimum): bool
    {
        return $this->top($left) >= $minimum && !$this->absorbs($left, $precedence, $minimum);
    }

    /**
     * Tells whether an operand can stand in a slot of a grammar level that ends at a closing token such as `)` or `,`.
     *
     * The operand must be of a nonterminal the slot accepts, and nothing on
     * its left edge may bind weaker than the slot. A slot followed by an
     * operator keyword additionally needs that the keyword does not bind
     * into the operand (`absorbs()`).
     */
    public function admits(Scalar $operand, int $slot): bool
    {
        return $this->opening($operand) >= $slot;
    }
}
