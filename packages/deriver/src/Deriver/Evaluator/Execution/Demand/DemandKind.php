<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Demand;

/**
 * The semantic information requested by a demand.
 * @visibility root
 */
enum DemandKind: string
{
    case Value = 'value';
    case Location = 'location';
    case Dispatch = 'dispatch';
    case ReceiverType = 'receiver-type';
    case Effect = 'effect';
}
