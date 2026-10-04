<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege;

/**
 * The value of a role membership option: OPTION, TRUE or FALSE.
 *
 * OPTION is the older spelling of TRUE, as in WITH ADMIN OPTION; each
 * spelling is kept as written.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility public
 * @example Telling that OPTION turns the membership option on
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\MembershipSetting::Option->enabled() // => true
 */
enum MembershipSetting: string
{
    case Option = 'OPTION';
    case True = 'TRUE';
    case False = 'FALSE';

    /**
     * Tells whether the value turns the option on.
     */
    public function enabled(): bool
    {
        return $this !== self::False;
    }
}
