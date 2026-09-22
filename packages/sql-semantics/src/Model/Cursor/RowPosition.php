<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A classified cursor rowposition choice.
 * @visibility public
 */
enum RowPosition: string implements Movement
{
    case Next = 'NEXT';
    case Prior = 'PRIOR';
    case First = 'FIRST';
    case Last = 'LAST';
}
