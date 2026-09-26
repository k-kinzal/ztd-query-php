<?php

declare(strict_types=1);

namespace Tests\Unit\Grammar\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Resource\SqlVersion;
use SqlFaker\Grammar\Resource\SqlVersionRegistry;

#[CoversClass(SqlVersion::class)]
#[UsesClass(SqlVersionRegistry::class)]
final class SqlVersionTest extends TestCase
{
    public function testResolveAnswersTheDefaultReleaseWithItsGrammar(): void
    {
        $version = SqlVersion::resolve('mysql');

        self::assertSame('mysql', $version->dialect);
        self::assertSame('mysql-8.4.7', $version->name);
        self::assertTrue(is_file($version->astPath));
    }

    public function testAllEnumeratesEveryRegisteredRelease(): void
    {
        $versions = SqlVersion::all();

        self::assertCount(11, $versions);
        self::assertSame(
            ['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44'],
            array_slice(array_map(static fn (SqlVersion $version): string => $version->name, $versions), 0, 3),
        );
        array_walk($versions, static function (SqlVersion $version): void {
            self::assertTrue(is_file($version->astPath));
        });
    }

    #[DataProvider('providerOfficialGrammarSource')]
    public function testAllEnumeratesReleasesWhoseGrammarsRecordTheirOfficialSource(string $name, string $source): void
    {
        $versions = array_values(array_filter(SqlVersion::all(), static fn (SqlVersion $version): bool => $version->name === $name));
        self::assertCount(1, $versions);
        $contents = (string) file_get_contents($versions[0]->astPath);
        $grammar = require $versions[0]->astPath;

        self::assertStringContainsString(" * Source: {$source}\n", $contents);
        self::assertStringContainsString(" * Version: {$name}\n", $contents);
        self::assertIsArray($grammar);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) array_key_first($grammar));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerOfficialGrammarSource(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            yield "mysql-{$release}" => ["mysql-{$release}", "https://raw.githubusercontent.com/mysql/mysql-server/refs/tags/mysql-{$release}/sql/sql_yacc.yy"];
        }
        yield 'pg-17.2' => ['pg-17.2', 'https://raw.githubusercontent.com/postgres/postgres/refs/tags/REL_17_2/src/backend/parser/gram.y'];
        yield 'sqlite-3.47.2' => ['sqlite-3.47.2', 'https://raw.githubusercontent.com/sqlite/sqlite/refs/tags/version-3.47.2/src/parse.y'];
    }

    public function testNamesEnumeratesTheReleasesOfOneDialect(): void
    {
        self::assertSame([
            'mysql-5.6.51',
            'mysql-5.7.44',
            'mysql-8.0.44',
            'mysql-8.1.0',
            'mysql-8.2.0',
            'mysql-8.3.0',
            'mysql-8.4.7',
            'mysql-9.0.1',
            'mysql-9.1.0',
        ], SqlVersion::names('mysql'));
    }

    public function testNamesRejectsADialectThePackageDoesNotShip(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown SQL dialect: unknown');

        SqlVersion::names('unknown');
    }

    public function testResolveRejectsAReleaseThePackageDoesNotShip(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported mysql version');

        SqlVersion::resolve('mysql', 'mysql-999.0.0');
    }

    public function testResolveRejectsADialectThePackageDoesNotShip(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown SQL dialect');

        SqlVersion::resolve('oracle');
    }

}
