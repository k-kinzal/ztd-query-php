<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

use Override;

/**
 * A supplied value, with optional insertion default and update expression.
 *
 * @visibility public
 */
final class SuppliedColumn implements Generation
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly ?\SqlSemantics\Model\Expression $default = null,
        public readonly ?\SqlSemantics\Model\Expression $onUpdate = null,
    ) {
    }

    #[Override]
    public function expressions(): array
    {
        return array_values(array_filter([$this->default, $this->onUpdate], static fn (?\SqlSemantics\Model\Expression $value): bool => $value !== null));
    }
}
