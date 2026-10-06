<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text;

/**
 * The ends of a string TRIM removes characters from.
 *
 * BOTH is the default and the written BOTH is noise; each side selects the
 * function the server calls.
 * Source: https://www.postgresql.org/docs/17/functions-string.html.
 *
 * @visibility public
 * @example Reading the function trimming the start calls
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\TrimSide::Leading->function() // => 'ltrim'
 */
enum TrimSide: string
{
    case Both = 'BOTH';
    case Leading = 'LEADING';
    case Trailing = 'TRAILING';

    /**
     * Answers the `pg_catalog` function the server calls.
     */
    public function function(): string
    {
        return match ($this) {
            self::Both => 'btrim',
            self::Leading => 'ltrim',
            self::Trailing => 'rtrim',
        };
    }
}
