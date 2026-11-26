<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy;

/**
 * The native concurrency request for an ordinary InnoDB index change.
 *
 * @visibility public
 * @example Choosing the native shared-lock request
 *     \SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexLock::Shared->value // => 'SHARED'
 */
enum IndexLock: string
{
    case Default = 'DEFAULT';
    case None = 'NONE';
    case Shared = 'SHARED';
    case Exclusive = 'EXCLUSIVE';
}
