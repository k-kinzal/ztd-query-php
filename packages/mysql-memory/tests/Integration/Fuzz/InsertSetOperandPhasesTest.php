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
final class InsertSetOperandPhasesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $operands = ['ROW(1,2)', 'ROW(b,2)', 'ROW(@@missing,2)', 'ROW((SELECT id FROM other),2)', '1+ROW(1,2)'];
            if (!str_starts_with($release, '5.')) {
                $operands = [...$operands, ...array_map(static fn (string $value): string => "CAST($value AT TIME ZONE 'invalid' AS DATETIME)", $operands)];
            }
            $operands = [...$operands, 'TRIM(ROW(1,2) FROM b)', 'TRIM(b FROM ROW(1,2))', 'TRIM(ROW(1,2) FROM @@missing)', 'TRIM(ROW(1,2) FROM (SELECT id FROM other))', 'TIMESTAMPADD(DAY,ROW(1,2),b)', 'TIMESTAMPADD(DAY,b,ROW(1,2))', 'TIMESTAMPDIFF(DAY,ROW(1,2),b)', 'TIMESTAMPDIFF(DAY,b,ROW(1,2))'];
            $queries = [];
            foreach ($operands as $value) {
                $queries = [...$queries, "SELECT @@missing+$value", "REPLACE INTO t SET a=$value", "INSERT INTO t SET a=DEFAULT, b=$value", "INSERT INTO t SET a=1 ON DUPLICATE KEY UPDATE b=$value", "EXPLAIN INSERT INTO t SET a=$value"];
            }
            $original = in_array($release, ['8.4.7', '9.1.0'], true) ? [(string) file_get_contents(__DIR__ . '/fixtures/replace-timezone-row.sql')] : [];
            yield $release => [$release, [...$original, ...$queries, 'CREATE DATABASE d', 'USE d', ...$queries, 'CREATE TABLE t(x INT)', ...$queries, 'ALTER TABLE t ADD a INT, ADD b INT', 'CREATE TABLE other(id INT)', 'INSERT INTO other VALUES(7)', 'INSERT INTO t VALUES(1,2,3)', ...$queries]];
        }
    }

    /**
     * @param list<string> $statements Complete operands and repeated destination states
     */
    #[DataProvider('providerInputs')]
    public function testPreparationRetainsEverySetOperandAndItsDestination(string $release, array $statements): void
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
