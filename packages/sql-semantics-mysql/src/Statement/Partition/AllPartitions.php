<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The keyword ALL in place of a partition list: every partition of the table.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table-partition-operations.html.
 *
 * @visibility public
 * @example Selecting every partition
 *     (new \SqlSemantics\Platform\MySql\Statement\Partition\AllPartitions()) instanceof \SqlSemantics\Platform\MySql\Statement\Partition\PartitionSelection // => true
 */
final class AllPartitions implements PartitionSelection
{
    use Snapshot;

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALL');
    }
}
