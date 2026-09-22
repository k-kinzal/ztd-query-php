<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A classified cursor sensitivity choice.
 * @visibility public
 */
enum Sensitivity: string
{
    case Default = '';
    case Asensitive = 'ASENSITIVE';
    case Insensitive = 'INSENSITIVE';
}
