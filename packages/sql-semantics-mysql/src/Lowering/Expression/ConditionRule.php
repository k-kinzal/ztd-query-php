<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\MySql\Statement\Expression\Truth;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the expr and bool_pri levels: logical operators, NOT, truth tests, NULL tests and comparisons.
 *
 * Rule: MYSQL-CONDITION-001. Scope: expr, bool_pri, or, and, comp_op,
 * all_or_any. The binary operators of each level are left recursive; the
 * left spine is walked in a loop and the operations are built from the
 * innermost outwards, so the structure associates as the grammar does.
 * `&&` is AND and `||` without PIPES_AS_CONCAT is OR (their nonterminals are
 * synonyms); SOME is the keyword ANY. Constructs: Logical, Not, TruthTest,
 * NullTest, Comparison, QuantifiedComparison. Terminates: the spine loop
 * descends one left child per step; every other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/expressions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ConditionRule
{
    /**
     * The left-recursive productions of expr, by the operator they apply.
     */
    private const LOGICAL = [
        'expr: expr or expr' => LogicalOperator::Or, 'expr: expr XOR expr' => LogicalOperator::Xor, 'expr: expr and expr' => LogicalOperator::And,
    ];

    /**
     * The truth test productions: the truth value and whether NOT is written.
     */
    private const TRUTH = [
        'expr: bool_pri IS TRUE_SYM' => [Truth::True, false], 'expr: bool_pri IS not TRUE_SYM' => [Truth::True, true],
        'expr: bool_pri IS FALSE_SYM' => [Truth::False, false], 'expr: bool_pri IS not FALSE_SYM' => [Truth::False, true],
        'expr: bool_pri IS UNKNOWN_SYM' => [Truth::Unknown, false], 'expr: bool_pri IS not UNKNOWN_SYM' => [Truth::Unknown, true],
    ];

    /**
     * The left-recursive productions of bool_pri.
     */
    private const BOOL_PRI = [
        'bool_pri: bool_pri IS NULL_SYM' => true, 'bool_pri: bool_pri IS not NULL_SYM' => true, 'bool_pri: bool_pri EQUAL_SYM predicate' => true,
        'bool_pri: bool_pri comp_op predicate' => true, 'bool_pri: bool_pri comp_op all_or_any ( subselect )' => true,
        'bool_pri: bool_pri comp_op all_or_any table_subquery' => true,
    ];

    /**
     * The comparison operator productions.
     */
    private const OPERATORS = [
        'comp_op: EQ' => ComparisonOperator::Equal, 'comp_op: GE' => ComparisonOperator::GreaterOrEqual, 'comp_op: GT_SYM' => ComparisonOperator::Greater,
        'comp_op: LE' => ComparisonOperator::LessOrEqual, 'comp_op: LT' => ComparisonOperator::Less, 'comp_op: NE' => ComparisonOperator::NotEqual,
        'comp_op: EQUAL_SYM' => ComparisonOperator::NullSafeEqual,
    ];

    /**
     * The quantifier productions.
     */
    private const QUANTIFIERS = ['all_or_any: ALL' => Quantifier::All, 'all_or_any: ANY_SYM' => Quantifier::Any];

    /**
     * The synonym keyword productions of the logical operators.
     */
    private const SYNONYMS = ['or: OR_SYM' => true, 'or: OR2_SYM' => true, 'and: AND_SYM' => true, 'and: AND_AND_SYM' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of expr.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function expression(Node $expression): Scalar
    {
        $pending = [];
        $form = $this->lowering->form($expression);
        while (isset(self::LOGICAL[$form->signature])) {
            if ($form->signature !== 'expr: expr XOR expr') {
                $this->synonym($form->node(1));
            }
            $pending[] = [self::LOGICAL[$form->signature], $form->node(2)];
            $form = $this->lowering->form($form->node(0));
        }
        $result = $this->unit($form);
        foreach (array_reverse($pending) as [$operator, $right]) {
            $result = new Logical($operator, $result, $this->expression($right));
        }

        return $result;
    }

    /**
     * Lowers an expr production that is not a logical operation.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function unit(Form $form): Scalar
    {
        if ($form->signature === 'expr: bool_pri') {
            return $this->boolean($form->node(0));
        }
        if ($form->signature === 'expr: NOT_SYM expr') {
            return new Not($this->expression($form->node(1)));
        }
        [$truth, $negated] = self::TRUTH[$form->signature] ?? throw ImplementationGap::production($form);
        if ($negated) {
            $this->lowering->options->skip($form->node(2));
        }

        return new TruthTest($this->boolean($form->node(0)), $truth, $negated);
    }

    /**
     * Lowers a node of bool_pri.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function boolean(Node $expression): Scalar
    {
        $pending = [];
        $form = $this->lowering->form($expression);
        while (isset(self::BOOL_PRI[$form->signature])) {
            $pending[] = $form;
            $form = $this->lowering->form($form->node(0));
        }
        if ($form->signature !== 'bool_pri: predicate') {
            throw ImplementationGap::production($form);
        }
        $result = (new PredicateRule($this->lowering))->predicate($form->node(0));
        foreach (array_reverse($pending) as $operation) {
            $result = $this->apply($operation, $result);
        }

        return $result;
    }

    /**
     * Applies one left-recursive bool_pri production to its already lowered left operand.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function apply(Form $form, Scalar $left): Scalar
    {
        $predicates = new PredicateRule($this->lowering);

        return match ($form->signature) {
            'bool_pri: bool_pri IS NULL_SYM' => new NullTest($left),
            'bool_pri: bool_pri IS not NULL_SYM' => $this->negatedNull($form, $left),
            'bool_pri: bool_pri EQUAL_SYM predicate' => new Comparison(ComparisonOperator::NullSafeEqual, $left, $predicates->predicate($form->node(2))),
            'bool_pri: bool_pri comp_op predicate' => new Comparison($this->operator($form->node(1)), $left, $predicates->predicate($form->node(2))),
            'bool_pri: bool_pri comp_op all_or_any ( subselect )' => new QuantifiedComparison($left, $this->operator($form->node(1)), $this->quantifier($form->node(2)), $this->lowering->queries->query($form->node(4))),
            'bool_pri: bool_pri comp_op all_or_any table_subquery' => new QuantifiedComparison($left, $this->operator($form->node(1)), $this->quantifier($form->node(2)), $this->lowering->queries->query($form->node(3))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `IS NOT NULL`, confirming the NOT keyword.
     */
    public function negatedNull(Form $form, Scalar $left): NullTest
    {
        $this->lowering->options->skip($form->node(2));

        return new NullTest($left, true);
    }

    /**
     * Lowers a comparison operator.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function operator(Node $operator): ComparisonOperator
    {
        $form = $this->lowering->form($operator);

        return self::OPERATORS[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers ALL or ANY.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function quantifier(Node $quantifier): Quantifier
    {
        $form = $this->lowering->form($quantifier);

        return self::QUANTIFIERS[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Confirms that a node is one of the synonym keywords of AND or OR.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function synonym(Node $keyword): void
    {
        $form = $this->lowering->form($keyword);
        if (!isset(self::SYNONYMS[$form->signature])) {
            throw ImplementationGap::production($form);
        }
    }
}
