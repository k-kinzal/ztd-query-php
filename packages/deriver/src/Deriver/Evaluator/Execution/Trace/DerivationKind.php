<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Trace;

/**
 * The dependency recorded by an execution explanation edge.
 * @example Reading a classification identifier
 *     \Deriver\Deriver\Evaluator\Execution\Trace\DerivationKind::Data->value // => 'data'
 * @visibility root
 */
enum DerivationKind: string
{
    case Data = 'data';
    case Effect = 'effect';
    case Call = 'call';
}
