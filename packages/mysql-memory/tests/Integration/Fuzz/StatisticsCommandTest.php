<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use Fuzz\Target\StatisticsObservation;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class StatisticsCommandTest extends TestCase
{
    /**
     * @return iterable<string, array{bool}>
     */
    public static function providerServers(): iterable
    {
        yield 'MySQL' => [false];
        yield 'memory' => [true];
    }

    #[DataProvider('providerServers')]
    public function testPdoServerInfoAgreesWithStatusAndCountsOnlyAsAQuery(bool $memory): void
    {
        [$target] = Servers::shared();
        $target->repair($memory ? $target->memoryGuard() : $target->guard());
        $pdo = new PDO($memory ? $target->memory : $target->native, $memory ? 'root' : $target->nativeUser, $memory ? '' : $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        self::assertTrue(StatisticsObservation::settled($pdo), 'Connection cleanup did not settle before the counter observation.');
        $pdo->exec('SET timestamp=1234567890');
        $counts = static function () use ($pdo): array {
            $statement = $pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Queries','Questions','Uptime','Open_tables','Opened_tables','Flush_commands','Slow_queries','Threads_connected')");
            self::assertNotFalse($statement);
            $values = [];
            foreach ($statement->fetchAll(PDO::FETCH_KEY_PAIR) as $name => $value) {
                self::assertIsString($name);
                self::assertIsString($value);
                self::assertIsNumeric($value);
                $values[$name] = $value;
            }

            return $values;
        };
        $before = $counts();
        $info = $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
        $after = $counts();
        $queries = bcadd($before['Queries'], '1', 0);
        $average = bcdiv($queries, $before['Uptime'], 3);

        self::assertSame('Uptime: ' . $before['Uptime'] . '  Threads: ' . $before['Threads_connected'] . '  Questions: ' . $queries
            . '  Slow queries: ' . $before['Slow_queries'] . '  Opens: ' . $before['Opened_tables'] . '  Flush tables: ' . $before['Flush_commands']
            . '  Open tables: ' . $before['Open_tables'] . '  Queries per second avg: ' . $average, $info);
        self::assertSame(bcadd($before['Queries'], '2', 0), $after['Queries']);
        self::assertSame(bcadd($before['Questions'], '1', 0), $after['Questions']);
    }

    #[DataProvider('providerServers')]
    public function testPdoServerInfoRetainsWarningsAndRowCount(bool $memory): void
    {
        [$target] = Servers::shared();
        $target->repair($memory ? $target->memoryGuard() : $target->guard());
        $pdo = new PDO($memory ? $target->memory : $target->native, $memory ? 'root' : $target->nativeUser, $memory ? '' : $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('DO 0');
        $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
        $rowCount = $pdo->query('SELECT ROW_COUNT()');
        self::assertNotFalse($rowCount);
        self::assertSame(0, $rowCount->fetchColumn());
        $pdo->exec("DO 'a'+1,'b'+1");
        $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
        $warnings = $pdo->query('SHOW WARNINGS');
        self::assertNotFalse($warnings);
        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: 'a'"], ['Warning', 1292, "Truncated incorrect DOUBLE value: 'b'"]], $warnings->fetchAll(PDO::FETCH_NUM));
    }
}
