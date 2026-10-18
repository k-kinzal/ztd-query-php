<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Completion;

/**
 * Normal progress, a suspended completion, or an analysis boundary.
 * @visibility root
 */
enum CompletionKind: string
{
    case Normal = 'normal';
    case Return = 'return';
    case Throw = 'throw';
    case Jump = 'jump';
    case Exit = 'exit';
    case Observed = 'observed';
    case Residual = 'residual';
}
