<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise and synonym token positions of the productions of stored programs.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise, and only terminals the manual defines as synonyms may share a
 * key. Every entry is listed in the method documentation with its reason and
 * the manual page that states it. Nothing else is skipped or merged by the
 * token correspondence check.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RoutineNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `sp_opt_fetch_noise: NEXT_SYM FROM` and `sp_opt_fetch_noise: FROM`:
     *   `FETCH [[NEXT] FROM] cursor_name INTO ...`; the words are optional
     *   and the grammar action stores nothing for them
     *   (https://dev.mysql.com/doc/refman/8.4/en/fetch.html).
     * - `opt_value: VALUE_SYM`: `SQLSTATE [VALUE] sqlstate_value`; the word
     *   is optional (https://dev.mysql.com/doc/refman/8.4/en/declare-condition.html,
     *   https://dev.mysql.com/doc/refman/8.4/en/signal.html).
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'sp_opt_fetch_noise: NEXT_SYM FROM' => [0, 1],
            'sp_opt_fetch_noise: FROM' => [0],
            'opt_value: VALUE_SYM' => [0],
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
