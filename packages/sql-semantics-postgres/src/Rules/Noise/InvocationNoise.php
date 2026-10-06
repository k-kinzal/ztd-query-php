<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Noise;

/**
 * The token positions of the invocation family that carry no meaning.
 *
 * A position is listed only when the token there has no effect on what the
 * statement requests in that production, and each entry states the reason and
 * cites the manual. A significant token never belongs here: when rendering
 * cannot reproduce it, the model lacks a distinction.
 *
 * @visibility SqlSemantics
 */
final class InvocationNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            // "The second form is the same as the first, since ALL is the default." https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-AGGREGATES
            'func_application: func_name ( ALL func_arg_list opt_sort_clause )' => [2],
            // "EXCLUDE NO OTHERS simply specifies explicitly the default behavior of not excluding the current row or its peers." https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-WINDOW-FUNCTIONS
            'opt_window_exclusion_clause: EXCLUDE NO OTHERS' => [0, 1, 2],
            // trim ( [ LEADING | TRAILING | BOTH ] ... ): "BOTH is the default". https://www.postgresql.org/docs/17/functions-string.html
            'func_expr_common_subexpr: TRIM ( BOTH trim_list )' => [2],
            // trim ( [ LEADING | TRAILING | BOTH ] [ FROM ] string text [, characters text ] ): FROM is optional without characters. https://www.postgresql.org/docs/17/functions-string.html
            'trim_list: FROM expr_list' => [0],
            // "The BY REF and BY VALUE clauses are accepted in PostgreSQL, but are ignored." https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-PREDICATES-XMLEXISTS
            'xml_passing_mech: BY REF_P' => [0, 1],
            'xml_passing_mech: BY VALUE_P' => [0, 1],
            // { WITH | WITHOUT } UNIQUE [ KEYS ]: the KEYS word is optional. https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE
            'json_key_uniqueness_constraint_opt: WITH UNIQUE KEYS' => [2],
            'json_key_uniqueness_constraint_opt: WITHOUT UNIQUE KEYS' => [2],
            // { WITHOUT | WITH { CONDITIONAL | [ UNCONDITIONAL ] } } [ ARRAY ] WRAPPER: UNCONDITIONAL and ARRAY are optional. https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING
            'json_wrapper_behavior: WITHOUT ARRAY WRAPPER' => [1],
            'json_wrapper_behavior: WITH ARRAY WRAPPER' => [1],
            'json_wrapper_behavior: WITH CONDITIONAL ARRAY WRAPPER' => [2],
            'json_wrapper_behavior: WITH UNCONDITIONAL ARRAY WRAPPER' => [1, 2],
            'json_wrapper_behavior: WITH UNCONDITIONAL WRAPPER' => [1],
            // { KEEP | OMIT } QUOTES [ ON SCALAR STRING ]: the ON SCALAR STRING words are optional. https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING
            'json_quotes_clause_opt: KEEP QUOTES ON SCALAR STRING_P' => [2, 3, 4],
            'json_quotes_clause_opt: OMIT QUOTES ON SCALAR STRING_P' => [2, 3, 4],
        ];
    }
}
