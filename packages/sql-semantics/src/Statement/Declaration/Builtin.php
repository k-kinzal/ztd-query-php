<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

/**
 * A built-in database type the binder models, named after its canonical SQL spelling.
 *
 * Each dialect maps its own spellings and synonyms onto these cases and
 * reports which cases it supports. Three cases are not declared types:
 * Unknown is the type of a value the binder has not resolved, such as NULL
 * or a parameter; Dynamic is a value whose storage class is decided at run
 * time; Any is a declared type that accepts every storage class.
 *
 * @example Reading semantic facts
 *     \SqlSemantics\Statement\Declaration\Builtin::DoublePrecision->value // => 'double precision'
 *     \SqlSemantics\Statement\Declaration\Builtin::BigInt->isNumeric() // => true
 *     \SqlSemantics\Statement\Declaration\Builtin::VarChar->isCharacter() // => true
 *
 * @visibility public
 */
enum Builtin: string
{
    case Unknown = 'unknown';
    case Dynamic = 'dynamic';
    case Any = 'any';
    case TinyInt = 'tinyint';
    case SmallInt = 'smallint';
    case MediumInt = 'mediumint';
    case Integer = 'integer';
    case BigInt = 'bigint';
    case Numeric = 'numeric';
    case Real = 'real';
    case DoublePrecision = 'double precision';
    case Money = 'money';
    case Boolean = 'boolean';
    case Bit = 'bit';
    case BitVarying = 'bit varying';
    case Char = 'char';
    case VarChar = 'varchar';
    case TinyText = 'tinytext';
    case Text = 'text';
    case MediumText = 'mediumtext';
    case LongText = 'longtext';
    case QuotedChar = '"char"';
    case Name = 'name';
    case Enum = 'enum';
    case Set = 'set';
    case Binary = 'binary';
    case VarBinary = 'varbinary';
    case TinyBlob = 'tinyblob';
    case Blob = 'blob';
    case MediumBlob = 'mediumblob';
    case LongBlob = 'longblob';
    case Bytea = 'bytea';
    case Date = 'date';
    case Time = 'time';
    case TimeTz = 'time with time zone';
    case DateTime = 'datetime';
    case Timestamp = 'timestamp';
    case TimestampTz = 'timestamp with time zone';
    case Year = 'year';
    case Interval = 'interval';
    case Json = 'json';
    case Jsonb = 'jsonb';
    case JsonPath = 'jsonpath';
    case Xml = 'xml';
    case Uuid = 'uuid';
    case Vector = 'vector';
    case Geometry = 'geometry';
    case GeometryCollection = 'geometrycollection';
    case Point = 'point';
    case MultiPoint = 'multipoint';
    case LineString = 'linestring';
    case MultiLineString = 'multilinestring';
    case Polygon = 'polygon';
    case MultiPolygon = 'multipolygon';
    case Line = 'line';
    case LineSegment = 'lseg';
    case Box = 'box';
    case Path = 'path';
    case Circle = 'circle';
    case Inet = 'inet';
    case Cidr = 'cidr';
    case MacAddr = 'macaddr';
    case MacAddr8 = 'macaddr8';
    case TsVector = 'tsvector';
    case TsQuery = 'tsquery';
    case Int4Range = 'int4range';
    case Int8Range = 'int8range';
    case NumRange = 'numrange';
    case TsRange = 'tsrange';
    case TsTzRange = 'tstzrange';
    case DateRange = 'daterange';
    case Int4MultiRange = 'int4multirange';
    case Int8MultiRange = 'int8multirange';
    case NumMultiRange = 'nummultirange';
    case TsMultiRange = 'tsmultirange';
    case TsTzMultiRange = 'tstzmultirange';
    case DateMultiRange = 'datemultirange';
    case Oid = 'oid';
    case RegClass = 'regclass';
    case RegCollation = 'regcollation';
    case RegConfig = 'regconfig';
    case RegDictionary = 'regdictionary';
    case RegNamespace = 'regnamespace';
    case RegOper = 'regoper';
    case RegOperator = 'regoperator';
    case RegProc = 'regproc';
    case RegProcedure = 'regprocedure';
    case RegRole = 'regrole';
    case RegType = 'regtype';
    case PgLsn = 'pg_lsn';
    case PgSnapshot = 'pg_snapshot';
    case TxidSnapshot = 'txid_snapshot';

    /**
     * Reports whether values are exact or approximate numbers, which may carry a sign fact.
     */
    public function isNumeric(): bool
    {
        return in_array($this, [self::TinyInt, self::SmallInt, self::MediumInt, self::Integer, self::BigInt, self::Numeric, self::Real, self::DoublePrecision], true);
    }

    /**
     * Reports whether values are character strings, which may carry a character set.
     */
    public function isCharacter(): bool
    {
        return in_array($this, [self::Char, self::VarChar, self::TinyText, self::Text, self::MediumText, self::LongText, self::Enum, self::Set], true);
    }

    /**
     * Reports whether values are octet strings.
     */
    public function isBinary(): bool
    {
        return in_array($this, [self::Binary, self::VarBinary, self::TinyBlob, self::Blob, self::MediumBlob, self::LongBlob, self::Bytea], true);
    }

    /**
     * Reports whether values are dates, times, or durations whose modifier is a fractional seconds precision; a year has a display width instead.
     */
    public function isTemporal(): bool
    {
        return in_array($this, [self::Date, self::Time, self::TimeTz, self::DateTime, self::Timestamp, self::TimestampTz, self::Interval], true);
    }
}
