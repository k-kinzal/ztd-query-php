<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Completion\Quality;

/**
 * Whether unresolved dependencies remain in the result.
 * @example Reading a classification identifier
 *     \Deriver\Deriver\Evaluator\Execution\Completion\Quality\DependencyClosure::Open->value // => 'open'
 * @visibility root
 */
enum DependencyClosure: string
{
    case Open = 'open';
    case Closed = 'closed';
}
