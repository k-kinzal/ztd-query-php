<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keyword;

/**
 * The productions of `type_func_name_keyword`: the keywords that may be used as a function or type name but not as a column name.
 *
 * Rule: PG-KEYWORD-NAME-001 (table). The list is the union of the shipped
 * grammar releases; each production reads one keyword terminal as a name.
 * Source: https://www.postgresql.org/docs/17/sql-keywords-appendix.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class TypeFunctionNameKeywords
{
    /**
     * The production signatures, one per keyword.
     */
    public const SIGNATURES = [
        'type_func_name_keyword: AUTHORIZATION', 'type_func_name_keyword: BINARY', 'type_func_name_keyword: COLLATION', 'type_func_name_keyword: CONCURRENTLY',
        'type_func_name_keyword: CROSS', 'type_func_name_keyword: CURRENT_SCHEMA', 'type_func_name_keyword: FREEZE', 'type_func_name_keyword: FULL',
        'type_func_name_keyword: ILIKE', 'type_func_name_keyword: INNER_P', 'type_func_name_keyword: IS', 'type_func_name_keyword: ISNULL',
        'type_func_name_keyword: JOIN', 'type_func_name_keyword: LEFT', 'type_func_name_keyword: LIKE', 'type_func_name_keyword: NATURAL',
        'type_func_name_keyword: NOTNULL', 'type_func_name_keyword: OUTER_P', 'type_func_name_keyword: OVERLAPS', 'type_func_name_keyword: RIGHT',
        'type_func_name_keyword: SIMILAR', 'type_func_name_keyword: TABLESAMPLE', 'type_func_name_keyword: VERBOSE',
    ];
}
