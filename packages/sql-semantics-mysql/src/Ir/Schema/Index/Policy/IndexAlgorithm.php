<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy;

/**
 * The native physical algorithm requested for an ordinary InnoDB index change.
 *
 * @visibility public
 * @example Selecting the native in-place request
 *     \SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexAlgorithm::InPlace->value // => 'INPLACE'
 */
enum IndexAlgorithm: string
{
    case Default = 'DEFAULT';
    case InPlace = 'INPLACE';
    case Copy = 'COPY';
}
