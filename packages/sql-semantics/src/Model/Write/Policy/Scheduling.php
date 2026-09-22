<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

/**
 * Scheduling alternatives.
 *
 * @visibility public
 */
enum Scheduling: string
{
    case Default = '';
    case LowPriority = 'LOW_PRIORITY';
    case HighPriority = 'HIGH_PRIORITY';
    case Delayed = 'DELAYED';
}
