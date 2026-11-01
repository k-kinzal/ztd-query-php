<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Problem;

use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * RANGE or LIST partitioning without the required partition definitions.
 *
 * The server reports ER_PARTITIONS_MUST_BE_DEFINED_ERROR before opening an ALTER target.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-range.html,
 * https://dev.mysql.com/doc/refman/8.4/en/partitioning-list.html.
 *
 * @visibility public
 * @example Describing a missing partition list
 *     (new \SqlSemantics\Platform\MySql\Statement\Partition\Problem\MissingPartitions(\SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind::Range))->message() // => 'For RANGE partitions each partition must be defined.'
 */
final class MissingPartitions implements Diagnostic
{
    use Snapshot;

    /**
     * @param PartitionKind $kind The partition method requiring explicit definitions
     */
    public function __construct(public readonly PartitionKind $kind)
    {
    }

    /**
     * Describes the omitted definitions.
     */
    public function message(): string
    {
        return 'For ' . $this->kind->value . ' partitions each partition must be defined.';
    }
}
