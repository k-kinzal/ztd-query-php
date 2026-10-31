<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

use SqlSemantics\Rendering\Output;

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
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALL');
    }
    /**
     * Constructs this SQL form with optional completed semantics and an input range.
     */
    public function __construct(public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }
}
