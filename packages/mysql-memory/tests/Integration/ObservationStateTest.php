<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fuzz\Observation\Observer;
use Fuzz\Target\ExecutionTarget;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversNothing]
#[Large]
final class ObservationStateTest extends TestCase
{
    public function testADefaultChangeIsObservedEvenWhenRowsStayEqual(): void
    {
        $server = MemoryDatabase::start('8.4.7', ['shop']);
        try {
            $pdo = new PDO($server->dsn('shop'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('CREATE TABLE items (id INT DEFAULT 1)');
            $observer = new Observer();
            $before = $observer->definitions($pdo, 'shop');
            $rows = $observer->tables($pdo, 'shop');
            $pdo->exec('ALTER TABLE items ALTER COLUMN id SET DEFAULT 2');

            self::assertSame($rows, $observer->tables($pdo, 'shop'));
            self::assertNotSame($before, $observer->definitions($pdo, 'shop'));
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    #[DataProvider('providerErrorModes')]
    public function testADroppedDatabaseDiffersFromAnEmptyDatabase(int $mode): void
    {
        $server = MemoryDatabase::start('8.4.7', ['shop']);
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => $mode]);
            $observer = new Observer();
            self::assertNotNull($observer->definitions($pdo, 'shop'));
            $pdo->exec('DROP DATABASE shop');

            self::assertSame([], $observer->tables($pdo, 'shop'));
            self::assertNull($observer->definitions($pdo, 'shop'));
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    public function testAnUnreadableCatalogCannotBecomeAnEmptySnapshot(): void
    {
        $server = MemoryDatabase::start('8.4.7', ['shop']);
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server->stop();
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot read observed table state.');

            (new Observer())->tables($pdo, 'shop');
        } finally {
            $server->stop();
        }
    }

    #[DataProvider('providerDriverModes')]
    public function testTheProfileIsSetupAndRawRowsPrecedeComparisonSorting(bool $emulate): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $target = new ExecutionTarget($server->dsn(), 'root', '', $server->dsn(), $emulate, server: $server);
            $result = $target->run($server->dsn(), 'root', '', "SELECT 'z' || 'b' UNION ALL SELECT 'a' || 'b'", 'PIPES_AS_CONCAT');

            $raw = $result['raw']['results'];
            $comparable = $result['comparable']['results'];
            self::assertIsArray($raw);
            self::assertIsArray($comparable);
            self::assertIsArray($raw[0]);
            self::assertIsArray($comparable[0]);
            self::assertSame([['zb'], ['ab']], $raw[0]['rows']);
            self::assertSame([['ab'], ['zb']], $comparable[0]['rows']);
            self::assertCount(1, $raw);
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function providerDriverModes(): iterable
    {
        yield 'text protocol' => [true];
        yield 'native prepared protocol' => [false];
    }

    #[DataProvider('providerErrorModes')]
    public function testSqlErrorsRetainTheirDiagnosticsInEveryDriverErrorMode(int $mode): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => $mode]);
            $observation = (new Observer())->observe($pdo, 'USE absent_fixture', true);

            self::assertSame([1049, '42000', "Unknown database 'absent_fixture'"], $observation['error']);
            self::assertSame([['Error', 1049, "Unknown database 'absent_fixture'"]], $observation['warnings']);
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    #[DataProvider('providerErrorModes')]
    public function testAConditionReadFailureCannotBeComparedAsAnOrdinaryResult(int $mode): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => $mode]);
            $server->stop();
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot read observed conditions');

            (new Observer())->warnings($pdo);
        } finally {
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerErrorModes(): iterable
    {
        yield 'exceptions' => [PDO::ERRMODE_EXCEPTION];
        yield 'silent driver errors' => [PDO::ERRMODE_SILENT];
    }

    public function testEqualCompletionPacketsCannotHideTransactionEffects(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $target = new ExecutionTarget($server->dsn(), 'root', '', $server->dsn(), server: $server);
            $idle = $target->run($server->dsn(), 'root', '', 'DO 0');
            $begin = $target->run($server->dsn(), 'root', '', 'BEGIN');

            self::assertSame($idle['raw']['results'], $begin['raw']['results']);
            self::assertIsArray($idle['raw']['session']);
            self::assertIsArray($begin['raw']['session']);
            self::assertFalse($idle['raw']['session']['transaction']);
            self::assertTrue($begin['raw']['session']['transaction']);
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    public function testGlobalEffectsAreCapturedBeforeRepair(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $target = new ExecutionTarget($server->dsn(), 'root', '', $server->dsn(), server: $server);
            $result = $target->run($server->dsn(), 'root', '', 'SET GLOBAL read_only = ON');
            self::assertIsArray($result['raw']['globals']);
            self::assertIsArray($result['comparable']['globals']);
            self::assertSame('ON', $result['raw']['globals']['read_only']);
            self::assertSame('ON', $result['comparable']['globals']['read_only']);

            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $readOnly = $connection->query('SELECT @@GLOBAL.read_only');
            self::assertNotFalse($readOnly);
            self::assertSame(0, $readOnly->fetchColumn());
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }
}
