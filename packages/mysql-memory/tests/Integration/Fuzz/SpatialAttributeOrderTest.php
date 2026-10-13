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
final class SpatialAttributeOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [];
        foreach (['0', '1', '4294967296', '1.5'] as $srid) {
            foreach (["NAME 'a\\nb'", "NAME 'a' NAME 'b' DESCRIPTION 'a\\nb'", "NAME 'a' NAME 'b\\nc'", "NAME 'a\\nb' NAME 'c'", "ORGANIZATION 'a\\nb' IDENTIFIED BY 4294967296", "NAME ' x ' DEFINITION 'a\\nb'", "DESCRIPTION 'a\\nb' NAME 'x' DEFINITION 'a'", "NAME 'a' DESCRIPTION 'x' NAME 'b' DEFINITION 'c\\nd'", "ORGANIZATION 'a' IDENTIFIED BY 1.5 NAME 'b\\nc'", "ORGANIZATION 'a\\nb' IDENTIFIED BY 1.5", "NAME 'a' DEFINITION 'b' DESCRIPTION '" . str_repeat('猫', 60) . "\\nx'"] as $attributes) {
                $statements[] = 'CREATE SPATIAL REFERENCE SYSTEM ' . $srid . ' ' . $attributes;
            }
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Attribute orders with competing rejection reasons
     */
    #[DataProvider('providerReleases')]
    public function testSpatialLiteralAndAttributeErrorsMatchMySql(string $release, array $statements): void
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
