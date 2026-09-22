<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A classified cursor offsetorigin choice.
 * @visibility public
 */
enum OffsetOrigin: string
{
    case Absolute = 'ABSOLUTE';
    case Relative = 'RELATIVE';
}
