<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\StorageOptions;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(StorageOptions::class)]
#[Medium]
final class StorageOptionsTest extends TestCase
{
    public function testResolveChecksPartitionEnginesBeforeOpeningTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->run('ALTER TABLE d.absent ADD PARTITION (PARTITION p ENGINE missing)');

        self::assertSame([['Error', 1286, "Unknown storage engine 'missing'"]], $session->diagnostics->conditions);
    }

    public function testResolveReportsEveryUnknownEngineBeforeTheFallback(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; SET sql_mode=''; CREATE TABLE d.t(a INT) ENGINE first ENGINE second PARTITION BY HASH(a) (PARTITION p ENGINE third)");

        self::assertSame([1286, 1286, 1286, 1266], array_column($session->diagnostics->conditions, 1));
        self::assertSame("Unknown storage engine 'first'", $session->diagnostics->conditions[0][2]);
    }

    public function testResolveNeedsTheCurrentDatabaseBeforeEngineLookup(): void
    {
        $session = (new Instance())->connect();
        $session->run('CREATE TABLE t(a INT) ENGINE missing');

        self::assertSame([['Error', 1046, 'No database selected']], $session->diagnostics->conditions);
    }

    public function testCheckReportsLegacyTablespaceAndEngineErrorsInOrder(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d');
        $session->run('CREATE TABLE d.t(a INT) TABLESPACE absent');

        self::assertSame([['Error', 1812, 'InnoDB: A general tablespace named `absent` cannot be found.'], ['Error', 1031, "Table storage engine for 't' doesn't have this option"]], $session->diagnostics->conditions);
    }

    public function testEngineUsesTheConfiguredDefaultAndResolvesAliases(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; SET default_storage_engine=MEMORY; CREATE TABLE a(c INT); CREATE TABLE b(c INT) ENGINE=HEAP');

        self::assertNotNull($session->instance->dictionary->table('d', 'a'));
        self::assertNotNull($session->instance->dictionary->table('d', 'b'));
        self::assertSame('MEMORY', $session->instance->dictionary->table('d', 'a')->definition->engine);
        self::assertSame('MEMORY', $session->instance->dictionary->table('d', 'b')->definition->engine);
    }

    public function testCheckRefusesAnUnknownEngineUnderNoEngineSubstitution(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->run('CREATE TABLE d.t(c INT) ENGINE=absent');

        self::assertSame([['Error', 1286, "Unknown storage engine 'absent'"]], $session->diagnostics->conditions);
    }

    public function testEngineReportsBothSubstitutionWarnings(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; SET sql_mode=''; CREATE TABLE d.t(c INT) ENGINE=absent");

        self::assertSame([['Warning', 1286, "Unknown storage engine 'absent'"], ['Warning', 1266, "Using storage engine InnoDB for table 't'"]], $session->diagnostics->conditions);
        self::assertNotNull($session->instance->dictionary->table('d', 't'));
        self::assertSame('InnoDB', $session->instance->dictionary->table('d', 't')->definition->engine);
    }

    public function testAttributesRefusesPrimaryEngineAttributesAfterJsonValidation(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->run("CREATE TABLE d.t(c INT ENGINE_ATTRIBUTE '{}')");

        self::assertSame([['Error', 3981, "Storage engine 'InnoDB' does not support ENGINE_ATTRIBUTE."]], $session->diagnostics->conditions);
    }
}
