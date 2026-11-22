<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ParentTable;

/**
 * ATTACH PARTITION: makes a table a partition of the altered partitioned table.
 *
 * Mirrors `AT_AttachPartition` with a `PartitionCmd`. The partition is resolved and is the relation fact of
 * its node; the bound values see no column.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
 */
final class AttachPartition implements AlterCommand
{
    use Snapshot;

    /**
     * @param ParentTable $partition The table that becomes a partition
     * @param PartitionBound $bound Its bound
     */
    public function __construct(public readonly ParentTable $partition, public readonly PartitionBound $bound)
    {
    }

    /**
     * Resolves the partition and derives the bound.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->target($this->partition, (new Targets())->resolve($derivation, $this->partition->name));
        $this->bound->deriveClause($derivation, $environment);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ATTACH', 'PARTITION')->node($this->partition)->node($this->bound);
    }
}
