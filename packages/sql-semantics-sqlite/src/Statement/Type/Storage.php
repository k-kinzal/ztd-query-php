<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Type;

use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * The storage class of a value SQLite computes: the type of an expression that is not a plain column.
 *
 * Source: https://sqlite.org/datatype3.html#storage_classes_and_datatypes.
 *
 * @visibility public
 * @example Reading the storage class of a literal
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1');
 *     $query->field(0)->type->descriptor // => \SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer
 */
enum Storage: string implements TypeDescriptor
{
    case Integer = 'INTEGER';
    case Real = 'REAL';
    case Text = 'TEXT';
    case Blob = 'BLOB';

    /**
     * Names the storage class as `typeof()` reports it, in upper case.
     */
    public function name(): string
    {
        return $this->value;
    }
}
