<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

/**
 * DropBehavior alternatives.
 *
 * @visibility public
 */
enum DropBehavior: string
{
    case Default = '';
    case Restrict = 'RESTRICT';
    case Cascade = 'CASCADE';
}
