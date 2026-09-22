<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

/**
 * Requested table lock level for a MySQL index operation.
 * @visibility public
 */
enum IndexLock: string
{
    case Default = 'DEFAULT';
    case None = 'NONE';
    case Shared = 'SHARED';
    case Exclusive = 'EXCLUSIVE';
}
