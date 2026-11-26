<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

/**
 * The transition requested for a stock SQLite flag.
 *
 * @visibility public
 * @example Inspecting the native schema reload operation
 *     \SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagChange::Reset->value // => 'RESET'
 */
enum FlagChange: string
{
    case Disable = '0';
    case Enable = '1';
    case Reset = 'RESET';
}
