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
final class ProgramDefinitionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE program_definition CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
            'USE program_definition',
            'CREATE PROCEDURE p(a INT) BEGIN /* retained */ SELECT a; END',
            'SHOW CREATE PROCEDURE p',
            'CALL p(7)',
            'CREATE FUNCTION f(a INT) RETURNS INT DETERMINISTIC RETURN /* retained */ a;',
            'SHOW CREATE FUNCTION f',
            'SELECT f(9)',
            'CREATE TABLE t(a INT)',
            'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @seen = 23',
            'INSERT INTO t VALUES (1)',
            'SELECT @seen',
            "SELECT ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = 'program_definition'",
            "CREATE EVENT e ON SCHEDULE EVERY 1 DAY STARTS '2030-01-01 00:00:00' DISABLE DO SET @seen = 17",
            'SHOW CREATE EVENT e',
            'CREATE PROCEDURE tail0() SELECT 1 /* before */; /* after */',
            'SHOW CREATE PROCEDURE tail0',
            'CREATE PROCEDURE tail1() BEGIN SELECT 1; END;',
            'SHOW CREATE PROCEDURE tail1',
            "CREATE PROCEDURE tail2() SELECT ';' /* before */;",
            'SHOW CREATE PROCEDURE tail2',
            'ALTER EVENT e DO SET @seen = 19',
            'SHOW CREATE EVENT e',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The stored definition history to compare
     */
    #[DataProvider('providerReleases')]
    public function testStoredDefinitionTextAndExecutionMatchNative(string $release, array $statements): void
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
