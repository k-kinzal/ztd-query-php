<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

/**
 * An old or new record at a trigger activation.
 *
 * @visibility public
 * @example Reading the proposed row
 *     \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage::New->value // => 'NEW'
 */
enum RowImage: string
{
    case New = 'NEW';
    case Old = 'OLD';
}
