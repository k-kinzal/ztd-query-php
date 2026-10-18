<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Partition;

use MySqlMemory\Dictionary\Partition\PartitionMethod;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\PartitionError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Storage\Partitions;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Partition\DefinitionShape;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\ReorganizePartition;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Replaces a contiguous partition interval and checks its resolved range.
 *
 * Definition roles are checked against the existing method before partition
 * names. RANGE boundaries retain their total interval, with extension allowed
 * at the last partition. SQL observations cover MySQL 5.6 through 9.1.
 *
 * @visibility MySqlMemory
 */
final class Reorganization
{
    /**
     * Builds the new layout without changing the stored rows.
     *
     * @throws SqlError When the method or selected interval is incompatible
     */
    public function rewrite(PartitionClause $clause, ReorganizePartition $command): PartitionClause
    {
        $ranged = $clause->method instanceof ExpressionMethod || $clause->method instanceof ColumnsMethod;
        if ($command->partitions === null) {
            if ($ranged) {
                throw PartitionError::ReorganizeWithoutNames->error();
            }
            return $clause;
        }
        $this->roles($clause, $command);
        $definitions = $clause->definitions === [] ? array_map(static fn (int $index): PartitionDefinition => new PartitionDefinition(new Name('p' . $index), null, [], []), range(0, (int) ($clause->partitions->text ?? '1') - 1)) : $clause->definitions;
        $count = $clause->subpartitioning === null ? 0 : (int) ($clause->subpartitioning->count->text ?? (string) count($definitions[0]->subpartitions));
        $replacement = $this->subpartitions($command->definitions, $count);
        $definitions = $this->subpartitions($definitions, $count);
        $names = array_map(static fn (PartitionDefinition $definition): string => mb_strtolower($definition->name->value), $definitions);
        $selected = array_map(static fn (Name $name): string => mb_strtolower($name->value), $command->partitions->names);
        $positions = array_keys(array_intersect($names, $selected));
        if ($positions === [] || count($positions) !== count($selected)) {
            throw PartitionError::PartitionListError->error('REORGANIZE');
        }
        $first = min($positions);
        if ($positions !== range($first, max($positions))) {
            throw PartitionError::ReorganizeConsecutive->error();
        }
        if (!$ranged && count($selected) !== count($command->definitions)) {
            throw PartitionError::ReorganizeHashCount->error();
        }
        array_splice($definitions, $first, count($positions), $replacement);
        return new PartitionClause($clause->method, null, $clause->subpartitioning, $definitions);
    }

    /**
     * Expands omitted subpartition definitions under the table's existing count.
     *
     * @param list<PartitionDefinition> $definitions The written partition definitions
     * @return list<PartitionDefinition>
     * @throws SqlError When an explicit list contradicts the stored count
     */
    public function subpartitions(array $definitions, int $count): array
    {
        return array_map(static function (PartitionDefinition $definition) use ($count): PartitionDefinition {
            if ($definition->subpartitions !== []) {
                if (count($definition->subpartitions) !== $count) {
                    throw PartitionError::SubpartitionCount->error();
                }
                return $definition;
            }
            $subpartitions = $count === 0 ? [] : array_map(static fn (int $index): \SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition => new \SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition(new Name($definition->name->value . 'sp' . $index), []), range(0, $count - 1));
            return new PartitionDefinition($definition->name, $definition->values, $definition->options, $subpartitions);
        }, $definitions);
    }

    /**
     * Checks operands whose roles depend on the existing table's method.
     *
     * @throws SqlError When a replacement bound disagrees with the existing method
     */
    public function roles(PartitionClause $clause, ReorganizePartition $command): void
    {
        foreach ($command->definitions as $definition) {
            $problem = (new DefinitionShape())->problem($definition, $clause->method, null);
            if ($problem !== null) {
                throw new SqlError($problem === DefinitionIssue::MissingRangeValues || $problem === DefinitionIssue::MissingListValues ? PartitionError::MissingValues : PartitionError::WrongValuesKind, $problem->message());
            }
        }
    }

    /**
     * Checks evaluated RANGE bounds after the replacement definition has been built.
     *
     * @throws SqlError When the replacement changes a protected range
     */
    public function validate(TableDefinition $old, TableDefinition $new, ReorganizePartition $command, Context $context): void
    {
        $before = $old->partitioning;
        $after = $new->partitioning;
        if ($command->partitions === null || $before === null || $after === null || !in_array($before->method, [PartitionMethod::Range, PartitionMethod::RangeColumns], true)) {
            return;
        }
        $selected = array_map(static fn (Name $name): string => mb_strtolower($name->value), $command->partitions->names);
        $positions = array_keys(array_filter($before->partitions, static fn ($partition): bool => in_array(mb_strtolower($partition->name), $selected, true)));
        \SqlSemantics\Diagnostic\Check::invariant($positions !== [], 'The partition replacement retains its selected interval.');
        $last = max($positions);
        $bound = $before->partitions[$last]->bound;
        $replacement = $after->partitions[min($positions) + count($command->definitions) - 1]->bound;
        $comparison = new Partitions($old, $before, $context);
        $equal = $bound === null || $replacement === null ? $bound === $replacement : $comparison->equal($bound, $replacement);
        $extends = $last === count($before->partitions) - 1 && $bound !== null && ($replacement === null || $comparison->below($bound, $replacement));
        if (!$equal && !$extends) {
            throw PartitionError::ReorganizeRange->error();
        }
    }

    /**
     * Counts the selected rows copied by the legacy partition handler.
     */
    public function affected(StoredTable $table, ReorganizePartition $command, Context $context): int
    {
        if ($context->modes->release !== GrammarRelease::MySql5651 || $table->definition->partitioning === null) {
            return 0;
        }
        $partitions = new Partitions($table->definition, $table->definition->partitioning, $context);
        $selected = $command->partitions === null ? null : $partitions->selected($command->partitions->names);
        return count($partitions->ordered($table->data->rows, $selected));
    }
}
