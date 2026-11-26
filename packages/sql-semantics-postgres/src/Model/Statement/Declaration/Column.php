<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\TypeDescriptor;

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
     * @param bool $identity Whether the column owns an identity sequence
     * @param DeclaredTextCollation|null $collation A resolved declared collation, when known
     */
    public function __construct(
        public readonly Name $name,
        public readonly TypeDescriptor $type,
        public readonly Nullability $nullability = Nullability::Nullable,
        public readonly bool $generated = false,
        public readonly bool $identity = false,
        public readonly ?DeclaredTextCollation $collation = null,
    ) {
    }
}
