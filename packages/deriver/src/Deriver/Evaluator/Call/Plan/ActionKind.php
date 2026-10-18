<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\Plan;

/**
 * Ordered effects and completions supported by a semantic plan.
 * @visibility root
 */
enum ActionKind: string
{
    case Return = 'return';
    case Throw = 'throw';
    case ReturnReference = 'return-reference';
    case StateWrite = 'state-write';
    case WriteParameter = 'write-parameter';
    case LocationWrite = 'location-write';
    case Callback = 'callback';
    case Invoke = 'invoke';
    case Allocate = 'allocate';
    case Alias = 'alias';
    case Choice = 'choice';
    case Havoc = 'havoc';
}
