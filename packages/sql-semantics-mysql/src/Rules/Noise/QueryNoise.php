<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise and synonym token positions of the productions of queries and table references.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise, and only terminals the manual defines as synonyms may share a
 * key. Every entry is listed in the method documentation with its reason and
 * the manual page that states it. Nothing else is skipped or merged by the
 * token correspondence check.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class QueryNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `select_alias: AS ident`, `select_alias: AS TEXT_STRING_sys`,
     *   `select_alias: AS TEXT_STRING_validated`, position 0: the AS keyword
     *   before a column alias is optional
     *   (https://dev.mysql.com/doc/refman/8.4/en/select.html: "select_expr [[AS] alias]").
     * - `table_alias: AS`: the AS keyword before a table alias is optional
     *   (https://dev.mysql.com/doc/refman/5.7/en/join.html: "tbl_name [[AS] alias]").
     * - `table_alias: EQ`: the 5.x grammars accept `=` in place of the
     *   optional AS before a table alias; the server reads both as the
     *   alias marker (sql/sql_yacc.yy 5.7, rule table_alias: AS | '=') and
     *   the alias means the same (https://dev.mysql.com/doc/refman/5.7/en/join.html).
     * - `opt_outer: OUTER`, `opt_outer: OUTER_SYM`: OUTER after LEFT or
     *   RIGHT is optional (https://dev.mysql.com/doc/refman/8.4/en/join.html:
     *   "LEFT [OUTER] JOIN").
     * - `opt_inner: INNER_SYM`: INNER after NATURAL is optional
     *   (https://dev.mysql.com/doc/refman/8.4/en/join.html: "NATURAL [INNER
     *   | {LEFT|RIGHT} [OUTER]] JOIN").
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'select_alias: AS ident' => [0],
            'select_alias: AS TEXT_STRING_sys' => [0],
            'select_alias: AS TEXT_STRING_validated' => [0],
            'table_alias: AS' => [0],
            'table_alias: EQ' => [0],
            'opt_outer: OUTER' => [0],
            'opt_outer: OUTER_SYM' => [0],
            'opt_inner: INNER_SYM' => [0],
        ];
    }

    /**
     * Answers the key of each synonym position by production signature.
     *
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return [];
    }
}
