<?php

declare(strict_types=1);

namespace SqlFormatter\Platform\MySql;

use SqlFormatter\Core\Compact\Grouping;
use SqlFormatter\Core\Compact\Keywords;
use SqlFormatter\Core\Compact\Rules;
use SqlFormatter\Core\Compact\Settings;
use SqlFormatter\Core\Dialect as FormattingDialect;
use SqlFormatter\Core\Syntax\Rules as SyntaxRules;
use SqlParser\Lexer\Token;

/**
 * Declares MySql grammar ownership and lexical formatting behavior.
 *
 * @visibility SqlFormatter
 */
final class Dialect implements FormattingDialect
{
    /**
     * Describes layout roles across the supported grammar releases.
     */
    public function syntaxRules(): SyntaxRules
    {
        return new SyntaxRules([
            'clauses' => ['from_clause', 'where_clause', 'opt_where_clause', 'group_clause', 'opt_group_clause', 'having_clause', 'opt_having_clause', 'order_clause', 'limit_clause', 'with_clause', 'opt_window_clause', 'opt_order_clause', 'opt_limit_clause', 'opt_into', 'into_clause', 'insert_values', 'values', 'window_spec', 'opt_partition_clause', 'opt_window_order_by_clause', 'opt_window_frame_clause'],
            'lists' => ['select_item_list', 'group_list', 'order_list', 'table_reference_list', 'with_list', 'update_list', 'field_list', 'table_element_list', 'values_list'],
            'statements' => ['query_specification', 'select_init', 'select_init2', 'select_part2', 'select_options_and_item_list', 'select', 'query_expression_body', 'query_expression', 'insert', 'insert_stmt', 'insert_query_expression', 'update', 'update_stmt', 'delete', 'delete_stmt', 'select_from', 'select_into'],
            'joins' => ['joined_table', 'inner_join_type', 'outer_join_type', 'join_table'],
            'expressions' => ['expr', 'simple_expr', 'signed_literal'],
            'branches' => ['when_list', 'opt_else'],
            'genericLists' => ['opt_partition_clause'],
            'tables' => ['create_table_stmt'],
            'directChildren' => [],
            'logicalChildren' => ['and', 'or'],
        ], ['SELECT', 'SELECT DISTINCT', 'SELECT ALL', 'SELECT DISTINCTROW', 'FROM', 'WHERE', 'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT', 'OFFSET', 'FETCH', 'RETURNING', 'WITH', 'WITH RECURSIVE', 'WINDOW', 'SET', 'VALUES', 'INSERT INTO', 'INSERT', 'UPDATE', 'DELETE FROM', 'DELETE', 'UNION', 'UNION ALL', 'UNION DISTINCT', 'INTERSECT', 'INTERSECT ALL', 'EXCEPT', 'EXCEPT ALL', 'ON', 'USING', 'JOIN', 'INNER JOIN', 'LEFT JOIN', 'LEFT OUTER JOIN', 'RIGHT JOIN', 'RIGHT OUTER JOIN', 'FULL JOIN', 'FULL OUTER JOIN', 'CROSS JOIN', 'NATURAL JOIN', 'NATURAL LEFT JOIN', 'NATURAL LEFT OUTER JOIN', 'STRAIGHT_JOIN', 'INTO', 'FOR UPDATE', 'FOR SHARE', 'PARTITION BY', 'ROWS', 'RANGE', 'GROUPS']);
    }

    /**
     * Composes canonicalization rules without depending on another platform.
     */
    public function compactRules(): Settings
    {
        return new Settings(
            new Keywords(['ident', 'IDENT_sys', 'ident_or_text', 'label_ident', 'role_ident'], ['IDENT', 'IDENT_QUOTED', 'UNDERSCORE_CHARSET'], ['NE' => '<>', 'EQ' => '=', 'WITH_ROLLUP_SYM' => 'WITH ROLLUP', 'WITH_CUBE_SYM' => 'WITH CUBE', 'DISTINCT' => 'DISTINCT', 'REGEXP' => 'RLIKE', 'INT_SYM' => 'INT', 'DECIMAL_SYM' => 'DEC'], null, ['NULL', 'INTEGER', 'FLOAT', 'BLOB', 'STRING', 'VARIABLE', 'CTIME_KW'], 'joinop'),
            new Rules(['opt_outer' => [['OUTER']], 'order_dir' => [['ASC']], 'ordering_direction' => [['ASC']], 'opt_ordering_direction' => [['ASC']], 'union_option' => [['DISTINCT']]], ['normal_join', 'inner_join_type', 'outer_join_type', 'natural_join_type'], [], 'select_options', null),
            new Grouping(['expr' => ['expr'], 'simple_expr' => ['expr']]),
            ['select_alias' => null, 'opt_as' => 'opt_table_alias', 'table_alias' => 'opt_table_alias'],
            static fn (string $text, int $start, int $offset, int $depth, bool $executable): bool => !$executable && $depth === 1 && substr($text, $start + 2, 1) !== '!' && substr($text, $offset + 2, 1) === '!',
            static fn (Token $left, Token $right, ?Token $before): ?string => ($left->text === '@' || ($before?->text === '@' && $right->text === '.')) ? '' : null,
        );
    }
}
