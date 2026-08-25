<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\Sqlite\SqliteProfileBuilder;

#[CoversClass(SqliteProfileBuilder::class)]
final class SqliteProfileBuilderTest extends TestCase
{
    public function testSourceUrlsReadsTheKeywordHashAndTheTokenizer(): void
    {
        $urls = (new SqliteProfileBuilder())->sourceUrls('sqlite-3.47.2');

        self::assertStringEndsWith('/tool/mkkeywordhash.c', $urls['keywords']);
        self::assertStringEndsWith('/src/tokenize.c', $urls['scanner']);
    }

    public function testSourceUrlsSpellsTheVersionBackIntoAReleaseTag(): void
    {
        foreach ((new SqliteProfileBuilder())->sourceUrls('sqlite-3.47.2') as $url) {
            self::assertStringContainsString('/refs/tags/version-3.47.2', $url);
        }
    }

    public function testBuildReportsAnUpstreamFileItCannotRead(): void
    {
        $source = $this->createStub(LexerSource::class);
        $source->method('fetch')->willThrowException(new RuntimeException('Failed to fetch'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch');

        (new SqliteProfileBuilder($source))->build('sqlite-3.47.2');
    }
}
