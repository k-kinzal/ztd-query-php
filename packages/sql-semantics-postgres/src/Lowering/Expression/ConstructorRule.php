<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\ArrayItems;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseBranch;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowSpelling;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers row constructors, array constructors and CASE.
 *
 * Rule: PG-EXPRESSION-CONSTRUCTOR-001. Scope: `row`, `explicit_row`,
 * `implicit_row`, `array_expr`, `array_expr_list`, `case_expr`,
 * `case_arg`, `when_clause_list`, `when_clause` and `case_default`.
 * Constructors: `RowConstructor`, `ArrayItems`, `CaseExpression`,
 * `CaseBranch`. Termination: lists are flattened iteratively; nested array
 * levels recurse on the depth of the tree.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-ROW-CONSTRUCTORS,
 * https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-ARRAY-CONSTRUCTORS,
 * https://www.postgresql.org/docs/17/functions-conditional.html#FUNCTIONS-CASE. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class ConstructorRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `row`, `explicit_row` or `implicit_row`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function row(Node $row): RowConstructor
    {
        $form = $this->lowering->productions->form($row);
        $expressions = $this->lowering->expressions;

        return match ($form->signature) {
            'row: ROW ( expr_list )', 'explicit_row: ROW ( expr_list )' => new RowConstructor($expressions->expressions($form->node(2))),
            'row: ROW ( )', 'explicit_row: ROW ( )' => new RowConstructor([]),
            'row: ( expr_list , a_expr )', 'implicit_row: ( expr_list , a_expr )' => new RowConstructor(
                [...$expressions->expressions($form->node(1)), $expressions->expression($form->node(3))],
                RowSpelling::Implicit,
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `array_expr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function items(Node $array): ArrayItems
    {
        $form = $this->lowering->productions->form($array);
        if ($form->signature === 'array_expr: [ expr_list ]') {
            return new ArrayItems($this->lowering->expressions->expressions($form->node(1)));
        }
        if ($form->signature === 'array_expr: [ ]') {
            return new ArrayItems();
        }
        if ($form->signature !== 'array_expr: [ array_expr_list ]') {
            throw ImplementationGap::production($form);
        }
        $nested = [];
        foreach ($this->lowering->items($form->node(1), 'array_expr_list: array_expr', 'array_expr_list: array_expr_list , array_expr') as $item) {
            $nested[] = $this->items($item);
        }

        return new ArrayItems([], $nested);
    }

    /**
     * Lowers `case_expr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function caseExpression(Node $case): CaseExpression
    {
        $form = $this->lowering->productions->form($case);
        if ($form->signature !== 'case_expr: CASE case_arg when_clause_list case_default END_P') {
            throw ImplementationGap::production($form);
        }
        $branches = [];
        foreach ($this->lowering->items($form->node(2), 'when_clause_list: when_clause', 'when_clause_list: when_clause_list when_clause') as $clause) {
            $when = $this->lowering->productions->form($clause);
            if ($when->signature !== 'when_clause: WHEN a_expr THEN a_expr') {
                throw ImplementationGap::production($when);
            }
            $branches[] = new CaseBranch($this->lowering->expressions->expression($when->node(1)), $this->lowering->expressions->expression($when->node(3)));
        }

        return new CaseExpression($this->optional($form->node(1), 'case_arg: a_expr', 'case_arg:'), $branches, $this->optional($form->node(3), 'case_default: ELSE a_expr', 'case_default:'));
    }

    /**
     * Lowers `case_arg` or `case_default`: the expression, or null when none is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function optional(Node $optional, string $present, string $absent): ?Scalar
    {
        $form = $this->lowering->productions->form($optional);
        if ($form->signature === $absent) {
            return null;
        }
        if ($form->signature !== $present) {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->expressions->expression($form->node(count($form->node->children) - 1));
    }
}
