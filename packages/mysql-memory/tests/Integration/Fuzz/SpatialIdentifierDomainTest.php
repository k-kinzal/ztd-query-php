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
final class SpatialIdentifierDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        foreach (['1.5', '1e2', '18446744073709551616'] as $number) {
            array_push(
                $statements,
                'DROP SPATIAL REFERENCE SYSTEM ' . $number,
                'DROP SPATIAL REFERENCE SYSTEM IF EXISTS ' . $number,
                'CREATE SPATIAL REFERENCE SYSTEM ' . $number . " NAME 'a' DEFINITION 'b'",
                'CREATE OR REPLACE SPATIAL REFERENCE SYSTEM ' . $number . " NAME 'a'",
                "CREATE SPATIAL REFERENCE SYSTEM 7 ORGANIZATION 'a' IDENTIFIED BY " . $number . " NAME 'a\\nb'",
                'DROP SPATIAL REFERENCE SYSTEM ' . $number . ' BOGUS',
                'CREATE PROCEDURE p() BEGIN IF FALSE THEN DROP SPATIAL REFERENCE SYSTEM ' . $number . '; END IF; END',
            );
        }
        array_push($statements, 'DROP SPATIAL REFERENCE SYSTEM IF EXISTS 0x10', 'DROP SPATIAL REFERENCE SYSTEM IF EXISTS 18446744073709551615');
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Attribute orders with competing rejection reasons
     */
    #[DataProvider('providerReleases')]
    public function testIntegerRefusalsKeepNativeInputOrder(string $release, array $statements): void
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
