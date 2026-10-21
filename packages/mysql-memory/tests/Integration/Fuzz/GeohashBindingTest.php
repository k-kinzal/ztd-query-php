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
final class GeohashBindingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, int|float|string|null, string}>}>
     */
    public static function providerBindings(): iterable
    {
        $cases = [];
        foreach (['ST_GEOHASH(?,0,100)', 'ST_GEOHASH(0,?,100)', 'ST_GEOHASH(0,0,?)', "ST_POINTFROMGEOHASH('s',?)", 'ST_LATFROMGEOHASH(?)'] as $function) {
            foreach ([1.25, NAN, INF, -INF] as $coordinate) {
                $cases[] = ['SELECT ' . $function . ' AS v', $coordinate, 'd'];
            }
        }
        foreach (['ST_GEOHASH(?,0,4)', 'ST_GEOHASH(0,?,4)', 'ST_GEOHASH(0,0,?)', 'ST_LATFROMGEOHASH(?)', "ST_POINTFROMGEOHASH('s',?)", 'ST_POINTFROMGEOHASH(?,0)', 'ST_GEOHASH(?,4)'] as $function) {
            foreach ([['i', 1], ['d', 1.0], ['s', '1'], ['s', 's'], ['s', null], ['i', null], ['d', null]] as [$type, $value]) {
                $cases[] = ['SELECT ' . $function . ' AS v', $value, $type];
            }
        }
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $cases];
        }
    }

    /**
     * @param list<array{string, int|float|string|null, string}> $cases Finite and non-finite binary bindings in coordinate, length, reference and hash roles
     */
    #[DataProvider('providerBindings')]
    public function testBoundGeohashOperandsMatchNativeValuesAndConditions(string $release, array $cases): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $observe = static function (mysqli $connection, string $sql, int|float|string|null $value, string $type): string {
                    $result = [];
                    try {
                        $statement = $connection->prepare($sql);
                        self::assertNotFalse($statement);
                        try {
                            $statement->bind_param($type, $value);
                            $statement->execute();
                            $rows = $statement->get_result();
                            self::assertInstanceOf(mysqli_result::class, $rows);
                            $result['result'] = (new Native())->result($rows);
                        } finally {
                            $statement->close();
                        }
                    } catch (mysqli_sql_exception $error) {
                        $result['error'] = [$error->getCode(), $error->getSqlState(), $error->getMessage()];
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
