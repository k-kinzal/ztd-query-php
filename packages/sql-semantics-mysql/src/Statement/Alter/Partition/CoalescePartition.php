<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

/**
 * `COALESCE PARTITION [NO_WRITE_TO_BINLOG] n`: a request to merge away n partitions of a HASH or KEY table.
 *
 * Mirrors PT_alter_table_coalesce_partition. LOCAL and NO_WRITE_TO_BINLOG
 * are synonyms.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-management-hash-key.html.
 *
 * @visibility SqlSemantics
 */
final class CoalescePartition implements StandaloneCommand
{
    use Snapshot;

    /**
     * @param bool $local Whether NO_WRITE_TO_BINLOG or LOCAL is written
     * @param Numeral $count The number of partitions to remove
     */
    public function __construct(public readonly bool $local, public readonly Numeral $count)
    {
    }

    /**
     * Derives nothing: the action holds no expression.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('COALESCE', 'PARTITION');
        if ($this->local) {
            $out->keyword('NO_WRITE_TO_BINLOG');
        }
        $out->node($this->count);
    }
}
