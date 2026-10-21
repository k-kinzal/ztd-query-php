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
final class FunctionalOperandDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, int}>}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            $statements = [['CREATE INDEX k ON t ((?))', 1], ['ALTER TABLE t ADD KEY k ((?))', 1], ['CREATE DATABASE d', 0], ['USE d', 0], ['CREATE INDEX k ON t ((?))', 1], ['ALTER TABLE t ADD KEY k ((?))', 1], ['SET @run=0', 0]];
            foreach (['a', '(SELECT 1)', 'RAND()', 'ABS(a)', 'NOW()', '@x', '@@sql_mode', '?', 'SUM(a)', 'ROW_NUMBER() OVER ()', 'f(a)', '(SELECT f(a))', '(SELECT f(a) FROM missing)', 'DEFAULT(a)', "NAME_CONST('a',1)", 'CAST(@x AS UNSIGNED ARRAY)'] as $expression) {
                $parameters = $expression === '?' ? 1 : 0;
                $create = 'CREATE TABLE t(a INT,KEY k ((' . $expression . ')))';
                array_push($statements, ['DROP TABLE IF EXISTS t', 0], [$create, $parameters], ['SHOW CREATE TABLE t', 0], ['DROP TABLE IF EXISTS t', 0], ['CREATE TABLE t(a INT)', 0], ['INSERT INTO t VALUES (7)', 0], [str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $create), $parameters], ['CREATE INDEX j ON t ((' . $expression . '))', $parameters], ['ALTER TABLE t ADD INDEX k ((' . $expression . '))', $parameters]);
                if ($parameters === 0) {
                    $program = 'CREATE PROCEDURE p() BEGIN IF @run THEN ALTER TABLE t ADD INDEX q ((' . $expression . ')); END IF; END';
                    array_push($statements, [$program, 0], ['CALL p()', 0], ['SET @run=1', 0], ['CALL p()', 0], ['SET @run=0', 0], ['CALL p()', 0], ['DROP PROCEDURE p', 0]);
                }
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<array{string, int}> $statements
     */
    #[DataProvider('providerStatements')]
    public function testFunctionalOperandsKeepTheirConditionalPreparationRole(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (array $input): string => serialize((new Native())->observe($native, ...$input)), $statements);
                $actual = array_map(static fn (array $input): string => serialize((new Native())->observe($memory, ...$input)), $statements);
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
