<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Declaration;

use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\TypeDescriptor;

/**
 * A column declaration supplied to an analysis context.
 *
 * Resolved references point to this object itself; it is never copied.
 *
 * @visibility SqlSemantics
 */
final class Column
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param TypeDescriptor $type The declared type
     * @param Nullability $nullability Whether the column can hold NULL
     * @param bool $generated Whether the column is computed from other columns of its row and cannot be written
     * @param Name|null $collation The explicitly declared default collation
     */
    public function __construct(
        public readonly Name $name,
        public readonly TypeDescriptor $type,
        public readonly Nullability $nullability = Nullability::Nullable,
        public readonly bool $generated = false,
        public readonly ?Name $collation = null,
    ) {
    }
}
