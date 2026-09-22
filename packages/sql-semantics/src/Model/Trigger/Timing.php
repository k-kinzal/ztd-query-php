<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Trigger;

/**
 * The point at which a trigger acts relative to its row operation.
 * @visibility public
 */
enum Timing: string
{
    case Before = 'BEFORE';
    case After = 'AFTER';
    case InsteadOf = 'INSTEAD OF';
}
