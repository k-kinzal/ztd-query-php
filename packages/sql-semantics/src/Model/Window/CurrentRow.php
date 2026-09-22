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
    /**
     * Returns no offset expressions: this boundary denotes the current row.
     */
    public function expressions(): array
    {
        return [];
    }
}
