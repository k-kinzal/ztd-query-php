<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

use Override;

/**
 * A generated value with a mandatory computation and storage policy.
 *
 * @visibility public
 */
final class ComputedColumn implements Generation
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly \SqlSemantics\Model\Expression $expression,
        public readonly GeneratedStorage $storage,
    ) {
    }

    /**
     * Returns the expression that defines the generated column.
     */
    #[Override]
    public function expressions(): array
    {
        return [$this->expression];
    }
}
