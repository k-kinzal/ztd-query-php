<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise and synonym token positions of the shared leaf productions: names, optional keywords and variables.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise, and only terminals the manual defines as synonyms may share a
 * key. Every entry is listed in the method documentation with its reason and
 * the manual page that states it.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LeafNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `opt_as: AS`: the keyword before the query of CREATE TABLE ... SELECT
     *   (5.6, 5.7) is optional and changes nothing
     *   (https://dev.mysql.com/doc/refman/5.7/en/create-table-select.html: "[AS] query_expression").
     *   Before a table alias (8.0 and later) the model keeps the keyword as
     *   written (AliasMark), since it can be part of the text MySQL names an
     *   unaliased select list expression after.
     * - `equal: EQ`, `equal: SET_VAR`: an option is written `name [=] value`, and
     *   in SET and UPDATE assignments `:=` and `=` are the same assignment
     *   operator; where the rule is mandatory the token cannot be left out, so
     *   dropping its key loses nothing
     *   (https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
     *   https://dev.mysql.com/doc/refman/8.4/en/assignment-operators.html).
     * - `opt_default: DEFAULT`, `opt_default: DEFAULT_SYM`: `[DEFAULT] CHARACTER
     *   SET`, `[DEFAULT] COLLATE` and `[DEFAULT] ENCRYPTION` are the same option
     *   with or without the keyword
     *   (https://dev.mysql.com/doc/refman/8.4/en/create-database.html,
     *   https://dev.mysql.com/doc/refman/8.4/en/create-table.html).
     * - `opt_wild: . *`: `tbl_name.*` in a multiple-table DELETE is the table
     *   name; the suffix is accepted for compatibility with Access
     *   (https://dev.mysql.com/doc/refman/8.4/en/delete.html).
     * - `opt_comma: ,`: options in an option list are separated by an optional
     *   comma (https://dev.mysql.com/doc/refman/8.4/en/create-table.html:
     *   "table_option [[,] table_option]").
     * - `opt_storage: STORAGE_SYM`: `[STORAGE] ENGINE` and `SHOW [STORAGE]
     *   ENGINES` (https://dev.mysql.com/doc/refman/8.4/en/show-engines.html,
     *   https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html).
     * - `opt_table: TABLE_SYM`: `TRUNCATE [TABLE] tbl_name`, and TABLE is the
     *   default object type of GRANT and REVOKE
     *   (https://dev.mysql.com/doc/refman/8.4/en/truncate-table.html,
     *   https://dev.mysql.com/doc/refman/8.4/en/grant.html).
     * - `table_ident: . ident` and `field_ident: . ident`, position 0: a
     *   leading dot names the default database, as an unqualified name does
     *   (https://dev.mysql.com/doc/refman/5.7/en/identifier-qualifiers.html).
     *   A table of a FROM clause and a column of an expression keep the dot
     *   (TableReference, ColumnUse), since it is part of the text MySQL names
     *   an unaliased select list expression after.
     * - `charset: CHAR_SYM SET` and `character_set: CHAR_SYM SET_SYM`, position
     *   1: with the synonym key of position 0, the two keywords are the one
     *   keyword CHARSET (https://dev.mysql.com/doc/refman/8.4/en/create-table.html:
     *   "CHARSET is a synonym for CHARACTER SET"). The character set attribute
     *   of a type keeps the two-word form (CharsetForm::CharacterSet), since a
     *   cast target is part of the text MySQL names an unaliased select list
     *   expression after.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'opt_as: AS' => [0],
            'equal: EQ' => [0],
            'equal: SET_VAR' => [0],
            'opt_default: DEFAULT' => [0],
            'opt_default: DEFAULT_SYM' => [0],
            'opt_wild: . *' => [0, 1],
            'opt_comma: ,' => [0],
            'opt_storage: STORAGE_SYM' => [0],
            'opt_table: TABLE_SYM' => [0],
            'table_ident: . ident' => [0],
            'field_ident: . ident' => [0],
            'charset: CHAR_SYM SET' => [1],
            'character_set: CHAR_SYM SET_SYM' => [1],
        ];
    }

    /**
     * Answers the key of each synonym position by production signature.
     *
     * - `charset: CHAR_SYM SET`, `character_set: CHAR_SYM SET_SYM`: CHARACTER SET
     *   is CHARSET (https://dev.mysql.com/doc/refman/8.4/en/create-table.html).
     * - `key_or_index: KEY_SYM`: KEY is a synonym for INDEX
     *   (https://dev.mysql.com/doc/refman/8.4/en/create-table.html: "KEY is
     *   normally a synonym for INDEX").
     * - `keys_or_index: KEYS`, `keys_or_index: INDEX_SYM`: SHOW INDEX, SHOW
     *   INDEXES and SHOW KEYS are one statement
     *   (https://dev.mysql.com/doc/refman/8.4/en/show-index.html).
     * - `table_or_tables: TABLES`: `{TABLE | TABLES}`
     *   (https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html,
     *   https://dev.mysql.com/doc/refman/8.4/en/flush.html).
     * - `not: NOT2_SYM`: under HIGH_NOT_PRECEDENCE the lexer reads every NOT as
     *   this terminal; outside an expression, as in IF NOT EXISTS and NOT NULL,
     *   it is the keyword NOT
     *   (https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_high_not_precedence).
     * - `opt_no_write_to_binlog: LOCAL_SYM`: LOCAL is an alias of
     *   NO_WRITE_TO_BINLOG (https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html).
     * - the `LOCAL_SYM .` alternatives of the scope rules: LOCAL is a synonym of
     *   SESSION (https://dev.mysql.com/doc/refman/8.4/en/using-system-variables.html).
     * - `lvalue_variable: DEFAULT_SYM . ident`: `DEFAULT.name` names the
     *   instance `default` of a structured system variable
     *   (https://dev.mysql.com/doc/refman/8.4/en/structured-system-variables.html).
     * - the BINARY alternatives of the character set and collation name rules:
     *   the keyword is the name `binary`
     *   (https://dev.mysql.com/doc/refman/8.4/en/charset-binary-set.html).
     *
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return [
            'charset: CHAR_SYM SET' => [0 => 'CHARSET'],
            'character_set: CHAR_SYM SET_SYM' => [0 => 'CHARSET'],
            'key_or_index: KEY_SYM' => [0 => 'INDEX_SYM'],
            'keys_or_index: KEYS' => [0 => 'INDEXES'],
            'keys_or_index: INDEX_SYM' => [0 => 'INDEXES'],
            'table_or_tables: TABLES' => [0 => 'TABLE_SYM'],
            'not: NOT2_SYM' => [0 => 'NOT_SYM'],
            'opt_no_write_to_binlog: LOCAL_SYM' => [0 => 'NO_WRITE_TO_BINLOG'],
            'opt_var_ident_type: LOCAL_SYM .' => [0 => 'SESSION_SYM'],
            'opt_rvalue_system_variable_type: LOCAL_SYM .' => [0 => 'SESSION_SYM'],
            'opt_set_var_ident_type: LOCAL_SYM .' => [0 => 'SESSION_SYM'],
            'lvalue_variable: DEFAULT_SYM . ident' => [0 => 'name:default'],
            'charset_name: BINARY' => [0 => 'name:binary'],
            'charset_name: BINARY_SYM' => [0 => 'name:binary'],
            'old_or_new_charset_name: BINARY' => [0 => 'name:binary'],
            'old_or_new_charset_name: BINARY_SYM' => [0 => 'name:binary'],
            'collation_name: BINARY_SYM' => [0 => 'name:binary'],
        ];
    }
}
