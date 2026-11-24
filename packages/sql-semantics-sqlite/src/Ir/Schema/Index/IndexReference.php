<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Index;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The absolute identity of an application-defined SQLite index and its owner.
 *
 * @visibility public
 * @example Referring to an index over a temporary table
 *     $target = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Index\IndexReference(new \SqlSemantics\Platform\Sqlite\Ir\Identifier('temp'), new \SqlSemantics\Platform\Sqlite\Ir\Identifier('by date'), new \SqlSemantics\Platform\Sqlite\Ir\Identifier('entries'));
 *     $target->quoted() // => '"temp"."by date"'
 */
final class IndexReference
{
    use Snapshot;

    /**
     * Fixes the indexed object and owner within the supplied external schema.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $name,
        public readonly Identifier $table,
    ) {
        Check::input(!str_starts_with($name->key(), 'sqlite_'), 'An ordinary index cannot belong to the reserved SQLite namespace.');
    }

    /**
     * Emits the absolute index reference for a schema operation.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->name->quoted();
    }
}
