<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Partition;

use LogicException;
use MySqlMemory\Command\Definition\TableChange;
use MySqlMemory\Error\Family\PartitionError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\AddPartition;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\CoalescePartition;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\DropPartition;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintainPartitions;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintenanceKind;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\PartitionBy;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\RemovePartitioning;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;

/**
 * Applies the partition actions of ALTER TABLE to the layout of a table.
 *
 * PARTITION BY partitions the table anew and REMOVE PARTITIONING unpartitions it, both copying
 * the rows. ADD PARTITION adds RANGE or LIST partitions after the others, or HASH and KEY
 * partitions to their number; COALESCE PARTITION takes HASH and KEY partitions away; DROP
 * PARTITION drops RANGE or LIST partitions with their rows, and TRUNCATE PARTITION empties
 * partitions. A table that is not partitioned refuses every action but PARTITION BY (verified on
 * a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-management.html.
 *
 * @visibility MySqlMemory
 */
final class PartitionChange
{
    /**
     * @param TableChange $change The change of the table
     */
    public function __construct(public readonly TableChange $change)
    {
    }

    /**
     * Applies a partition action.
     *
     * @throws SqlError When the table refuses the action
     * @throws LogicException When no implementation applies the action
     */
    public function apply(AlterCommand $command): void
    {
        $layout = $this->change->layout;
        if ($command instanceof PartitionBy) {
            $layout->partitioning = $command->partitioning;
            $this->change->copies = true;

            return;
        }
        $clause = $layout->partitioning;
        if (!$clause instanceof PartitionClause) {
            throw SchemaError::PartitionManagementOnNonpartitioned->error();
        }
        if ($command instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Partition\ReorganizePartition) {
            $layout->partitioning = (new Reorganization())->rewrite($clause, $command);
            $this->change->reorganization = $command;
            return;
        }
        $ranged = $clause->method instanceof ExpressionMethod || $clause->method instanceof ColumnsMethod;
        if ($command instanceof AddPartition && !$ranged && $this->change->release === \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            $this->change->copies = true;
        }
        match (true) {
            $command instanceof RemovePartitioning => $this->removed(),
            $command instanceof AddPartition => $layout->partitioning = (new Addition())->rewrite($clause, $command),
            $command instanceof CoalescePartition => $layout->partitioning = $this->coalesced($clause, $ranged, (new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->option32($command->count)),
            $command instanceof DropPartition => $layout->partitioning = $this->dropped($clause, $ranged, $command->partitions),
            $command instanceof MaintainPartitions && $command->kind === MaintenanceKind::Truncate => $this->truncated($clause, $command),
            $command instanceof MaintainPartitions && $command->kind === MaintenanceKind::Rebuild => $this->rebuilt($clause, $command),
            default => throw new LogicException('No partition alteration for ' . $command::class),
        };
    }

    /**
     * Removes the partitioning, copying the rows.
     */
    public function removed(): void
    {
        $this->change->layout->partitioning = null;
        $this->change->copies = true;
    }

    /**
     * Answers the number of partitions of a clause.
     */
    public function count(PartitionClause $clause): int
    {
        return $clause->definitions === [] ? ($clause->partitions === null ? 1 : (new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->option32($clause->partitions)) : count($clause->definitions);
    }

    /**
     * Answers a HASH or KEY clause with fewer partitions.
     *
     * @throws SqlError When the table is partitioned by RANGE or LIST, or no partition would remain
     */
    public function coalesced(PartitionClause $clause, bool $ranged, int $count): PartitionClause
    {
        if ($ranged) {
            throw PartitionError::CoalesceOnlyHashKey->error();
        }
        if ($count >= $this->count($clause)) {
            throw PartitionError::DropAllPartitions->error();
        }

        return new PartitionClause($clause->method, new Numeral((string) ($this->count($clause) - $count)), $clause->subpartitioning, []);
    }

    /**
     * Answers a RANGE or LIST clause without some partitions, whose rows go.
     *
     * @throws SqlError When the table is partitioned by HASH or KEY, a partition is unknown, or none would remain
     */
    public function dropped(PartitionClause $clause, bool $ranged, NamedPartitions $partitions): PartitionClause
    {
        if (!$ranged) {
            throw PartitionError::OnlyRangeList->error('DROP');
        }
        $names = array_map(static fn ($name): string => mb_strtolower($name->value), $partitions->names);
        $kept = array_values(array_filter($clause->definitions, static fn ($definition): bool => !in_array(mb_strtolower($definition->name->value), $names, true)));
        if (count($clause->definitions) - count($kept) !== count(array_unique($names))) {
            throw PartitionError::PartitionListError->error('DROP');
        }
        if ($kept === []) {
            throw PartitionError::DropAllPartitions->error();
        }
        foreach ($partitions->names as $name) {
            $this->change->emptied[] = $name->value;
        }

        return new PartitionClause($clause->method, null, $clause->subpartitioning, $kept);
    }

    /**
     * Empties partitions, or every partition.
     *
     * @throws SqlError When a partition is unknown
     */
    public function truncated(PartitionClause $clause, MaintainPartitions $command): void
    {
        array_push($this->change->emptied, ...$this->selected($clause, $command));
    }

    /**
     * Rebuilds selected partitions without changing their definition or rows.
     * MySQL 5.6 reports the selected rows copied by its legacy partition handler.
     *
     * @throws SqlError When a partition is unknown
     */
    public function rebuilt(PartitionClause $clause, MaintainPartitions $command): void
    {
        $this->selected($clause, $command);
        $this->change->rebuiltPartitions = $command->partitions;
    }

    /**
     * Counts the selected rows rebuilt by the legacy partition handler.
     */
    public function affected(\MySqlMemory\Dictionary\StoredTable $table, \MySqlMemory\Evaluation\Context $context): int
    {
        if ($context->modes->release !== \SqlSemantics\Contract\GrammarRelease::MySql5651 || $table->definition->partitioning === null) {
            return 0;
        }
        $partitions = new \MySqlMemory\Storage\Partitions($table->definition, $table->definition->partitioning, $context);
        $selection = $this->change->rebuiltPartitions;
        $selected = $selection instanceof NamedPartitions ? $partitions->selected($selection->names) : null;
        return count($partitions->ordered($table->data->rows, $selected));
    }

    /**
     * Resolves every requested partition before a maintenance action changes storage.
     *
     * @return list<string> The selected partition names
     * @throws SqlError When a partition is unknown
     */
    public function selected(PartitionClause $clause, MaintainPartitions $command): array
    {
        $names = [];
        if ($clause->definitions === []) {
            $names = array_map(static fn (int $index): string => 'p' . $index, range(0, $this->count($clause) - 1));
        }
        foreach ($clause->definitions as $definition) {
            $names[] = $definition->name->value;
        }
        $selected = $command->partitions instanceof NamedPartitions ? array_map(static fn ($name): string => $name->value, $command->partitions->names) : $names;
        foreach ($selected as $name) {
            if (!in_array(mb_strtolower($name), array_map(mb_strtolower(...), $names), true)) {
                if ($command->kind === MaintenanceKind::Rebuild) {
                    throw PartitionError::PartitionListError->error('REBUILD');
                }
                throw SchemaError::UnknownPartition->error($name, $this->change->table);
            }
        }
        return $selected;
    }
}
