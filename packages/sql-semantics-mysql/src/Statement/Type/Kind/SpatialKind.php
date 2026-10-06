<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Kind;

/**
 * The spatial types of MySQL.
 *
 * Each case holds the keywords the type is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/spatial-type-overview.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind::MultiPolygon->value // => 'MULTIPOLYGON'
 */
enum SpatialKind: string
{
    case Geometry = 'GEOMETRY';
    case GeometryCollection = 'GEOMETRYCOLLECTION';
    case Point = 'POINT';
    case MultiPoint = 'MULTIPOINT';
    case LineString = 'LINESTRING';
    case MultiLineString = 'MULTILINESTRING';
    case Polygon = 'POLYGON';
    case MultiPolygon = 'MULTIPOLYGON';
}
