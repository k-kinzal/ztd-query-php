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

    public function testADroppedDatabaseDiffersFromAnEmptyDatabase(): void
    {
        $server = MemoryDatabase::start('8.4.7', ['shop']);
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
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
}
