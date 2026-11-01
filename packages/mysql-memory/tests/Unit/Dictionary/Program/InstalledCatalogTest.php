<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary\Program;

use MySqlMemory\Dictionary\Program\InstalledCatalog;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(InstalledCatalog::class)]
#[Small]
final class InstalledCatalogTest extends TestCase
{
    public function testEntriesExposesImmutableSignaturesIndependentlyOfInstanceState(): void
    {
        $entries = InstalledCatalog::entries();
        $names = array_map(static fn ($entry): int|string|null => $entry->metadata['ROUTINE_NAME'], $entries);

        self::assertCount(48, $entries);
        self::assertContains('version_major', $names);
        self::assertContains('table_exists', $names);
        self::assertSame($entries, InstalledCatalog::entries());
    }

    public function testDataContainsOnlyPublicMetadataAndParameters(): void
    {
        $data = InstalledCatalog::data();

        self::assertCount(48, $data['routines']);
        self::assertCount(83, $data['parameters']);
        self::assertSame([25], array_values(array_unique(array_map(count(...), $data['routines']))));
        self::assertSame([14], array_values(array_unique(array_map(count(...), $data['parameters']))));
    }

    public function testDataRetainsTheObservedLegacyCharsetAndTypeDeclarations(): void
    {
        $data = InstalledCatalog::data(GrammarRelease::MySql5744);
        $functions = array_column($data['routines'], null, 0);

        self::assertCount(48, $data['routines']);
        self::assertCount(83, $data['parameters']);
        self::assertSame('tinyint(3) unsigned', $functions['version_major'][10]);
        self::assertSame(['utf8', 'utf8_general_ci', 'utf8_general_ci'], array_slice($functions['version_major'], 22));
        self::assertSame([], InstalledCatalog::entries(GrammarRelease::MySql5651));
    }

    public function testParametersKeepsTheReturnValueAndArgumentModes(): void
    {
        $rows = InstalledCatalog::parameters(InstalledCatalog::data()['parameters'], 'PROCEDURE', 'table_exists');

        self::assertSame([1, 2, 3], array_column($rows, 'ORDINAL_POSITION'));
        self::assertSame(['IN', 'IN', 'OUT'], array_column($rows, 'PARAMETER_MODE'));
        self::assertSame(['in_db', 'in_table', 'out_exists'], array_column($rows, 'PARAMETER_NAME'));
        self::assertSame([], InstalledCatalog::parameters(InstalledCatalog::data()['parameters'], 'FUNCTION', 'table_exists'));
    }

    /**
     * @return iterable<string, array{GrammarRelease, int, int}>
     */
    public static function providerReleases(): iterable
    {
        yield 'MySQL 5.6 has no sys catalog' => [GrammarRelease::MySql5651, 0, 0];
        yield 'MySQL 5.7' => [GrammarRelease::MySql5744, 22, 26];
        yield 'MySQL 8.0' => [GrammarRelease::MySql8044, 22, 26];
        yield 'MySQL 8.4' => [GrammarRelease::MySql847, 22, 26];
        yield 'MySQL 9.1' => [GrammarRelease::MySql910, 22, 26];
    }

    #[DataProvider('providerReleases')]
    public function testInstallCreatesIndependentRoutineEntries(GrammarRelease $release, int $functions, int $procedures): void
    {
        $schema = new Schema('sys');
        InstalledCatalog::install($schema, $release);

        self::assertCount($functions, $schema->functions);
        self::assertCount($procedures, $schema->procedures);
    }

    public function testInstallLetsDdlAlterDropAndReplaceInstalledEntries(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $session->query("ALTER FUNCTION sys.version_major COMMENT 'changed' SQL SECURITY DEFINER");
        $routine = $instance->dictionary->schemas['sys']->functions['version_major'];
        self::assertSame(['changed', 'DEFINER'], [$routine->comment, $routine->security]);
        $session->query('DROP FUNCTION sys.version_major');
        $session->query('CREATE FUNCTION sys.version_major() RETURNS INT DETERMINISTIC RETURN 42');

        $result = $session->query('SELECT sys.version_major()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['42']], $result->rows);
        self::assertNotSame('changed', (new Instance())->dictionary->schemas['sys']->functions['version_major']->comment);
    }
}
