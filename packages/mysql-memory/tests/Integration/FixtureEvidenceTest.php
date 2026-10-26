<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\MySqlRelease;
use Fuzz\Fixture\Servers;
use Fuzz\Observation\Record;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversNothing]
#[Large]
final class FixtureEvidenceTest extends TestCase
{
    public function testTextFixtureConversionRetainsTheUnconvertedBinaryAndIntegerValues(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
            $record = new Record('memory', '', '');
            $rows = (new Servers())->rows($connection, "SELECT _binary 0xFF, 7, ''", $record);

            self::assertSame([["\xFF", '7', '']], $rows);
            self::assertCount(1, $record->probes);
            self::assertSame('complete', $record->probes[0]->phase);
            self::assertIsArray($record->probes[0]->raw['results']);
            self::assertIsArray($record->probes[0]->raw['results'][0]);
            self::assertSame([["\xFF", 7, '']], $record->probes[0]->raw['results'][0]['rows']);
            self::assertFalse((bool) $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    public function testNullCannotBecomeAnEmptyTextFixtureValue(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
            $record = new Record('memory', '', '');
            try {
                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('requires a non-null text or integer cell');

                (new Servers())->rows($connection, 'SELECT NULL', $record);
            } finally {
                self::assertCount(1, $record->probes);
                self::assertIsArray($record->probes[0]->raw['results']);
                self::assertIsArray($record->probes[0]->raw['results'][0]);
                self::assertSame([[null]], $record->probes[0]->raw['results'][0]['rows']);
                self::assertFalse((bool) $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            }
        } finally {
            $server->stop();
        }
    }

    #[RunInSeparateProcess]
    #[DataProvider('providerVersions')]
    public function testNativeInitializationAndRestorationRetainEvidenceAndConnectionSettings(string $version): void
    {
        putenv('MYSQL_VERSION=' . $version);
        [$target, $grammar, $server] = (new Servers())->start(false, true);
        try {
            self::assertSame('mysql-' . $version, $grammar);
            self::assertNotNull($target->startup);
            self::assertSame('complete', $target->startup->phase);
            self::assertSame('ownership transferred', $target->startup->cleanup);
            self::assertCount(2, $target->startup->probes);
            self::assertSame('native', $target->startup->probes[0]->engine);
            self::assertSame('memory', $target->startup->probes[1]->engine);
            self::assertNotNull($target->baseline);
            $connection = $target->guard();
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $connection->exec('CREATE DATABASE leaked_fixture');
            $connection->exec("SET GLOBAL sql_mode = 'PIPES_AS_CONCAT'");
            $record = new Record('native', '', '');

            $target->baseline->restore($connection, $record);

            self::assertSame(PDO::ERRMODE_EXCEPTION, $connection->getAttribute(PDO::ATTR_ERRMODE));
            self::assertFalse((bool) $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            self::assertSame([], (new Servers())->rows($connection, "SHOW DATABASES LIKE 'leaked_fixture'"));
            self::assertSame($target->baseline->globals, (new Servers())->globals($connection, $version));
            $probes = array_column($record->probes, null, 'sql');
            self::assertArrayHasKey('DROP DATABASE IF EXISTS `leaked_fixture`', $probes);
            self::assertSame('complete', $probes['DROP DATABASE IF EXISTS `leaked_fixture`']->phase);
            self::assertArrayHasKey('SHOW GRANTS FOR ' . $target->baseline->identity, $probes);
            self::assertSame('complete', $probes['SHOW GRANTS FOR ' . $target->baseline->identity]->phase);
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
            $target->reference?->stop();
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        foreach (MySqlRelease::versions() as $version) {
            yield $version => [$version];
        }
    }
}
