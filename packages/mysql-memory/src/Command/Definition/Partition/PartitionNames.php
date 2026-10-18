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
