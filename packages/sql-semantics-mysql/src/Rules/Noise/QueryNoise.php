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
     * None: the optional AS or `=` before an alias and the optional OUTER
     * and INNER of a join do not change the query, but MySQL names an
     * unaliased select list expression after its text, which can hold them
     * in a subquery, so the model keeps them (AliasMark, JoinOperator).
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
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return [];
    }
}
