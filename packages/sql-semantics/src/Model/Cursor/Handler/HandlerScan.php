<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor\Handler;

/**
 * A step of a HANDLER table scan in natural row order.
 * @visibility public
 * @example Reading the scan step
 *     \SqlSemantics\Model\Cursor\Handler\HandlerScan::Next->value // => 'NEXT'
 */
enum HandlerScan: string
{
    case First = 'FIRST';
    case Next = 'NEXT';
}
