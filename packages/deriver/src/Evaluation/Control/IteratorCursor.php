<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\Memory\Location;
use Deriver\Value\Term;

/**
 * A foreach snapshot or a live reference to its iterable.
 *
 * @visibility root
 */
final class IteratorCursor
{
    /**
     * @param Term $array array
     * @param Location|null $location location
     * @param int $position position
     */
    public function __construct(
        public readonly Term $array,
        public readonly ?Location $location = null,
        public readonly int $position = -1,
    ) {
    }
}
