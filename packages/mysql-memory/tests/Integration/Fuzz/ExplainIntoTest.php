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
final class ExplainIntoTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['CREATE DATABASE d'];
        foreach (['INTO @p', 'FORMAT=TREE INTO @p', 'FORMAT=TRADITIONAL INTO @p', 'FORMAT=JSON INTO @p'] as $options) {
            foreach (['SELECT 1', 'SELECT BINARY 1', "SELECT DATE 'bad'", "SELECT 'unfinished"] as $operand) {
                foreach (['', 'SHOW PARSE_TREE ', 'CREATE PROCEDURE d.p() '] as $prefix) {
                    $statements[] = $prefix . 'EXPLAIN ' . $options . ' ' . $operand;
                    $statements[] = 'DROP PROCEDURE IF EXISTS d.p';
                }
            }
        }
        $statements = [...$statements,
            "SHOW PARSE_TREE DESC INTO @'text' INSERT IGNORE `name` SET PREPARE ._sqlfaker_identifier = DEFAULT AS SHUTDOWN",
            'CREATE PROCEDURE d.p() BEGIN IF 0 THEN EXPLAIN INTO @p SELECT 1; END IF; END',
            'EXPLAIN INTO @p FOR CONNECTION 0',
            'EXPLAIN ANALYZE FORMAT=TREE INTO @p SELECT BINARY 1',
            'SHOW PARSE_TREE EXPLAIN ANALYZE FORMAT=TREE INTO @p SELECT BINARY 1',
        ];
        foreach (['8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Completed options, debug wrappers, routine bodies and unfinished operands
     */
    #[DataProvider('providerInputs')]
    public function testIntoRefusalsRetainNativeInputBoundariesAndNotices(string $release, array $statements): void
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
