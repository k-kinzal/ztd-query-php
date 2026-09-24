<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

/**
 * Whether an event is dropped or kept once its schedule has expired.
 * @visibility public
 * @example Reading the completion policy
 *     \SqlSemantics\Model\Definition\Routine\Stored\EventCompletion::Preserve->value // => 'ON COMPLETION PRESERVE'
 */
enum EventCompletion: string
{
    case Drop = 'ON COMPLETION NOT PRESERVE';
    case Preserve = 'ON COMPLETION PRESERVE';
}
