<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

/**
 * The logical join operation, before physical planning.
 *
 * @example Reading semantic facts
 *     \SqlSemantics\Model\JoinKind::Left->value // => 'left'
 *
 * @visibility public
 */
enum JoinKind: string
{
    case Cross = 'cross';
    case Inner = 'inner';
    case Left = 'left';
    case Right = 'right';
    case Full = 'full';
}
