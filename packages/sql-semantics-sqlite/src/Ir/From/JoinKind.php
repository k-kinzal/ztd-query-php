<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\From;

/**
 * SQLite join operations on explicitly bound inputs.
 *
 * @visibility public
 * @example Reading a SQL operator's spelling
 *     \SqlSemantics\Platform\Sqlite\Ir\From\JoinKind::Inner->value // => 'INNER'
 */
enum JoinKind: string
{
    case Inner = 'INNER';
    case Cross = 'CROSS';
    case Left = 'LEFT';
    case Right = 'RIGHT';
    case Full = 'FULL';
}
