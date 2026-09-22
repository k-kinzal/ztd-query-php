<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

use Override;

/**
 * A value supplied by a declared sequence, with an override policy.
 *
 * @visibility public
 */
final class IdentityColumn implements Generation
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly IdentityMode $mode,
        public readonly SequenceOptions $sequence = new SequenceOptions(),
    ) {
    }

    #[Override]
    public function expressions(): array
    {
        return [];
    }
}
