<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A classified cursor scandirection choice.
 * @visibility public
 */
enum ScanDirection: string
{
    case Forward = 'FORWARD';
    case Backward = 'BACKWARD';
}
