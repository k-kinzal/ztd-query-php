<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * A column declaration supplied to an analysis context.
 *
 * Resolved references point to this object itself; it is never copied.
 *
 * @visibility public
 * @example Reaching the declared column from a query field
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL)')->declarations()[0];
 *     $semantics->analyze('SELECT a FROM t', [$table])->field('a')->column() === $table->columns[0] // => true
 */
final class Column
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param TypeDescriptor $type The declared type
     * @param Nullability $nullability Whether the column can hold NULL
     * @param bool $generated Whether the column is computed from other columns of its row and cannot be written
     */
    public function __construct(
        public readonly Name $name,
        public readonly TypeDescriptor $type,
        public readonly Nullability $nullability = Nullability::Nullable,
        public readonly bool $generated = false,
    ) {
    }
}
