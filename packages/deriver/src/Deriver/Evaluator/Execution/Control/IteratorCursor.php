<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Control;

use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Term;

/**
 * A foreach snapshot or a live reference to its iterable.
 *
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
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
