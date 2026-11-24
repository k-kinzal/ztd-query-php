<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The absolute SQLite relation selected by a maintenance statement.
 *
 * @visibility public
 * @example Addressing a table in the temporary schema
 *     $target = new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget(new \SqlSemantics\Platform\Sqlite\Ir\Identifier('temp'), new \SqlSemantics\Platform\Sqlite\Ir\Identifier('entries'));
 *     $target->quoted() // => '"temp"."entries"'
 */
final class RelationTarget
{
    use Snapshot;

    /**
     * Records the relation and its native kind.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $name,
        public readonly ExternalRelationKind $kind = ExternalRelationKind::BaseTable,
    ) {
    }

    /**
     * Emits the exact schema and relation for a native statement.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->name->quoted();
    }
}
