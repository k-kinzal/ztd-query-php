<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Declaration;

use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Catalog identity of an explicit SQLite index on a declared table.
 *
 * @visibility SqlSemantics
 */
final class Index
{
    use Snapshot;

    /**
     * Records the actual schema, index and owner table names from the binding.
     */
    public function __construct(
        public readonly Name $schema,
        public readonly Name $name,
        public readonly Name $table,
    ) {
    }
}
