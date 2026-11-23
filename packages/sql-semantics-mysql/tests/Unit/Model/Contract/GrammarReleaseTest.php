<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Resource\VersionRegistry;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(GrammarRelease::class)]
#[Small]
final class GrammarReleaseTest extends TestCase
{
    #[DataProvider('providerReleases')]
    public function testGrammarDigestPinsTheShippedGrammar(GrammarRelease $release): void
    {
        $artifact = (new VersionRegistry())->resolve('mysql', $release->value);

        self::assertSame(hash_file('sha256', $artifact->tablePath), $release->grammarDigest());
    }

    #[DataProvider('providerReleases')]
    public function testKeywordDigestPinsTheShippedKeywords(GrammarRelease $release): void
    {
        $artifact = (new VersionRegistry())->resolve('mysql', $release->value);

        self::assertSame(hash_file('sha256', $artifact->keywordPath), $release->keywordDigest());
    }

    #[DataProvider('providerReleases')]
    public function testDatabaseNamesTheDatabaseFamily(GrammarRelease $release): void
    {
        self::assertSame('mysql', $release->database());
        self::assertCount(9, GrammarRelease::cases());
    }

    #[DataProvider('providerReleases')]
    public function testTextCollationIsFixedForEachRelease(GrammarRelease $release): void
    {
        $legacy = str_starts_with($release->value, 'mysql-5.');

        self::assertSame($legacy ? 'utf8mb4_general_ci' : 'utf8mb4_0900_ai_ci', $release->textCollation());
    }

    /**
     * @return list<array{GrammarRelease}>
     */
    public static function providerReleases(): array
    {
        return array_map(static fn (GrammarRelease $release): array => [$release], GrammarRelease::cases());
    }
}
