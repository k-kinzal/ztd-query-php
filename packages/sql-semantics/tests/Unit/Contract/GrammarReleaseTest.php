<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Resource\VersionRegistry;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(GrammarRelease::class)]
#[Small]
final class GrammarReleaseTest extends TestCase
{
    public function testGrammarDigestPinsTheShippedSqliteArtifact(): void
    {
        $artifact = (new VersionRegistry())->resolve('sqlite', GrammarRelease::Sqlite3472->value);

        self::assertSame(hash_file('sha256', $artifact->tablePath), GrammarRelease::Sqlite3472->grammarDigest());
    }

    public function testGrammarDigestDiffersBetweenReleases(): void
    {
        self::assertNotSame(GrammarRelease::MySql8044->grammarDigest(), GrammarRelease::MySql847->grammarDigest());
        self::assertNotSame(GrammarRelease::PostgreSql166->grammarDigest(), GrammarRelease::PostgreSql172->grammarDigest());
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', GrammarRelease::MySql5651->grammarDigest());
    }

    public function testKeywordDigestPinsTheShippedSqliteKeywords(): void
    {
        $artifact = (new VersionRegistry())->resolve('sqlite', GrammarRelease::Sqlite3472->value);

        self::assertSame(hash_file('sha256', $artifact->keywordPath), GrammarRelease::Sqlite3472->keywordDigest());
    }

    public function testKeywordDigestIsAHexDigestForEveryRelease(): void
    {
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', GrammarRelease::MySql910->keywordDigest());
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', GrammarRelease::PostgreSql172->keywordDigest());
        self::assertNotSame(GrammarRelease::MySql5744->keywordDigest(), GrammarRelease::MySql8044->keywordDigest());
    }

    public function testDatabaseGroupsTheReleasesByFamily(): void
    {
        self::assertSame('mysql', GrammarRelease::MySql5651->database());
        self::assertSame('mysql', GrammarRelease::MySql910->database());
        self::assertSame('postgresql', GrammarRelease::PostgreSql166->database());
        self::assertSame('sqlite', GrammarRelease::Sqlite3472->database());
    }

    public function testDatabaseCoversTwelveShippedReleases(): void
    {
        self::assertCount(12, GrammarRelease::cases());
        self::assertSame('sqlite-3.47.2', GrammarRelease::Sqlite3472->value);
    }
}
