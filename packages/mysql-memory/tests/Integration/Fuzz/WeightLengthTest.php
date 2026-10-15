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
final class WeightLengthTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        foreach (['CHAR', 'BINARY'] as $cast) {
            foreach (['0', '00000', '00001', '1.0', '1e0', '0x01', '0x00'] as $length) {
                $statements[] = 'SELECT WEIGHT_STRING(NULL AS ' . $cast . '(' . $length . '))';
            }
        }
        $statements[] = 'SELECT WEIGHT_STRING(NULL AS BINARY(0)) trailing garbage';
        $statements[] = 'SELECT BINARY 1, WEIGHT_STRING(NULL AS BINARY(0))';
        $statements[] = 'CREATE PROCEDURE d.p() SELECT WEIGHT_STRING(NULL AS BINARY(0))';
        $statements[] = "SELECT HEX(WEIGHT_STRING('abcdefghij' AS BINARY(0xA)))";
        $statements[] = "SELECT HEX(WEIGHT_STRING('abcdefghij' AS BINARY(0x20)))";
        $statements[] = "SELECT HEX(WEIGHT_STRING('abcdefghij' AS BINARY(4294967297)))";
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The same ordered history for both databases
     */
    #[DataProvider('providerInputs')]
    public function testCastLengthDomainMatchesNativeRefusalsAndWarnings(string $release, array $statements): void
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
