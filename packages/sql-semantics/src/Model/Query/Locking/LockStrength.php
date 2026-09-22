<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Locking;

/**
 * The row-lock mode requested by a query.
 * @visibility public
 * @example Reading a lock option
 *     \SqlSemantics\Model\Query\Locking\LockStrength::Update->value // => 'UPDATE'
 */
enum LockStrength: string
{
    case Update = 'UPDATE';
    case NoKeyUpdate = 'NO KEY UPDATE';
    case Share = 'SHARE';
    case KeyShare = 'KEY SHARE';
}
