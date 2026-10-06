<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Call;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge;
use SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment;
use SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the window functions and the window written after OVER.
 *
 * Rule: MYSQL-CALL-WINDOW-001. Scope: window_func_call, opt_lead_lag_info,
 * opt_ll_default, stable_integer, param_or_var, opt_null_treatment,
 * opt_from_first_last, opt_windowing_clause, windowing_clause,
 * window_name_or_spec, window_name. The count of NTILE and the offset of
 * LEAD and LAG are an integer, a parameter marker, a routine variable or a
 * user variable. RESPECT NULLS, IGNORE NULLS, FROM FIRST and FROM LAST are
 * kept as written. Constructs: WindowFunction, RoutineVariable. Terminates:
 * every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-descriptions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/window-functions-usage.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class WindowRule
{
    /**
     * The window function productions: the function, and the positions of the value, the count, the LEAD and LAG options, the row number, the edge, the NULL treatment and the window.
     */
    private const FUNCTIONS = [
        'window_func_call: ROW_NUMBER_SYM ( ) windowing_clause' => [WindowFunctionKind::RowNumber, null, null, null, null, null, null, 3],
        'window_func_call: RANK_SYM ( ) windowing_clause' => [WindowFunctionKind::Rank, null, null, null, null, null, null, 3],
        'window_func_call: DENSE_RANK_SYM ( ) windowing_clause' => [WindowFunctionKind::DenseRank, null, null, null, null, null, null, 3],
        'window_func_call: CUME_DIST_SYM ( ) windowing_clause' => [WindowFunctionKind::CumulativeDistribution, null, null, null, null, null, null, 3],
        'window_func_call: PERCENT_RANK_SYM ( ) windowing_clause' => [WindowFunctionKind::PercentRank, null, null, null, null, null, null, 3],
        'window_func_call: NTILE_SYM ( stable_integer ) windowing_clause' => [WindowFunctionKind::Tile, null, 2, null, null, null, null, 4],
        'window_func_call: LEAD_SYM ( expr opt_lead_lag_info ) opt_null_treatment windowing_clause' => [WindowFunctionKind::Lead, 2, null, 3, null, null, 5, 6],
        'window_func_call: LAG_SYM ( expr opt_lead_lag_info ) opt_null_treatment windowing_clause' => [WindowFunctionKind::Lag, 2, null, 3, null, null, 5, 6],
        'window_func_call: FIRST_VALUE_SYM ( expr ) opt_null_treatment windowing_clause' => [WindowFunctionKind::FirstValue, 2, null, null, null, null, 4, 5],
        'window_func_call: LAST_VALUE_SYM ( expr ) opt_null_treatment windowing_clause' => [WindowFunctionKind::LastValue, 2, null, null, null, null, 4, 5],
        'window_func_call: NTH_VALUE_SYM ( expr , simple_expr ) opt_from_first_last opt_null_treatment windowing_clause' => [WindowFunctionKind::NthValue, 2, null, null, 4, 6, 7, 8],
    ];

    /**
     * The NULL treatment productions.
     */
    private const NULLS = ['opt_null_treatment:' => null, 'opt_null_treatment: RESPECT_SYM NULLS_SYM' => NullTreatment::Respect, 'opt_null_treatment: IGNORE_SYM NULLS_SYM' => NullTreatment::Ignore];

    /**
     * The edge productions of NTH_VALUE.
     */
    private const EDGES = ['opt_from_first_last:' => null, 'opt_from_first_last: FROM FIRST_SYM' => CountingEdge::First, 'opt_from_first_last: FROM LAST_SYM' => CountingEdge::Last];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     * @param FrameRule $frames The window specification rules
     */
    public function __construct(private readonly Lowering $lowering, private readonly FrameRule $frames)
    {
    }

    /**
     * Lowers a window function: a node of window_func_call.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function function(Node $call): WindowFunction
    {
        $form = $this->lowering->form($call);
        [$kind, $value, $count, $options, $row, $edge, $nulls, $window] = self::FUNCTIONS[$form->signature] ?? throw ImplementationGap::production($form);
        $arguments = [];
        if ($value !== null) {
            $arguments[] = $this->lowering->expressions->expression($form->node($value));
        }
        if ($count !== null) {
            $arguments[] = $this->stable($form->node($count));
        }
        if ($options !== null) {
            array_push($arguments, ...$this->offset($form->node($options)));
        }
        if ($row !== null) {
            $arguments[] = $this->lowering->expressions->simpleExpression($form->node($row));
        }

        return new WindowFunction(
            $kind,
            $arguments,
            $this->window($form->node($window)),
            $nulls === null ? null : $this->nulls($form->node($nulls)),
            $edge === null ? null : $this->edge($form->node($edge)),
        );
    }

    /**
     * Lowers the offset and default of LEAD and LAG: a node of opt_lead_lag_info.
     *
     * @return list<Scalar>
     * @throws ImplementationGap When a production has no rule
     */
    public function offset(Node $options): array
    {
        $form = $this->lowering->form($options);
        if ($form->signature === 'opt_lead_lag_info:') {
            return [];
        }
        if ($form->signature !== 'opt_lead_lag_info: , stable_integer opt_ll_default') {
            throw ImplementationGap::production($form);
        }
        $arguments = [$this->stable($form->node(1))];
        $default = $this->lowering->form($form->node(2));

        return match ($default->signature) {
            'opt_ll_default:' => $arguments,
            'opt_ll_default: , expr' => [...$arguments, $this->lowering->expressions->expression($default->node(1))],
            default => throw ImplementationGap::production($default),
        };
    }

    /**
     * Lowers a count or offset: a node of stable_integer.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function stable(Node $integer): Scalar
    {
        $form = $this->lowering->form($integer);
        if ($form->signature === 'stable_integer: int64_literal') {
            return $this->lowering->literals->number($form->node(0));
        }
        if ($form->signature !== 'stable_integer: param_or_var') {
            throw ImplementationGap::production($form);
        }
        $variable = $this->lowering->form($form->node(0));

        return match ($variable->signature) {
            'param_or_var: param_marker' => $this->lowering->literals->parameter($variable->node(0)),
            'param_or_var: ident' => new RoutineVariable($this->lowering->names->identifier($variable->node(0))),
            'param_or_var: @ ident_or_text' => $this->lowering->variables->user($variable->node(1)),
            default => throw ImplementationGap::production($variable),
        };
    }

    /**
     * Lowers the NULL treatment: a node of opt_null_treatment.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function nulls(Node $treatment): ?NullTreatment
    {
        $form = $this->lowering->form($treatment);
        if (!array_key_exists($form->signature, self::NULLS)) {
            throw ImplementationGap::production($form);
        }

        return self::NULLS[$form->signature];
    }

    /**
     * Lowers the edge of NTH_VALUE: a node of opt_from_first_last.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function edge(Node $edge): ?CountingEdge
    {
        $form = $this->lowering->form($edge);
        if (!array_key_exists($form->signature, self::EDGES)) {
            throw ImplementationGap::production($form);
        }

        return self::EDGES[$form->signature];
    }

    /**
     * Lowers an optional window: a node of opt_windowing_clause.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function windowing(Node $clause): Name|WindowSpecification|null
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_windowing_clause:' => null,
            'opt_windowing_clause: windowing_clause' => $this->window($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the window after OVER: a node of windowing_clause.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function window(Node $clause): Name|WindowSpecification
    {
        $form = $this->lowering->form($clause);
        if ($form->signature !== 'windowing_clause: OVER_SYM window_name_or_spec') {
            throw ImplementationGap::production($form);
        }
        $window = $this->lowering->form($form->node(1));

        return match ($window->signature) {
            'window_name_or_spec: window_name' => $this->name($window->node(0)),
            'window_name_or_spec: window_spec' => $this->frames->specification($window->node(0)),
            default => throw ImplementationGap::production($window),
        };
    }

    /**
     * Lowers a window name: a node of window_name.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function name(Node $name): Name
    {
        $form = $this->lowering->form($name);
        if ($form->signature !== 'window_name: ident') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->names->identifier($form->node(0));
    }
}
