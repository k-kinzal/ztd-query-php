<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class TrafficCountersTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, string, int, int}>
     */
    public static function providerTraffic(): iterable
    {
        foreach ([false, true] as $memory) {
            $server = $memory ? 'memory' : 'MySQL';
            yield $server . ' scalar' => [$memory, 'SELECT 1', 92, 67];
            yield $server . ' text' => [$memory, "SELECT 'abc'", 96, 71];
            yield $server . ' completion' => [$memory, 'DO 1', 88, 22];
            yield $server . ' multiple results' => [$memory, 'SELECT 1; SELECT 2', 102, 123];
            yield $server . ' length encoded text' => [$memory, "SELECT REPEAT('x', 300)", 107, 383];
        }
    }

    #[DataProvider('providerTraffic')]
    public function testSessionCountsRequestAndResponsePackets(bool $memory, string $sql, int $received, int $sent): void
    {
        [$target] = Servers::shared();
        $pdo = new PDO($memory ? $target->memory : $target->native, $memory ? 'root' : $target->nativeUser, $memory ? '' : $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('FLUSH LOCAL STATUS');
        $statement = $pdo->query($sql);
        self::assertNotFalse($statement);
        $statement->closeCursor();
        $read = $pdo->query("SHOW SESSION STATUS WHERE Variable_name IN ('Bytes_received','Bytes_sent')");
        self::assertNotFalse($read);

        self::assertSame(['Bytes_received' => (string) $received, 'Bytes_sent' => (string) $sent], $read->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerScripts(): iterable
    {
        yield 'response before next statement' => ["FLUSH LOCAL STATUS; SELECT 1; SHOW SESSION STATUS WHERE Variable_name IN ('Bytes_received','Bytes_sent')"];
        yield 'reset in the middle of a request' => ["SELECT 1; FLUSH LOCAL STATUS; SHOW SESSION STATUS WHERE Variable_name IN ('Bytes_received','Bytes_sent')"];
        yield 'success before an error' => ['FLUSH LOCAL STATUS; SELECT 1; SELECT missing; SELECT 2'];
    }

    #[DataProvider('providerScripts')]
    public function testEachStatementSeesPreviouslySentReplies(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function providerServers(): iterable
    {
        yield 'MySQL' => [false];
        yield 'memory' => [true];
    }

    #[DataProvider('providerServers')]
    public function testGlobalReceivedIncludesOtherConnections(bool $memory): void
    {
        [$target] = Servers::shared();
        $connect = static fn (): PDO => new PDO($memory ? $target->memory : $target->native, $memory ? 'root' : $target->nativeUser, $memory ? '' : $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $observer = $connect();
        $writer = $connect();
        $sql = "SHOW GLOBAL STATUS LIKE 'Bytes_received'";
        $first = $observer->query($sql);
        self::assertNotFalse($first);
        $before = $first->fetchColumn(1);
        self::assertIsNumeric($before);
        $writer->exec('DO 1');
        $last = $observer->query($sql);
        self::assertNotFalse($last);
        $after = $last->fetchColumn(1);
        self::assertIsNumeric($after);

        self::assertSame(9 + strlen($sql) + 5, (int) $after - (int) $before);
    }

    public function testNativePreparedPacketsMatchForAnIntegerParameter(): void
    {
        [$target] = Servers::shared();
        $observe = static function (PDO $pdo): array {
            $pdo->exec('FLUSH LOCAL STATUS');
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $statement = $pdo->prepare('SELECT ?');
            self::assertNotFalse($statement);
            $statement->bindValue(1, 7, PDO::PARAM_INT);
            $statement->execute();
            self::assertSame([[7]], $statement->fetchAll(PDO::FETCH_NUM));
            unset($statement);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $read = $pdo->query("SHOW SESSION STATUS WHERE Variable_name IN ('Bytes_received','Bytes_sent')");
            self::assertNotFalse($read);

            return $read->fetchAll(PDO::FETCH_KEY_PAIR);
        };
        $expected = $observe(new PDO($target->native, $target->nativeUser, $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));
        $actual = $observe(new PDO($target->memory, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));

        self::assertSame($expected, $actual);
    }
}
