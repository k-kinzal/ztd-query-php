<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionBound;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * `PARTITION name [VALUES …] [options] [(subpartitions)]`: one partition of a partitioned table.
 *
 * Mirrors PT_part_definition. RANGE and LIST partitions state their values;
 * KEY and HASH partitions state none. An empty subpartition list means none
 * is written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-partitioning.
 *
 * @visibility public
 * @example Holding a range partition
 *     $partition = new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition(new \SqlSemantics\Statement\Identifier\Name('p0'), new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan(null), [], []);
 *     [$partition->name->value, $partition->values?->row] // => ['p0', null]
 */
final class PartitionDefinition implements Node
{
    use Snapshot;

    /**
     * @var list<PartitionOption> The options in order
     */
    public readonly array $options;

    /**
     * @var list<SubpartitionDefinition> The subpartitions in order
     */
    public readonly array $subpartitions;

    /**
     * @param Name $name The partition name
     * @param PartitionBound|null $values The partition values, when written
     * @param list<PartitionOption> $options The options in order
     * @param list<SubpartitionDefinition> $subpartitions The subpartitions in order
     */
    public function __construct(public readonly Name $name, public readonly ?PartitionBound $values, array $options, array $subpartitions)
    {
        $this->options = Check::listOf($options, PartitionOption::class, 'Partition options are a list of partition options.');
        $this->subpartitions = Check::listOf($subpartitions, SubpartitionDefinition::class, 'Subpartitions are a list of subpartition definitions.');
    }

    /**
     * Derives the partition values as constants.
     */
    public function derivePartition(Derivation $derivation): void
    {
        $this->values?->deriveBound($derivation);
    }

    /**
     * Writes the definition.
     */
    public function render(Output $out): void
    {
        $out->keyword('PARTITION')->name($this->name, NameUse::Label)->node($this->values);
        foreach ($this->options as $option) {
            $out->node($option);
        }
        if ($this->subpartitions !== []) {
            $out->symbol('(')->list($this->subpartitions)->symbol(')');
        }
    }
}
