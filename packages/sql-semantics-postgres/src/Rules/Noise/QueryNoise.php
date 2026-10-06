<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Noise;

/**
 * The token positions of the query family that carry no meaning.
 *
 * A position is listed only when the token there has no effect on what the
 * statement requests in that production, and each entry states the reason and
 * cites the manual. A significant token never belongs here: when rendering
 * cannot reproduce it, the model lacks a distinction.
 *
 * @visibility SqlSemantics
 */
final class QueryNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            // "The AS keyword is optional" before an output name. https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST
            'target_el: a_expr AS ColLabel' => [1],
            // [ AS ] before a table alias is optional. https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM
            'alias_clause: AS ColId' => [0],
            'alias_clause: AS ColId ( name_list )' => [0],
            'func_alias_clause: AS ColId ( TableFuncElementList )' => [0],
            // "INNER and OUTER are optional in all forms." https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM
            'join_type: INNER_P' => [0],
            'opt_outer: OUTER_P' => [0],
            // "ROW and ROWS as well as FIRST and NEXT are noise words that don't influence the effects of these clauses." https://www.postgresql.org/docs/17/sql-select.html#SQL-LIMIT
            'row_or_rows: ROW' => [0],
            'row_or_rows: ROWS' => [0],
            'first_or_next: FIRST_P' => [0],
            'first_or_next: NEXT' => [0],
            // The server drops a plus sign before the constant count. https://www.postgresql.org/docs/17/sql-select.html#SQL-LIMIT
            'select_fetch_first_value: + I_or_F_const' => [0],
            // "ALL specifies the opposite: all rows are kept; that is the default." https://www.postgresql.org/docs/17/sql-select.html#SQL-DISTINCT
            'opt_all_clause: ALL' => [0],
            // INTO [ TEMPORARY | TEMP | UNLOGGED ] [ TABLE ] new_table: TABLE is optional. https://www.postgresql.org/docs/17/sql-selectinto.html
            'OptTempTableName: TABLE qualified_name' => [0],
            // "Optionally, * can be specified after the table name to explicitly indicate that descendant tables are included." https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM
            'extended_relation_expr: qualified_name *' => [1],
            // ONLY ( name ) is the same request as ONLY name; the parentheses group nothing. https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM
            'extended_relation_expr: ONLY ( qualified_name )' => [1, 3],
            // NESTED [ PATH ] path_expression: PATH is optional. https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-TABLE
            'path_opt: PATH' => [0],
        ];
    }
}
