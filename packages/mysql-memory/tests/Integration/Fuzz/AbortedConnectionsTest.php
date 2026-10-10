<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class AbortedConnectionsTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, string, int, int}>
     */
    public static function providerDisconnects(): iterable
    {
        foreach ([false, true] as $memory) {
            $server = $memory ? 'memory' : 'MySQL';
            yield $server . ' quit' => [$memory, 'DO 1', 0, 0];
            yield $server . ' commit release' => [$memory, 'COMMIT RELEASE', 0, 1];
            yield $server . ' rollback release' => [$memory, 'ROLLBACK RELEASE', 0, 1];
            yield $server . ' completion type' => [$memory, 'SET completion_type=2; COMMIT', 0, 1];
            yield $server . ' self kill' => [$memory, 'KILL CONNECTION_ID()', 1317, 1];
            yield $server . ' peer kill' => [$memory, 'KILL', 0, 1];
        }
    }

    #[DataProvider('providerDisconnects')]
    public function testDisconnectCountsOnlyAbortedClientsAndFlushResetsTheTotal(bool $memory, string $sql, int $error, int $expected): void
    {
        [$target] = Servers::shared();
        $connect = static fn (): PDO => new PDO($memory ? $target->memory : $target->native, $memory ? 'root' : $target->nativeUser, $memory ? '' : $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $observer = $connect();
        $observer->exec('FLUSH LOCAL STATUS');
        $read = static function (string $scope) use ($observer): int {
            $statement = $observer->query("SHOW {$scope} STATUS LIKE 'Aborted_clients'");
            self::assertNotFalse($statement);

            return (int) $statement->fetchColumn(1);
        };
        $disconnect = static function () use ($connect, $observer, $sql): array {
            $client = $connect();
            $statement = $client->query('SELECT CONNECTION_ID()');
            self::assertNotFalse($statement);
            $id = (int) $statement->fetchColumn();
            unset($statement);
            try {
                $reply = $sql === 'KILL' ? $observer->query('KILL ' . $id) : $client->query($sql);
                self::assertNotFalse($reply);
                $reply->closeCursor();
            } catch (PDOException $failure) {
                return [$id, $failure->errorInfo[1] ?? 0];
            }

            return [$id, 0];
        };
        [$id, $actualError] = $disconnect();
        $wait = static function () use ($observer, $id): void {
            $deadline = microtime(true) + 3;
            do {
                $statement = $observer->query('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID=' . $id);
                self::assertNotFalse($statement);
                $connected = (int) $statement->fetchColumn();
                if ($connected === 0) {
                    return;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::fail('The disconnected client remained in the process list.');
        };
        $wait();

        self::assertSame($error, $actualError);
        self::assertSame([$expected, $expected], [$read('GLOBAL'), $read('SESSION')]);
        $observer->exec('FLUSH LOCAL STATUS');
        self::assertSame([0, 0], [$read('GLOBAL'), $read('SESSION')]);
    }
}
