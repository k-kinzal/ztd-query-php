<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnStar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\ArrayConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\FieldSelection;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\GroupingFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Indirection;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\IndirectionStep;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\AllFields;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\Slice;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\Subscript;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\ArraySubquery;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers `c_expr`, `columnref` and the indirection steps.
 *
 * Rule: PG-EXPRESSION-PRIMARY-001. Scope: `c_expr` (column references,
 * constants, parameters, parenthesized expressions, CASE, function calls,
 * scalar, EXISTS and ARRAY subqueries, array and row constructors,
 * GROUPING), `columnref`, `indirection`, `opt_indirection`,
 * `indirection_el` and `opt_slice_bound`. A dotted name followed only by
 * field names is a `ColumnReference`; one that ends in `.*` is a
 * `ColumnStar`; the steps from the first subscript on, or after a `.*`,
 * make an `Indirection` of it, as PostgreSQL's `makeColumnRef` splits them.
 * Function calls go to the invocation family, queries to the query family.
 * Termination: the indirection list is flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class PrimaryRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a `c_expr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function lower(Form $form): Scalar
    {
        $queries = $this->lowering->queries;

        return match ($form->signature) {
            'c_expr: columnref' => $this->columnReference($form->node(0)),
            'c_expr: AexprConst' => $this->lowering->literals->constant($form->node(0)),
            'c_expr: PARAM opt_indirection' => $this->parameter($form),
            'c_expr: ( a_expr ) opt_indirection' => $this->applied(new Grouped($this->lowering->expressions->expression($form->node(1))), $this->indirection($form->node(3))),
            'c_expr: case_expr' => $this->lowering->expressions->caseExpression($form->node(0)),
            'c_expr: func_expr' => $this->lowering->invocations->call($form->node(0)),
            'c_expr: select_with_parens' => new ScalarSubquery($queries->query($form->node(0))),
            'c_expr: select_with_parens indirection' => new Indirection(new ScalarSubquery($queries->query($form->node(0))), $this->indirection($form->node(1))),
            'c_expr: EXISTS select_with_parens' => new Exists($queries->query($form->node(1))),
            'c_expr: ARRAY select_with_parens' => new ArraySubquery($queries->query($form->node(1))),
            'c_expr: ARRAY array_expr' => new ArrayConstructor($this->lowering->expressions->arrayLiteral($form->node(1))),
            'c_expr: explicit_row', 'c_expr: implicit_row' => $this->lowering->expressions->rowConstructor($form->node(0)),
            'c_expr: GROUPING ( expr_list )' => new GroupingFunction($this->lowering->expressions->expressions($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `c_expr: PARAM opt_indirection`.
     */
    public function parameter(Form $form): Scalar
    {
        return $this->applied($this->lowering->literals->parameter($form->token(0)), $this->indirection($form->node(1)));
    }

    /**
     * Applies steps to a value: the value itself when there is none.
     *
     * @param list<IndirectionStep> $steps
     */
    public function applied(Scalar $base, array $steps): Scalar
    {
        return $steps === [] ? $base : new Indirection($base, $steps);
    }

    /**
     * Lowers `columnref`.
     *
     * @throws ImplementationGap When the production has no rule
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
        $index = 0;
        while ($index < count($steps) && $steps[$index] instanceof FieldSelection) {
            $parts[] = $steps[$index]->name;
            $index++;
        }
        $rest = array_slice($steps, $index);
        if ($rest !== [] && $rest[0] instanceof AllFields) {
            return $this->applied(new ColumnStar($parts), array_slice($rest, 1));
        }

        return $this->applied(new ColumnReference($parts), $rest);
    }

    /**
     * Lowers `indirection` or `opt_indirection`; no step is an empty list.
     *
     * @return list<IndirectionStep>
     *
     * @throws ImplementationGap When the list or a step has no rule
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
            $steps[] = $this->step($this->lowering->productions->form($element));
        }

        return $steps;
    }

    /**
     * Lowers `indirection_el`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function step(Form $form): IndirectionStep
    {
        return match ($form->signature) {
            'indirection_el: . attr_name' => new FieldSelection($this->lowering->names->name($form->node(1))),
            'indirection_el: . *' => new AllFields(),
            'indirection_el: [ a_expr ]' => new Subscript($this->lowering->expressions->expression($form->node(1))),
            'indirection_el: [ opt_slice_bound : opt_slice_bound ]' => new Slice($this->bound($form->node(1)), $this->bound($form->node(3))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_slice_bound`; an omitted bound is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function bound(Node $bound): ?Scalar
    {
        $form = $this->lowering->productions->form($bound);

        return match ($form->signature) {
            'opt_slice_bound: a_expr' => $this->lowering->expressions->expression($form->node(0)),
            'opt_slice_bound:' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
