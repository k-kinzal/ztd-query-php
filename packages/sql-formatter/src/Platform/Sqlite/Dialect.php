<?php

declare(strict_types=1);

namespace SqlFormatter\Platform\Sqlite;

use SqlFormatter\Core\Compact\Grouping;
use SqlFormatter\Core\Compact\Keywords;
use SqlFormatter\Core\Compact\Rules;
use SqlFormatter\Core\Compact\Settings;
use SqlFormatter\Core\Dialect as FormattingDialect;
use SqlFormatter\Core\Syntax\Rules as SyntaxRules;
use SqlParser\Lexer\Token;

/**
 * Declares Sqlite grammar ownership and lexical formatting behavior.
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
            'clauses' => ['from', 'where_opt', 'groupby_opt', 'having_opt', 'orderby_opt', 'limit_opt', 'returning', 'window_clause', 'on_using', 'where_opt_ret', 'values', 'mvalues', 'windowdefn', 'frame_opt', 'window', 'multiselect_op'],
            'lists' => ['selcollist', 'sclp', 'sortlist', 'wqlist', 'setlist', 'columnlist', 'conslist', 'mvalues'],
            'statements' => ['oneselect', 'select', 'selectnowith', 'cmd'],
            'joins' => ['joinop'],
            'expressions' => ['expr', 'ccons'],
            'branches' => ['when_clause', 'case_exprlist', 'case_else'],
            'genericLists' => ['groupby_opt', 'window'],
            'tables' => ['create_table_args'],
            'directChildren' => ['insert_cmd', 'between_op'],
            'logicalChildren' => [],
        ], ['SELECT', 'SELECT DISTINCT', 'SELECT ALL', 'SELECT DISTINCTROW', 'FROM', 'WHERE', 'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT', 'OFFSET', 'FETCH', 'RETURNING', 'WITH', 'WITH RECURSIVE', 'WINDOW', 'SET', 'VALUES', 'INSERT INTO', 'INSERT', 'UPDATE', 'DELETE FROM', 'DELETE', 'UNION', 'UNION ALL', 'UNION DISTINCT', 'INTERSECT', 'INTERSECT ALL', 'EXCEPT', 'EXCEPT ALL', 'ON', 'USING', 'JOIN', 'INNER JOIN', 'LEFT JOIN', 'LEFT OUTER JOIN', 'RIGHT JOIN', 'RIGHT OUTER JOIN', 'FULL JOIN', 'FULL OUTER JOIN', 'CROSS JOIN', 'NATURAL JOIN', 'NATURAL LEFT JOIN', 'NATURAL LEFT OUTER JOIN', 'STRAIGHT_JOIN', 'INTO', 'FOR UPDATE', 'FOR SHARE', 'PARTITION BY', 'ROWS', 'RANGE', 'GROUPS']);
    }

    /**
     * Composes canonicalization rules without depending on another platform.
     */
    public function compactRules(): Settings
    {
        return new Settings(
            new Keywords(['nm', 'idj'], ['ID'], ['NE' => '<>', 'EQ' => '='], 'expr', ['NULL', 'INTEGER', 'FLOAT', 'BLOB', 'STRING', 'VARIABLE', 'CTIME_KW'], 'joinop'),
            new Rules(['distinct' => [['ALL']], 'sortorder' => [['ASC']]], ['joinop'], [], null, 'joinop'),
            new Grouping(['expr' => ['expr']]),
            ['as' => null],
            static fn (string $text, int $start, int $offset, int $depth, bool $executable): bool => false,
            static fn (Token $left, Token $right, ?Token $before): ?string => null,
        );
    }
}
