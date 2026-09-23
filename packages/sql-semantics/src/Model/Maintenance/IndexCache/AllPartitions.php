<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\IndexCache;

/**
 * Every partition of the selected table.
 * @visibility public
 * @example Choosing the built-in selection
 *     \SqlSemantics\Model\Maintenance\IndexCache\AllPartitions::All->value // => 'ALL'
 */
enum AllPartitions: string
{
    case All = 'ALL';
}
