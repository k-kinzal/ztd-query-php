<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Message;

/**
 * SQLSTATE and message definitions owned by the PartitionError cases.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * @visibility MySqlMemory\Error
 */
final class PartitionMessages
{
    /**
     * @var array<string, array{string, string}> SQLSTATE and format, indexed by the owning enum case
     */
    public const DEFINITIONS = [
        'MissingValues' => ['HY000', 'Syntax error: %s PARTITIONING requires definition of VALUES %s for each partition'],
        'WrongValuesKind' => ['HY000', 'Only %s PARTITIONING can use VALUES %s in partition definition'],
        'MaxValueNotLast' => ['HY000', 'MAXVALUE can only be used in last partition definition'],
        'SubpartitionCount' => ['HY000', 'Wrong number of subpartitions defined, mismatch with previous setting'],
        'ConstantFunction' => ['HY000', 'Constant, random or timezone-dependent expressions in (sub)partitioning function are not allowed'],
        'PartitionsNotDefined' => ['HY000', 'For %s partitions each partition must be defined'],
        'RangeNotIncreasing' => ['HY000', 'VALUES LESS THAN value must be strictly increasing for each partition'],
        'ListConstantTwice' => ['HY000', 'Multiple definition of same constant in list partitioning'],
        'TooManyPartitions' => ['HY000', 'Too many partitions (including subpartitions) were defined'],
        'MixedSubpartitionMethods' => ['HY000', 'It is only possible to mix RANGE/LIST partitioning with HASH/KEY partitioning for subpartitioning'],
        'UniqueKeyColumns' => ['HY000', 'A %s must include all columns in the table\'s partitioning function (prefixed columns are not considered).'],
        'NoPartitions' => ['HY000', 'Number of partitions = 0 is not an allowed value'],
        'ForeignKeys' => ['HY000', 'Foreign keys are not yet supported in conjunction with partitioning'],
        'PartitionListError' => ['HY000', 'Error in list of partitions to %s'],
        'DropAllPartitions' => ['HY000', 'Cannot remove all partitions, use DROP TABLE instead'],
        'CoalesceOnlyHashKey' => ['HY000', 'COALESCE PARTITION can only be used on HASH/KEY partitions'],
        'ReorganizeHashCount' => ['HY000', 'REORGANIZE PARTITION can only be used to reorganize partitions not to change their numbers'],
        'ReorganizeWithoutNames' => ['HY000', 'REORGANIZE PARTITION without parameters can only be used on auto-partitioned tables using HASH PARTITIONs'],
        'OnlyRangeList' => ['HY000', '%s PARTITION can only be used on RANGE/LIST partitions'],
        'AddedSubpartitionCount' => ['HY000', 'Trying to Add partition(s) with wrong number of subpartitions'],
        'NoPartitionsAdded' => ['HY000', 'At least one partition must be added'],
        'NoPartitionsCoalesced' => ['HY000', 'At least one partition must be coalesced'],
        'DuplicatePartition' => ['HY000', 'Duplicate partition name %s'],
        'ReorganizeConsecutive' => ['HY000', 'When reorganizing a set of partitions they must be in consecutive order'],
        'ReorganizeRange' => ['HY000', 'Reorganize of range partitions cannot change total ranges except for last partition where it can extend the range'],
        'NoPartitionForValue' => ['HY000', 'Table has no partition for value %s'],
        'TemporaryPartitioned' => ['HY000', 'Cannot create temporary table with partitions'],
        'ConstantOutOfDomain' => ['HY000', 'Partition constant is out of partition function domain'],
        'FunctionNotAllowed' => ['HY000', 'This partition function is not allowed'],
        'NullInLessThan' => ['HY000', 'Not allowed to use NULL value in VALUES LESS THAN'],
        'IncorrectName' => ['HY000', 'Incorrect partition name'],
        'FieldTypeNotAllowed' => ['HY000', 'Field \'%s\' is of a not allowed type for this type of partitioning'],
        'ValuesNotInteger' => ['HY000', 'VALUES value for partition \'%s\' must have type INT'],
        'RowNotInPartitions' => ['HY000', 'Found a row not matching the given partition set'],
    ];
}
