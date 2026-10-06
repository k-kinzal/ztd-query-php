<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

/**
 * How DETACH PARTITION detaches: at once, concurrently, or finishing an interrupted concurrent detach.
 *
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Reading the keyword of a detach mode
 *     \SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachMode::Concurrently->value // => 'CONCURRENTLY'
 */
enum DetachMode: string
{
    case Plain = '';
    case Concurrently = 'CONCURRENTLY';
    case Finalize = 'FINALIZE';
}
