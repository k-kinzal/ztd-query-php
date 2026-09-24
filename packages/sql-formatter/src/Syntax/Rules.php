<?php

declare(strict_types=1);

namespace SqlFormatter\Syntax;

/**
 * Upstream grammar rules that own clause headers and vertically laid out lists.
 *
 * @visibility SqlFormatter
 */
final class Rules
{
    /**
     * Rules whose leading terminals identify a clause.
     */
    public const CLAUSES = [
        'from_clause', 'from', 'where_clause', 'where_opt', 'opt_where_clause',
        'group_clause', 'opt_group_clause', 'groupby_opt', 'having_clause',
        'opt_having_clause', 'having_opt', 'order_clause', 'sort_clause',
        'orderby_opt', 'limit_clause', 'limit_opt', 'offset_clause',
        'returning_clause', 'returning', 'with_clause', 'window_clause',
        'opt_window_clause', 'join_qual', 'on_using', 'opt_on',
        'opt_order_clause', 'opt_limit_clause', 'opt_into', 'into_clause',
        'where_or_current_clause', 'where_opt_ret', 'insert_values',
        'values_clause', 'values', 'mvalues', 'windowdefn', 'window_spec',
        'opt_partition_clause', 'opt_window_order_by_clause', 'opt_window_frame_clause',
        'opt_frame_clause', 'frame_opt', 'window', 'multiselect_op',
    ];

    /**
     * Rules whose direct commas separate independently displayed items.
     */
    public const LISTS = [
        'select_item_list', 'select_expr_list', 'select_list', 'target_list',
        'selcollist', 'sclp', 'group_list', 'group_by_list', 'order_list',
        'sortby_list', 'sortlist', 'from_list', 'table_reference_list',
        'with_list', 'cte_list', 'wqlist', 'update_list', 'update_elem_list',
        'set_clause_list', 'setlist', 'field_list', 'table_element_list',
        'TableElementList', 'columnlist', 'conslist', 'values_list',
        'values_clause', 'row_value_list', 'expr_list_with_rows', 'mvalues',
    ];

    /**
     * Rules whose direct terminals introduce statement clauses.
     */
    public const STATEMENTS = [
        'query_specification', 'simple_select', 'oneselect', 'select_init',
        'select_init2', 'select_part2', 'select_options_and_item_list',
        'select', 'selectnowith', 'query_expression_body', 'query_expression',
        'insert', 'insert_stmt', 'InsertStmt', 'insert_query_expression',
        'update', 'update_stmt', 'UpdateStmt', 'delete', 'delete_stmt',
        'DeleteStmt', 'cmd', 'select_from', 'select_into', 'insert_rest',
    ];

    /**
     * Recognized keyword sequences, retaining their original spelling.
     */
    public const HEADERS = [
        'SELECT', 'SELECT DISTINCT', 'SELECT ALL', 'SELECT DISTINCTROW', 'FROM', 'WHERE', 'GROUP BY', 'ORDER BY', 'HAVING',
        'LIMIT', 'OFFSET', 'FETCH', 'RETURNING', 'WITH', 'WITH RECURSIVE',
        'WINDOW', 'SET', 'VALUES', 'INSERT INTO', 'INSERT', 'UPDATE',
        'DELETE FROM', 'DELETE', 'UNION', 'UNION ALL', 'UNION DISTINCT',
        'INTERSECT', 'INTERSECT ALL', 'EXCEPT', 'EXCEPT ALL', 'ON', 'USING',
        'JOIN', 'INNER JOIN', 'LEFT JOIN', 'LEFT OUTER JOIN', 'RIGHT JOIN',
        'RIGHT OUTER JOIN', 'FULL JOIN', 'FULL OUTER JOIN', 'CROSS JOIN',
        'NATURAL JOIN', 'NATURAL LEFT JOIN', 'NATURAL LEFT OUTER JOIN',
        'STRAIGHT_JOIN', 'INTO', 'FOR UPDATE', 'FOR SHARE', 'PARTITION BY',
        'ROWS', 'RANGE', 'GROUPS',
    ];

    /**
     * Reports whether a grammar rule owns a join keyword.
     */
    public static function isJoin(string $name): bool
    {
        return in_array($name, ['joined_table', 'inner_join_type', 'outer_join_type', 'joinop', 'join_type', 'join_table'], true);
    }
}
