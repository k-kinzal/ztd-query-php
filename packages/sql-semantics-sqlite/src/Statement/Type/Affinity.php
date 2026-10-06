<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Type;

/**
 * The type affinity SQLite derives from a declared column type.
 *
 * Source: https://sqlite.org/datatype3.html#determination_of_column_affinity.
 *
 * @visibility public
 * @example Deriving the affinity of a declared type
 *     (new \SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain('VARCHAR(10)'))->affinity // => \SqlSemantics\Platform\Sqlite\Statement\Type\Affinity::Text
 */
enum Affinity
{
    case Integer;
    case Text;
    case Blob;
    case Real;
    case Numeric;
}
