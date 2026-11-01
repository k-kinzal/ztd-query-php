<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Partition;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\MissingPartitions;

/**
 * Checks definition-local bounds and the shared subpartition count.
 *
 * An ALTER action without its table's method still has a consistent list shape.
 * A list with no explicit subpartitions may use the declared default count.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-subpartitions.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DefinitionShape
{
    /**
     * Excludes inconsistent subpartition lists at public constructor boundaries.
     * Bound and method disagreements remain complete executable definitions.
     *
     * @param list<PartitionDefinition> $definitions The definitions in order
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When definitions disagree
     */
    public function check(array $definitions, ?int $count = null): void
    {
        $expected = ($definitions[0]->subpartitions ?? []) === [] ? 0 : $count;
        foreach ($definitions as $definition) {
            $problem = $this->problem($definition, null, $expected);
            Check::input($problem === null, $problem?->message() ?? 'Partition definitions agree.');
            $expected ??= count($definition->subpartitions);
        }
    }

    /**
     * Answers the first local incompatibility, before subsequent definitions.
     */
    public function problem(PartitionDefinition $definition, ?PartitionMethod $method, ?int $expected): ?DefinitionIssue
    {
        $kind = $method instanceof ExpressionMethod || $method instanceof ColumnsMethod || $method instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod ? $method->kind : null;
        if ($method !== null) {
            if ($definition->values === null && $kind !== null) {
                return $kind === PartitionKind::Range ? DefinitionIssue::MissingRangeValues : DefinitionIssue::MissingListValues;
            }
            if ($definition->values instanceof LessThan && $kind !== PartitionKind::Range) {
                return DefinitionIssue::RangeValues;
            }
            if ($definition->values !== null && !$definition->values instanceof LessThan && $kind !== PartitionKind::List) {
                return DefinitionIssue::ListValues;
            }
        }
        return $expected !== null && count($definition->subpartitions) !== $expected ? DefinitionIssue::SubpartitionCount : null;
    }

    /**
     * Answers the first condition that keeps a partitioning from being executable.
     *
     * Every definition is checked against the method before the written count:
     * RANGE needs VALUES LESS THAN and LIST needs VALUES IN for each partition,
     * no other method takes values, RANGE and LIST need at least one partition,
     * and a written PARTITIONS count equals the number of definitions.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-range.html,
     * https://dev.mysql.com/doc/refman/8.4/en/partitioning-list.html,
     * https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-partitioning.
     *
     * @param list<PartitionDefinition> $definitions The definitions in order
     */
    public function clause(PartitionMethod $method, ?\SqlSemantics\Platform\MySql\Statement\Literal\Numeral $partitions, array $definitions): MissingPartitions|DefinitionIssue|null
    {
        foreach ($definitions as $definition) {
            $problem = $this->problem($definition, $method, null);
            if ($problem !== null) {
                return $problem;
            }
        }
        $kind = $method instanceof ExpressionMethod || $method instanceof ColumnsMethod || $method instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod ? $method->kind : null;
        if ($definitions === []) {
            return $kind === null ? null : new MissingPartitions($kind);
        }
        $magnitudes = new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes();
        return $partitions !== null && $magnitudes->integral($partitions) && $magnitudes->option32($partitions) !== count($definitions) ? DefinitionIssue::PartitionCount : null;
    }
}
