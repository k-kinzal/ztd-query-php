<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A table column with its declared type, NOT NULL policy and optional literal default.
 *
 * @visibility public
 * @example Declaring a column that refuses NULL
 *     $name = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('id');
 *     $type = \SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName::Integer;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn($name, $type, \SqlSemantics\Platform\Sqlite\Ir\Schema\NotNullConflict::Abort))->toString() // => '"id" INTEGER NOT NULL'
 */
final class TableColumn
{
    use Snapshot;

    /**
     * Defines the column within its parent table.
     *
     * @param NotNullConflict|null $notNull The declared constraint algorithm, or null when no constraint is present
     * @param BlobLiteral|HexIntegerLiteral|IntegerLiteral|NullLiteral|RealLiteral|TextLiteral|null $default The explicit default when present
     */
    public function __construct(
        public readonly Identifier $name,
        public readonly DeclaredDatatypeName $type = DeclaredDatatypeName::Untyped,
        public readonly ?NotNullConflict $notNull = null,
        public readonly BlobLiteral|HexIntegerLiteral|IntegerLiteral|NullLiteral|RealLiteral|TextLiteral|null $default = null,
    ) {
    }

    /**
     * Emits the column declaration, keeping the untyped case distinct.
     */
    public function toString(): string
    {
        $type = $this->type === DeclaredDatatypeName::Untyped ? '' : ' ' . $this->type->value;

        $default = $this->default === null ? '' : ' DEFAULT ' . (new SelectSql())->expression($this->default, null);

        $constraint = match ($this->notNull) {
            null => '',
            NotNullConflict::Abort => ' NOT NULL',
            NotNullConflict::Fail => ' NOT NULL ON CONFLICT FAIL',
            NotNullConflict::Ignore => ' NOT NULL ON CONFLICT IGNORE',
            NotNullConflict::Replace => ' NOT NULL ON CONFLICT REPLACE',
            NotNullConflict::Rollback => ' NOT NULL ON CONFLICT ROLLBACK',
        };

        return $this->name->quoted() . $type . $constraint . $default;
    }
}
