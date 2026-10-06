<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `ADD PARTITION [NO_WRITE_TO_BINLOG] [(definitions) | PARTITIONS n]`: a request to add partitions.
 *
 * Mirrors PT_alter_table_add_partition, PT_alter_table_add_partition_def_list
 * and PT_alter_table_add_partition_num: definitions for RANGE and LIST
 * tables, a count for HASH and KEY tables, or neither. LOCAL and
 * NO_WRITE_TO_BINLOG are synonyms. The partition values are constants.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table-partition-operations.html.
 *
 * @visibility public
 * @example Adding two hash partitions
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t ADD PARTITION PARTITIONS 2');
 *     $alter->statement->commands[0]->count?->text // => '2'
 */
final class AddPartition implements StandaloneCommand
{
    use Snapshot;

    /**
     * @var list<PartitionDefinition> The new partitions in order
     */
    public readonly array $definitions;

    /**
     * @param bool $local Whether NO_WRITE_TO_BINLOG or LOCAL is written
     * @param list<PartitionDefinition> $definitions The new partitions in order
     * @param Numeral|null $count The PARTITIONS count, when written
     */
    public function __construct(public readonly bool $local, array $definitions, public readonly ?Numeral $count = null)
    {
        $this->definitions = Check::listOf($definitions, PartitionDefinition::class, 'ADD PARTITION holds a list of partition definitions.');
        Check::input($definitions === [] || $count === null, 'ADD PARTITION gives definitions or a count, not both.');
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
        $out->keyword('ADD', 'PARTITION');
        if ($this->local) {
            $out->keyword('NO_WRITE_TO_BINLOG');
        }
        if ($this->definitions !== []) {
            $out->symbol('(')->list($this->definitions)->symbol(')');
        }
        if ($this->count !== null) {
            $out->keyword('PARTITIONS')->node($this->count);
        }
    }
}
