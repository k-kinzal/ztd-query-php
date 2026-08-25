<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use Closure;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\WellKnownTextGeometry;

#[CoversClass(WellKnownTextGeometry::class)]
final class WellKnownTextGeometryTest extends TestCase
{
    #[DataProvider('providerGeometry')]
    public function testEachGeometryIsWrittenUnderItsOwnKeyword(Closure $write, string $keyword): void
    {
        $written = $write(new WellKnownTextGeometry(), Factory::create());

        self::assertStringStartsWith($keyword . '(', $written);
        self::assertStringEndsWith(')', $written);
    }

    /**
     * @return iterable<string, array{Closure(WellKnownTextGeometry, Generator): string, string}>
     */
    public static function providerGeometry(): iterable
    {
        yield 'point' => [
            static fn (WellKnownTextGeometry $g, Generator $f): string => $g->point($f),
            'POINT',
        ];
        yield 'line' => [
            static fn (WellKnownTextGeometry $g, Generator $f): string => $g->lineString($f),
            'LINESTRING',
        ];
        yield 'polygon' => [
            static fn (WellKnownTextGeometry $g, Generator $f): string => $g->polygon($f),
            'POLYGON',
        ];
        yield 'multi point' => [
            static fn (WellKnownTextGeometry $g, Generator $f): string => $g->multiPoint($f),
            'MULTIPOINT',
        ];
        yield 'multi line' => [
            static fn (WellKnownTextGeometry $g, Generator $f): string => $g->multiLineString($f),
            'MULTILINESTRING',
        ];
        yield 'multi polygon' => [
            static fn (WellKnownTextGeometry $g, Generator $f): string => $g->multiPolygon($f),
            'MULTIPOLYGON',
        ];
        yield 'collection' => [
            static fn (WellKnownTextGeometry $g, Generator $f): string => $g->collection($f),
            'GEOMETRYCOLLECTION',
        ];
    }

    public function testAPolygonClosesBackOnThePointItStartedFrom(): void
    {
        $written = (new WellKnownTextGeometry())->polygon(Factory::create());
        $points = explode(',', substr($written, strlen('POLYGON(('), -2));

        self::assertCount(5, $points);
        self::assertSame($points[0], $points[4]);
    }

    public function testACollectionCarriesWholeGeometriesRatherThanPoints(): void
    {
        $written = (new WellKnownTextGeometry())->collection(Factory::create());

        self::assertStringContainsString('POINT(', $written);
        self::assertStringContainsString('LINESTRING(', $written);
    }
}
