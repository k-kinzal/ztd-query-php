<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise and synonym token positions of the productions of data manipulation statements.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise, and only terminals the manual defines as synonyms may share a
 * key. Every entry is listed in the method documentation with its reason and
 * the manual page that states it. Nothing else is skipped or merged by the
 * token correspondence check.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DmlNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `opt_INTO: INTO` and `insert2: INTO insert_table`, position 0: INTO is
     *   optional (https://dev.mysql.com/doc/refman/8.4/en/insert.html:
     *   "INSERT ... [INTO] tbl_name"; https://dev.mysql.com/doc/refman/8.4/en/replace.html).
     * - `opt_from_keyword: FROM`: `LOAD DATA [FROM] [LOCAL] INFILE`, the word
     *   is optional (https://dev.mysql.com/doc/refman/8.4/en/load-data.html;
     *   the grammar action of sql/sql_yacc.yy 8.4 stores nothing).
     * - `opt_field_or_var_spec: ( )`: an empty column list of LOAD DATA is no
     *   list; the grammar action yields the same null value as an absent
     *   list, and the server then loads every column
     *   (https://dev.mysql.com/doc/refman/8.4/en/load-data.html, sql/sql_yacc.yy 8.4).
     * - `opt_sp_cparam_list: ( opt_sp_cparams )` and `opt_paren_expr_list: (
     *   opt_expr_list )`, positions 0 and 2: the parentheses of CALL;
     *   without arguments they may be omitted
     *   (https://dev.mysql.com/doc/refman/8.4/en/call.html: "CALL p() and
     *   CALL p are equivalent"). The writer adds them exactly when there are
     *   arguments.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'opt_INTO: INTO' => [0],
            'insert2: INTO insert_table' => [0],
            'opt_from_keyword: FROM' => [0],
            'opt_field_or_var_spec: ( )' => [0, 1],
            'opt_sp_cparam_list: ( opt_sp_cparams )' => [0, 2],
            'opt_paren_expr_list: ( opt_expr_list )' => [0, 2],
        ];
    }

    /**
     * Answers the key of each synonym position by production signature.
     *
     * - `insert_values: VALUE_SYM values_list` and `value_or_values: VALUE_SYM`:
     *   VALUE is a synonym for VALUES
     *   (https://dev.mysql.com/doc/refman/8.4/en/insert.html: "VALUE is a synonym for VALUES in this context").
     * - `deallocate_or_drop: DROP`: `{DEALLOCATE | DROP} PREPARE stmt_name`
     *   (https://dev.mysql.com/doc/refman/8.4/en/deallocate-prepare.html).
     * - `lines_or_rows: ROWS_SYM`: `IGNORE number {LINES | ROWS}` skips that
     *   many lines or rows at the start of the file; both set the same count
     *   (https://dev.mysql.com/doc/refman/8.4/en/load-data.html,
     *   https://dev.mysql.com/doc/refman/8.4/en/load-xml.html).
     *
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return [
            'insert_values: VALUE_SYM values_list' => [0 => 'VALUES'],
            'value_or_values: VALUE_SYM' => [0 => 'VALUES'],
            'deallocate_or_drop: DROP' => [0 => 'DEALLOCATE_SYM'],
            'lines_or_rows: ROWS_SYM' => [0 => 'LINES'],
        ];
    }
}
