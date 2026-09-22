<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public

 */
final class NamedWindow implements Window
{
    /**
     */
    public function __construct(
        public readonly string $name
    ) {
    }
    /**
     * Returns no local expressions: this window refers to a named definition.
     */
    public function expressions(): array
    {
        return [];
    }
}
