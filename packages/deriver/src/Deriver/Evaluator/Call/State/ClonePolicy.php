<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\State;

/**
 * Whether cloning preserves or reinitializes a evaluator state slot.
 * @visibility root
 */
enum ClonePolicy: string
{
    case Copy = 'copy';
    case Reset = 'reset';
}
