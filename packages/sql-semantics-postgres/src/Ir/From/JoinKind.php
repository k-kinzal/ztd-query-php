<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

/**
 * The operations of an explicitly scoped joined table.
 *
 * @visibility public
 * @example Reading the outer join keyword
 *     \SqlSemantics\Platform\PostgreSql\Ir\From\JoinKind::Left->value // => 'LEFT'
 */
enum JoinKind: string
{
    case Cross = 'CROSS';
    case Inner = 'INNER';
    case Left = 'LEFT';
    case Right = 'RIGHT';
    case Full = 'FULL';
}
