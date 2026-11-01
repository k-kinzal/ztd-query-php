<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary\Partition;

/**
 * One partition of a partitioned table: its name and the values it holds.
 *
 * A RANGE partition holds the rows below its bound and at or above the bound of the partition
 * before it; MAXVALUE is no bound. A LIST partition holds the rows whose value is one of its
 * values. A HASH or KEY partition holds the rows whose hash falls on it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-types.html.
 *
 * @visibility MySqlMemory
 */
final class Partition
{
    /**
     * @param string $name The partition name
     * @param list<int|float|string|null>|null $bound The upper bound of a RANGE partition, a value per partitioning column; null for MAXVALUE or another method
     * @param list<list<int|float|string|null>> $values The values of a LIST partition, a value per partitioning column each
     */
    public function __construct(public readonly string $name, public readonly ?array $bound = null, public readonly array $values = [])
    {
    }
}
