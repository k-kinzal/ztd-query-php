<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Completion\Quality;

/**
 * Whether the result can represent an over-approximation of executions.
 * @example Reading a classification identifier
 *     \Deriver\Deriver\Evaluator\Execution\Completion\Quality\Coverage::OverApproximation->value // => 'over-approximation'
 * @visibility root
 */
enum Coverage: string
{
    case OverApproximation = 'over-approximation';
    case Unavailable = 'unavailable';
}
