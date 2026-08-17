<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

enum ReferentialAction: string
{
    case NoAction = 'NO ACTION';
    case Restrict = 'RESTRICT';
    case Cascade = 'CASCADE';
    case SetNull = 'SET NULL';
    case SetDefault = 'SET DEFAULT';
}
