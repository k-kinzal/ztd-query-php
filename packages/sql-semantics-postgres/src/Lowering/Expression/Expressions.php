<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\FieldSelection;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\IndirectionStep;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Statement\Scalar;

/**
 * The entry point of the expression family.
 *
 * Rule: PG-EXPRESSION-001 (slice — the expression family completes or
 * replaces the bodies; the method signatures are the stable contract). Scope
 * today: `a_expr` and `b_expr` that are a `c_expr`, the six comparisons of
 * `a_expr`, `c_expr` that is a column reference, a constant or a parameter
 * without indirection, `columnref` of dotted names, `expr_list`,
 * `indirection` and `opt_indirection` of field selections. Constructors:
 * `BinaryOperation`, `ColumnReference`, `FieldSelection`. Termination: lists
 * are flattened iteratively; operand nesting recurses on the tree depth.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html. Status: Specified.
 *
 * @visibility SqlSemantics
 */
final class Expressions
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a scalar expression: `a_expr`, `b_expr` or `c_expr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function expression(Node $expression): Scalar
    {
        $form = $this->lowering->productions->form($expression);

        return match ($form->signature) {
            'a_expr: c_expr', 'b_expr: c_expr' => $this->expression($form->node(0)),
            'a_expr: a_expr < a_expr', 'a_expr: a_expr > a_expr', 'a_expr: a_expr = a_expr',
            'a_expr: a_expr LESS_EQUALS a_expr', 'a_expr: a_expr GREATER_EQUALS a_expr', 'a_expr: a_expr NOT_EQUALS a_expr' => $this->comparison($form),
            'c_expr: columnref' => $this->columnReference($form->node(0)),
            'c_expr: AexprConst' => $this->lowering->literals->constant($form->node(0)),
            'c_expr: PARAM opt_indirection' => $this->parameter($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an expression list: `expr_list`.
     *
     * @return list<Scalar>
     */
    public function expressions(Node $list): array
    {
        $expressions = [];
        foreach ($this->lowering->items($list, 'expr_list: a_expr', 'expr_list: expr_list , a_expr') as $expression) {
            $expressions[] = $this->expression($expression);
        }

        return $expressions;
    }

    /**
     * Lowers a column reference: `columnref`.
     *
     * @throws ImplementationGap When the production has no rule, or the reference holds a star, a subscript or a slice
     */
    public function columnReference(Node $reference): Scalar
    {
        $form = $this->lowering->productions->form($reference);
        $parts = [$this->lowering->names->name($form->node(0))];
        $steps = match ($form->signature) {
            'columnref: ColId' => [],
            'columnref: ColId indirection' => $this->indirection($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
        foreach ($steps as $step) {
            $parts[] = $step->field() ?? throw ImplementationGap::production($form);
        }

        return new ColumnReference($parts);
    }

    /**
     * Lowers the steps applied to a value or an assignment target: `indirection` or `opt_indirection`; no step is an empty list.
     *
     * @return list<IndirectionStep>
     *
     * @throws ImplementationGap When a step has no rule
     */
    public function indirection(Node $indirection): array
    {
        $spine = match ($indirection->name) {
            'indirection' => ['indirection: indirection_el', 'indirection: indirection indirection_el'],
            'opt_indirection' => ['opt_indirection:', 'opt_indirection: opt_indirection indirection_el'],
            default => throw ImplementationGap::production($this->lowering->productions->form($indirection)),
        };
        $steps = [];
        foreach ($this->lowering->items($indirection, ...$spine) as $element) {
            $form = $this->lowering->productions->form($element);
            $steps[] = match ($form->signature) {
                'indirection_el: . attr_name' => new FieldSelection($this->lowering->names->name($form->node(1))),
                default => throw ImplementationGap::production($form),
            };
        }

        return $steps;
    }

    /**
     * Lowers one of the six comparison productions of `a_expr`.
     */
    public function comparison(Form $form): BinaryOperation
    {
        return new BinaryOperation(new OperatorName($this->lowering->operators->symbol($form->token(1))), $this->expression($form->node(0)), $this->expression($form->node(2)));
    }

    /**
     * Lowers a parameter that no indirection follows.
     *
     * @throws ImplementationGap When an indirection follows the parameter
     */
    public function parameter(Form $form): Scalar
    {
        if ($this->indirection($form->node(1)) !== []) {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->literals->parameter($form->token(0));
    }
}
