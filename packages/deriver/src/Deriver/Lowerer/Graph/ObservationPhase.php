<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Graph;

/**
 * The point at which an instruction is observed.
 * @example Reading a classification identifier
 *     \Deriver\Deriver\Lowerer\Graph\ObservationPhase::Before->value // => 'before'
 * @visibility root
 */
enum ObservationPhase: string
{
    case Before = 'before';
    case After = 'after';
    case Invocation = 'invocation';
}
