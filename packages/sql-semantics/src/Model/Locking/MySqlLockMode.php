<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Locking;

/**
 * MySQL session table access modes, including legacy low-priority writes.
 * @visibility public
 * @example Reading the concurrency policy
 *     \SqlSemantics\Model\Locking\MySqlLockMode::ReadLocal->value // => 'READ LOCAL'
 */
enum MySqlLockMode: string
{
    case Read = 'READ';
    case ReadLocal = 'READ LOCAL';
    case Write = 'WRITE';
    case LowPriorityWrite = 'LOW_PRIORITY WRITE';
}
