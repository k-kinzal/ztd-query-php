<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use mysqli;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ProfileResponseTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, list<list<string>>}>
     */
    public static function providerHistories(): iterable
    {
        $cases = [
            ['CREATE PROCEDURE p() SHOW PROFILE', 'CALL p()'],
            ['CREATE PROCEDURE p() SHOW PROFILES', 'CALL p()'],
            ['CREATE PROCEDURE p() BEGIN IF FALSE THEN SELECT 1; END IF; SHOW PROFILE; END', 'CALL p()'],
            ['CREATE PROCEDURE p() BEGIN SHOW PROFILE; SELECT 1; END', 'CALL p()'],
            ['CREATE PROCEDURE p() BEGIN SELECT 1 INTO @v; SHOW PROFILE; END', 'CALL p()'],
            ['CREATE PROCEDURE q() SHOW PROFILE', 'CREATE PROCEDURE p() BEGIN CALL q(); END', 'CALL p()'],
            ['CREATE PROCEDURE q() SHOW PROFILE', 'CREATE PROCEDURE p() BEGIN IF FALSE THEN SELECT 1; END IF; CALL q(); END', 'CALL p()'],
            ['CREATE FUNCTION f() RETURNS INT DETERMINISTIC BEGIN SHOW PROFILE; RETURN 1; END', 'SELECT f()'],
            ['CREATE FUNCTION f() RETURNS INT DETERMINISTIC BEGIN SHOW PROFILE; RETURN 1; END', 'SELECT 2,f()'],
            ['CREATE FUNCTION f() RETURNS INT DETERMINISTIC BEGIN SHOW PROFILES; RETURN 1; END', 'DO f()'],
            ['CREATE TABLE t(a INT)', 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SHOW PROFILE', 'INSERT INTO t VALUES(1)'],
        ];
        foreach (['5.6.51', '5.7.44', '8.4.7', '9.1.0'] as $release) {
            foreach ([false, true] as $prepared) {
                yield $release . ':' . (int) $prepared => [$release, $prepared, $cases];
            }
        }
    }

    /**
     * @param list<list<string>> $cases Independent routine histories, each followed by a connection-liveness query
     */
    #[DataProvider('providerHistories')]
    public function testMissingDatabasePrecedesBodyNoticesInBothInputProtocols(string $release, bool $prepared, array $cases): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $statements = array_column($cases, 0);
                $observer = new Native();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, prepared: $prepared)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, prepared: $prepared)), $statements);

                self::assertSame($expected, $actual);
                $memory->close();
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

    /**
     * @param list<list<string>> $cases Independent routine histories, each followed by a connection-liveness query
     */
    #[DataProvider('providerHistories')]
    public function testMysqliObservesDeclaredContinuationAndResultsInsideCallerRows(string $release, bool $prepared, array $cases): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $server = Server::start($release);
            try {
                $histories = array_map(static function (array $statements, int $index) use ($endpoint, $server, $prepared): array {
                    $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
                    $memory = new mysqli($server->host, 'root', '', port: $server->port);
                    try {
                        $native->query('CREATE DATABASE d' . $index);
                        $native->select_db('d' . $index);
                        $memory->query('CREATE DATABASE d' . $index);
                        $memory->select_db('d' . $index);
                        $steps = [...$statements, 'SELECT 2'];
                        $observer = new Native();
                        return [array_map(static fn (string $sql, int $step): string => serialize($observer->observe($native, $sql, prepared: $prepared && $step >= count($steps) - 2)), $steps, array_keys($steps)), array_map(static fn (string $sql, int $step): string => serialize($observer->observe($memory, $sql, prepared: $prepared && $step >= count($steps) - 2)), $steps, array_keys($steps))];
                    } finally {
                        $native->close();
                        $memory->close();
                    }
                }, $cases, array_keys($cases));

                self::assertSame(array_column($histories, 0), array_column($histories, 1));
                $fresh = new mysqli($server->host, 'root', '', port: $server->port);
                self::assertSame([['2']], (new Native())->observe($fresh, 'SELECT 2')['results'][0]['rows']);
                $fresh->close();
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

    /**
     * @param list<list<string>> $cases The same histories through PDO, including its driver error and condition-reading outcome
     */
    #[DataProvider('providerHistories')]
    public function testPdoObservesTheSameResponseBoundaries(string $release, bool $prepared, array $cases): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $server = Server::start($release);
            try {
                $histories = array_map(static function (array $statements, int $index) use ($endpoint, $server, $prepared): array {
                    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => !$prepared];
                    $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, $options);
                    $memory = new PDO($server->dsn(), 'root', '', $options);
                    $steps = ['CREATE DATABASE d' . $index, 'USE d' . $index, ...$statements, 'SELECT 2'];
                    $observer = new Observer();
                    return [array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $steps), array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $steps)];
                }, $cases, array_keys($cases));

                self::assertSame(array_column($histories, 0), array_column($histories, 1));
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
