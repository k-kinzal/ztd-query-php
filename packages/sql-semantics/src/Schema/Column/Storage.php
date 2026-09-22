<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

/**
 * Storage alternatives.
 *
 * @visibility public
 */
enum Storage: string
{
    case Default = 'default';
    case Disk = 'disk';
    case Memory = 'memory';
}
