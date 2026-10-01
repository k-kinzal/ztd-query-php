<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction;

/**
 * When a transaction acquires its database locks.
 * @example Describing the operation
 *     $mode = \SqlSemantics\Statement\Transaction\LockAcquisition::Immediate;
 *     $mode->value // => 'IMMEDIATE'
 * @visibility public
 */
enum LockAcquisition: string
{
    case Default = '';
    case Deferred = 'DEFERRED';
    case Immediate = 'IMMEDIATE';
    case Exclusive = 'EXCLUSIVE';
}
