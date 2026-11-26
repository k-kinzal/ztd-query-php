<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Alter;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One declared member of a table being changed by a column removal.
 *
 * @visibility public
 * @example Declaring the position of a generated member
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\DropMember(new \SqlSemantics\Platform\Sqlite\Ir\Identifier('total'), generated: true))->generated // => true
 */
final class DropMember
{
    use Snapshot;

    /**
     * Fixes the name and the roles used by the owning removal.
     */
    public function __construct(
        public readonly Identifier $name,
        public readonly bool $generated = false,
        public readonly bool $protected = false,
    ) {
    }
}
