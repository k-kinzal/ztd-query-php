<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class SpatialAggregateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'SELECT ST_COLLECT(NULL)',
            'SELECT ST_COLLECT(Point(1,2))',
            'SELECT HEX(ST_COLLECT(g)) AS result FROM (SELECT Point(2,3) AS g UNION ALL SELECT Point(1,2) UNION ALL SELECT Point(2,3) UNION ALL SELECT NULL) AS t',
            'SELECT HEX(ST_COLLECT(DISTINCT g)) AS result FROM (SELECT Point(2,3) AS g UNION ALL SELECT Point(1,2) UNION ALL SELECT Point(2,3)) AS t',
            'SELECT HEX(ST_COLLECT(g)) AS result FROM (SELECT Point(2,3) AS g UNION ALL SELECT MultiPoint(Point(1,2)) UNION ALL SELECT GeometryCollection()) AS t',
            'SELECT HEX(ST_COLLECT(GeometryCollection()))',
            'SELECT ST_COLLECT(1)',
            'SELECT ST_COLLECT(\'a\')',
            'SELECT ST_COLLECT(\'\')',
            'SELECT ST_COLLECT(TRUE)',
            'SELECT HEX(ST_COLLECT(Point(1,2))) WHERE FALSE',
            'SELECT HEX(ST_COLLECT(DISTINCT g) OVER ()) AS result FROM (SELECT Point(2,3) AS g UNION ALL SELECT Point(1,2) UNION ALL SELECT Point(2,3)) AS t',
            'SELECT HEX(ST_COLLECT(g) OVER (ORDER BY n ROWS BETWEEN 1 PRECEDING AND CURRENT ROW)) AS result FROM (SELECT 1 AS n, Point(2,3) AS g UNION ALL SELECT 2,Point(1,2) UNION ALL SELECT 3,Point(2,3)) AS t',
            'SELECT ST_COLLECT(g) FROM (SELECT Point(2,3) AS g UNION ALL SELECT ST_SRID(Point(1,2),4326)) AS t',
            'SELECT ST_COLLECT(ST_SRID(Point(1,2),999999))',
            'SELECT ST_COLLECT(Point(1,2)) OVER ()',
            'SELECT ST_COLLECT(NULL) OVER ()',
            'SELECT ST_COLLECT(DISTINCT Point(1,2)) OVER ()',
            'SELECT HEX(ST_COLLECT(LineString(Point(1,2), Point(3,4))))',
            'SELECT HEX(ST_COLLECT(Polygon(LineString(Point(0,0), Point(1,0), Point(1,1), Point(0,0)))))',
            'SELECT HEX(ST_COLLECT(ST_SRID(Point(1,2),4326)))',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The spatial aggregate result and error observations
     */
    #[DataProvider('providerReleases')]
    public function testSpatialAggregationMatchesTheNativeServer(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
