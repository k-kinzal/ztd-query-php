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
final class QualifyOperandPhasesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $queries = [];
        foreach (['1', '*', 'ROW(1,2)', 'missing_col', 'missing.*', '@@unknown'] as $projection) {
            foreach (['', ' FROM t', ' FROM absent'] as $from) {
                foreach (['ROW(1,2)', 'a', '@@missing', 'ROW((SELECT b FROM absent),2)'] as $predicate) {
                    $queries[] = "SELECT $projection$from QUALIFY $predicate";
                }
            }
        }
        $original = file_get_contents(__DIR__ . '/fixtures/update-unused-qualify.sql');
        self::assertIsString($original);
        $queries = [...$queries, 'WITH unused AS (SELECT 1 QUALIFY ROW(1,2)) SELECT 1', 'WITH unused AS (SELECT 1 QUALIFY ROW(1,2)) UPDATE t,t SET a=1', $original];
        foreach (['8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, [...$queries, 'CREATE DATABASE d', 'USE d', ...$queries, 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(7)', ...$queries]];
        }
    }

    /**
     * @param list<string> $statements Complete generated input and operand/state combinations
     */
    #[DataProvider('providerInputs')]
    public function testQueryContextSurvivesDeferredQualifyPredicates(string $release, array $statements): void
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
