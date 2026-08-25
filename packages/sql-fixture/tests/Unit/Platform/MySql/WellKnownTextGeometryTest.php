<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\WellKnownTextGeometry;

#[CoversClass(WellKnownTextGeometry::class)]
final class WellKnownTextGeometryTest extends TestCase
{
    #[DataProvider('providerGeometry')]
    public function testEachGeometryIsWrittenUnderItsOwnKeyword(string $method, string $keyword): void
    {
        $written = (new WellKnownTextGeometry())->{$method}(Factory::create());

        self::assertStringStartsWith($keyword . '(', $written);
        self::assertStringEndsWith(')', $written);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGeometry(): iterable
    {
        yield 'point' => ['point', 'POINT'];
        yield 'line' => ['lineString', 'LINESTRING'];
        yield 'polygon' => ['polygon', 'POLYGON'];
        yield 'multi point' => ['multiPoint', 'MULTIPOINT'];
        yield 'multi line' => ['multiLineString', 'MULTILINESTRING'];
        yield 'multi polygon' => ['multiPolygon', 'MULTIPOLYGON'];
        yield 'collection' => ['collection', 'GEOMETRYCOLLECTION'];
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
