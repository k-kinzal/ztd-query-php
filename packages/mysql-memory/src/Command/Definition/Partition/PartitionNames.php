<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Partition;

use MySqlMemory\Error\Family\PartitionError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;

/**
 * Checks the names of reached partition definitions, including subpartitions.
 *
 * Stored instructions retain these names without opening their target table.
 * CREATE checks them before the existing-table shortcut; ALTER opens its target
 * first. Oversized identifiers have already been refused during input handling.
 *
 * @visibility MySqlMemory
 */
final class PartitionNames
{
    /**
     * Checks mandatory definitions and literal counts before the table's columns.
     *
     * Legacy releases postpone the upper count bound until after columns.
     * Observed through SQL on MySQL 5.7.44 and 8.4.7.
     *
     * @throws SqlError When a required partition definition or count is invalid
     */
    public function required(?Partitioning $partitioning, bool $legacy): void
    {
        if (!$partitioning instanceof PartitionClause) {
            return;
        }
        $method = $partitioning->method;
        if ($partitioning->definitions === [] && ($method instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod || $method instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod)) {
            throw PartitionError::PartitionsNotDefined->error($method->kind->value);
        }
        $count = $partitioning->partitions === null ? 1 : (new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->option32($partitioning->partitions);
        $count = $legacy ? $count : max(1, $count);
        if ($count === 0) {
            throw PartitionError::NoPartitions->error();
        }
        if (!$legacy && $count > PartitionDefinitions::LIMIT) {
            throw PartitionError::TooManyPartitions->error();
        }
    }

    /**
     * Refuses empty names and names ending in a space at definition preparation.
     *
     * @throws SqlError When a partition identifier has an incorrect spelling
     */
    public function check(?Partitioning $partitioning): void
    {
        foreach ($partitioning instanceof PartitionClause ? $partitioning->definitions : [] as $partition) {
            foreach ([$partition, ...$partition->subpartitions] as $definition) {
                if ($definition->name->value === '' || str_ends_with($definition->name->value, ' ')) {
                    throw PartitionError::IncorrectName->error();
                }
            }
        }
    }
}
