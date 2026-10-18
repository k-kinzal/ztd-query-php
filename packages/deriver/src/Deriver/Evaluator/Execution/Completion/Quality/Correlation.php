<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Completion\Quality;

/**
 * Whether relationships between alternatives have been preserved.
 * @example Reading a classification identifier
 *     \Deriver\Deriver\Evaluator\Execution\Completion\Quality\Correlation::Preserved->value // => 'preserved'
 * @visibility root
 */
enum Correlation: string
{
    case Preserved = 'preserved';
    case Relaxed = 'relaxed';
}
