<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Index;

/**
 * Direction alternatives.
 *
 * @visibility public
 */
enum Direction: string
{
    case Ascending = 'ASC';
    case Descending = 'DESC';
}
