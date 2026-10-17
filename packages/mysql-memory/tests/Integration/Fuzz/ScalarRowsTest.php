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
final class ScalarRowsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = ['CREATE DATABASE placement', 'USE placement'];
        foreach (['(1,2)', 'ROW(1,2)', '1 MEMBER OF ((1,2))', 'ABS((1,2))', 'SUM(SUM(1))', 'CASE (1,2) WHEN (1,2) THEN 1 ELSE 0 END', '(1,2) BETWEEN (0,1) AND (2,3)', '((1,2),3)=((1,2),3)'] as $expression) {
            $query = 'SELECT ' . $expression;
            array_push($statements, $query, 'CREATE PROCEDURE p() ' . $query, 'CALL p()', 'DROP PROCEDURE IF EXISTS p', 'CREATE PROCEDURE p() BEGIN IF FALSE THEN ' . $query . '; END IF; END', 'CALL p()', 'DROP PROCEDURE IF EXISTS p');
        }
        foreach (['CASE (1,2) WHEN missing THEN 1 END', 'CASE missing WHEN (1,2) THEN 1 END', 'CASE (1,2) WHEN (1,2) THEN missing END', 'CASE (1,2) WHEN (1,2) THEN 1 ELSE missing END', 'CASE WHEN (1,2) THEN missing END', 'CASE WHEN missing THEN (1,2) END', 'CASE (1,2) WHEN 1 THEN 2 END', 'CASE 1 WHEN (1,2) THEN 2 END', 'CASE (1,2) WHEN (1,2,3) THEN 2 END', 'CASE (SELECT 1,2) WHEN (SELECT 1,2) THEN 1 END'] as $expression) {
            array_push($statements, 'SELECT ' . $expression, 'SELECT ' . $expression . ' FROM absent_table');
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Row expressions, scalar use positions and stored-program histories
     */
    #[DataProvider('providerStatements')]
    public function testRowsKeepTheirExpressionAndDeferredProgramConditions(string $release, array $statements): void
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
