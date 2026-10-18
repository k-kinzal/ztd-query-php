<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Completion;

/**
 * Whether the selected observation may be reached.
 * @example Reading a classification identifier
 *     \Deriver\Deriver\Evaluator\Execution\Completion\Reachability::MayReach->value // => 'may-reach'
 * @visibility root
 */
enum Reachability: string
{
    case MayReach = 'may-reach';
    case Unreachable = 'unreachable';
}
