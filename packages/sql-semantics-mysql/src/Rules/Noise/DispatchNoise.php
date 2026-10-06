<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise token positions of the statement root productions.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise. Every entry is listed in the method documentation with its
 * reason and the manual page that states it.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DispatchNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `sql_statement: simple_statement_or_begin ; opt_end_of_input` and
     *   `query: verb_clause ; opt_end_of_input`, position 1: the semicolon ends
     *   the statement and is not part of it; the server accepts one statement
     *   with or without it (https://dev.mysql.com/doc/refman/8.4/en/sql-statements.html,
     *   https://dev.mysql.com/doc/c-api/8.4/en/c-api-multiple-queries.html).
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'sql_statement: simple_statement_or_begin ; opt_end_of_input' => [1],
            'query: verb_clause ; opt_end_of_input' => [1],
        ];
    }

    /**
     * Answers the key of each synonym position by production signature; the root productions have none.
     *
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return [];
    }
}
