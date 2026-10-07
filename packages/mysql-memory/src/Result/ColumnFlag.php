<?php

declare(strict_types=1);

namespace MySqlMemory\Result;

/**
 * A flag of a result column definition.
 *
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/group__group__cs__column__definition__flags.html.
 *
 * @visibility public
 * @example Combining flags
 *     \MySqlMemory\Result\ColumnFlag::NotNull->value | \MySqlMemory\Result\ColumnFlag::Unsigned->value // => 33
 */
enum ColumnFlag: int
{
    case NotNull = 1;
    case PrimaryKey = 2;
    case UniqueKey = 4;
    case MultipleKey = 8;
    case Blob = 16;
    case Unsigned = 32;
    case ZeroFill = 64;
    case Binary = 128;
    case Enum = 256;
    case AutoIncrement = 512;
    case Timestamp = 1024;
    case Set = 2048;
    case NoDefaultValue = 4096;
    case OnUpdateNow = 8192;
    case Numeric = 32768;
}
