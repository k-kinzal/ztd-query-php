<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers function call productions.
 *
 * Rule: SQLITE-EXPR-CALL-001. Scope: the `expr` productions of function
 * calls; filter_over, filter_clause, over_clause. A call keeps its
 * name, its arguments in order, the star form, the quantifier, the argument
 * ordering, the filter and the window. Terminates: every part is a strict
 * subtree. Source: https://sqlite.org/lang_expr.html#functions,
 * https://sqlite.org/windowfunctions.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CallRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a function call.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function expression(Form $form): Scalar
    {
        $lowering = $this->lowering;

        return match ($form->signature) {
            'expr: idj LP distinct exprlist RP' => new FunctionCall($this->name($form), $lowering->expressions->list($form->node(3)), false, $lowering->results->quantifier($form->node(2))),
            'expr: idj LP distinct exprlist ORDER BY sortlist RP' => new FunctionCall($this->name($form), $lowering->expressions->list($form->node(3)), false, $lowering->results->quantifier($form->node(2)), $lowering->ordering->terms($form->node(6))),
            'expr: idj LP STAR RP' => new FunctionCall($this->name($form), [], true),
            'expr: idj LP distinct exprlist RP filter_over' => new FunctionCall($this->name($form), $lowering->expressions->list($form->node(3)), false, $lowering->results->quantifier($form->node(2)), [], ...$this->filterOver($form->node(5))),
            'expr: idj LP distinct exprlist ORDER BY sortlist RP filter_over' => new FunctionCall($this->name($form), $lowering->expressions->list($form->node(3)), false, $lowering->results->quantifier($form->node(2)), $lowering->ordering->terms($form->node(6)), ...$this->filterOver($form->node(8))),
            'expr: idj LP STAR RP filter_over' => new FunctionCall($this->name($form), [], true, null, [], ...$this->filterOver($form->node(4))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the function name of a call production and records it as an operand leaf.
     */
    public function name(Form $form): Name
    {
        return $this->lowering->names->token($form->token(0));
    }

    /**
     * Lowers a `filter_over` into the filter predicate and the window.
     *
     * @return array{Scalar|null, WindowSpec|Name|null}
     * @throws ImplementationGap When the production has no rule
     */
    public function filterOver(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'filter_over: filter_clause over_clause' => [$this->filter($form->node(0)), $this->over($form->node(1))],
            'filter_over: over_clause' => [null, $this->over($form->node(0))],
            'filter_over: filter_clause' => [$this->filter($form->node(0)), null],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `filter_clause` into its predicate.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function filter(Node $clause): Scalar
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'filter_clause: FILTER LP WHERE expr RP' => $this->lowering->expressions->expression($form->node(3)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an `over_clause` into a window specification or a window name.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function over(Node $clause): WindowSpec|Name
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'over_clause: OVER LP window RP' => $this->lowering->windows->window($form->node(2)),
            'over_clause: OVER nm' => $this->lowering->names->name($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
