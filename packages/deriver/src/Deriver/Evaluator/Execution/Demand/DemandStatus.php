<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Demand;

/**
 * Progress of a demand, independent of whether its value set is empty.
 * @visibility root
 */
enum DemandStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Stable = 'stable';
    case Frontier = 'frontier';
}
