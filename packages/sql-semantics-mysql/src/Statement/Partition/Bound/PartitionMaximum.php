<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Bound;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The keyword MAXVALUE as one partition value: a value greater than every other value.
 *
 * Mirrors PT_part_value_item_max.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-columns-range.html.
 *
 * @visibility public
 * @example Holding the greatest value of a row of partition values
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow([new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionMaximum()]);
 *     $row->items[0] instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionMaximum // => true
 */
final class PartitionMaximum implements Node
{
    use Snapshot;

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword('MAXVALUE');
    }
}
