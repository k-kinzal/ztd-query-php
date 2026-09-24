<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation;

/**
 * The row identity recorded in the write-ahead log for logical replication, other than a named index.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Relation\ReplicaIdentity::Full->value // => 'FULL'
 */
enum ReplicaIdentity: string
{
    case Nothing = 'NOTHING';
    case Full = 'FULL';
    case Default = 'DEFAULT';
}
