<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

/**
 * The native signed integer storage of a declared column.
 *
 * @visibility public
 * @example Reading the small signed integer spelling
 *     \SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage::Small->value // => 'SMALLINT'
 */
enum IntegerStorage: string
{
    case Small = 'SMALLINT';
    case Standard = 'INTEGER';
    case Wide = 'BIGINT';
}
