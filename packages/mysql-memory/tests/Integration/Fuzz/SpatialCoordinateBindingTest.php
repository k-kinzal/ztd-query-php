<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class SpatialCoordinateBindingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, float}>}>
     */
    public static function providerBindings(): iterable
    {
        $cases = [];
        foreach (['POINT(1,2)', "ST_GEOMFROMTEXT('POINT(1 2)',4326)", 'NULL'] as $geometry) {
            foreach (['ST_X', 'ST_Y', 'ST_LONGITUDE', 'ST_LATITUDE'] as $function) {
                foreach ([1.25, NAN, INF, -INF] as $coordinate) {
                    $cases[] = ['SELECT HEX(' . $function . '(' . $geometry . ',?)) AS v', $coordinate];
                }
            }
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $cases];
        }
    }

    /**
     * @param list<array{string, float}> $cases Finite and non-finite replacement values over Cartesian, geographic and NULL inputs
     */
    #[DataProvider('providerBindings')]
    public function testBoundCoordinatesMatchNativeValuesAndConditions(string $release, array $cases): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $observe = static function (mysqli $connection, string $sql, float $coordinate): string {
                    $statement = $connection->prepare($sql);
                    self::assertNotFalse($statement);
                    $result = [];
                    try {
                        $statement->bind_param('d', $coordinate);
                        $statement->execute();
                        $rows = $statement->get_result();
                        self::assertInstanceOf(mysqli_result::class, $rows);
                        $result['result'] = (new Native())->result($rows);
                    } catch (mysqli_sql_exception $error) {
                        $result['error'] = [$error->getCode(), $error->getSqlState(), $error->getMessage()];
                    } finally {
                        $statement->close();
                    }
                    return serialize($result + (new Native())->conditions($connection));
                };
                self::assertSame(array_map(static fn (array $case): string => $observe($native, ...$case), $cases), array_map(static fn (array $case): string => $observe($memory, ...$case), $cases));
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
