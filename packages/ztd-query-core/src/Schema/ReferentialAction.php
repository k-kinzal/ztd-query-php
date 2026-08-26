<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * What a foreign key says should happen when the row it points at moves.
 *
 * Only CASCADE and SET NULL let the statement stand; the rest say the parent
 * was not free to move, so the statement is refused.
 */
enum ReferentialAction: string
{
    case NoAction = 'NO ACTION';
    case Restrict = 'RESTRICT';
    case Cascade = 'CASCADE';
    case SetNull = 'SET NULL';
    case SetDefault = 'SET DEFAULT';
}
