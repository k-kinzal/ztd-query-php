<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

use Override;

/**
 * Closed built-in and inferred result type identities.
 * @visibility public
 */
enum BuiltinIdentity: string implements TypeIdentity
{
    case Unknown = 'unknown';
    case Dynamic = 'dynamic';
    case Never = 'never';
    case Record = 'record';
    case Boolean = 'boolean';
    case TinyInt = 'tinyint';
    case SmallInt = 'smallint';
    case MediumInt = 'mediumint';
    case Integer = 'integer';
    case BigInt = 'bigint';
    case Numeric = 'numeric';
    case Real = 'real';
    case DoublePrecision = 'double precision';
    case Float = 'float';
    case Char = 'char';
    case Varchar = 'varchar';
    case Text = 'text';
    case TinyText = 'tinytext';
    case MediumText = 'mediumtext';
    case LongText = 'longtext';
    case Binary = 'binary';
    case Varbinary = 'varbinary';
    case Blob = 'blob';
    case TinyBlob = 'tinyblob';
    case MediumBlob = 'mediumblob';
    case LongBlob = 'longblob';
    case Bytea = 'bytea';
    case Bit = 'bit';
    case Varbit = 'varbit';
    case Date = 'date';
    case Time = 'time';
    case Timetz = 'timetz';
    case Timestamp = 'timestamp';
    case Timestamptz = 'timestamptz';
    case Datetime = 'datetime';
    case Year = 'year';
    case Interval = 'interval';
    case Json = 'json';
    case Jsonb = 'jsonb';
    case Uuid = 'uuid';
    case Xml = 'xml';
    case Geometry = 'geometry';
    case Point = 'point';
    case LineString = 'linestring';
    case Polygon = 'polygon';
    case MultiPoint = 'multipoint';
    case MultiLineString = 'multilinestring';
    case MultiPolygon = 'multipolygon';
    case GeometryCollection = 'geometrycollection';
    case Vector = 'vector';
    case Serial = 'serial';
    case SmallSerial = 'smallserial';
    case BigSerial = 'bigserial';

    #[Override]
    public function name(): string
    {
        return $this->value;
    }
}
