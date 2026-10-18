<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator;

/**
 * The definition selected by a validated analysis query.
 * @visibility root
 */
enum TargetKind: string
{
    case Parameter = 'parameter';
    case Return = 'return';
    case Value = 'value';
    case Tuple = 'tuple';
    case State = 'state';
}
