<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\State;

/**
 * Whether unavailable code may change a reachable evaluator state slot.
 * @visibility root
 */
enum InvalidationPolicy: string
{
    case Havoc = 'havoc';
    case Preserve = 'preserve';
}
