<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache;

/**
 * A connection spill switch without a new pager threshold.
 *
 * @visibility public
 * @example Disabling cache spill
 *     \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillMode::Disabled->value // => 'OFF'
 */
enum SpillMode: string
{
    case Enabled = 'ON';
    case Disabled = 'OFF';
}
