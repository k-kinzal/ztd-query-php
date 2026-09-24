<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor\Handler;

/**
 * A step of a HANDLER walk along an index.
 * @visibility public
 * @example Reading the index step
 *     \SqlSemantics\Model\Cursor\Handler\IndexStep::Previous->value // => 'PREV'
 */
enum IndexStep: string
{
    case First = 'FIRST';
    case Next = 'NEXT';
    case Previous = 'PREV';
    case Last = 'LAST';
}
