<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

/**
 * PostgreSQL's ordinary and concurrent index removal routes.
 *
 * @visibility public
 * @example Distinguishing concurrent removal
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexDropMode::Concurrently->value // => 'concurrently'
 */
enum IndexDropMode: string
{
    case Ordinary = 'ordinary';
    case Concurrently = 'concurrently';
}
