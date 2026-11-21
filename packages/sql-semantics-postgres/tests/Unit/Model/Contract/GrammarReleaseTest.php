<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Resource\VersionRegistry;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(GrammarRelease::class)]
#[Small]
final class GrammarReleaseTest extends TestCase
{
    #[DataProvider('providerReleases')]
    public function testGrammarDigestPinsTheShippedGrammar(GrammarRelease $release): void
    {
        $artifact = (new VersionRegistry())->resolve('postgresql', $release->value);

        self::assertSame(hash_file('sha256', $artifact->tablePath), $release->grammarDigest());
    }

    #[DataProvider('providerReleases')]
    public function testKeywordDigestPinsTheShippedKeywords(GrammarRelease $release): void
    {
        $artifact = (new VersionRegistry())->resolve('postgresql', $release->value);

        self::assertSame(hash_file('sha256', $artifact->keywordPath), $release->keywordDigest());
    }

    #[DataProvider('providerReleases')]
    public function testDatabaseNamesTheDatabaseFamily(GrammarRelease $release): void
    {
        self::assertSame('postgresql', $release->database());
        self::assertCount(2, GrammarRelease::cases());
    }

    /**
     * @return list<array{GrammarRelease}>
     */
    public static function providerReleases(): array
    {
        return array_map(static fn (GrammarRelease $release): array => [$release], GrammarRelease::cases());
    }
}
