<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Noise;

/**
 * The token positions of the query family that carry no meaning.
 *
 * A position is listed only when the token there has no effect on what the
 * statement requests in that production, and each entry states the reason and
 * cites the manual. A significant token never belongs here: when rendering
 * cannot reproduce it, the model lacks a distinction.
 *
 * @visibility SqlSemantics
 */
final class QueryNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            // "The AS keyword is optional" before an output name. https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST
            'target_el: a_expr AS ColLabel' => [1],
            // [ AS ] before a table alias is optional. https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM
            'alias_clause: AS ColId' => [0],
        ];
    }
}
