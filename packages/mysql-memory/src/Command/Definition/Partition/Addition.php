<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Partition;

use MySqlMemory\Error\Family\PartitionError;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\AddPartition;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Resolves added definitions against the existing table's partition method.
 *
 * Missing bounds and incompatible subpartition lists are state-dependent SQL
 * conditions. They are checked before constructing the replacement clause.
 * Explicit HASH partition names survive so duplicate names remain observable.
 *
 * @visibility MySqlMemory
 */
final class Addition
{
    /**
     * Builds the proposed layout without publishing it or changing stored rows.
     */
    public function rewrite(PartitionClause $clause, AddPartition $command): PartitionClause
    {
        $reorganization = new Reorganization();
        $reorganization->roles($clause, $command);
        $count = $clause->definitions === [] ? ($clause->partitions === null ? 1 : (new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->option32($clause->partitions)) : count($clause->definitions);
        if ($command->definitions === []) {
            if ($clause->method instanceof ExpressionMethod || $clause->method instanceof ColumnsMethod) {
                throw PartitionError::PartitionsNotDefined->error($clause->method->kind->value);
            }
            $added = ($command->count === null ? 0 : (new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->option32($command->count));
            if ($added === 0) {
                throw PartitionError::NoPartitionsAdded->error();
            }
            return new PartitionClause($clause->method, new Numeral((string) ($count + $added)), $clause->subpartitioning, []);
        }
        $definitions = $clause->definitions === [] ? array_map(static fn (int $index): PartitionDefinition => new PartitionDefinition(new Name('p' . $index), null, [], []), range(0, $count - 1)) : $clause->definitions;
        $subpartitions = $clause->subpartitioning === null ? 0 : ($clause->subpartitioning->count === null ? max(1, count($definitions[0]->subpartitions)) : (new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->option32($clause->subpartitioning->count));
        foreach ($command->definitions as $definition) {
            if ($definition->subpartitions !== [] && count($definition->subpartitions) !== $subpartitions) {
                throw PartitionError::AddedSubpartitionCount->error();
            }
        }
        $names = array_map(static fn (PartitionDefinition $definition): string => mb_strtolower($definition->name->value), $definitions);
        foreach ($command->definitions as $definition) {
            if (in_array(mb_strtolower($definition->name->value), $names, true)) {
                throw PartitionError::DuplicatePartition->error($definition->name->value);
            }
            $names[] = mb_strtolower($definition->name->value);
        }
        $definitions = $reorganization->subpartitions($definitions, $subpartitions);
        $added = $reorganization->subpartitions($command->definitions, $subpartitions);
        return new PartitionClause($clause->method, null, $clause->subpartitioning, [...$definitions, ...$added]);
    }
}
