<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Partition;

use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\PartitionError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Storage\Partitions;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Checks partition destinations while CREATE TABLE materializes a query.
 * MySQL 5.6 skips ignored out-of-range rows without a warning and appends error
 * 1567 when aborting creation after error 1526. Later releases use write diagnostics.
 * Observed with RANGE and LIST on 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class QueryRows
{
    /**
     * @param TableDefinition $definition The complete destination definition
     * @param Context $context The current creation and its diagnostics
     */
    public function __construct(public readonly TableDefinition $definition, public readonly Context $context)
    {
    }

    /**
     * @param list<int|float|string|null> $row The proposed stored values
     * @throws SqlError When the source row cannot be stored and IGNORE is absent
     */
    public function accept(array $row, bool $ignore): bool
    {
        if ($this->definition->partitioning === null) {
            return true;
        }
        $partitions = new Partitions($this->definition, $this->definition->partitioning, $this->context);
        if ($this->context->modes->release !== GrammarRelease::MySql5651) {
            return $partitions->accept($row, null, $ignore);
        }
        try {
            $partitions->place($row, null);
        } catch (SqlError $error) {
            if ($error->error !== PartitionError::NoPartitionForValue) {
                throw $error;
            }
            if (!$ignore) {
                throw new SqlError($error->error, $error->getMessage(), $error, [[PartitionError::IncorrectName->number(), PartitionError::IncorrectName->message()]]);
            }
            return false;
        }
        return true;
    }
}
