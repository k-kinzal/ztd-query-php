<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Invocation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Argument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\ArgumentSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\NamedArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\PositionalArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers function calls written with the function's name, and their arguments and aggregate clauses.
 *
 * Rule: PG-CALL-LOWERING-001. Scope: `func_expr`, `func_expr_windowless`,
 * `func_application`, `func_arg_list`, `func_arg_expr`, `func_arg_list_opt`,
 * `within_group_clause`, `filter_clause`. Constructor: `FunctionCall`,
 * `PositionalArgument`, `NamedArgument`; the SQL-syntax functions and the
 * JSON aggregates go to their rules. `ALL` before the arguments builds the
 * same node as no quantifier (noise, see `InvocationNoise`). The grammar
 * rejects WITHIN GROUP together with an ordering inside the parentheses,
 * DISTINCT or VARIADIC: such SQL is outside the language and raises an
 * `AnalysisException` with the server's message. Termination: argument lists
 * are flattened iteratively. Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-FUNCTION-CALLS,
 * https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-AGGREGATES. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class CallRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `func_expr`, `func_expr_windowless` or `func_application`.
     *
     * @throws AnalysisException When WITHIN GROUP is combined with an ordering, DISTINCT or VARIADIC
     * @throws ImplementationGap When the production has no rule
     */
    public function call(Node $call): Scalar
    {
        $form = $this->lowering->productions->form($call);

        return match ($form->signature) {
            'func_expr: func_application within_group_clause filter_clause over_clause' => $this->windowed($form),
            'func_expr: json_aggregate_func filter_clause over_clause' => (new JsonRule($this->lowering))->aggregate(
                $form->node(0),
                $this->lowering->invocations->filter($form->node(1)),
                $this->lowering->invocations->over($form->node(2)),
            ),
            'func_expr: func_expr_common_subexpr', 'func_expr_windowless: func_expr_common_subexpr' => $this->lowering->invocations->subexpression($form->node(0)),
            'func_expr_windowless: func_application' => $this->application($form->node(0)),
            'func_expr_windowless: json_aggregate_func' => (new JsonRule($this->lowering))->aggregate($form->node(0), null, null),
            default => $this->application($call),
        };
    }

    /**
     * Lowers `func_application` with the clauses that may follow it.
     *
     * @param list<SortItem> $withinGroup The ordering written after WITHIN GROUP
     * @param Node|null $over The `over_clause` that follows
     *
     * @throws AnalysisException When WITHIN GROUP is combined with an ordering, DISTINCT or VARIADIC, which the action of `func_expr: func_application within_group_clause filter_clause over_clause` in `gram.y` rejects
     * @throws ImplementationGap When the production has no rule
     */
    public function application(Node $application, array $withinGroup = [], ?Scalar $filter = null, ?Node $over = null): FunctionCall
    {
        $form = $this->lowering->productions->form($application);
        $name = $this->lowering->names->dotted($form->node(0));
        [$arguments, $star, $distinct, $variadic, $order] = match ($form->signature) {
            'func_application: func_name ( )' => [[], false, false, false, []],
            'func_application: func_name ( * )' => [[], true, false, false, []],
            'func_application: func_name ( func_arg_list opt_sort_clause )' => [$this->arguments($form->node(2)), false, false, false, $this->order($form->node(3))],
            'func_application: func_name ( ALL func_arg_list opt_sort_clause )' => [$this->arguments($form->node(3)), false, false, false, $this->order($form->node(4))],
            'func_application: func_name ( DISTINCT func_arg_list opt_sort_clause )' => [$this->arguments($form->node(3)), false, true, false, $this->order($form->node(4))],
            'func_application: func_name ( VARIADIC func_arg_expr opt_sort_clause )' => [[$this->argument($form->node(3))], false, false, true, $this->order($form->node(4))],
            'func_application: func_name ( func_arg_list , VARIADIC func_arg_expr opt_sort_clause )' => [
                [...$this->arguments($form->node(2)), $this->argument($form->node(5))], false, false, true, $this->order($form->node(6)),
            ],
            default => throw ImplementationGap::production($form),
        };
        $grouped = $withinGroup !== [];
        if ($grouped) {
            $problem = match (true) {
                $order !== [] => 'cannot use multiple ORDER BY clauses with WITHIN GROUP',
                $distinct => 'cannot use DISTINCT with WITHIN GROUP',
                $variadic => 'cannot use VARIADIC with WITHIN GROUP',
                default => null,
            };
            if ($problem !== null) {
                throw new AnalysisException($problem);
            }
        }
        $window = $over === null ? null : $this->lowering->invocations->over($over);

        return new FunctionCall($name, $arguments, $star, $distinct, $variadic, $grouped ? $withinGroup : $order, $grouped, $filter, $window);
    }

    /**
     * Lowers `func_expr: func_application within_group_clause filter_clause over_clause`.
     *
     * @throws AnalysisException When WITHIN GROUP is combined with an ordering, DISTINCT or VARIADIC
     */
    public function windowed(Form $form): FunctionCall
    {
        $withinGroup = $this->withinGroup($form->node(1));

        return $this->application($form->node(0), $withinGroup, $this->lowering->invocations->filter($form->node(2)), $form->node(3));
    }

    /**
     * Lowers `within_group_clause`; no clause is an empty ordering.
     *
     * @return list<SortItem>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function withinGroup(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'within_group_clause:' => [],
            'within_group_clause: WITHIN GROUP_P ( sort_clause )' => $this->order($form->node(3)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `filter_clause`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function filter(Node $clause): ?Scalar
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'filter_clause:' => null,
            'filter_clause: FILTER ( WHERE a_expr )' => $this->lowering->expressions->expression($form->node(3)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `func_arg_list` or `func_arg_list_opt`; no argument is an empty list.
     *
     * @return list<Argument>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function arguments(Node $list): array
    {
        if ($list->name === 'func_arg_list_opt') {
            $form = $this->lowering->productions->form($list);

            return match ($form->signature) {
                'func_arg_list_opt:' => [],
                'func_arg_list_opt: func_arg_list' => $this->arguments($form->node(0)),
                default => throw ImplementationGap::production($form),
            };
        }
        $arguments = [];
        foreach ($this->lowering->items($list, 'func_arg_list: func_arg_expr', 'func_arg_list: func_arg_list , func_arg_expr') as $argument) {
            $arguments[] = $this->argument($argument);
        }

        return $arguments;
    }

    /**
     * Lowers `func_arg_expr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function argument(Node $argument): Argument
    {
        $form = $this->lowering->productions->form($argument);

        return match ($form->signature) {
            'func_arg_expr: a_expr' => new PositionalArgument($this->lowering->expressions->expression($form->node(0))),
            'func_arg_expr: param_name COLON_EQUALS a_expr' => new NamedArgument($this->lowering->names->name($form->node(0)), $this->lowering->expressions->expression($form->node(2)), ArgumentSpelling::Assignment),
            'func_arg_expr: param_name EQUALS_GREATER a_expr' => new NamedArgument($this->lowering->names->name($form->node(0)), $this->lowering->expressions->expression($form->node(2)), ArgumentSpelling::Arrow),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an ordering: `opt_sort_clause`, `sort_clause` or `sortby_list`.
     *
     * @return list<SortItem>
     */
    public function order(Node $clause): array
    {
        return $this->lowering->queries->sortClause($clause);
    }
}
