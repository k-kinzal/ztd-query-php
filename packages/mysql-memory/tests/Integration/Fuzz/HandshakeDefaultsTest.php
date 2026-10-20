<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Containers\PortStrategy\StaticPortStrategy;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class HandshakeDefaultsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, string}>}>
     */
    public static function providerInputs(): iterable
    {
        $cases = [];
        foreach (['utf8mb4', 'latin1'] as $input) {
            foreach (['utf8mb4_bin', 'latin1_swedish_ci'] as $connection) {
                foreach (['NULL', 'latin1', 'utf8mb4'] as $result) {
                    foreach (['', ';charset=utf8mb4', ';charset=latin1'] as $client) {
                        $cases[] = ['SET GLOBAL character_set_client=' . $input . ', GLOBAL collation_connection=' . $connection . ', GLOBAL character_set_results=' . $result, $client];
                    }
                }
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $cases];
        }
    }

    /**
     * @param list<array{string, string}> $cases Global defaults and the negotiated connection encoding
     */
    #[DataProvider('providerInputs')]
    public function testNewConnectionsMatchNativeInheritedAndNegotiatedEncodings(string $release, array $cases): void
    {
        $container = Testcontainers::run((new DisposableMySql($release))->withPortStrategy(new StaticPortStrategy(29760)));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $dsn = 'mysql:host=' . $endpoint->host . ';port=' . $endpoint->port;
            $native = new PDO($dsn, $endpoint->username, $endpoint->password);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '');
                $expected = array_map(fn (array $case): string => (new \Tests\Connection\HandshakeDefaults())->observe($native, $dsn, $endpoint->username, $endpoint->password, $case), $cases);
                $actual = array_map(fn (array $case): string => (new \Tests\Connection\HandshakeDefaults())->observe($memory, $server->dsn(), 'root', '', $case), $cases);
                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

}
