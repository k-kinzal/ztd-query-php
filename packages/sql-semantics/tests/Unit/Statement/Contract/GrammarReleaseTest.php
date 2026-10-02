<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Resource\VersionRegistry;
use SqlSemantics\Statement\Contract\GrammarRelease;

#[CoversClass(GrammarRelease::class)]
#[Small]
final class GrammarReleaseTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerShippedArtifacts(): iterable
    {
        foreach ((new VersionRegistry())->entries() as $dialect => $entry) {
            foreach (array_keys($entry['versions']) as $version) {
                yield $version => [$dialect, $version];
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerShippedArtifacts')]
    public function testGrammarDigestMatchesEveryShippedArtifactWithoutDroppingVersions(string $dialect, string $version): void
    {
        $release = GrammarRelease::from($version);
        $artifact = (new VersionRegistry())->resolve($dialect, $version);
        self::assertSame(hash_file('sha256', $artifact->tablePath), $release->grammarDigest());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerShippedArtifacts')]
    public function testKeywordDigestIncludesTheExactLexicalArtifact(string $dialect, string $version): void
    {
        $release = GrammarRelease::from($version);
        $artifact = (new VersionRegistry())->resolve($dialect, $version);
        self::assertSame(hash_file('sha256', $artifact->keywordPath), $release->keywordDigest());
    }

    public function testDatabaseRetainsTheArtifactFamily(): void
    {
        self::assertSame('mysql', GrammarRelease::MySql847->database());
        self::assertSame('postgresql', GrammarRelease::PostgreSql172->database());
        self::assertSame('sqlite', GrammarRelease::Sqlite3472->database());
    }
}
