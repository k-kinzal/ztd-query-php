<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Index;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * An absent SQLite index with the exact schema verification scope.
 *
 * @visibility public
 * @example Representing a guarded absence in every searched schema
 *     $target = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Index\MissingIndex(new \SqlSemantics\Platform\Sqlite\Ir\Identifier('by date'));
 *     $target->quoted() // => '"by date"'
 */
final class MissingIndex
{
    use Snapshot;

    /**
     * Records the index's observed absence under an optional exact qualifier.
     */
    public function __construct(
        public readonly Identifier $name,
        public readonly ?Identifier $schema = null,
    ) {
    }

    /**
     * Emits the qualified or unqualified selector for an optional operation.
     */
    public function quoted(): string
    {
        return ($this->schema === null ? '' : $this->schema->quoted() . '.') . $this->name->quoted();
    }
}
