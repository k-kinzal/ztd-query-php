<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

use Override;

/**
 * An integer value supplied by the table auto-increment mechanism.
 *
 * @visibility public
 */
final class AutoIncrementColumn implements Generation
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
    ) {
    }

    #[Override]
    public function expressions(): array
    {
        return [];
    }
}
