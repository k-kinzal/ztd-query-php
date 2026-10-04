<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise and synonym token positions of the productions of expressions and operators.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise, and only terminals the manual defines as synonyms may share a
 * key. Every entry is listed in the method documentation with its reason and
 * the manual page that states it. Nothing else is skipped or merged by the
 * token correspondence check.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ExpressionNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `simple_expr: ROW_SYM ( expr , expr_list )` position 0: "The
     *   expressions (1,2) and ROW(1,2) are sometimes called row constructors.
     *   The two are equivalent" (https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html).
     * - `ident_list_arg: ( ident_list )` positions 0 and 2: the column list of
     *   MATCH is the same with or without parentheses; the server builds the
     *   same `Item_func_match` from both alternatives
     *   (https://dev.mysql.com/doc/refman/8.4/en/fulltext-search.html#function_match).
     * - `opt_natural_language_mode: IN_SYM NATURAL LANGUAGE_SYM MODE_SYM`
     *   positions 0 to 3: a natural language search is performed "if the IN
     *   NATURAL LANGUAGE MODE modifier is given or if no modifier is given" (https://dev.mysql.com/doc/refman/8.4/en/fulltext-natural-language.html).
     * - `opt_of: OF_SYM` position 0: the grammar makes OF optional in
     *   `MEMBER [OF] (json_array)`; both spellings build `Item_func_member_of`
     *   (https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html#operator_member-of).
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'simple_expr: ROW_SYM ( expr , expr_list )' => [0],
            'ident_list_arg: ( ident_list )' => [0, 2],
            'opt_natural_language_mode: IN_SYM NATURAL LANGUAGE_SYM MODE_SYM' => [0, 1, 2, 3],
            'opt_of: OF_SYM' => [0],
        ];
    }

    /**
     * Answers the key of each synonym position by production signature.
     *
     * - `and: AND_AND_SYM`: `&&` is a synonym of AND, `or: OR2_SYM`: `||` is a
     *   synonym of OR when PIPES_AS_CONCAT is off; under that mode the lexer
     *   reads `||` as the concatenation terminal instead, which this entry
     *   does not touch (https://dev.mysql.com/doc/refman/8.4/en/logical-operators.html).
     * - `not2: NOT2_SYM`: under HIGH_NOT_PRECEDENCE the lexer reads NOT as
     *   this terminal, and `NOT x` then means `!x`
     *   (https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_high_not_precedence).
     * - `bit_expr: bit_expr MOD_SYM bit_expr` position 1: "N % M, N MOD M …
     *   Modulo operation" (https://dev.mysql.com/doc/refman/8.4/en/arithmetic-functions.html#operator_mod).
     * - `simple_expr: CONVERT_SYM ( expr , cast_type )` positions 0 and 3:
     *   "CONVERT(expr,type) … is equivalent to CAST(expr AS type)"
     *   (https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_convert).
     *
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return [
            'and: AND_AND_SYM' => [0 => 'AND_SYM'],
            'or: OR2_SYM' => [0 => 'OR_SYM'],
            'not2: NOT2_SYM' => [0 => '!'],
            'bit_expr: bit_expr MOD_SYM bit_expr' => [1 => '%'],
            'simple_expr: CONVERT_SYM ( expr , cast_type )' => [0 => 'CAST_SYM', 3 => 'AS'],
        ];
    }
}
