<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Kind;

/**
 * The target types CAST and CONVERT accept.
 *
 * Each case holds the keywords the type is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Unsigned->value // => 'UNSIGNED'
 */
enum CastKind: string
{
    case Binary = 'BINARY';
    case Char = 'CHAR';
    case NationalChar = 'NCHAR';
    case Signed = 'SIGNED';
    case Unsigned = 'UNSIGNED';
    case Date = 'DATE';
    case Time = 'TIME';
    case DateTime = 'DATETIME';
    case Decimal = 'DECIMAL';
    case Json = 'JSON';
    case Year = 'YEAR';
    case Real = 'REAL';
    case Double = 'DOUBLE';
    case Float = 'FLOAT';
    case Point = 'POINT';
    case LineString = 'LINESTRING';
    case Polygon = 'POLYGON';
    case MultiPoint = 'MULTIPOINT';
    case MultiLineString = 'MULTILINESTRING';
    case MultiPolygon = 'MULTIPOLYGON';
    case GeometryCollection = 'GEOMETRYCOLLECTION';
}
