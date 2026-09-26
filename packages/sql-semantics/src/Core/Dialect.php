<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

/**
 * A language identity and the semantic policies supplied for it.
 *
 * @property-read string $value Stable language identity
 * @visibility SqlSemantics
 */
interface Dialect
{
    /**
     * Supplies semantic behavior without core selecting an implementation.
     */
    public function platform(): Platform;
}
