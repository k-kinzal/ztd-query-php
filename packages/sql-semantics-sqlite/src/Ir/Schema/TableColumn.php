<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A table column with its declared type and nullability constraint.
 *
 * @visibility public
 * @example Declaring a column that refuses NULL
 *     $name = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('id');
 *     $type = \SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName::Integer;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn($name, $type, true))->toString() // => '"id" INTEGER NOT NULL'
 */
final class TableColumn
{
    use Snapshot;

    /**
     * Defines the column within its parent table.
     */
    public function __construct(
        public readonly Identifier $name,
        public readonly DeclaredDatatypeName $type = DeclaredDatatypeName::Untyped,
        public readonly bool $notNull = false,
    ) {
    }

    /**
     * Emits the column declaration, keeping the untyped case distinct.
     */
    public function toString(): string
    {
        $type = $this->type === DeclaredDatatypeName::Untyped ? '' : ' ' . $this->type->value;

        return $this->name->quoted() . $type . ($this->notNull ? ' NOT NULL' : '');
    }
}
