<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

/**
 * The native key policy removed with a resolved index.
 *
 * @visibility public
 * @example Distinguishing a unique secondary index
 *     \SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole::Unique->value // => 'unique'
 */
enum IndexRole: string
{
    case Primary = 'primary';
    case Unique = 'unique';
    case Ordinary = 'ordinary';
}
