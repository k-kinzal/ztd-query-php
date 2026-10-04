<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise and synonym token positions of the productions of ALTER TABLE, partitioning, DROP, RENAME and TRUNCATE.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise, and only terminals the manual defines as synonyms may share a
 * key. Every entry is listed in the method documentation with its reason and
 * the manual page that states it. Nothing else is skipped or merged by the
 * token correspondence check.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TableChangeNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `opt_column: COLUMN_SYM`: COLUMN after ADD, CHANGE, MODIFY, DROP and
     *   ALTER is optional (https://dev.mysql.com/doc/refman/8.4/en/alter-table.html:
     *   "ADD [COLUMN] col_name", "DROP [COLUMN] col_name").
     * - `opt_to: TO_SYM`, `opt_to: EQ`, `opt_to: AS`: the word after RENAME of
     *   ALTER TABLE is optional and the three are the same request
     *   (https://dev.mysql.com/doc/refman/8.4/en/alter-table.html: "RENAME
     *   [TO | AS] new_tbl_name"; the 5.x grammar also reads `=`, sql/sql_yacc.yy
     *   rule opt_to).
     * - `opt_table_sym: TABLE_SYM`: TABLE after TRUNCATE is optional
     *   (https://dev.mysql.com/doc/refman/5.7/en/truncate-table.html:
     *   "TRUNCATE [TABLE] tbl_name").
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'opt_column: COLUMN_SYM' => [0],
            'opt_to: TO_SYM' => [0],
            'opt_to: EQ' => [0],
            'opt_to: AS' => [0],
            'opt_table_sym: TABLE_SYM' => [0],
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
