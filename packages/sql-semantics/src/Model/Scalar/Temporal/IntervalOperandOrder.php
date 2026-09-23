<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Temporal;

/**
 * Order of temporal and interval inputs, preserving positional parameter binding order.
 * @visibility public
 * @example Reading the interval first
 *     \SqlSemantics\Model\Scalar\Temporal\IntervalOperandOrder::IntervalFirst->value // => 'interval-first'
 */
enum IntervalOperandOrder: string
{
    case TemporalFirst = 'temporal-first';
    case IntervalFirst = 'interval-first';
}
