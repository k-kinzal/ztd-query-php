<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

/**
 * A native MySQL collation for a checked utf8mb4 VARCHAR field.
 *
 * @visibility public
 * @example Identifying the PAD SPACE binary collation
 *     \SqlSemantics\Platform\MySql\Ir\From\TextCollation::Utf8mb4Bin->value // => 'utf8mb4_bin'
 */
enum TextCollation: string
{
    case Utf8mb4Bin = 'utf8mb4_bin';
}
