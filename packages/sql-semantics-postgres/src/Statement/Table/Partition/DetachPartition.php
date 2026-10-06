<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ParentTable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * DETACH PARTITION: makes a partition a stand-alone table.
 *
 * Mirrors `AT_DetachPartition` (`concurrent`) and `AT_DetachPartitionFinalize`. The partition is resolved and
 * is the relation fact of its node.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Finalizing a detach
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE p DETACH PARTITION p1 FINALIZE');
 *     $statement->statement->commands[0]->mode // => \SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachMode::Finalize
 */
final class DetachPartition implements AlterCommand
{
    use Snapshot;

    /**
     * @param ParentTable $partition The partition
     * @param DetachMode $mode How the partition is detached
     */
    public function __construct(public readonly ParentTable $partition, public readonly DetachMode $mode = DetachMode::Plain)
    {
    }

    /**
     * Resolves the partition.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->target($this->partition, (new Targets())->resolve($derivation, $this->partition->name));
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('DETACH', 'PARTITION')->node($this->partition);
        if ($this->mode !== DetachMode::Plain) {
            $out->keyword($this->mode->value);
        }
    }
}
