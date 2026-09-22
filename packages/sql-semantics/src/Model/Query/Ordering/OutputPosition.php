<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Ordering;

use SqlSemantics\Model\OutputColumn;

/**
 * Sorts by a projected result column by its position.
 * @visibility public
 */
final class OutputPosition
{
    public function __construct(public readonly OutputColumn $output)
    {
    }
}
