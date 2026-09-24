<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Partition;

/**
 * The unbounded ends of a range partition bound.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Relation\Partition\RangeBoundary::MaxValue->value // => 'MAXVALUE'
 */
enum RangeBoundary: string
{
    case MinValue = 'MINVALUE';
    case MaxValue = 'MAXVALUE';
}
