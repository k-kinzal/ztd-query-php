<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `REORGANIZE PARTITION [NO_WRITE_TO_BINLOG] [p, … INTO (definitions)]`: a request to redistribute rows into new partitions.
 *
 * Mirrors PT_alter_table_reorganize_partition (no partitions: rebuild every
 * partition of a HASH or KEY table) and
 * PT_alter_table_reorganize_partition_into. LOCAL and NO_WRITE_TO_BINLOG
 * are synonyms. The partition values are constants.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-management-range-list.html.
 *
 * @visibility public
 * @example Splitting a partition
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t REORGANIZE PARTITION p0 INTO (PARTITION a VALUES LESS THAN (5), PARTITION b VALUES LESS THAN (10))');
 *     count($alter->statement->commands[0]->definitions) // => 2
 */
final class ReorganizePartition implements StandaloneCommand
{
    use Snapshot;

    /**
     * @var list<PartitionDefinition> The new partitions in order
     */
    public readonly array $definitions;

    /**
     * @param bool $local Whether NO_WRITE_TO_BINLOG or LOCAL is written
     * @param NamedPartitions|null $partitions The partitions to reorganize, or null for none written
     * @param list<PartitionDefinition> $definitions The new partitions in order; at least one exactly when partitions are named
     */
    public function __construct(public readonly bool $local, public readonly ?NamedPartitions $partitions = null, array $definitions = [])
    {
        $this->definitions = Check::listOf($definitions, PartitionDefinition::class, 'REORGANIZE PARTITION holds a list of partition definitions.');
        Check::input(($partitions === null) === ($definitions === []), 'Named partitions are reorganized INTO new definitions.');
    }

    /**
     * Derives the partition values as constants.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        foreach ($this->definitions as $definition) {
            $definition->derivePartition($derivation);
        }
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('REORGANIZE', 'PARTITION');
        if ($this->local) {
            $out->keyword('NO_WRITE_TO_BINLOG');
        }
        if ($this->partitions !== null) {
            $out->node($this->partitions)->keyword('INTO')->symbol('(')->list($this->definitions)->symbol(')');
        }
    }
}
