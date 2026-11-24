<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The absolute SQLite index and owner selected by a maintenance statement.
 *
 * @visibility public
 * @example Selecting an automatic constraint index
 *     $target = new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget(new \SqlSemantics\Platform\Sqlite\Ir\Identifier('main'), new \SqlSemantics\Platform\Sqlite\Ir\Identifier('sqlite_autoindex_items_1'), new \SqlSemantics\Platform\Sqlite\Ir\Identifier('items'));
 *     $target->quoted() // => '"main"."sqlite_autoindex_items_1"'
 */
final class IndexTarget
{
    use Snapshot;

    /**
     * Records the index and owner, including native constraint indexes.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $name,
        public readonly Identifier $table,
    ) {
    }

    /**
     * Emits the absolute index name for a native statement.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->name->quoted();
    }
}
