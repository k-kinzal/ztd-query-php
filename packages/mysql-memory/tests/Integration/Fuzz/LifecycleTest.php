<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Lifecycle;
use Fuzz\Target\Servers;
use JsonException;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class LifecycleTest extends TestCase
{
    public function testReadyRejectsAnAuthenticatedConnectionWhoseSqlChannelClosed(): void
    {
        $server = Server::start();
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } finally {
            $server->stop();
        }

        self::assertFalse((new Lifecycle())->ready($pdo));
    }

    public function testReadyAcceptsACompletedSqlStatement(): void
    {
        $server = Server::start();
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            self::assertTrue((new Lifecycle())->ready($pdo));
        } finally {
            $server->stop();
        }
    }

    public function testObserveWaitsForShutdownToStopServingSql(): void
    {
        $server = Server::start();
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $observed = (new Lifecycle())->observe($pdo, $server->dsn(), 'root', '', 'SHUTDOWN');

            self::assertSame(['result' => ['affected' => 0, 'lastInsertId' => '0'], 'disconnected' => true, 'reconnected' => false, 'tables' => null, 'status' => null], $observed);
        } finally {
            $server->stop();
        }
    }

    public function testObserveReadsPreservedTablesAfterRestartBecomesReady(): void
    {
        $server = Server::start();
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $observed = (new Lifecycle())->observe($pdo, $server->dsn(), 'root', '', 'RESTART');
            $again = (new Lifecycle())->reconnect($server->dsn(), 'root', '', false);

            self::assertTrue($observed['disconnected']);
            self::assertTrue($observed['reconnected']);
            self::assertInstanceOf(PDO::class, $again);
            self::assertSame((new \Fuzz\Target\Observer())->tables($again, 'fz'), $observed['tables']);
            self::assertSame((new \Fuzz\Target\Observer())->observe($again, "SHOW GLOBAL STATUS LIKE 'Aborted_clients'", true), $observed['status']);
        } finally {
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLifecycleStatements(): iterable
    {
        yield 'shutdown' => ['SHUTDOWN'];
        yield 'restart' => ['RESTART'];
    }

    /**
     * @throws JsonException When an observation cannot be serialized
     */
    #[DataProvider('providerLifecycleStatements')]
    public function testCompareChecksCompletionAndTheServerTransition(string $sql): void
    {
        $comparison = (new Lifecycle())->compare($sql, (new Servers())->environment('MYSQL_VERSION', '8.4.7'));

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
