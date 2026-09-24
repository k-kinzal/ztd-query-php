<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Partition;

/**
 * How a partition is detached: immediately, concurrently, or by finalizing an interrupted concurrent detach.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Relation\Partition\PartitionDetachMode::Concurrently->value // => 'CONCURRENTLY'
 */
enum PartitionDetachMode: string
{
    case Immediate = '';
    case Concurrently = 'CONCURRENTLY';
    case Finalize = 'FINALIZE';
}
