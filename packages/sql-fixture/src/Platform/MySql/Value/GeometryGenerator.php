<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Value;

use Faker\Generator;
use LogicException;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Generates geometry values for the dialect's declared column type.
 *
 * @visibility root
 */
final class GeometryGenerator
{
    /**
     * Generates a value for a supported member of this type family.
     * @throws LogicException
     */
    public function generate(Generator $faker, ColumnDefinition $column): string
    {
        return match (strtoupper($column->type)) {
            'POINT' => (new SpatialGenerator())->generatePoint($faker),
            'LINESTRING' => (new SpatialGenerator())->generateLineString($faker),
            'POLYGON' => (new SpatialGenerator())->generatePolygon($faker),
            'MULTIPOINT' => (new SpatialGenerator())->generateMultiPoint($faker),
            'MULTILINESTRING' => (new SpatialGenerator())->generateMultiLineString($faker),
            'MULTIPOLYGON' => (new SpatialGenerator())->generateMultiPolygon($faker),
            'GEOMETRY' => (new SpatialGenerator())->generatePoint($faker),
            'GEOMETRYCOLLECTION' => (new SpatialGenerator())->generateGeometryCollection($faker),
            default => throw new LogicException('Unsupported geometry type: ' . $column->type),
        };
    }
}
