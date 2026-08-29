<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Lexical\LexicalProfileSource;
use SqlFaker\Grammar\Source\SqlVersion;

#[CoversClass(LexicalProfileSource::class)]
#[UsesClass(SqlVersion::class)]
#[Medium]
final class LexicalProfileSourceTest extends TestCase
{
    #[DataProvider('providerSupportedVersion')]
    public function testLoadReadsTheProfileTheVersionWasBuiltFrom(string $dialect, string $version): void
    {
        $profile = (new LexicalProfileSource())->load($dialect, $version);

        self::assertSame($dialect, $profile['dialect'] ?? null);
        self::assertSame($version, $profile['version'] ?? null);
    }

    #[DataProvider('providerSupportedVersion')]
    public function testLoadCarriesTheCatalogTheProfileDeclares(string $dialect, string $version): void
    {
        $profile = (new LexicalProfileSource())->load($dialect, $version);

        self::assertIsArray($profile['catalog'] ?? null);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerSupportedVersion(): iterable
    {
        yield 'MySQL' => ['mysql', 'mysql-8.4.7'];
        yield 'PostgreSQL' => ['postgresql', 'pg-17.2'];
        yield 'SQLite' => ['sqlite', 'sqlite-3.47.2'];
    }

    public function testLoadRefusesAVersionNoProfileExistsFor(): void
    {
        $this->expectException(RuntimeException::class);

        (new LexicalProfileSource())->load('mysql', 'mysql-0.0.0');
    }

    #[DataProvider('providerDialectName')]
    public function testDisplayNameSpellsADialectTheWayMessagesDo(string $dialect, string $expected): void
    {
        self::assertSame($expected, (new LexicalProfileSource())->displayName($dialect));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDialectName(): iterable
    {
        yield 'mysql' => ['mysql', 'MySQL'];
        yield 'postgresql' => ['postgresql', 'PostgreSQL'];
        yield 'sqlite' => ['sqlite', 'SQLite'];
        yield 'anything else stands for itself' => ['duckdb', 'duckdb'];
    }
}
