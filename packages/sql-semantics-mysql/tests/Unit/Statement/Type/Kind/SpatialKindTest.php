<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;

#[CoversClass(SpatialKind::class)]
#[Small]
final class SpatialKindTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEverySpatialType(): void
    {
        self::assertSame(['Geometry', 'GeometryCollection', 'Point', 'MultiPoint', 'LineString', 'MultiLineString', 'Polygon', 'MultiPolygon'], array_column(SpatialKind::cases(), 'name'));
        self::assertSame(['GEOMETRY', 'GEOMETRYCOLLECTION', 'POINT', 'MULTIPOINT', 'LINESTRING', 'MULTILINESTRING', 'POLYGON', 'MULTIPOLYGON'], array_column(SpatialKind::cases(), 'value'));
    }
}
