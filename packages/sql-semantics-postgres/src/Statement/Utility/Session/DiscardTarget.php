<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

/**
 * What DISCARD releases.
 *
 * Mirrors PostgreSQL's `DiscardMode`. TEMP and TEMPORARY are two spellings
 * of the same request, the temporary tables of the session; the spelling
 * written is kept. The value is the keyword.
 * Source: https://www.postgresql.org/docs/17/sql-discard.html.
 *
 * @visibility public
 * @example Telling that both spellings release temporary tables
 *     [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\DiscardTarget::Temp->temporary(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\DiscardTarget::Temporary->temporary()] // => [true, true]
 */
enum DiscardTarget: string
{
    case All = 'ALL';
    case Plans = 'PLANS';
    case Sequences = 'SEQUENCES';
    case Temp = 'TEMP';
    case Temporary = 'TEMPORARY';

    /**
     * Tells whether the temporary tables are what is released, under either spelling.
     */
    public function temporary(): bool
    {
        return $this === self::Temp || $this === self::Temporary;
    }
}
