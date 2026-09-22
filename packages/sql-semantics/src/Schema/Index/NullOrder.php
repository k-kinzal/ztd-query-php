<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Index;

/**
 * NullOrder alternatives.
 *
 * @visibility public
 */
enum NullOrder: string
{
    case First = 'FIRST';
    case Last = 'LAST';
}
