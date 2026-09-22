<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public

 */
final class Unbounded implements Boundary
{
    /**
     */
    public function __construct(
        public readonly Direction $direction
    ) {
    }
    /**
     * Returns no offset expressions: this boundary denotes an unbounded frame edge.
     */
    public function expressions(): array
    {
        return [];
    }
}
