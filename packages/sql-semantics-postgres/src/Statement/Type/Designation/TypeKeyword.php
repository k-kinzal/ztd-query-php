<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * The keyword spellings of types that take no modifier.
 *
 * INT and INTEGER are the same type; both spellings exist in the grammar and
 * the one written is kept. JSON is a keyword from PostgreSQL 17 on.
 * Source: https://www.postgresql.org/docs/17/datatype.html#DATATYPE-TABLE.
 *
 * @visibility public
 * @example Reading the catalog type of a spelling
 *     \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword::Int->builtin() // => \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4
 */
enum TypeKeyword: string
{
    case Int = 'INT';
    case Integer = 'INTEGER';
    case Smallint = 'SMALLINT';
    case Bigint = 'BIGINT';
    case Real = 'REAL';
    case DoublePrecision = 'DOUBLE PRECISION';
    case Boolean = 'BOOLEAN';
    case Json = 'JSON';

    /**
     * Answers the catalog type the spelling denotes.
     */
    public function builtin(): Builtin
    {
        return match ($this) {
            self::Int, self::Integer => Builtin::Int4,
            self::Smallint => Builtin::Int2,
            self::Bigint => Builtin::Int8,
            self::Real => Builtin::Float4,
            self::DoublePrecision => Builtin::Float8,
            self::Boolean => Builtin::Bool,
            self::Json => Builtin::Json,
        };
    }
}
