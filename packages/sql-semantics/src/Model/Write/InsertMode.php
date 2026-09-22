<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

/**
 * Whether conflicting records are replaced as part of insertion.
 * @visibility public
 */
enum InsertMode: string
{
    case Insert = 'INSERT';
    case Replace = 'REPLACE';
}
