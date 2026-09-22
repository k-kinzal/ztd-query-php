<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public

 */
final class Offset implements Boundary
{
    /**
     */
    public function __construct(
        public readonly Direction $direction,
        public readonly \SqlSemantics\Model\Expression $value
    ) {
    }
    /**
     * Returns the required offset expression evaluated for this frame boundary.
     */
    public function expressions(): array
    {
        return [$this->value];
    }
}
