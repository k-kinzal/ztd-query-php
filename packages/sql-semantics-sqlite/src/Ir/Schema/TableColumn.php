<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\ColumnGeneration;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A table column with its declared type, builtin collation, NOT NULL policy and optional default or generation.
 *
 * @visibility public
 * @example Declaring a column that refuses NULL
 *     $name = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('id');
 *     $type = \SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName::Integer;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn($name, $type, \SqlSemantics\Platform\Sqlite\Ir\Schema\NotNullConflict::Abort))->type->value // => 'INTEGER'
 */
final class TableColumn
{
    use Snapshot;

    /**
     * Defines the column within its parent table.
     *
     * @param NotNullConflict|null $notNull The declared constraint algorithm, or null when no constraint is present
     * @param BlobLiteral|ColumnDefaultExpression|HexIntegerLiteral|IntegerLiteral|NullLiteral|RealLiteral|TextLiteral|null $default The explicit default when present
     * @param ColumnGeneration|null $generated The generated value when declared
     */
    public function __construct(
        public readonly Identifier $name,
        public readonly DeclaredDatatypeName $type = DeclaredDatatypeName::Untyped,
        public readonly ?NotNullConflict $notNull = null,
        public readonly BlobLiteral|ColumnDefaultExpression|HexIntegerLiteral|IntegerLiteral|NullLiteral|RealLiteral|TextLiteral|null $default = null,
        public readonly Collation $collation = Collation::Binary,
        public readonly ?ColumnGeneration $generated = null,
    ) {
        Check::input($generated === null || $default === null, 'A generated column cannot specify a default.');
    }
}
