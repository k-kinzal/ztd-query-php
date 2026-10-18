<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call;

/**
 * Whether a evaluator handles, declines, or cannot support a call.
 * @visibility root
 */
enum DecisionKind: string
{
    case Handled = 'handled';
    case Declined = 'declined';
    case Unsupported = 'unsupported';
}
