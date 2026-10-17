<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class StoredSetRowsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        $bodies = ['SET @x=(1,2)', 'SET @x=(missing,2)', 'SET p=(1,2)', 'SET missing_target=(1,2)', 'SET @@missing_target=(1,2)', 'SET @@sql_mode=(missing,2)', 'SET GLOBAL sql_mode=(1,2)', 'SET @x=10,@y=(1,2)', 'SET p=10,@y=(1,2)', 'SET @x=(1,2),@y=20', 'SET p=10,q=(1,2)', 'SET p=(1,2),q=20', 'SET @@missing_target=(missing,2)'];
        foreach ($bodies as $body) {
            $statements[] = 'SET @x=1,@y=2';
            $statements[] = 'DROP PROCEDURE IF EXISTS p';
            $statements[] = 'CREATE PROCEDURE p(INOUT p INT,INOUT q INT) ' . $body;
            $statements[] = 'CALL p(@x,@y)';
            $statements[] = 'SELECT @x,@y';
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Stored definitions, reached instructions and skipped instructions
     */
    #[DataProvider('providerReleases')]
    public function testStoredAssignmentTargetsDependenciesAndWriteHistoryAgree(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, substr_count($sql, '?'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, substr_count($sql, '?'))), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
