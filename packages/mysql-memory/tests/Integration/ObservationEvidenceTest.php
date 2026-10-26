<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\Endpoint;
use Fuzz\Container\DisposableMySql;
use Fuzz\Observation\CatalogState;
use Fuzz\Observation\Observer;
use Fuzz\Observation\ProbeFailure;
use Fuzz\Observation\Record;
use Fuzz\Observation\StatementIdentities;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ObservationEvidenceTest extends TestCase
{
    #[DataProvider('providerModes')]
    public function testLaterProbeFailuresRetainPrimaryAndEarlierBinaryEvidence(int $mode, bool $emulate): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => $mode, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $record = new Record('memory', 'SELECT _binary 0x00', '');
            $observer = new Observer();
            $primary = $observer->observe($connection, $record->sql, true, $record);
            $record->phase = 'catalog state';
            $earlier = $observer->probe($connection, 'SELECT _binary 0xFE', $record);
            $failure = null;
            try {
                $observer->probe($connection, 'SELECT _binary 0xFF; USE absent_probe', $record);
            } catch (ProbeFailure $error) {
                $failure = $error;
            }

            self::assertNotNull($failure);
            self::assertSame($primary, $record->raw);
            self::assertSame('catalog state', $record->phase);
            self::assertCount(2, $record->probes);
            self::assertSame($earlier, $record->probes[0]->raw);
            self::assertSame('complete', $record->probes[0]->phase);
            self::assertSame($failure->record, $record->probes[1]);
            self::assertSame([["\xFF"]], $observer->rowset($failure->record->raw));
            self::assertSame([1049, '42000', "Unknown database 'absent_probe'"], $failure->record->raw['error']);
            self::assertSame(serialize($record), serialize(unserialize(serialize($record), ['allowed_classes' => [Record::class]])));
            self::assertSame($emulate, $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            self::assertSame($mode, $connection->getAttribute(PDO::ATTR_ERRMODE));
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    #[DataProvider('providerModes')]
    public function testDatabaseSnapshotsRetainCompletedDefinitionsAndRowsBeforeAReadRefusal(int $mode, bool $emulate): void
    {
        $server = Testcontainers::run(new DisposableMySql('8.4.7'));
        try {
            $endpoint = $server->getData(Endpoint::class);
            $administrator = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $administrator->exec('CREATE DATABASE shop');
            $administrator->exec('CREATE TABLE shop.a (value VARBINARY(1))');
            $administrator->exec('CREATE TABLE shop.b (value INT)');
            $administrator->exec('INSERT INTO shop.a VALUES (0xFF), (0xFF)');
            $administrator->exec("CREATE USER 'snapshot_reader'@'%'");
            $administrator->exec("GRANT SELECT ON shop.a TO 'snapshot_reader'@'%'");
            $administrator->exec("GRANT INSERT ON shop.b TO 'snapshot_reader'@'%'");
            $connection = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, 'snapshot_reader', '', [PDO::ATTR_ERRMODE => $mode, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $record = new Record('memory', 'DO 0', '');
            $record->phase = 'catalog state';
            $failure = null;
            try {
                (new CatalogState())->databases($connection, $record);
            } catch (RuntimeException $error) {
                $failure = $error;
            }

            self::assertNotNull($failure);
            self::assertInstanceOf(ProbeFailure::class, $failure->getPrevious());
            $evidence = array_column($record->probes, null, 'sql');
            self::assertArrayHasKey('SHOW CREATE TABLE `shop`.`a`', $evidence);
            self::assertSame('complete', $evidence['SHOW CREATE TABLE `shop`.`a`']->phase);
            self::assertSame([["\xFF"], ["\xFF"]], (new Observer())->rowset($evidence['SELECT * FROM `shop`.`a`']->raw));
            self::assertSame('state validation', $evidence['SELECT * FROM `shop`.`b`']->phase);
            self::assertIsArray($evidence['SELECT * FROM `shop`.`b`']->raw['error']);
            self::assertSame(1142, $evidence['SELECT * FROM `shop`.`b`']->raw['error'][0]);
            self::assertSame('catalog state', $record->phase);
            self::assertSame($emulate, $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            self::assertSame($mode, $connection->getAttribute(PDO::ATTR_ERRMODE));
        } finally {
            $connection = null;
            $administrator = null;
            $server->stop();
        }
    }

    #[DataProvider('providerModes')]
    public function testCatalogRefusalsRetainEarlierCatalogReads(int $mode, bool $emulate): void
    {
        $server = Testcontainers::run(new DisposableMySql('8.4.7'));
        try {
            $endpoint = $server->getData(Endpoint::class);
            $administrator = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $administrator->exec("CREATE USER 'catalog_reader'@'%'");
            $connection = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, 'catalog_reader', '', [PDO::ATTR_ERRMODE => $mode, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $record = new Record('memory', 'DO 0', '');
            $failure = null;
            try {
                (new CatalogState())->capture($connection, '8.4.7', $record);
            } catch (ProbeFailure $error) {
                $failure = $error;
            }

            self::assertNotNull($failure);
            self::assertSame('SELECT * FROM information_schema.SCHEMATA', $record->probes[0]->sql);
            self::assertSame('complete', $record->probes[0]->phase);
            self::assertArrayHasKey('results', $record->probes[0]->raw);
            self::assertSame('SELECT * FROM mysql.user', $failure->record->sql);
            self::assertSame($failure->record, $record->probes[count($record->probes) - 1]);
            self::assertIsArray($failure->record->raw['error']);
            self::assertSame(1142, $failure->record->raw['error'][0]);
            self::assertSame($emulate, $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        } finally {
            $connection = null;
            $administrator = null;
            $server->stop();
        }
    }

    #[DataProvider('providerModes')]
    public function testAnUnreadableIdentityCannotSilentlyDisableItsComparisonContract(int $mode, bool $emulate): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => $mode, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $server->stop();
            $this->expectException(ProbeFailure::class);
            $this->expectExceptionMessage('SELECT @@statement_id, CONNECTION_ID()');

            StatementIdentities::capture($connection, '8.4.7');
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
