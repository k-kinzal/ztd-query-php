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
use Tests\Fixture\SpyGenerator;

#[CoversClass(WellKnownTextGeometry::class)]
final class WellKnownTextGeometryTest extends TestCase
{
    /**
     * @param Closure(WellKnownTextGeometry, Generator): string $write
     */
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

    public function testPointWritesOneCoordinatePair(): void
    {
        self::assertMatchesRegularExpression(
            '/^POINT\(-?[\d.]+ -?[\d.]+\)$/',
            (new WellKnownTextGeometry())->point(Factory::create()),
        );
    }

    public function testLineStringWritesAtLeastTwoPoints(): void
    {
        $written = (new WellKnownTextGeometry())->lineString(Factory::create());

        self::assertGreaterThanOrEqual(2, count(explode(',', $written)));
    }

    public function testPolygonWritesOneClosedRing(): void
    {
        self::assertStringStartsWith('POLYGON((', (new WellKnownTextGeometry())->polygon(Factory::create()));
    }

    public function testMultiPointBracketsEachPointOnItsOwn(): void
    {
        self::assertStringStartsWith('MULTIPOINT((', (new WellKnownTextGeometry())->multiPoint(Factory::create()));
    }

    public function testMultiLineStringBracketsEachLineOnItsOwn(): void
    {
        $written = (new WellKnownTextGeometry())->multiLineString(Factory::create());

        self::assertStringStartsWith('MULTILINESTRING((', $written);
    }

    public function testMultiPolygonNestsOneLevelDeeperThanAPolygon(): void
    {
        self::assertStringStartsWith('MULTIPOLYGON(((', (new WellKnownTextGeometry())->multiPolygon(Factory::create()));
    }

    public function testCollectionCarriesGeometriesOfMoreThanOneKind(): void
    {
        $written = (new WellKnownTextGeometry())->collection(Factory::create());

        self::assertStringStartsWith('GEOMETRYCOLLECTION(POINT(', $written);
    }
    public function testLineStringWritesBetweenTwoAndFourPoints(): void
    {
        $geometry = new WellKnownTextGeometry();
        $faker = Factory::create();
        $faker->seed(20260827);
        $counts = array_map(
            static fn (int $draw): int => substr_count($geometry->lineString($faker), ',') + 1,
            range(1, 200),
        );
        sort($counts);

        self::assertSame([2, 3, 4], array_values(array_unique($counts)));
    }

    public function testMultiPointWritesBetweenTwoAndFourPoints(): void
    {
        $geometry = new WellKnownTextGeometry();
        $faker = Factory::create();
        $faker->seed(20260827);
        $counts = array_map(
            static fn (int $draw): int => substr_count($geometry->multiPoint($faker), '(') - 1,
            range(1, 200),
        );
        sort($counts);

        self::assertSame([2, 3, 4], array_values(array_unique($counts)));
    }

    public function testMultiLineStringWritesBetweenTwoAndThreeLines(): void
    {
        $geometry = new WellKnownTextGeometry();
        $faker = Factory::create();
        $faker->seed(20260827);
        $counts = array_map(
            static fn (int $draw): int => substr_count($geometry->multiLineString($faker), '(') - 1,
            range(1, 200),
        );
        sort($counts);

        self::assertSame([2, 3], array_values(array_unique($counts)));
    }

    public function testEachLineOfAMultiLineStringWritesBetweenTwoAndThreePoints(): void
    {
        $geometry = new WellKnownTextGeometry();
        $faker = Factory::create();
        $faker->seed(20260827);
        $counts = array_merge(...array_map(
            static function (int $draw) use ($geometry, $faker): array {
                preg_match_all('/\(([^()]+)\)/', $geometry->multiLineString($faker), $matches);

                return array_map(static fn (string $line): int => substr_count($line, ',') + 1, $matches[1]);
            },
            range(1, 200),
        ));
        sort($counts);

        self::assertSame([2, 3], array_values(array_unique($counts)));
    }

    public function testMultiPolygonWritesExactlyTwoPolygons(): void
    {
        $geometry = new WellKnownTextGeometry();
        $faker = Factory::create();
        $faker->seed(20260827);
        $counts = array_map(
            static fn (int $draw): int => substr_count($geometry->multiPolygon($faker), '(('),
            range(1, 50),
        );

        self::assertSame([2], array_values(array_unique($counts)));
    }

    public function testAPolygonIsDrawnWhereItsCornersStayOnTheGlobe(): void
    {
        $faker = SpyGenerator::create();

        (new WellKnownTextGeometry())->polygon($faker);

        self::assertSame(
            [[[-170, 170]], [[-80, 80]], [[2, 0.1, 1.0]]],
            [$faker->methodCalls['longitude'] ?? [], $faker->methodCalls['latitude'] ?? [], $faker->randomFloatCalls],
        );
    }

    public function testEachPolygonOfAMultiPolygonIsDrawnWhereItsCornersStayOnTheGlobe(): void
    {
        $faker = SpyGenerator::create();

        (new WellKnownTextGeometry())->multiPolygon($faker);

        self::assertSame(
            [[[-170, 170], [-170, 170]], [[-80, 80], [-80, 80]], [[2, 0.1, 0.5], [2, 0.1, 0.5]]],
            [$faker->methodCalls['longitude'] ?? [], $faker->methodCalls['latitude'] ?? [], $faker->randomFloatCalls],
        );
    }

    public function testAPolygonWalksItsCornersRoundRatherThanBackAndForth(): void
    {
        $written = (new WellKnownTextGeometry())->polygon(Factory::create());
        preg_match('/^POLYGON\(\((.*)\)\)$/', $written, $matches);
        $corners = array_map(
            static fn (string $corner): array => array_map(floatval(...), explode(' ', $corner)),
            explode(',', $matches[1] ?? ''),
        );

        self::assertSame(
            [true, true, true, true],
            [
                $corners[1][0] > $corners[0][0],
                $corners[1][1] === $corners[0][1],
                $corners[2][1] > $corners[1][1],
                $corners[3][0] === $corners[0][0],
            ],
        );
    }

    public function testEachPolygonOfAMultiPolygonWalksItsCornersRound(): void
    {
        $written = (new WellKnownTextGeometry())->multiPolygon(Factory::create());
        preg_match('/^MULTIPOLYGON\(\(\((.*?)\)\)/', $written, $matches);
        $corners = array_map(
            static fn (string $corner): array => array_map(floatval(...), explode(' ', $corner)),
            explode(',', $matches[1] ?? ''),
        );

        self::assertSame(
            [true, true, true, true],
            [
                count($corners) === 4,
                $corners[1][0] > $corners[0][0],
                $corners[2][1] > $corners[1][1],
                $corners[3] === $corners[0],
            ],
        );
    }
}
