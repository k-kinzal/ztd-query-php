<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Constraint;

/**
 * CheckingTime alternatives.
 *
 * @visibility public
 */
enum CheckingTime: string
{
    case Immediate = 'not-deferrable';
    case DeferrableImmediate = 'deferrable-immediate';
    case DeferrableDeferred = 'deferrable-deferred';
}
