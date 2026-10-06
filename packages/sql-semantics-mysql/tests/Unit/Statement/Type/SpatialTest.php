<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(Spatial::class)]
#[Small]
final class SpatialTest extends TestCase
{
    public function testNameSpellsTheKeywordOfEachKind(): void
    {
        self::assertSame('GEOMETRY', (new Spatial(SpatialKind::Geometry))->name());
        self::assertSame('GEOMETRYCOLLECTION', (new Spatial(SpatialKind::GeometryCollection))->name());
        self::assertSame('POINT', (new Spatial(SpatialKind::Point))->name());
        self::assertSame('MULTIPOINT', (new Spatial(SpatialKind::MultiPoint))->name());
        self::assertSame('LINESTRING', (new Spatial(SpatialKind::LineString))->name());
        self::assertSame('MULTILINESTRING', (new Spatial(SpatialKind::MultiLineString))->name());
        self::assertSame('POLYGON', (new Spatial(SpatialKind::Polygon))->name());
        self::assertSame('MULTIPOLYGON', (new Spatial(SpatialKind::MultiPolygon))->name());
    }

    public function testRenderWritesTheKeywordAlone(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Spatial(SpatialKind::MultiLineString))->render($out);

        self::assertSame('MULTILINESTRING', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesGeometrycollectionForTheShortSpelling(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c GEOMCOLLECTION)')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Spatial::class, $type);
        self::assertSame(SpatialKind::GeometryCollection, $type->kind);
        $type->render($out);
        self::assertSame('GEOMETRYCOLLECTION', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesASpatialTypeLoweredUnderTheOldestRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c MULTIPOLYGON)')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Spatial::class, $type);
        self::assertSame(SpatialKind::MultiPolygon, $type->kind);
        $type->render($out);
        self::assertSame('MULTIPOLYGON', (new Lexical())->join($out->pieces()));
    }
}
