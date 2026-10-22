<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Partition;

use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\PartitionBy;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;

/**
 * Identifies non-scalar partition methods retained only by ALTER instructions.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class MethodRoles
{
    /**
     * Identifies a retained method in either partitioning level.
     */
    public function deferred(?Partitioning $partitioning): bool
    {
        return $partitioning instanceof PartitionClause && ($partitioning->method instanceof DeferredMethod || $partitioning->subpartitioning?->method instanceof DeferredMethod);
    }

    /**
     * Identifies ALTER actions that require a retained instruction.
     *
     * @param list<AlterCommand> $commands The ordered alteration actions
     */
    public function commands(array $commands): bool
    {
        foreach ($commands as $command) {
            if ($command instanceof PartitionBy && $this->deferred($command->partitioning)) {
                return true;
            }
        }
        return false;
    }
}
