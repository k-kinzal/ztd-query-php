<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\Histogram;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An explicit histogram bucket limit in MySQL's supported range.
 * @visibility public
 * @example Selecting a histogram resolution
 *     (new \SqlSemantics\Model\Maintenance\Histogram\BucketCount(100))->value // => 100
 */
final class BucketCount
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly int $value)
    {
        if ($value < 1 || $value > 1024) {
            throw new InvalidStructure('A histogram requires between 1 and 1024 buckets.');
        }
    }
}
