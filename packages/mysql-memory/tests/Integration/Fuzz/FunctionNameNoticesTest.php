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
final class FunctionNameNoticesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = [
            'CREATE FUNCTION `ORDER`(x INT) RETURNS INT RETURN x',
            'CREATE FUNCTION missing.`ORDER`(x INT) RETURNS INT RETURN x',
            'CREATE FUNCTION missing.`ORDER`(x INT) RETURNS INT RETURN',
            "CREATE FUNCTION missing.`ORDER`(x INT) RETURNS INT RETURN DATE 'bad'",
            'CREATE FUNCTION missing.`ORDER`(x INT, x INT) RETURNS INT RETURN x',
            'CREATE DATABASE d', 'USE d', 'SET GLOBAL log_bin_trust_function_creators=1',
            'CREATE FUNCTION d.`COLLATION`(x INT) RETURNS INT BEGIN SET @visits=COALESCE(@visits,0)+1; RETURN @visits; END',
            'CREATE FUNCTION d.`ORDER`(x INT) RETURNS INT RETURN x',
            'CREATE FUNCTION d.ABS(x INT) RETURNS INT RETURN 7',
            'SELECT `COLLATION`(3), d.`ORDER`(3), ABS(3), d.ABS(3)',
            'SELECT `COLLATION`(3), d.`ORDER`(3), ABS(3), d.ABS(3)',
            'CREATE TABLE t(x INT)', 'INSERT INTO t VALUES (1),(2),(3)',
            'SET @visits=0',
            'SELECT (SELECT `COLLATION`(3)) AS visits FROM t',
            'SELECT @visits',
            'SELECT d.`ORDER`(3) AS alias, (d.ABS(3)) AS parens, ABS(3), d.ABS(3)+1 AS expr',
            'FLUSH TABLES', 'SELECT `ORDER`(3)',
            "ALTER FUNCTION d.`ORDER` COMMENT 'changed'", 'SELECT `ORDER`(3)', 'SELECT `ORDER`(3)',
            'DROP FUNCTION d.`ORDER`', 'SELECT `ORDER`(3)',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Name resolution, load notices, refusal order and repeated row evaluation
     */
    #[DataProvider('providerInputs')]
    public function testFunctionNamesPreserveNativePrecedenceNoticesAndPerRowEvaluation(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        $server = Server::start($release);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $memory = new mysqli($server->host, 'root', '', port: $server->port);
            try {
                $native->set_charset('utf8mb4');
                $memory->set_charset('utf8mb4');
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $memory->close();
            }
        } finally {
            $server->stop();
            $container->stop();
        }
    }
}
