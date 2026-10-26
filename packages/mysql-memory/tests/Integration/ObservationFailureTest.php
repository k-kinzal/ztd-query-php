<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\Endpoint;
use Fuzz\Container\DisposableMySql;
use Fuzz\Observation\Observer;
use Fuzz\Observation\OwnerThread;
use Fuzz\Observation\Record;
use Fuzz\Target\ExecutionTarget;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ObservationFailureTest extends TestCase
{
    public function testAConditionReadFailureRetainsEarlierBinaryResults(): void
    {
        $server = MemoryDatabase::start('5.6.51');
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $sql = 'SELECT _binary 0xFF; KILL CONNECTION_ID()';
            $record = new Record('memory', $sql, '');
            $failure = null;
            try {
                (new Observer())->observe($connection, $sql, true, $record);
            } catch (RuntimeException $error) {
                $failure = $error;
            }

            self::assertNotNull($failure);
            self::assertStringContainsString('Cannot read observed conditions', $failure->getMessage());
            self::assertSame('conditions', $record->phase);
            self::assertIsArray($record->raw['results']);
            self::assertIsArray($record->raw['results'][0]);
            self::assertSame([["\xFF"]], $record->raw['results'][0]['rows']);
            self::assertStringContainsString("\xFF", serialize($record));
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    public function testAnInitialRestorationFailurePermanentlyRefusesReuse(): void
    {
        $server = MemoryDatabase::start('5.6.51');
        try {
            $target = new ExecutionTarget($server->dsn(), 'root', '', $server->dsn(), version: '5.6.51', server: $server);
            $guard = (new OwnerThread())->connection($target->guard());
            $administrator = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $administrator->exec('KILL ' . $guard);
            $failure = null;
            try {
                $target->compare('CREATE DATABASE should_not_exist');
            } catch (RuntimeException $error) {
                $failure = $error;
            }

            self::assertNotNull($failure);
            self::assertCount(1, $target->observations);
            self::assertSame('initial restoration', $target->observations[0]->phase);
            self::assertFalse($target->observations[0]->restored);
            $absent = $administrator->query("SHOW DATABASES LIKE 'should_not_exist'");
            self::assertNotFalse($absent);
            self::assertSame([], $absent->fetchAll(PDO::FETCH_NUM));
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('unusable after an incomplete restoration');

            $target->run($server->dsn(), 'root', '', 'CREATE DATABASE should_not_exist');
        } finally {
            $server->stop();
        }
    }

    public function testFailedSetupStillReleasesItsConnection(): void
    {
        $server = Testcontainers::run(new DisposableMySql('8.4.7'));
        try {
            $endpoint = $server->getData(Endpoint::class);
            $dsn = 'mysql:host=' . $endpoint->host . ';port=' . $endpoint->port;
            $administrator = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $administrator->exec("CREATE USER 'limited_fixture'@'%'");
            $target = new ExecutionTarget($dsn, $endpoint->username, $endpoint->password, $dsn);
            $failure = null;
            try {
                $target->run($dsn, 'limited_fixture', '', 'SELECT 1');
            } catch (RuntimeException $error) {
                $failure = $error;
            }

            self::assertNotNull($failure);
            self::assertCount(1, $target->observations);
            self::assertSame('fixture setup', $target->observations[0]->phase);
            self::assertSame('complete', $target->observations[0]->cleanup);
            self::assertTrue($target->observations[0]->restored);
            self::assertSame([], $target->observations[0]->raw);
            $clients = $administrator->query("SELECT ID FROM information_schema.PROCESSLIST WHERE USER = 'limited_fixture'");
            self::assertNotFalse($clients);
            self::assertSame([], $clients->fetchAll(PDO::FETCH_NUM));
            $target->assertUsable();
        } finally {
            $administrator = null;
            $target = null;
            $server->stop();
        }
    }

    public function testAProbeAndRestorationFailureRetainBothCausesAndThePrimaryResult(): void
    {
        $server = MemoryDatabase::start('5.6.51');
        try {
            $target = new ExecutionTarget($server->dsn(), 'root', '', $server->dsn(), version: '5.6.51', server: $server);
            $guard = (new OwnerThread())->connection($target->guard());
            $failure = null;
            try {
                $target->run($server->dsn(), 'root', '', 'KILL ' . $guard);
            } catch (RuntimeException $error) {
                $failure = $error;
            }

            self::assertNotNull($failure);
            self::assertNotNull($failure->getPrevious());
            self::assertStringContainsString('Cannot read a differential fixture: SHOW GLOBAL VARIABLES', (string) $failure);
            self::assertStringContainsString('Cannot terminate the differential input connection', $failure->getMessage());
            self::assertCount(1, $target->observations);
            $record = $target->observations[0];
            self::assertSame('global variables', $record->phase);
            self::assertSame('disconnect', $record->cleanup);
            self::assertFalse($record->restored);
            self::assertSame([['affected' => 0, 'lastInsertId' => '0']], $record->raw['results']);
            self::assertSame([], $record->raw['warnings']);
            self::assertArrayHasKey('session', $record->raw);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('unusable after an incomplete restoration');

            $target->compare('SELECT 1');
        } finally {
            $server->stop();
        }
    }
}
