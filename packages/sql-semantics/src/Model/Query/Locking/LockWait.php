<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Locking;

/**
 * How a locking read handles a row already locked by another transaction.
 * @visibility public
 * @example Reading a lock option
 *     \SqlSemantics\Model\Query\Locking\LockWait::Wait->value // => ''
 */
enum LockWait: string
{
    case Wait = '';
    case NoWait = 'NOWAIT';
    case SkipLocked = 'SKIP LOCKED';
}
