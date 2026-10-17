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
final class NativeCallSignatureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['SELECT ABS()', 'CREATE TABLE t(a INT DEFAULT (ABS()))', 'SET @a=ABS()', 'CREATE DATABASE d', 'USE d'];
        foreach (['ABS()', 'ABS(1,2)', 'ABS(1 AS n)', 'ABS(1 AS n,2)', 'ABS(1)', 'CONCAT()', 'CONCAT(1,2)', 'REGEXP_LIKE()', 'X()', 'd.ABS()', 'INTERNAL_TABLE_ROWS()', 'ABS(ROUND())', 'ABS(@@unknown_variable)', "ABS(DATE 'not-a-date')"] as $call) {
            $statements[] = 'SELECT ' . $call;
            $statements[] = 'SELECT BINARY 1,' . $call . ',BINARY 2';
            $statements[] = 'SELECT ' . $call . ' FROM absent';
        }
        $statements[] = 'SELECT ABS(),';
        $statements[] = 'SELECT BINARY 1,ABS() +';
        $statements[] = 'SELECT ABS(ROUND()),ACOS()';
        $statements[] = 'CREATE PROCEDURE p() BEGIN IF 0 THEN SELECT ABS(); END IF; END';
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, [...$statements, ...(!str_starts_with($release, '5.') ? ['SHOW PARSE_TREE SELECT ABS()', 'SHOW PARSE_TREE SELECT BINARY 1,ABS(),BINARY 2'] : [])]];
        }
    }

    /**
     * @param list<string> $statements Native signatures, competing input diagnostics and unfinished grammar operands
     */
    #[DataProvider('providerInputs')]
    public function testNativeCallInputRefusalsMatchNative(string $release, array $statements): void
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
