<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public

 */
final class CurrentRow implements Boundary
{
    /**
     */
    public function __construct(

    ) {
    }
    public function expressions(): array
    {
        return [];
    }
}
