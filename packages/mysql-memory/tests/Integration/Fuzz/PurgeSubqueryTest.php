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
final class PurgeSubqueryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE PROCEDURE p() PURGE BINARY LOGS BEFORE (SELECT 1)',
            'CREATE DATABASE purge_domain',
            'USE purge_domain',
            'PURGE BINARY LOGS BEFORE (SELECT 1)',
            'PURGE BINARY LOGS BEFORE ((SELECT 1))',
            'PURGE BINARY LOGS BEFORE 1 + (SELECT 1)',
            'PURGE BINARY LOGS BEFORE EXISTS (SELECT 1)',
            'PURGE BINARY LOGS BEFORE 1 IN (SELECT 1)',
            'PURGE BINARY LOGS BEFORE 1 = ANY (SELECT 1)',
            'PURGE BINARY LOGS BEFORE CASE WHEN 0 THEN (SELECT 1) ELSE 0 END',
            'PURGE BINARY LOGS BEFORE (SELECT a.b.c)',
            'PURGE BINARY LOGS BEFORE BINARY 0 + (SELECT BINARY 1)',
            'PURGE BINARY LOGS BEFORE (SELECT 1) + BINARY 1',
            'PURGE BINARY LOGS BEFORE CONVERT(1 USING wrong) + (SELECT 1)',
            'PURGE BINARY LOGS BEFORE (SELECT CONVERT(1 USING wrong))',
            "PURGE BINARY LOGS BEFORE (SELECT DATE 'invalid')",
            'PURGE BINARY LOGS BEFORE (SELECT 1) + ?',
            'PURGE BINARY LOGS BEFORE (SELECT 1) abc',
            'PURGE BINARY LOGS BEFORE (SELECT 1) +',
            'PURGE BINARY LOGS BEFORE CASE WHEN 0 THEN (SELECT 1) +',
            'PURGE BINARY LOGS BEFORE EXISTS (SELECT',
            'PURGE BINARY LOGS BEFORE (SELECT FROM',
            "PURGE BINARY LOGS BEFORE (SELECT 1) 'unclosed",
            'CREATE PROCEDURE p() PURGE BINARY LOGS BEFORE (SELECT 1)',
            'CREATE PROCEDURE p() BEGIN IF 0 THEN PURGE BINARY LOGS BEFORE (SELECT 1); END IF; END',
            'CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE x INT; PURGE BINARY LOGS BEFORE (SELECT 1); END',
            "PREPARE p FROM 'PURGE BINARY LOGS BEFORE (SELECT 1)'",
            'SELECT (SELECT 1)',
            'PURGE BINARY LOGS BEFORE VALUES(a.b.c)',
            'CREATE PROCEDURE q() PURGE BINARY LOGS BEFORE x',
            'CALL q()',
            'CREATE PROCEDURE r() BEGIN PURGE BINARY LOGS BEFORE x; SELECT (SELECT 1); END',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Refused query operands and preceding input conditions
     */
    #[DataProvider('providerReleases')]
    public function testQueryFreePurgeDomainPreservesNativeInputOutcomes(string $release, array $statements): void
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
