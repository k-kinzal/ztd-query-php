<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Storage;

/**
 * Whether a table or sequence writes to the write-ahead log.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Relation\Storage\RelationLogging::Unlogged->value // => 'UNLOGGED'
 */
enum RelationLogging: string
{
    case Logged = 'LOGGED';
    case Unlogged = 'UNLOGGED';
}
