<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

/**
 * Identifies the checked native aggregate over signed input fields.
 *
 * @visibility public
 * @example Identifying the native sum operation
 *     \SqlSemantics\Platform\MySql\Ir\Aggregation\AggregateOperator::Sum->value // => 'sum'
 */
enum AggregateOperator: string
{
    case Count = 'count';
    case Sum = 'sum';
    case Min = 'min';
    case Max = 'max';
}
