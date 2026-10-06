<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise and synonym token positions of the productions of table, index and view definitions.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise, and only terminals the manual defines as synonyms may share a
 * key. Every entry is listed in the method documentation with its reason and
 * the manual page that states it. Nothing else is skipped or merged by the
 * token correspondence check.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TableDefinitionNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `attribute: UNIQUE_SYM KEY_SYM`, `column_attribute: UNIQUE_SYM KEY_SYM`,
     *   `gcol_attribute: UNIQUE_SYM KEY_SYM`, position 1: a column attribute is
     *   written `UNIQUE [KEY]` (https://dev.mysql.com/doc/refman/8.4/en/create-table.html).
     * - `opt_primary: PRIMARY_SYM`: "KEY, when used alone in a column definition,
     *   is a synonym for PRIMARY KEY"; `[PRIMARY] KEY` is one attribute
     *   (https://dev.mysql.com/doc/refman/8.4/en/create-table.html).
     * - `opt_generated_always: GENERATED ALWAYS_SYM`: a generated column is
     *   written `[GENERATED ALWAYS] AS (expr)`
     *   (https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html).
     * - `create2: ( LIKE table_ident )` positions 0 and 3,
     *   `create_table_stmt: … ( LIKE table_ident )` positions 5 and 8: `LIKE
     *   old_tbl_name` and `(LIKE old_tbl_name)` are the same request
     *   (https://dev.mysql.com/doc/refman/8.4/en/create-table.html: "{ LIKE
     *   old_tbl_name | (LIKE old_tbl_name) }").
     * - `as_create_query_expression: AS query_expression_with_opt_locking_clauses`:
     *   the query of CREATE TABLE ... SELECT is written `[AS] query_expression`
     *   (https://dev.mysql.com/doc/refman/8.4/en/create-table-select.html).
     * - `create_table_options: create_table_option , create_table_options`,
     *   position 1: options are separated by an optional comma (create-table.html:
     *   "table_option [[,] table_option] ...").
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'attribute: UNIQUE_SYM KEY_SYM' => [1],
            'column_attribute: UNIQUE_SYM KEY_SYM' => [1],
            'gcol_attribute: UNIQUE_SYM KEY_SYM' => [1],
            'opt_primary: PRIMARY_SYM' => [0],
            'opt_generated_always: GENERATED ALWAYS_SYM' => [0, 1],
            'create2: ( LIKE table_ident )' => [0, 3],
            'create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident ( LIKE table_ident )' => [5, 8],
            'as_create_query_expression: AS query_expression_with_opt_locking_clauses' => [0],
            'create_table_options: create_table_option , create_table_options' => [1],
        ];
    }

    /**
     * Answers the key of each synonym position by production signature.
     *
     * - `index_type_clause: TYPE_SYM index_type`, `key_using_alg: TYPE_SYM
     *   btree_or_rtree`, `opt_index_name_and_type: ident TYPE_SYM index_type`:
     *   "TYPE type_name is recognized as a synonym for USING type_name"
     *   (https://dev.mysql.com/doc/refman/8.4/en/create-table.html).
     *
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return [
            'index_type_clause: TYPE_SYM index_type' => [0 => 'USING'],
            'key_using_alg: TYPE_SYM btree_or_rtree' => [0 => 'USING'],
            'opt_index_name_and_type: ident TYPE_SYM index_type' => [1 => 'USING'],
        ];
    }
}
