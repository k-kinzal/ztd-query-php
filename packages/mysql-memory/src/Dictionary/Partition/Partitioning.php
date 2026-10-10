<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary\Partition;

use MySqlMemory\Evaluation\Evaluable;

/**
 * How a partitioned table splits its rows: the method, what the rows are partitioned by, and the partitions.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-types.html.
 *
 * @visibility MySqlMemory
 */
final class Partitioning
{
    /**
     * @param PartitionMethod $method The partitioning method
     * @param Evaluable|null $expression The expression RANGE, LIST and HASH partition by, read over a row of the table; null for COLUMNS and KEY
     * @param list<int> $columns The positions of the columns RANGE COLUMNS, LIST COLUMNS and KEY partition by
     * @param list<Partition> $partitions The partitions in order
     * @param bool $linear Whether HASH or KEY partitioning is LINEAR
     * @param string $text The partitioning as SHOW CREATE TABLE writes it, after the table options
     * @param list<int> $read The positions of the columns the partitioning reads, which every unique key must hold
     */
    public function __construct(
        public readonly PartitionMethod $method,
        public readonly ?Evaluable $expression,
        public readonly array $columns,
        public readonly array $partitions,
        public readonly bool $linear = false,
        public readonly string $text = '',
        public readonly array $read = [],
    ) {
    }

    /**
     * Finds a partition by name, compared without regard to case, or answers null.
     */
    public function partition(string $name): ?int
    {
        foreach ($this->partitions as $index => $partition) {
            if (strcasecmp($partition->name, $name) === 0) {
                return $index;
            }
        }

        return null;
    }
}
