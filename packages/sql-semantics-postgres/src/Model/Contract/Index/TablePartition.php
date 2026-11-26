<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Contract\Index;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An absolute local table coordinate with a known partition role.
 *
 * @visibility SqlSemantics
 */
final class TablePartition
{
    use Snapshot;

    /**
     * Captures the already resolved declaration's schema, table and partition role.
     */
    public function __construct(
        public readonly Name $schema,
        public readonly Name $table,
        public readonly TablePartitionRole $role,
    ) {
    }
}
