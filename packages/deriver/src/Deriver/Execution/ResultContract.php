<?php

declare(strict_types=1);

namespace Deriver\Deriver\Execution;

/**
 * The semantic contract used to interpret an execution result.
 * @example Reading a classification identifier
 *     \Deriver\Deriver\Execution\ResultContract::Execution->value // => 'execution'
 * @visibility root
 */
enum ResultContract: string
{
    case Execution = 'execution';
}
