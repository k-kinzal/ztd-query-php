<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Family;

use MySqlMemory\Error\CatalogedError;
use MySqlMemory\Error\ErrorCode;

/**
 * A server error about the partitions of a table: how a table is partitioned, and the rows a partition holds.
 *
 * The SQLSTATE and message format of each error are those of the server error reference, which resources/errors.php holds.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Building the error of a row no partition holds
 *     \MySqlMemory\Error\Family\PartitionError::NoPartitionForValue->error('5')->getMessage() // => 'Table has no partition for value 5'
 */
enum PartitionError: int implements ErrorCode
{
    use CatalogedError;

    case WrongValuesKind = 1480;
    case MaxValueNotLast = 1481;
    case PartitionsNotDefined = 1492;
    case RangeNotIncreasing = 1493;
    case ListConstantTwice = 1495;
    case TooManyPartitions = 1499;
    case UniqueKeyColumns = 1503;
    case NoPartitions = 1504;
    case ForeignKeys = 1506;
    case PartitionListError = 1507;
    case DropAllPartitions = 1508;
    case CoalesceOnlyHashKey = 1509;
    case OnlyRangeList = 1512;
    case DuplicatePartition = 1517;
    case NoPartitionForValue = 1526;
    case TemporaryPartitioned = 1562;
    case ConstantOutOfDomain = 1563;
    case FunctionNotAllowed = 1564;
    case NullInLessThan = 1566;
    case FieldTypeNotAllowed = 1659;
    case ValuesNotInteger = 1697;
    case RowNotInPartitions = 1748;
}
