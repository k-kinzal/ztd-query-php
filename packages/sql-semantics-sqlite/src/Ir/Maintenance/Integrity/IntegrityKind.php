<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity;

/**
 * The native SQLite integrity algorithms.
 *
 * @visibility public
 * @example Selecting the abbreviated algorithm
 *     \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityKind::Quick->value // => 'quick_check'
 */
enum IntegrityKind: string
{
    case Full = 'integrity_check';
    case Quick = 'quick_check';
}
