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
final class LoadAssignmentPhasesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $operands = ['1', 'DEFAULT', 'ROW(1,2)', '1+ROW(1,2)', 'ROW(@@missing,2)', 'ROW((SELECT a FROM absent),2)', '(SELECT * FROM absent)', 'SUM(ROW(1,2))', 'SUM(ROW(1,2)) OVER w', '1 COLLATE missing', 'ROW(1,2) COLLATE missing', 'CONVERT(ROW(1,2) USING missing)', 'ROW(1,2)+@@missing'];
        $queries = [];
        foreach (['DATA', 'XML'] as $format) {
            foreach ($operands as $operand) {
                foreach (['INFILE', 'LOCAL INFILE', 'URL'] as $source) {
                    $queries[] = "LOAD $format $source 'f' INTO TABLE t SET a=$operand";
                }
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, [...$queries, 'CREATE DATABASE d', 'USE d', ...$queries, 'CREATE TABLE t(a INT)', ...$queries, 'CREATE TABLE absent(a INT)', 'INSERT INTO absent VALUES(7)', ...$queries, ...array_map(static fn (string $sql): string => 'CREATE PROCEDURE p() ' . $sql, $queries)]];
        }
    }

    /**
     * @param list<string> $statements Complete generated input and operand/state combinations
     */
    #[DataProvider('providerInputs')]
    public function testInputAndRelationConditionsPrecedeNonScalarAssignments(string $release, array $statements): void
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
