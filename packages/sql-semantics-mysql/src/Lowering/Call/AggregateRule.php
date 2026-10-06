<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Call;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the aggregate functions: sum_expr with its argument forms, GROUP_CONCAT and JSON_OBJECTAGG.
 *
 * Rule: MYSQL-CALL-AGGREGATE-001. Scope: sum_expr, in_sum_expr,
 * opt_distinct, opt_gorder_clause, gorder_list, opt_gconcat_separator. Each
 * production names its function, whether DISTINCT is written, the form and
 * position of its argument (an operand with an optional ALL, an expression
 * list, or the star with an optional ALL), and the position of its window
 * (MySQL 8.0 and later). Constructs: Aggregate, GroupConcat,
 * JsonObjectAggregate. Terminates: the ordering list is flattened in a loop;
 * every other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AggregateRule
{
    /**
     * The aggregate productions: the function, DISTINCT, the argument form and position, and the window position.
     */
    private const AGGREGATES = [
        'sum_expr: AVG_SYM ( in_sum_expr )' => [AggregateFunction::Average, false, 'in', 2, null],
        'sum_expr: AVG_SYM ( DISTINCT in_sum_expr )' => [AggregateFunction::Average, true, 'in', 3, null],
        'sum_expr: BIT_AND ( in_sum_expr )' => [AggregateFunction::BitAnd, false, 'in', 2, null],
        'sum_expr: BIT_OR ( in_sum_expr )' => [AggregateFunction::BitOr, false, 'in', 2, null],
        'sum_expr: BIT_XOR ( in_sum_expr )' => [AggregateFunction::BitXor, false, 'in', 2, null],
        'sum_expr: COUNT_SYM ( opt_all * )' => [AggregateFunction::Count, false, 'star', 2, null],
        'sum_expr: COUNT_SYM ( in_sum_expr )' => [AggregateFunction::Count, false, 'in', 2, null],
        'sum_expr: COUNT_SYM ( DISTINCT expr_list )' => [AggregateFunction::Count, true, 'list', 3, null],
        'sum_expr: MIN_SYM ( in_sum_expr )' => [AggregateFunction::Minimum, false, 'in', 2, null],
        'sum_expr: MIN_SYM ( DISTINCT in_sum_expr )' => [AggregateFunction::Minimum, true, 'in', 3, null],
        'sum_expr: MAX_SYM ( in_sum_expr )' => [AggregateFunction::Maximum, false, 'in', 2, null],
        'sum_expr: MAX_SYM ( DISTINCT in_sum_expr )' => [AggregateFunction::Maximum, true, 'in', 3, null],
        'sum_expr: STD_SYM ( in_sum_expr )' => [AggregateFunction::StandardDeviation, false, 'in', 2, null],
        'sum_expr: VARIANCE_SYM ( in_sum_expr )' => [AggregateFunction::Variance, false, 'in', 2, null],
        'sum_expr: STDDEV_SAMP_SYM ( in_sum_expr )' => [AggregateFunction::SampleStandardDeviation, false, 'in', 2, null],
        'sum_expr: VAR_SAMP_SYM ( in_sum_expr )' => [AggregateFunction::SampleVariance, false, 'in', 2, null],
        'sum_expr: SUM_SYM ( in_sum_expr )' => [AggregateFunction::Sum, false, 'in', 2, null],
        'sum_expr: SUM_SYM ( DISTINCT in_sum_expr )' => [AggregateFunction::Sum, true, 'in', 3, null],
        'sum_expr: JSON_ARRAYAGG ( in_sum_expr )' => [AggregateFunction::JsonArray, false, 'in', 2, null],
        'sum_expr: AVG_SYM ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::Average, false, 'in', 2, 4],
        'sum_expr: AVG_SYM ( DISTINCT in_sum_expr ) opt_windowing_clause' => [AggregateFunction::Average, true, 'in', 3, 5],
        'sum_expr: BIT_AND_SYM ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::BitAnd, false, 'in', 2, 4],
        'sum_expr: BIT_OR_SYM ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::BitOr, false, 'in', 2, 4],
        'sum_expr: JSON_ARRAYAGG ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::JsonArray, false, 'in', 2, 4],
        'sum_expr: ST_COLLECT_SYM ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::Collect, false, 'in', 2, 4],
        'sum_expr: ST_COLLECT_SYM ( DISTINCT in_sum_expr ) opt_windowing_clause' => [AggregateFunction::Collect, true, 'in', 3, 5],
        'sum_expr: BIT_XOR_SYM ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::BitXor, false, 'in', 2, 4],
        'sum_expr: COUNT_SYM ( opt_all * ) opt_windowing_clause' => [AggregateFunction::Count, false, 'star', 2, 5],
        'sum_expr: COUNT_SYM ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::Count, false, 'in', 2, 4],
        'sum_expr: COUNT_SYM ( DISTINCT expr_list ) opt_windowing_clause' => [AggregateFunction::Count, true, 'list', 3, 5],
        'sum_expr: MIN_SYM ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::Minimum, false, 'in', 2, 4],
        'sum_expr: MIN_SYM ( DISTINCT in_sum_expr ) opt_windowing_clause' => [AggregateFunction::Minimum, true, 'in', 3, 5],
        'sum_expr: MAX_SYM ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::Maximum, false, 'in', 2, 4],
        'sum_expr: MAX_SYM ( DISTINCT in_sum_expr ) opt_windowing_clause' => [AggregateFunction::Maximum, true, 'in', 3, 5],
        'sum_expr: STD_SYM ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::StandardDeviation, false, 'in', 2, 4],
        'sum_expr: VARIANCE_SYM ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::Variance, false, 'in', 2, 4],
        'sum_expr: STDDEV_SAMP_SYM ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::SampleStandardDeviation, false, 'in', 2, 4],
        'sum_expr: VAR_SAMP_SYM ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::SampleVariance, false, 'in', 2, 4],
        'sum_expr: SUM_SYM ( in_sum_expr ) opt_windowing_clause' => [AggregateFunction::Sum, false, 'in', 2, 4],
        'sum_expr: SUM_SYM ( DISTINCT in_sum_expr ) opt_windowing_clause' => [AggregateFunction::Sum, true, 'in', 3, 5],
    ];

    /**
     * The GROUP_CONCAT productions, by the position of their window.
     */
    private const CONCATENATIONS = [
        'sum_expr: GROUP_CONCAT_SYM ( opt_distinct expr_list opt_gorder_clause opt_gconcat_separator )' => null,
        'sum_expr: GROUP_CONCAT_SYM ( opt_distinct expr_list opt_gorder_clause opt_gconcat_separator ) opt_windowing_clause' => 7,
    ];

    /**
     * The JSON_OBJECTAGG productions, by the position of their window.
     */
    private const OBJECTS = [
        'sum_expr: JSON_OBJECTAGG ( in_sum_expr , in_sum_expr )' => null,
        'sum_expr: JSON_OBJECTAGG ( in_sum_expr , in_sum_expr ) opt_windowing_clause' => 6,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     * @param WindowRule $windows The window rules
     */
    public function __construct(private readonly Lowering $lowering, private readonly WindowRule $windows)
    {
    }

    /**
     * Lowers an aggregate: a node of sum_expr.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function aggregate(Node $call): Scalar
    {
        $form = $this->lowering->form($call);
        if (array_key_exists($form->signature, self::CONCATENATIONS)) {
            return $this->concatenation($form, self::CONCATENATIONS[$form->signature]);
        }
        if (array_key_exists($form->signature, self::OBJECTS)) {
            $window = self::OBJECTS[$form->signature];
            [$keyAll, $key] = $this->operand($form->node(2));
            [$valueAll, $value] = $this->operand($form->node(4));

            return new JsonObjectAggregate($key, $value, $keyAll, $valueAll, $window === null ? null : $this->windows->windowing($form->node($window)));
        }
        [$function, $distinct, $argument, $position, $window] = self::AGGREGATES[$form->signature] ?? throw ImplementationGap::production($form);
        [$all, $arguments] = match ($argument) {
            'in' => $this->single($form->node($position)),
            'list' => [false, $this->lowering->expressions->expressions($form->node($position))],
            'star' => [$this->lowering->options->present($form->node($position)), []],
        };

        return new Aggregate($function, $arguments, $distinct, $all, $window === null ? null : $this->windows->windowing($form->node($window)));
    }

    /**
     * Lowers GROUP_CONCAT.
     */
    public function concatenation(Form $form, ?int $window): GroupConcat
    {
        return new GroupConcat(
            $this->lowering->expressions->expressions($form->node(3)),
            $this->distinct($form->node(2)),
            $this->ordering($form->node(4)),
            $this->separator($form->node(5)),
            $window === null ? null : $this->windows->windowing($form->node($window)),
        );
    }

    /**
     * Lowers the one operand of an aggregate as an argument list.
     *
     * @return array{bool, list<Scalar>}
     */
    public function single(Node $operand): array
    {
        [$all, $expression] = $this->operand($operand);

        return [$all, [$expression]];
    }

    /**
     * Lowers an aggregated operand with its optional ALL: a node of in_sum_expr.
     *
     * @return array{bool, Scalar}
     * @throws ImplementationGap When the production has no rule
     */
    public function operand(Node $operand): array
    {
        $form = $this->lowering->form($operand);
        if ($form->signature !== 'in_sum_expr: opt_all expr') {
            throw ImplementationGap::production($form);
        }

        return [$this->lowering->options->present($form->node(0)), $this->lowering->expressions->expression($form->node(1))];
    }

    /**
     * Tells whether DISTINCT is written: a node of opt_distinct.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function distinct(Node $option): bool
    {
        $form = $this->lowering->form($option);

        return match ($form->signature) {
            'opt_distinct:' => false,
            'opt_distinct: DISTINCT' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the ordering of GROUP_CONCAT: a node of opt_gorder_clause.
     *
     * @return list<OrderItem>
     * @throws ImplementationGap When a production has no rule
     */
    public function ordering(Node $clause): array
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_gorder_clause:') {
            return [];
        }
        if ($form->signature !== 'opt_gorder_clause: ORDER_SYM BY gorder_list') {
            throw ImplementationGap::production($form);
        }
        $items = [];
        $pending = [$form->node(2)];
        while ($pending !== []) {
            $list = $this->lowering->form(array_pop($pending));
            $item = match ($list->signature) {
                'gorder_list: gorder_list , order_ident order_dir' => $this->lowering->queries->orderItem($list->node(2), $list->node(3)),
                'gorder_list: order_ident order_dir' => $this->lowering->queries->orderItem($list->node(0), $list->node(1)),
                'gorder_list: gorder_list , order_expr' => $this->lowering->queries->orderItem($list->node(2)),
                'gorder_list: order_expr' => $this->lowering->queries->orderItem($list->node(0)),
                default => throw ImplementationGap::production($list),
            };
            array_unshift($items, $item);
            $first = $list->node->children[0];
            if ($first instanceof Node && $first->name === 'gorder_list') {
                $pending[] = $first;
            }
        }

        return $items;
    }

    /**
     * Lowers the separator of GROUP_CONCAT: a node of opt_gconcat_separator.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function separator(Node $clause): ?Text
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_gconcat_separator:' => null,
            'opt_gconcat_separator: SEPARATOR_SYM text_string' => $this->lowering->literals->text($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
