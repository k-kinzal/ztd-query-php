<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\InList;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\InSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\MatchKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\QuantifiedArray;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\QuantifiedSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\UniquePredicate;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the productions of `a_expr` that compare a value with a query or a list.
 *
 * Rule: PG-EXPRESSION-SUBLINK-001. Scope: `a_expr [NOT] IN in_expr`,
 * `a_expr subquery_Op sub_type select_with_parens`, `a_expr subquery_Op
 * sub_type ( a_expr )`, `UNIQUE opt_unique_null_treatment
 * select_with_parens`, and `in_expr`, `sub_type`, `subquery_Op`.
 * Constructors: `InList`, `InSubquery`, `QuantifiedSubquery`,
 * `QuantifiedArray`, `UniquePredicate`. The query in the parentheses is
 * lowered by the query family without those parentheses. Termination: each
 * operand is a strictly smaller subtree.
 * Source: https://www.postgresql.org/docs/17/functions-subquery.html, https://www.postgresql.org/docs/17/functions-comparisons.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class SublinkRule
{
    /**
     * The quantifier of each `sub_type` production.
     */
    private const QUANTIFIERS = ['sub_type: ANY' => Quantifier::Any, 'sub_type: SOME' => Quantifier::Some, 'sub_type: ALL' => Quantifier::All];

    /**
     * The keyword of each pattern-matching `subquery_Op` production.
     */
    private const KEYWORDS = [
        'subquery_Op: LIKE' => MatchKeyword::Like, 'subquery_Op: NOT_LA LIKE' => MatchKeyword::NotLike,
        'subquery_Op: ILIKE' => MatchKeyword::ILike, 'subquery_Op: NOT_LA ILIKE' => MatchKeyword::NotILike,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a comparison with a query or a list, or answers null when the production is not one.
     */
    public function lower(Form $form): ?Scalar
    {
        $expressions = $this->lowering->expressions;

        return match ($form->signature) {
            'a_expr: a_expr IN_P in_expr' => $this->membership($expressions->expression($form->node(0)), false, $form->node(2)),
            'a_expr: a_expr NOT_LA IN_P in_expr' => $this->membership($expressions->expression($form->node(0)), true, $form->node(3)),
            'a_expr: a_expr subquery_Op sub_type select_with_parens' => new QuantifiedSubquery(
                $expressions->expression($form->node(0)),
                $this->comparator($form->node(1)),
                $this->quantifier($form->node(2)),
                $this->lowering->queries->query($form->node(3)),
            ),
            'a_expr: a_expr subquery_Op sub_type ( a_expr )' => new QuantifiedArray(
                $expressions->expression($form->node(0)),
                $this->comparator($form->node(1)),
                $this->quantifier($form->node(2)),
                $expressions->expression($form->node(4)),
            ),
            'a_expr: UNIQUE opt_unique_null_treatment select_with_parens' => new UniquePredicate(
                $this->lowering->tables->uniqueNullTreatment($form->node(1)),
                $this->lowering->queries->query($form->node(2)),
            ),
            default => null,
        };
    }

    /**
     * Lowers the right side of IN: `in_expr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function membership(Scalar $operand, bool $negated, Node $in): Scalar
    {
        $form = $this->lowering->productions->form($in);

        return match ($form->signature) {
            'in_expr: select_with_parens' => new InSubquery($operand, $negated, $this->lowering->queries->query($form->node(0))),
            'in_expr: ( expr_list )' => new InList($operand, $negated, $this->lowering->expressions->expressions($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `subquery_Op`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function comparator(Node $operator): OperatorName|MatchKeyword
    {
        $form = $this->lowering->productions->form($operator);
        $keyword = self::KEYWORDS[$form->signature] ?? null;
        if ($keyword !== null) {
            return $keyword;
        }

        return match ($form->signature) {
            'subquery_Op: all_Op' => $this->lowering->operators->operator($form->node(0)),
            'subquery_Op: OPERATOR ( any_operator )' => $this->lowering->operators->qualified($form->node(2), true),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `sub_type`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function quantifier(Node $quantifier): Quantifier
    {
        $form = $this->lowering->productions->form($quantifier);

        return self::QUANTIFIERS[$form->signature] ?? throw ImplementationGap::production($form);
    }
}
