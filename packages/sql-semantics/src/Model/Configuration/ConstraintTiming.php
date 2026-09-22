<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

/**
 * When a deferrable constraint is checked in the current transaction.
 * @visibility public
 * @example Choosing a mode
 *     \SqlSemantics\Model\Configuration\ConstraintTiming::Immediate->value // => 'IMMEDIATE'
 */
enum ConstraintTiming: string
{
    case Immediate = 'IMMEDIATE';
    case Deferred = 'DEFERRED';
}
