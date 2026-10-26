<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\Endpoint;
use Fuzz\Container\DisposableMySql;
use Fuzz\Observation\MetadataLocks;
use Fuzz\Observation\Observer;
use Fuzz\Observation\OwnerThread;
use Fuzz\Observation\Record;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class MetadataLockObservationTest extends TestCase
{
    #[DataProvider('providerVersions')]
    public function testLocksAreObservedBeforeOwnerProbesAndWithoutReleasingThem(string $version): void
    {
        $server = Testcontainers::run(new DisposableMySql($version));
        try {
            $endpoint = $server->getData(Endpoint::class);
            $owner = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $administrator = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $connection = (new OwnerThread())->connection($owner);
            $locks = new MetadataLocks();
            $locks->prepare($administrator, $connection);
            $empty = $locks->capture($administrator, $connection, $version);
            self::assertIsArray($empty['raw']['results']);
            self::assertCount(1, $empty['raw']['results']);
            self::assertIsArray($empty['raw']['results'][0]);
            self::assertSame([], $empty['raw']['results'][0]['rows']);
            $administrator->exec("DO GET_LOCK('foreign_lock', 0)");
            self::assertSame($empty['comparable'], $locks->capture($administrator, $connection, $version)['comparable']);
            $owner->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $primary = (new Observer())->observe($owner, "SELECT GET_LOCK('held_lock', 0)", true);
            self::assertIsArray($primary['results']);
            self::assertCount(1, $primary['results']);
            self::assertIsArray($primary['results'][0]);
            self::assertSame([[1]], $primary['results'][0]['rows']);
            $record = new Record('native', "SELECT GET_LOCK('held_lock', 0)", '');
            $held = $locks->capture($administrator, $connection, $version, $record);
            self::assertStringContainsString('held_lock', serialize($record->probes));
            self::assertSame('complete', $record->probes[count($record->probes) - 1]->phase);
            $column = str_starts_with($version, '5.7.') ? [] : [null];
            self::assertIsArray($held['raw']['results']);
            self::assertCount(1, $held['raw']['results']);
            self::assertIsArray($held['raw']['results'][0]);
            self::assertSame([['USER LEVEL LOCK', null, 'held_lock', ...$column, 'EXCLUSIVE', 'EXPLICIT', 'GRANTED']], $held['raw']['results'][0]['rows']);
            self::assertSame($held['comparable'], $locks->capture($administrator, $connection, $version)['comparable']);
            self::assertFalse($owner->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            $contended = (new Observer())->observe($administrator, "SELECT GET_LOCK('held_lock', 0)", true);
            self::assertIsArray($contended['results']);
            self::assertCount(1, $contended['results']);
            self::assertIsArray($contended['results'][0]);
            self::assertSame([[0]], $contended['results'][0]['rows']);
            $owner->exec("DO RELEASE_LOCK('held_lock')");
            self::assertSame($empty['comparable'], $locks->capture($administrator, $connection, $version)['comparable']);

            $owner->exec('CREATE DATABASE lock_fixture');
            $owner->exec('CREATE TABLE lock_fixture.items (id INT PRIMARY KEY)');
            $owner->exec('INSERT INTO lock_fixture.items VALUES (1)');
            $owner->exec('BEGIN');
            (new Observer())->observe($owner, 'SELECT * FROM lock_fixture.items FOR UPDATE', true);
            $transaction = $locks->capture($administrator, $connection, $version);
            self::assertIsArray($transaction['raw']['results']);
            self::assertCount(1, $transaction['raw']['results']);
            self::assertIsArray($transaction['raw']['results'][0]);
            self::assertIsArray($transaction['raw']['results'][0]['rows']);
            self::assertContains(['TABLE', 'lock_fixture', 'items', ...$column, 'SHARED_WRITE', 'TRANSACTION', 'GRANTED'], $transaction['raw']['results'][0]['rows']);
            self::assertTrue($owner->inTransaction());
            $owner->exec('ROLLBACK');
            self::assertSame($empty['comparable'], $locks->capture($administrator, $connection, $version)['comparable']);

            $owner->exec('LOCK TABLES lock_fixture.items WRITE');
            $table = $locks->capture($administrator, $connection, $version);
            self::assertIsArray($table['raw']['results']);
            self::assertCount(1, $table['raw']['results']);
            self::assertIsArray($table['raw']['results'][0]);
            self::assertIsArray($table['raw']['results'][0]['rows']);
            self::assertContains(['TABLE', 'lock_fixture', 'items', ...$column, 'SHARED_NO_READ_WRITE', 'TRANSACTION', 'GRANTED'], $table['raw']['results'][0]['rows'], var_export($table['raw'], true));
            self::assertSame($table['comparable'], $locks->capture($administrator, $connection, $version)['comparable']);
            $owner->exec('UNLOCK TABLES');
            self::assertSame($empty['comparable'], $locks->capture($administrator, $connection, $version)['comparable']);

            $administrator->exec("UPDATE performance_schema.setup_instruments SET ENABLED='NO' WHERE NAME='wait/lock/metadata/sql/mdl'");
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('instrumentation is missing or disabled');
            $locks->capture($administrator, $connection, $version);
        } finally {
            $owner = null;
            $administrator = null;
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        foreach (\Container\MySqlRelease::versions() as $version) {
            if (!str_starts_with($version, '5.6.')) {
                yield $version => [$version];
            }
        }
    }
}
