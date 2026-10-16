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
final class ProgramValueHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = ['CREATE DATABASE d','USE d','CREATE TABLE t(a INT)','INSERT INTO t VALUES(0)'];
        $bodies = ['BEGIN IF FALSE THEN SELECT 1; END IF; END','BEGIN DECLARE v INT DEFAULT 0; IF v THEN SELECT 1; END IF; END','BEGIN IF 1-1 THEN SELECT 1; END IF; END','BEGIN IF (SELECT 0) THEN SELECT 1; END IF; END','BEGIN IF (SELECT a FROM t LIMIT 1) THEN SELECT 1; END IF; END','BEGIN WHILE FALSE DO SELECT 1; END WHILE; END','BEGIN CASE WHEN FALSE THEN SELECT 1; ELSE BEGIN END; END CASE; END','BEGIN DECLARE v INT DEFAULT 1; END','BEGIN DECLARE v INT; SET v=1; END',"BEGIN IF 'new'+0 THEN SELECT 1; END IF; END",'BEGIN IF EXISTS(SELECT * FROM t) THEN BEGIN END; END IF; END', "BEGIN DECLARE CONTINUE HANDLER FOR SQLWARNING SET @hits=@hits+1; SET @hits=0; DO 'new'+0; IF FALSE THEN SELECT 1; END IF; SELECT @hits; END"];
        foreach ($bodies as $index => $body) {
            $statements[] = 'CREATE PROCEDURE p' . $index . '() ' . $body;
        }
        foreach ($bodies as $index => $body) {
            foreach (["SELECT 'old'+0", 'SELECT (1,2)'] as $prior) {
                array_push($statements, 'SELECT * FROM t', $prior, 'CALL p' . $index . '()');
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Control conditions and defaults, including subqueries, following stale warnings or errors
     */
    #[DataProvider('providerStatements')]
    public function testProgramExpressionsPreserveNativeDiagnosticHistory(string $release, array $statements): void
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
