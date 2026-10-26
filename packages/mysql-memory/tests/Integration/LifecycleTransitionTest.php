<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fuzz\Execution\Lifecycle;
use Fuzz\Execution\LifecycleAction;
use Fuzz\Execution\LifecycleTransition;
use Fuzz\Observation\Record;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversNothing]
#[Large]
final class LifecycleTransitionTest extends TestCase
{
    public function testAnAcknowledgedCommandWithoutDisconnectionFailsInsteadOfReturningFalse(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $owner = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $record = new Record('memory', 'SHUTDOWN', '');
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('within ten seconds');

            (new LifecycleTransition())->await($owner, $server->dsn(), 'root', '', LifecycleAction::Shutdown, $record);
        } finally {
            $server->stop();
        }
    }

    public function testShutdownRecordsTheCompletionAndRequiresTheServerToStopServingSql(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $owner = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $record = new Record('memory', 'SHUTDOWN', '');
            $result = (new Lifecycle())->observe($owner, $server->dsn(), 'root', '', LifecycleAction::Shutdown, $record);

            self::assertSame(['affected' => 0, 'lastInsertId' => '0'], $result['result']);
            self::assertTrue($result['disconnected']);
            self::assertFalse($result['reconnected']);
            self::assertSame('complete', $record->phase);
            self::assertSame($result, $record->raw);
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    public function testRestartRetainsPostRestartRowsAndDefinitions(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $owner = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $record = new Record('memory', 'RESTART', '');
            $result = (new Lifecycle())->observe($owner, $server->dsn(), 'root', '', LifecycleAction::Restart, $record);

            self::assertTrue($result['disconnected']);
            self::assertTrue($result['reconnected']);
            self::assertIsArray($result['tables']);
            self::assertSame([[10]], $result['tables']['_sqlfaker_identifier']);
            self::assertIsArray($result['definitions']);
            self::assertIsArray($result['definitions']['relations']);
            self::assertCount(3, $result['definitions']['relations']);
            self::assertArrayHasKey('status', $result);
            self::assertSame('complete', $record->phase);
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    #[DataProvider('providerErrorModes')]
    public function testSqlRefusalsRetainConditionsAndPostStatementStateInBothPdoErrorModes(int $mode): void
    {
        $server = MemoryDatabase::start('8.4.7', supervised: false);
        try {
            $owner = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => $mode]);
            $record = new Record('memory', 'RESTART', '');
            $result = (new Lifecycle())->observe($owner, $server->dsn(), 'root', '', LifecycleAction::Restart, $record);

            self::assertIsArray($result['error']);
            self::assertSame([3707, 'HY000', 'Restart server failed (mysqld is not managed by supervisor process).'], $result['error']);
            self::assertSame([
                ['Error', 3707, 'Restart server failed (mysqld is not managed by supervisor process).'],
                ['Error', 3707, 'Restart server failed (Restart server failed).'],
            ], $result['warnings']);
            self::assertIsArray($result['tables']);
            self::assertSame([[10]], $result['tables']['_sqlfaker_identifier']);
            self::assertIsArray($result['definitions']);
            self::assertIsArray($result['definitions']['relations']);
            self::assertCount(3, $result['definitions']['relations']);
            self::assertIsArray($result['status']);
            self::assertArrayNotHasKey('error', $result['status']);
            self::assertArrayNotHasKey('disconnected', $result);
            self::assertSame('complete', $record->phase);
            $server->assertNoInternalFailure();
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
        yield 'silent' => [PDO::ERRMODE_SILENT];
    }

    public function testAStoppedEndpointIsUnavailableToTheReadinessProbe(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        $dsn = $server->dsn();
        $server->stop();

        self::assertNull((new LifecycleTransition())->probe($dsn, 'root', ''));
    }

    public function testASqlConnectionRefusalDoesNotCountAsTransportUnavailability(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unknown database');

            (new LifecycleTransition())->probe($server->dsn() . ';dbname=absent_lifecycle_fixture', 'root', '');
        } finally {
            $server->stop();
        }
    }
}
