<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

/**
 * The effective PostgreSQL index rebuild request.
 *
 * @visibility public
 * @example Selecting the concurrent request mode
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode::Concurrent->value // => 'concurrently'
 */
enum IndexRebuildMode: string
{
    case Standard = 'standard';
    case Concurrent = 'concurrently';
}
