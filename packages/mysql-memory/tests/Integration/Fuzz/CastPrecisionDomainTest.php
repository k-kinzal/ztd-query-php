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
final class CastPrecisionDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.1.0'] as $release) {
            $inputs = ['CREATE DATABASE precision_domain', 'USE precision_domain'];
            $types = str_starts_with($release, '5.') ? ['TIME', 'DATETIME'] : ['TIME', 'DATETIME', 'FLOAT'];
            foreach ($types as $type) {
                foreach (['0', '6', '7', '53', '54', '2147483648', '4294967296', '18446744073709551615'] as $precision) {
                    foreach (['CAST(NULL AS ' . $type . '(' . $precision . '))', 'CONVERT(NULL,' . $type . '(' . $precision . '))'] as $cast) {
                        $inputs[] = 'SELECT ' . $cast;
                        $inputs[] = 'SELECT BINARY 1, ' . $cast . ', BINARY 2';
                        $inputs[] = 'SELECT ' . $cast . ', CAST(1 AS DATETIME(8)), BINARY 2';
                        $inputs[] = 'CREATE PROCEDURE p() SELECT ' . $cast;
                        $inputs[] = 'DROP PROCEDURE IF EXISTS p';
                        $inputs[] = 'SELECT ' . $cast . ',?';
                    }
                }
            }
            if (str_starts_with($release, '5.')) {
                array_push(
                    $inputs,
                    'SELECT SQL_CACHE CAST(NULL AS TIME(7)), CAST(NULL AS DATETIME(8)),?',
                    'SELECT SQL_CACHE SQL_CACHE CAST(NULL AS TIME(7)),?',
                    'SELECT CAST(NULL AS TIME(7)), (SELECT SQL_CACHE SQL_CACHE 1),?',
                    'SELECT CAST(NULL AS TIME(7)) UNION SELECT SQL_CACHE SQL_CACHE 1,?',
                    'SELECT CAST(NULL AS TIME(7)), (SELECT DISTINCT ALL 1),?',
                    'SELECT DISTINCT ALL CAST(NULL AS TIME(7)),?',
                );
            }
            yield $release => [$release, $inputs];
        }
    }

    /**
     * @param list<string> $statements Valid and refused precisions, with competing parse and execution conditions
     */
    #[DataProvider('providerStatements')]
    public function testCastPrecisionInputBoundaryMatchesNative(string $release, array $statements): void
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
