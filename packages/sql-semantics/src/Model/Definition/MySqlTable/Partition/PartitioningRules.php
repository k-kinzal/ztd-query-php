<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Partition;

use SqlSemantics\Model\Definition\Relation\Partition\RangeBoundary;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Partition\PartitionStrategy;

/**
 * Checks the consistency MySQL requires between a partitioning function, its counts, and its partitions.
 * @visibility SqlSemantics
 */
final class PartitioningRules
{
    /**
     * Counts are positive, bounded by 8192 partitions, and agree with explicit partitions.
     * @param list<PartitionDefinition> $partitions
     * @throws InvalidStructure
     */
    public static function counts(?int $partitionCount, HashPartitioning|KeyPartitioning|null $subpartitioning, ?int $subpartitionCount, array $partitions): void
    {
        foreach ([$partitionCount, $subpartitionCount] as $count) {
            if ($count !== null && ($count < 1 || $count > 8192)) {
                throw new InvalidStructure('A partition count is between 1 and 8192.');
            }
        }
        if ($partitionCount !== null && $partitions !== [] && $partitionCount !== count($partitions)) {
            throw new InvalidStructure('PARTITIONS must agree with the number of partition definitions.');
        }
        if ($subpartitionCount !== null && $subpartitioning === null) {
            throw new InvalidStructure('SUBPARTITIONS requires SUBPARTITION BY.');
        }
    }

    /**
     * RANGE and LIST partitions carry values of their kind and width; HASH and KEY partitions carry none.
     * @param list<PartitionDefinition> $partitions
     * @throws InvalidStructure
     */
    public static function values(PartitionFunction $function, array $partitions): void
    {
        $strategy = $function instanceof ExpressionPartitioning || $function instanceof ColumnsPartitioning ? $function->strategy : null;
        $width = $function instanceof ColumnsPartitioning ? count($function->columns) : 1;
        if ($strategy !== null && $partitions === []) {
            throw new InvalidStructure('RANGE and LIST partitioning require partition definitions.');
        }
        foreach ($partitions as $partition) {
            $values = $partition->values;
            $expected = match ($strategy) {
                PartitionStrategy::Range => RangeBound::class,
                PartitionStrategy::List => ListBound::class,
                null, PartitionStrategy::Hash => null,
            };
            if ($expected === null ? $values !== null : !$values instanceof $expected) {
                throw new InvalidStructure('Partition values must match the partitioning strategy.');
            }
            $widths = $values instanceof ListBound ? array_map(count(...), $values->tuples) : ($values instanceof RangeBound ? [count($values->bound)] : []);
            foreach ($widths as $actual) {
                if ($actual !== $width) {
                    throw new InvalidStructure('Partition values need one value per partitioning column.');
                }
            }
        }
    }

    /**
     * Only RANGE and LIST subpartition, and explicit subpartitions agree in number.
     * @param list<PartitionDefinition> $partitions
     * @throws InvalidStructure
     */
    public static function subpartitions(PartitionFunction $function, HashPartitioning|KeyPartitioning|null $subpartitioning, ?int $subpartitionCount, array $partitions): void
    {
        if ($subpartitioning !== null && !$function instanceof ExpressionPartitioning && !$function instanceof ColumnsPartitioning) {
            throw new InvalidStructure('Only RANGE and LIST partitioning can be subpartitioned.');
        }
        if ($subpartitioning instanceof KeyPartitioning && $subpartitioning->columns === []) {
            throw new InvalidStructure('SUBPARTITION BY KEY names its columns.');
        }
        $counts = array_values(array_unique(array_map(static fn (PartitionDefinition $partition): int => count($partition->subpartitions), $partitions)));
        if ($counts === [] || $counts === [0]) {
            return;
        }
        if ($subpartitioning === null || count($counts) !== 1 || ($subpartitionCount !== null && $counts[0] !== $subpartitionCount)) {
            throw new InvalidStructure('Every partition defines the same number of subpartitions of a SUBPARTITION BY clause.');
        }
    }

    /**
     * Partition and subpartition names are unique within the table, ignoring case.
     * @param list<PartitionDefinition> $partitions
     * @throws InvalidStructure
     */
    public static function names(array $partitions): void
    {
        $names = [];
        foreach ($partitions as $partition) {
            $names[] = strtolower($partition->name);
            foreach ($partition->subpartitions as $subpartition) {
                $names[] = strtolower($subpartition->name);
            }
        }
        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidStructure('Partition and subpartition names must be unique.');
        }
    }

    /**
     * Reports whether a bound is the single MAXVALUE bound.
     */
    public static function unbounded(RangeBound $values): bool
    {
        return $values->bound === [RangeBoundary::MaxValue];
    }
}
