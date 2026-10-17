<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class SpatialContainsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $release) {
            $geometries = ['NULL', "'text'", 'POINT(0,0)', 'POINT(1,1)', 'POINT(0,1)', 'LINESTRING(POINT(0,0),POINT(2,2))', 'LINESTRING(POINT(0,0),POINT(0,2))', 'MULTIPOINT(POINT(0,0),POINT(2,2))', 'GEOMETRYCOLLECTION()', 'GEOMETRYCOLLECTION(POINT(0,0))', 'POLYGON(LINESTRING(POINT(0,0),POINT(2,0),POINT(2,2),POINT(0,2),POINT(0,0)))'];
            $statements = [];
            foreach ($geometries as $left) {
                foreach ($geometries as $right) {
                    $statements[] = 'SELECT CONTAINS(' . $left . ',' . $right . ') AS value';
                }
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Typed literals, dependent operands and reached/skipped stored instructions
     */
    #[DataProvider('providerReleases')]
    public function testContainsMatchesTheNativeGeometryPairs(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $catalog = $native->query('SELECT COUNT(*) FROM mysql.time_zone_name');
            self::assertInstanceOf(mysqli_result::class, $catalog);
            $row = $catalog->fetch_row();
            self::assertIsArray($row);
            self::assertArrayHasKey(0, $row);
            self::assertIsString($row[0]);
            $server = Server::start($release, namedTimeZones: (int) $row[0] > 0);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
    /**
     * @return iterable<string, array{string, list<\SqlSemantics\Platform\MySql\Statement\Call\KeywordCall>}>
     */
    public static function providerConstructionReleases(): iterable
    {
        $choices = new \Fuzz\Structure\Choices('contains-typed-20261017');
        $geometry = static function () use ($choices): \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall {
            $points = [];
            for ($index = 0; $index < 2; $index++) {
                $points[] = new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::Point, [new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) $choices->integer(0, 4)), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) $choices->integer(0, 4))]);
            }
            return match ($choices->integer(0, 3)) {
                0 => $points[0],
                1 => new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::LineString, $points),
                2 => new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::MultiPoint, $points),
                default => new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::GeometryCollection, $points),
            };
        };
        $calls = [];
        for ($trial = 0; $trial < 100; $trial++) {
            $calls[] = new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::Contains, [$geometry(), $geometry()]);
        }
        yield '5.6.51' => ['5.6.51', $calls];
        yield '5.7.44' => ['5.7.44', $calls];
    }

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\Call\KeywordCall> $calls
     */
    #[DataProvider('providerConstructionReleases')]
    public function testGeneratedGeometryCallsAgreeWithoutParsingTheirInput(string $release, array $calls): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new \MySqlMemory\Instance($release))->connect();
            $session->text = 'unrelated invalid input';
            $expected = array_map(static fn ($call): string => serialize((new Native())->observe($native, (new \Fuzz\Structure\Reference())->sql($call, $session->semantics()->profile()))), $calls);
            $actual = array_map(static fn ($call): string => serialize((new \Fuzz\Structure\Audit\Memory())->observe($session, $call)), $calls);
            self::assertSame($expected, $actual, $release);
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
