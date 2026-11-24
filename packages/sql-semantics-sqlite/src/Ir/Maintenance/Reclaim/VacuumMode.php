<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim;

/**
 * The effective native automatic page reclamation mode.
 *
 * @visibility public
 * @example Selecting incremental reclamation
 *     \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\VacuumMode::Incremental->value // => '2'
 */
enum VacuumMode: string
{
    case None = '0';
    case Full = '1';
    case Incremental = '2';
}
