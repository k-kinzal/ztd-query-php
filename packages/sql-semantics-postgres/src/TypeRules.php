<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Policy\TypeRules as Contract;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDeclaration;

/**
 * PostgreSql TypeRules implementation.
 *
 * @visibility SqlSemantics
 */
final class TypeRules implements Contract
{
    private const SUPPORTED = [
        Builtin::Unknown,
        Builtin::SmallInt, Builtin::Integer, Builtin::BigInt, Builtin::Numeric, Builtin::Real, Builtin::DoublePrecision, Builtin::Money,
        Builtin::Boolean, Builtin::Bit, Builtin::BitVarying,
        Builtin::Char, Builtin::VarChar, Builtin::Text, Builtin::QuotedChar, Builtin::Name, Builtin::Bytea,
        Builtin::Date, Builtin::Time, Builtin::TimeTz, Builtin::Timestamp, Builtin::TimestampTz, Builtin::Interval,
        Builtin::Json, Builtin::Jsonb, Builtin::JsonPath, Builtin::Xml, Builtin::Uuid,
        Builtin::Point, Builtin::Line, Builtin::LineSegment, Builtin::Box, Builtin::Path, Builtin::Polygon, Builtin::Circle,
        Builtin::Inet, Builtin::Cidr, Builtin::MacAddr, Builtin::MacAddr8, Builtin::TsVector, Builtin::TsQuery,
        Builtin::Int4Range, Builtin::Int8Range, Builtin::NumRange, Builtin::TsRange, Builtin::TsTzRange, Builtin::DateRange,
        Builtin::Int4MultiRange, Builtin::Int8MultiRange, Builtin::NumMultiRange, Builtin::TsMultiRange, Builtin::TsTzMultiRange, Builtin::DateMultiRange,
        Builtin::Oid, Builtin::RegClass, Builtin::RegCollation, Builtin::RegConfig, Builtin::RegDictionary, Builtin::RegNamespace,
        Builtin::RegOper, Builtin::RegOperator, Builtin::RegProc, Builtin::RegProcedure, Builtin::RegRole, Builtin::RegType,
        Builtin::PgLsn, Builtin::PgSnapshot, Builtin::TxidSnapshot,
    ];




    /**
     * Reads a declared type by its grammar production and the catalog names it refers to.
     */
    public function read(Node $node, ValueReader $values, ?Node $table = null): TypeDeclaration
    {
        return (new TypeReader())->read($node);
    }

    /**
     * Reports whether this dialect has the built-in type.
     */
    public function supports(Builtin $type): bool
    {
        return in_array($type, self::SUPPORTED, true);
    }
}
