<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fuzz\Fixture\Tables;
use Fuzz\Observation\Observer;
use Fuzz\Observation\Record;
use Fuzz\Observation\SessionState;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversNothing]
#[Large]
final class ObservationProbeTest extends TestCase
{
    #[DataProvider('providerModes')]
    public function testSessionReadsPreserveTransactionStateAndTheInputProtocol(int $mode, bool $emulate): void
    {
        $server = MemoryDatabase::start('8.4.7', ['shop']);
        try {
            $connection = new PDO($server->dsn('shop'), 'root', '', [PDO::ATTR_ERRMODE => $mode, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            (new Tables())->execute($connection, 'BEGIN');
            $observer = new Observer();
            $before = $observer->probe($connection, "SHOW SESSION STATUS LIKE 'Com_stmt_prepare'");
            $state = (new SessionState())->capture($connection, '8.4.7');

            self::assertTrue($state['raw']['transaction']);
            self::assertSame('shop', $state['raw']['database']);
            self::assertArrayHasKey('variables', $state['raw']);
            self::assertSame($before, $observer->probe($connection, "SHOW SESSION STATUS LIKE 'Com_stmt_prepare'"));
            self::assertTrue($connection->inTransaction());
            self::assertSame($emulate, $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            self::assertSame($mode, $connection->getAttribute(PDO::ATTR_ERRMODE));
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    #[DataProvider('providerModes')]
    public function testSchemaReadsDoNotPrepareStatementsOrChangeTheInputProtocol(int $mode, bool $emulate): void
    {
        $server = MemoryDatabase::start('8.4.7', ['shop']);
        try {
            $connection = new PDO($server->dsn('shop'), 'root', '', [PDO::ATTR_ERRMODE => $mode, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            (new Tables())->execute($connection, 'CREATE TABLE items (id INT DEFAULT 7)');
            (new Tables())->execute($connection, 'INSERT INTO items VALUES (7)');
            $observer = new Observer();
            $before = $observer->probe($connection, "SHOW SESSION STATUS LIKE 'Com_stmt_prepare'");

            self::assertSame(['items' => [[7]]], $observer->tables($connection, 'shop'));
            self::assertNotNull($observer->definitions($connection, 'shop'));
            self::assertSame([], $observer->tables($connection, 'absent_shop'));
            self::assertNull($observer->definitions($connection, 'absent_shop'));
            self::assertSame($before, $observer->probe($connection, "SHOW SESSION STATUS LIKE 'Com_stmt_prepare'"));
            self::assertSame($emulate, $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            self::assertSame($mode, $connection->getAttribute(PDO::ATTR_ERRMODE));
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    #[DataProvider('providerModes')]
    public function testSuccessfulProbesPreserveBinaryValuesAndRestoreTheInputProtocol(int $mode, bool $emulate): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => $mode, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $observation = (new Observer())->probe($connection, 'SELECT _binary 0xFF');

            self::assertIsArray($observation['results']);
            self::assertIsArray($observation['results'][0]);
            self::assertSame([["\xFF"]], $observation['results'][0]['rows']);
            self::assertSame([], $observation['warnings']);
            self::assertSame($emulate, $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            self::assertSame($mode, $connection->getAttribute(PDO::ATTR_ERRMODE));
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    #[DataProvider('providerModes')]
    public function testSqlFailuresRetainEarlierRowsAndConditionsInsteadOfBecomingState(int $mode, bool $emulate): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => $mode, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $sql = 'SELECT _binary 0xFF; USE absent_probe';
            $failure = null;
            try {
                (new Observer())->probe($connection, $sql);
            } catch (RuntimeException $error) {
                $failure = $error;
            }

            self::assertNotNull($failure);
            self::assertStringContainsString('Cannot read observed state: ' . $sql, $failure->getMessage());
            $evidence = explode('; base64 serialized probe: ', $failure->getMessage(), 2);
            self::assertCount(2, $evidence);
            $serialized = base64_decode($evidence[1], true);
            self::assertNotFalse($serialized);
            $record = unserialize($serialized, ['allowed_classes' => [Record::class]]);
            self::assertInstanceOf(Record::class, $record);
            self::assertSame($sql, $record->sql);
            self::assertIsArray($record->raw['results']);
            self::assertIsArray($record->raw['results'][0]);
            self::assertSame([["\xFF"]], $record->raw['results'][0]['rows']);
            self::assertSame([1049, '42000', "Unknown database 'absent_probe'"], $record->raw['error']);
            self::assertSame([['Error', 1049, "Unknown database 'absent_probe'"]], $record->raw['warnings']);
            self::assertSame($emulate, $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            self::assertSame($mode, $connection->getAttribute(PDO::ATTR_ERRMODE));
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    #[DataProvider('providerModes')]
    public function testCompletionWithoutARowSetCannotBeAcceptedAsState(int $mode, bool $emulate): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => $mode, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot read observed state: DO 0');

            (new Observer())->probe($connection, 'DO 0');
        } finally {
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function providerModes(): iterable
    {
        yield 'exception, text' => [PDO::ERRMODE_EXCEPTION, true];
        yield 'exception, prepared' => [PDO::ERRMODE_EXCEPTION, false];
        yield 'silent, text' => [PDO::ERRMODE_SILENT, true];
        yield 'silent, prepared' => [PDO::ERRMODE_SILENT, false];
    }
}
