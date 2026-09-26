<?php

declare(strict_types=1);

namespace SqlFormatter\Platform\PostgreSql;

use SqlFormatter\Core\Compact\Grouping;
use SqlFormatter\Core\Compact\Keywords;
use SqlFormatter\Core\Compact\Rules;
use SqlFormatter\Core\Compact\Settings;
use SqlFormatter\Core\Dialect as FormattingDialect;
use SqlFormatter\Core\Syntax\Rules as SyntaxRules;
use SqlParser\Lexer\Token;

/**
 * Declares PostgreSql grammar ownership and lexical formatting behavior.
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
            'clauses' => ['from_clause', 'where_clause', 'group_clause', 'having_clause', 'sort_clause', 'limit_clause', 'offset_clause', 'returning_clause', 'with_clause', 'window_clause', 'join_qual', 'into_clause', 'where_or_current_clause', 'values_clause', 'opt_partition_clause', 'opt_frame_clause'],
            'lists' => ['target_list', 'group_by_list', 'sortby_list', 'from_list', 'cte_list', 'set_clause_list', 'TableElementList', 'values_clause'],
            'statements' => ['simple_select', 'InsertStmt', 'UpdateStmt', 'DeleteStmt', 'insert_rest'],
            'joins' => ['joined_table', 'join_type'],
            'expressions' => ['a_expr', 'b_expr', 'case_expr'],
            'branches' => ['when_clause', 'case_default'],
            'genericLists' => ['opt_partition_clause'],
            'tables' => ['CreateStmt'],
            'directChildren' => ['join_type'],
            'logicalChildren' => [],
        ], ['SELECT', 'SELECT DISTINCT', 'SELECT ALL', 'SELECT DISTINCTROW', 'FROM', 'WHERE', 'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT', 'OFFSET', 'FETCH', 'RETURNING', 'WITH', 'WITH RECURSIVE', 'WINDOW', 'SET', 'VALUES', 'INSERT INTO', 'INSERT', 'UPDATE', 'DELETE FROM', 'DELETE', 'UNION', 'UNION ALL', 'UNION DISTINCT', 'INTERSECT', 'INTERSECT ALL', 'EXCEPT', 'EXCEPT ALL', 'ON', 'USING', 'JOIN', 'INNER JOIN', 'LEFT JOIN', 'LEFT OUTER JOIN', 'RIGHT JOIN', 'RIGHT OUTER JOIN', 'FULL JOIN', 'FULL OUTER JOIN', 'CROSS JOIN', 'NATURAL JOIN', 'NATURAL LEFT JOIN', 'NATURAL LEFT OUTER JOIN', 'STRAIGHT_JOIN', 'INTO', 'FOR UPDATE', 'FOR SHARE', 'PARTITION BY', 'ROWS', 'RANGE', 'GROUPS']);
    }

    /**
     * Composes canonicalization rules without depending on another platform.
     */
    public function compactRules(): Settings
    {
        return new Settings(
            new Keywords(['ColId', 'ColLabel', 'BareColLabel', 'type_function_name', 'NonReservedWord'], ['IDENT'], ['NOT_EQUALS' => '<>'], 'expr', ['NULL', 'INTEGER', 'FLOAT', 'BLOB', 'STRING', 'VARIABLE', 'CTIME_KW'], 'joinop'),
            new Rules(['opt_outer' => [['OUTER']], 'opt_all_clause' => [['ALL']], 'opt_asc_desc' => [['ASC']]], ['join_type'], ['set_quantifier' => 'simple_select'], null, null),
            new Grouping(['c_expr' => ['a_expr']]),
            ['target_el' => null, 'alias_clause' => null, 'opt_as' => 'opt_table_alias'],
            static fn (string $text, int $start, int $offset, int $depth, bool $executable): bool => true,
            static fn (Token $left, Token $right, ?Token $before): ?string => null,
        );
    }
}
