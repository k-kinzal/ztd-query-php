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
    public function expressions(): array
    {
        return [];
    }
}
