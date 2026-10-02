<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the thin query slice: SELECT of expressions from one table with a WHERE predicate, in both grammar generations.
 *
 * Slice of the query family, written with the leaf layers so that the
 * pipeline runs end to end in MySQL 5.7 and in 8.0 and later; the query
 * family completes or replaces it.
 *
 * Rule: MYSQL-SELECT-SLICE-001. Scope: the alternatives of select_stmt,
 * query_expression, query_expression_body, query_primary,
 * query_specification, opt_from_clause, from_clause, from_tables,
 * table_reference_list, table_reference, table_factor, single_table (8.0
 * and later) and of select, select_init, select_part2,
 * select_options_and_item_list, join_table_list, derived_table_list,
 * esc_table_ref, table_ref (5.7) that a plain selection takes, with every
 * other clause absent; select_item_list, select_item, select_alias,
 * opt_table_alias, table_alias, where_clause, opt_where_clause. Select items keep their
 * written order. Constructs: Select, SelectExpression, TableReference.
 * Terminates: the select list is flattened iteratively; every other child
 * is a strict subtree. Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SelectSlice
{
    /**
     * The unit productions from a query statement of 8.0 and later down to its query specification.
     */
    private const MODERN_PATH = ['query_expression_body: query_primary', 'query_primary: query_specification'];

    /**
     * The unit productions from a FROM clause of 8.0 and later down to its single table.
     */
    private const MODERN_TABLE = ['from_tables: table_reference_list', 'table_reference_list: table_reference', 'table_reference: table_factor', 'table_factor: single_table'];

    /**
     * The unit productions from a FROM clause of 5.7 down to its table factor.
     */
    private const LEGACY_TABLE = ['table_reference_list: join_table_list', 'join_table_list: derived_table_list', 'derived_table_list: esc_table_ref', 'esc_table_ref: table_ref', 'table_ref: table_factor'];

    /**
     * The clauses a plain selection leaves absent, by the production that writes nothing.
     */
    private const ABSENT = [
        'select_options:' => true, 'opt_group_clause:' => true, 'opt_having_clause:' => true, 'opt_window_clause:' => true, 'opt_qualify_clause:' => true,
        'opt_order_clause:' => true, 'opt_limit_clause:' => true, 'opt_into:' => true, 'opt_procedure_analyse_clause:' => true,
        'opt_select_lock_type:' => true, 'opt_union_clause:' => true, 'opt_use_partition:' => true, 'opt_index_hints_list:' => true,
        'opt_tablesample_clause:' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a query statement of the slice.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Node $statement): Select
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature === 'select: select_init') {
            return $this->legacy($this->lowering->productions->form($form->node(0)));
        }
        if ($form->signature !== 'select_stmt: query_expression') {
            throw ImplementationGap::production($form);
        }
        $expression = $this->lowering->productions->form($form->node(0));
        if ($expression->signature !== 'query_expression: query_expression_body opt_order_clause opt_limit_clause') {
            throw ImplementationGap::production($expression);
        }
        $this->absent($expression, [1, 2]);
        $specification = $this->lowering->productions->form($this->descend($expression->node(0), self::MODERN_PATH));
        if (!in_array($specification->signature, [
            'query_specification: SELECT_SYM select_options select_item_list opt_from_clause opt_where_clause opt_group_clause opt_having_clause opt_window_clause',
            'query_specification: SELECT_SYM select_options select_item_list opt_from_clause opt_where_clause opt_group_clause opt_having_clause opt_window_clause opt_qualify_clause',
        ], true)) {
            throw ImplementationGap::production($specification);
        }
        $this->absent($specification, array_slice([1, 5, 6, 7, 8], 0, count($specification->node->children) - 4));

        return new Select($this->items($specification->node(2)), $this->from($specification->node(3)), $this->where($specification->node(4)));
    }

    /**
     * Lowers a query statement of MySQL 5.7.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function legacy(Form $form): Select
    {
        if ($form->signature !== 'select_init: SELECT_SYM select_part2 opt_union_clause') {
            throw ImplementationGap::production($form);
        }
        $this->absent($form, [2]);
        $body = $this->lowering->productions->form($form->node(1));
        if ($body->signature === 'select_part2: select_options_and_item_list opt_order_clause opt_limit_clause opt_select_lock_type') {
            $this->absent($body, [1, 2, 3]);

            return new Select($this->legacyItems($body->node(0)));
        }
        if ($body->signature !== 'select_part2: select_options_and_item_list opt_into from_clause opt_where_clause opt_group_clause opt_having_clause opt_order_clause opt_limit_clause opt_procedure_analyse_clause opt_into opt_select_lock_type') {
            throw ImplementationGap::production($body);
        }
        $this->absent($body, [1, 4, 5, 6, 7, 8, 9, 10]);

        return new Select($this->legacyItems($body->node(0)), $this->from($body->node(2)), $this->where($body->node(3)));
    }

    /**
     * Lowers the select options and select list of MySQL 5.7.
     *
     * @return list<SelectExpression>
     * @throws ImplementationGap When a production has no rule
     */
    public function legacyItems(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature !== 'select_options_and_item_list: select_options select_item_list') {
            throw ImplementationGap::production($form);
        }
        $this->absent($form, [0]);

        return $this->items($form->node(1));
    }

    /**
     * Lowers the select list in written order.
     *
     * @return list<SelectExpression>
     * @throws ImplementationGap When a production has no rule
     */
    public function items(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature !== 'select_item_list: select_item_list , select_item' && $form->signature !== 'select_item_list: select_item') {
            throw ImplementationGap::production($form);
        }
        $items = [];
        foreach ((new Lists())->items($list) as $item) {
            $itemForm = $this->lowering->productions->form($item);
            if ($itemForm->signature !== 'select_item: expr select_alias') {
                throw ImplementationGap::production($itemForm);
            }
            $items[] = new SelectExpression($this->lowering->expressions->expression($itemForm->node(0)), $this->alias($itemForm->node(1)));
        }

        return $items;
    }

    /**
     * Lowers the optional alias of a select item or of a table reference.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alias(Node $alias): ?Name
    {
        $form = $this->lowering->productions->form($alias);
        if ($form->signature === 'opt_table_alias: table_alias ident') {
            $keyword = $this->lowering->productions->form($form->node(0));
            if ($keyword->signature !== 'table_alias:' && $keyword->signature !== 'table_alias: AS') {
                throw ImplementationGap::production($keyword);
            }

            return $this->lowering->names->identifier($form->node(1));
        }

        return match ($form->signature) {
            'select_alias:', 'opt_table_alias:' => null,
            'select_alias: AS ident', 'select_alias: AS TEXT_STRING_sys', 'select_alias: AS TEXT_STRING_validated', 'opt_table_alias: opt_as ident' => $this->lowering->names->identifier($form->node(1)),
            'select_alias: ident', 'select_alias: TEXT_STRING_sys', 'select_alias: TEXT_STRING_validated' => $this->lowering->names->identifier($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the optional FROM clause with one table reference.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function from(Node $clause): ?TableReference
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'opt_from_clause:') {
            return null;
        }
        if ($form->signature === 'opt_from_clause: from_clause') {
            $form = $this->lowering->productions->form($form->node(0));
        }
        $table = match ($form->signature) {
            'from_clause: FROM from_tables' => $this->lowering->productions->form($this->descend($form->node(1), self::MODERN_TABLE)),
            'from_clause: FROM table_reference_list' => $this->lowering->productions->form($this->descend($form->node(1), self::LEGACY_TABLE)),
            default => throw ImplementationGap::production($form),
        };
        if (!in_array($table->signature, [
            'single_table: table_ident opt_use_partition opt_table_alias opt_key_definition',
            'single_table: table_ident opt_use_partition opt_table_alias opt_key_definition opt_tablesample_clause',
            'table_factor: table_ident opt_use_partition opt_table_alias opt_key_definition',
        ], true)) {
            throw ImplementationGap::production($table);
        }
        $hints = $this->lowering->productions->form($table->node(3));
        if ($hints->signature !== 'opt_key_definition: opt_index_hints_list') {
            throw ImplementationGap::production($hints);
        }
        $this->absent($hints, [0]);
        $this->absent($table, count($table->node->children) === 5 ? [1, 4] : [1]);

        return new TableReference($this->lowering->names->qualified($table->node(0)), $this->alias($table->node(2)));
    }

    /**
     * Lowers the optional WHERE clause.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function where(Node $clause): ?Scalar
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'opt_where_clause: where_clause') {
            $form = $this->lowering->productions->form($form->node(0));
        }

        return match ($form->signature) {
            'opt_where_clause:', 'where_clause:' => null,
            'opt_where_clause: WHERE expr', 'where_clause: WHERE expr' => $this->lowering->expressions->expression($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Follows a fixed path of unit productions and answers the node at its end.
     *
     * @param list<string> $path The unit productions in order
     * @throws ImplementationGap When a production is not the expected one
     */
    public function descend(Node $node, array $path): Node
    {
        foreach ($path as $signature) {
            $form = $this->lowering->productions->form($node);
            if ($form->signature !== $signature) {
                throw ImplementationGap::production($form);
            }
            $node = $form->node(0);
        }

        return $node;
    }

    /**
     * Requires the clauses at the given positions to be absent, because the slice has no structure for them.
     *
     * @param list<int> $positions The positions of optional clauses
     * @throws ImplementationGap When a clause is written
     */
    public function absent(Form $form, array $positions): void
    {
        foreach ($positions as $position) {
            $clause = $this->lowering->productions->form($form->node($position));
            if (!isset(self::ABSENT[$clause->signature])) {
                throw ImplementationGap::production($clause);
            }
        }
    }
}
