<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Identifier;

use SqlSemantics\Statement\Snapshot;

/**
 * A decoded identifier: the name the database compares, not its SQL spelling.
 *
 * Quotes and escapes are removed and the case folding of the language profile
 * is already applied. Encoding a name for a position is the job of the
 * profile codec, never string concatenation.
 *
 * @visibility public
 * @example Holding a decoded name
 *     (new \SqlSemantics\Statement\Identifier\Name('order items'))->value // => 'order items'
 */
final class Name
{
    use Snapshot;

    /**
     * @param string $value The decoded identifier
     */
    public function __construct(public readonly string $value)
    {
    }
}
