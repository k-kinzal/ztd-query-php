<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Completion\Quality;

/**
 * Whether all concrete values have been enumerated.
 * @example Reading a classification identifier
 *     \Deriver\Deriver\Evaluator\Execution\Completion\Quality\Enumeration::FiniteExhaustive->value // => 'finite-exhaustive'
 * @visibility root
 */
enum Enumeration: string
{
    case FiniteExhaustive = 'finite-exhaustive';
    case NotEnumerated = 'not-enumerated';
}
