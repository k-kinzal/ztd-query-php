<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Control;

/**
 * The active body of an exception handler.
 * @visibility root
 */
enum HandlerPhase: string
{
    case Try = 'try';
    case Catch = 'catch';
    case Finally = 'finally';
}
