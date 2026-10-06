<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionMethod;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `PARTITION BY method [PARTITIONS n] [SUBPARTITION BY …] [(definitions)]`: how a table is partitioned.
 *
 * Mirrors PT_partition. Rule: MYSQL-PARTITIONING-001. The partitioning
 * function and the partitioning columns are derived in the scope of the
 * partitioned table, which the statement holding the clause provides: an
 * expression gets the facts of its column references, a column name the
 * completely known table does not have is the diagnostic UnknownColumn. The
 * partition values are constants and see no column. An empty definition
 * list means none is written. Terminates: one pass over the definitions.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-partitioning.
 * Status: Implemented.
 *
 * @visibility public
 * @example Holding a hash partitioning into four partitions
 *     $clause = new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause(new \SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod(false, null, []), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('4'), null, []);
 *     $clause->partitions?->text // => '4'
 */
final class PartitionClause implements Partitioning
{
    use Snapshot;

    /**
     * @var list<PartitionDefinition> The partition definitions in order
     */
    public readonly array $definitions;

    /**
     * @param PartitionMethod $method The partitioning method
     * @param Numeral|null $partitions The PARTITIONS count, when written
     * @param Subpartitioning|null $subpartitioning The subpartitioning, when written
     * @param list<PartitionDefinition> $definitions The partition definitions in order
     */
    public function __construct(
        public readonly PartitionMethod $method,
        public readonly ?Numeral $partitions,
        public readonly ?Subpartitioning $subpartitioning,
        array $definitions,
    ) {
        $this->definitions = Check::listOf($definitions, PartitionDefinition::class, 'Partition definitions are a list of partition definitions.');
    }

    /**
     * Derives the method and the subpartitioning in the scope of the table and the partition values as constants.
     */
    public function derivePartitioning(Derivation $derivation, Environment $scope): void
    {
        $this->method->deriveMethod($derivation, $scope);
        $this->subpartitioning?->deriveSubpartitioning($derivation, $scope);
        foreach ($this->definitions as $definition) {
            $definition->derivePartition($derivation);
        }
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('PARTITION', 'BY')->node($this->method);
        if ($this->partitions !== null) {
            $out->keyword('PARTITIONS')->node($this->partitions);
        }
        $out->node($this->subpartitioning);
        if ($this->definitions !== []) {
            $out->symbol('(')->list($this->definitions)->symbol(')');
        }
    }
}
