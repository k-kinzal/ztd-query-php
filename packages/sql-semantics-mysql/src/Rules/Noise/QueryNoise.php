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
