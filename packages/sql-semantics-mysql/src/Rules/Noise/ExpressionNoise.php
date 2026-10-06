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
     * None: the optional ROW of a row constructor, the parentheses of the
     * column list of MATCH, IN NATURAL LANGUAGE MODE and the OF of MEMBER OF
     * do not change the expression, but they are part of the text MySQL
     * names an unaliased select list expression after, so the model keeps
     * them (OptionalWords).
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [];
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
