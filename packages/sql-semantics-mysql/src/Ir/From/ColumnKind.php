<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

/**
 * The native signed integer type of a MySQL input slot.
 *
 * @visibility public
 * @example Reading the signed storage name
 *     \SqlSemantics\Platform\MySql\Ir\From\ColumnKind::SignedInt->value // => 'int'
 */
enum ColumnKind: string
{
    case SignedInt = 'int';
}
