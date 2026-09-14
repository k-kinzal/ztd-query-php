<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Value;

use Faker\Factory;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Value\SpatialGenerator as Subject;

#[CoversClass(Subject::class)]
#[Medium]
final class SpatialGeneratorTest extends TestCase
{
    #[DataProvider('providerGeometrySeeds')]
    public function testGeneratePointProducesValidNonemptyGeometry(int $seed): void
    {
        $pdo = new PDO(
            (string) getenv('SQL_FIXTURE_MYSQL_DSN'),
            (string) getenv('SQL_FIXTURE_MYSQL_USER'),
            (string) getenv('SQL_FIXTURE_MYSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $faker = Factory::create();
        $faker->seed($seed);
        $value = (new Subject())->generatePoint($faker);
        $statement = $pdo->prepare('SELECT ST_IsValid(g), ST_IsEmpty(g), ST_GeometryType(g), ST_Dimension(g) FROM (SELECT ST_GeomFromText(?) AS g) AS geometry_input');
        $statement->execute([$value]);
        self::assertSame([1, 0, 'POINT', 0], $statement->fetch(PDO::FETCH_NUM));
    }

    #[DataProvider('providerGeometrySeeds')]
    public function testGenerateLineStringProducesValidNonemptyGeometry(int $seed): void
    {
        $pdo = new PDO(
            (string) getenv('SQL_FIXTURE_MYSQL_DSN'),
            (string) getenv('SQL_FIXTURE_MYSQL_USER'),
            (string) getenv('SQL_FIXTURE_MYSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $faker = Factory::create();
        $faker->seed($seed);
        $value = (new Subject())->generateLineString($faker);
        $statement = $pdo->prepare('SELECT ST_IsValid(g), ST_IsEmpty(g), ST_GeometryType(g), ST_Dimension(g) FROM (SELECT ST_GeomFromText(?) AS g) AS geometry_input');
        $statement->execute([$value]);
        self::assertSame([1, 0, 'LINESTRING', 1], $statement->fetch(PDO::FETCH_NUM));
    }

    #[DataProvider('providerGeometrySeeds')]
    public function testGeneratePolygonProducesValidNonemptyGeometry(int $seed): void
    {
        $pdo = new PDO(
            (string) getenv('SQL_FIXTURE_MYSQL_DSN'),
            (string) getenv('SQL_FIXTURE_MYSQL_USER'),
            (string) getenv('SQL_FIXTURE_MYSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $faker = Factory::create();
        $faker->seed($seed);
        $value = (new Subject())->generatePolygon($faker);
        $statement = $pdo->prepare('SELECT ST_IsValid(g), ST_IsEmpty(g), ST_GeometryType(g), ST_Dimension(g) FROM (SELECT ST_GeomFromText(?) AS g) AS geometry_input');
        $statement->execute([$value]);
        self::assertSame([1, 0, 'POLYGON', 2], $statement->fetch(PDO::FETCH_NUM));
    }

    #[DataProvider('providerGeometrySeeds')]
    public function testGenerateMultiPointProducesValidNonemptyGeometry(int $seed): void
    {
        $pdo = new PDO(
            (string) getenv('SQL_FIXTURE_MYSQL_DSN'),
            (string) getenv('SQL_FIXTURE_MYSQL_USER'),
            (string) getenv('SQL_FIXTURE_MYSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $faker = Factory::create();
        $faker->seed($seed);
        $value = (new Subject())->generateMultiPoint($faker);
        $statement = $pdo->prepare('SELECT ST_IsValid(g), ST_IsEmpty(g), ST_GeometryType(g), ST_Dimension(g) FROM (SELECT ST_GeomFromText(?) AS g) AS geometry_input');
        $statement->execute([$value]);
        self::assertSame([1, 0, 'MULTIPOINT', 0], $statement->fetch(PDO::FETCH_NUM));
    }

    #[DataProvider('providerGeometrySeeds')]
    public function testGenerateMultiLineStringProducesValidNonemptyGeometry(int $seed): void
    {
        $pdo = new PDO(
            (string) getenv('SQL_FIXTURE_MYSQL_DSN'),
            (string) getenv('SQL_FIXTURE_MYSQL_USER'),
            (string) getenv('SQL_FIXTURE_MYSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $faker = Factory::create();
        $faker->seed($seed);
        $value = (new Subject())->generateMultiLineString($faker);
        $statement = $pdo->prepare('SELECT ST_IsValid(g), ST_IsEmpty(g), ST_GeometryType(g), ST_Dimension(g) FROM (SELECT ST_GeomFromText(?) AS g) AS geometry_input');
        $statement->execute([$value]);
        self::assertSame([1, 0, 'MULTILINESTRING', 1], $statement->fetch(PDO::FETCH_NUM));
    }

    #[DataProvider('providerGeometrySeeds')]
    public function testGenerateMultiPolygonProducesValidNonemptyGeometry(int $seed): void
    {
        $pdo = new PDO(
            (string) getenv('SQL_FIXTURE_MYSQL_DSN'),
            (string) getenv('SQL_FIXTURE_MYSQL_USER'),
            (string) getenv('SQL_FIXTURE_MYSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $faker = Factory::create();
        $faker->seed($seed);
        $value = (new Subject())->generateMultiPolygon($faker);
        $statement = $pdo->prepare('SELECT ST_IsValid(g), ST_IsEmpty(g), ST_GeometryType(g), ST_Dimension(g) FROM (SELECT ST_GeomFromText(?) AS g) AS geometry_input');
        $statement->execute([$value]);
        self::assertSame([1, 0, 'MULTIPOLYGON', 2], $statement->fetch(PDO::FETCH_NUM));
    }

    #[DataProvider('providerGeometrySeeds')]
    public function testGenerateGeometryCollectionProducesValidNonemptyGeometry(int $seed): void
    {
        $pdo = new PDO(
            (string) getenv('SQL_FIXTURE_MYSQL_DSN'),
            (string) getenv('SQL_FIXTURE_MYSQL_USER'),
            (string) getenv('SQL_FIXTURE_MYSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $faker = Factory::create();
        $faker->seed($seed);
        $value = (new Subject())->generateGeometryCollection($faker);
        $statement = $pdo->prepare('SELECT ST_IsValid(g), ST_IsEmpty(g), ST_GeometryType(g), ST_Dimension(g) FROM (SELECT ST_GeomFromText(?) AS g) AS geometry_input');
        $statement->execute([$value]);
        self::assertSame([1, 0, 'GEOMCOLLECTION', 1], $statement->fetch(PDO::FETCH_NUM));
    }

    /**
     * @return list<array{int}>
     */
    public static function providerGeometrySeeds(): array
    {
        return array_map(static fn (int $seed): array => [$seed], range(1, 24));
    }
}
