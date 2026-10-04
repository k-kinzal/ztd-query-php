<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Modern;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ClauseRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\FromRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ItemRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TableRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TailRule;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\ValueRow;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Statement\Query;

/**
 * Lowers the query primaries and the WITH clause of the 8.0 and later grammars.
 *
 * Rule: MYSQL-QUERY-PRIMARY-001. Scope: query_primary, query_specification
 * (8.0 and later), table_value_constructor, values_row_list,
 * row_value_explicit, explicit_table, with_clause, opt_with_clause,
 * with_list, common_table_expr. A query specification is a query block
 * whose INTO clause, when written, follows the select list. Constructs: the
 * query block, ValuesQuery, ValueRow, ExplicitTable, With,
 * CommonTableExpression. Terminates: lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/values.html,
 * https://dev.mysql.com/doc/refman/8.4/en/table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/with.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class PrimaryRule
{
    /**
     * The query specification productions by whether they write INTO after the select list and QUALIFY.
     */
    private const SPECIFICATIONS = [
        'query_specification: SELECT_SYM select_options select_item_list into_clause opt_from_clause opt_where_clause opt_group_clause opt_having_clause opt_window_clause' => [true, false],
        'query_specification: SELECT_SYM select_options select_item_list opt_from_clause opt_where_clause opt_group_clause opt_having_clause opt_window_clause' => [false, false],
        'query_specification: SELECT_SYM select_options select_item_list into_clause opt_from_clause opt_where_clause opt_group_clause opt_having_clause opt_window_clause opt_qualify_clause' => [true, true],
        'query_specification: SELECT_SYM select_options select_item_list opt_from_clause opt_where_clause opt_group_clause opt_having_clause opt_window_clause opt_qualify_clause' => [false, true],
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a query primary: a query block, a VALUES statement or a TABLE statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function primary(Node $primary): Block|Query
    {
        $form = $this->lowering->form($primary);

        return match ($form->signature) {
            'query_primary: query_specification' => $this->specification($form->node(0)),
            'query_primary: table_value_constructor' => $this->values($form->node(0)),
            'query_primary: explicit_table' => $this->explicit($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a query specification into an open query block.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function specification(Node $specification): Block
    {
        $form = $this->lowering->form($specification);
        [$into, $qualify] = self::SPECIFICATIONS[$form->signature] ?? throw ImplementationGap::production($form);
        $at = $into ? 4 : 3;
        $clauses = new ClauseRule($this->lowering);
        $items = new ItemRule($this->lowering);

        return new Block(
            $items->options($form->node(1)),
            $items->items($form->node(2)),
            $into ? (new TailRule($this->lowering))->into($form->node(3)) : null,
            (new FromRule($this->lowering))->from($form->node($at)),
            $clauses->predicate($form->node($at + 1)),
            $clauses->grouping($form->node($at + 2)),
            $clauses->predicate($form->node($at + 3)),
            $clauses->windows($form->node($at + 4)),
            $qualify ? $clauses->predicate($form->node($at + 5)) : null,
        );
    }

    /**
     * Lowers a VALUES statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function values(Node $constructor): ValuesQuery
    {
        $form = $this->lowering->form($constructor);
        if ($form->signature !== 'table_value_constructor: VALUES values_row_list') {
            throw ImplementationGap::production($form);
        }
        $list = $this->lowering->form($form->node(1));
        if ($list->signature !== 'values_row_list: values_row_list , row_value_explicit' && $list->signature !== 'values_row_list: row_value_explicit') {
            throw ImplementationGap::production($list);
        }
        $rows = [];
        foreach ((new Lists())->items($list->node) as $item) {
            $row = $this->lowering->form($item);
            if ($row->signature !== 'row_value_explicit: ROW_SYM ( opt_values )') {
                throw ImplementationGap::production($row);
            }
            $rows[] = new ValueRow($this->lowering->dml->rowValues($row->node(2)));
        }

        return new ValuesQuery($rows);
    }

    /**
     * Lowers a TABLE statement.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function explicit(Node $table): ExplicitTable
    {
        $form = $this->lowering->form($table);
        if ($form->signature !== 'explicit_table: TABLE_SYM table_ident') {
            throw ImplementationGap::production($form);
        }

        return new ExplicitTable($this->lowering->names->qualified($form->node(1)));
    }

    /**
     * Lowers a WITH clause; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function with(Node $clause): ?With
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_with_clause:') {
            return null;
        }
        if ($form->signature === 'opt_with_clause: with_clause') {
            return $this->with($form->node(0));
        }
        $recursive = match ($form->signature) {
            'with_clause: WITH with_list' => false,
            'with_clause: WITH RECURSIVE_SYM with_list' => true,
            default => throw ImplementationGap::production($form),
        };
        $list = $this->lowering->form($form->node($recursive ? 2 : 1));
        if ($list->signature !== 'with_list: with_list , common_table_expr' && $list->signature !== 'with_list: common_table_expr') {
            throw ImplementationGap::production($list);
        }
        $tables = [];
        foreach ((new Lists())->items($list->node) as $item) {
            $table = $this->lowering->form($item);
            if ($table->signature !== 'common_table_expr: ident opt_derived_column_list AS table_subquery') {
                throw ImplementationGap::production($table);
            }
            $tables[] = new CommonTableExpression($this->lowering->names->identifier($table->node(0)), (new TableRule($this->lowering))->columns($table->node(1)), (new ExpressionRule($this->lowering))->subquery($table->node(3)));
        }

        return new With($recursive, $tables);
    }
}
