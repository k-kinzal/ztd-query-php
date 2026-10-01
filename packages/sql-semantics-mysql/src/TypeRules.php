<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Policy\TypeRules as Contract;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDeclaration;

/**
 * MySql TypeRules implementation.
 *
 * @visibility SqlSemantics
 */
final class TypeRules implements Contract
{
    private const SUPPORTED = [
        Builtin::Unknown,
        Builtin::TinyInt,
        Builtin::SmallInt,
        Builtin::MediumInt,
        Builtin::Integer,
        Builtin::BigInt,
        Builtin::Numeric,
        Builtin::Real,
        Builtin::DoublePrecision,
        Builtin::Bit,
        Builtin::Char,
        Builtin::VarChar,
        Builtin::TinyText,
        Builtin::Text,
        Builtin::MediumText,
        Builtin::LongText,
        Builtin::Enum,
        Builtin::Set,
        Builtin::Binary,
        Builtin::VarBinary,
        Builtin::TinyBlob,
        Builtin::Blob,
        Builtin::MediumBlob,
        Builtin::LongBlob,
        Builtin::Date,
        Builtin::Time,
        Builtin::DateTime,
        Builtin::Timestamp,
        Builtin::Year,
        Builtin::Json,
        Builtin::Vector,
        Builtin::Geometry,
        Builtin::GeometryCollection,
        Builtin::Point,
        Builtin::MultiPoint,
        Builtin::LineString,
        Builtin::MultiLineString,
        Builtin::Polygon,
        Builtin::MultiPolygon,
    ];




    /**
     * Reads a declared type by its keyword tokens, arguments and attributes.
     */
    public function read(Node $node, ValueReader $values, ?Node $table = null): TypeDeclaration
    {
        return (new TypeReader())->read($node, $values);
    }

    /**
     * Reports whether this dialect has the built-in type.
     */
    public function supports(Builtin $type): bool
    {
        return in_array($type, self::SUPPORTED, true);
    }
}
