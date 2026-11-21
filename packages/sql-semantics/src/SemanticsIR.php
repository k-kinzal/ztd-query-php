<?php

declare(strict_types=1);

namespace SqlSemantics;

/**
 * A complete, immutable statement under a fixed database language profile.
 *
 * Database packages own the concrete operations, operands and resolved
 * references. The structure is sufficient to reconstruct equivalent SQL.
 *
 * @visibility public
 * @example Consuming a dialect implementation through the interface
 *     $profile = static fn (\SqlSemantics\SemanticsIR $statement): string => $statement->profileId();
 *     is_callable($profile) // => true
 */
interface SemanticsIR extends Query
{
    /**
     * Identifies the dialect, release and language settings of this value.
     */
    public function profileId(): string;
}
