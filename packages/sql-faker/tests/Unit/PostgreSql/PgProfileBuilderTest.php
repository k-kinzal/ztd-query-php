<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\PostgreSql\PgProfileBuilder;

#[CoversClass(PgProfileBuilder::class)]
final class PgProfileBuilderTest extends TestCase
{
    public function testSourceUrlsReadsTheKeywordListTheScannerAndTheParser(): void
    {
        $urls = (new PgProfileBuilder())->sourceUrls('pg-17.2');

        self::assertStringEndsWith('/src/include/parser/kwlist.h', $urls['keywords']);
        self::assertStringEndsWith('/src/backend/parser/scan.l', $urls['scanner']);
        self::assertStringEndsWith('/src/backend/parser/parser.c', $urls['parser']);
    }

    public function testSourceUrlsSpellsTheVersionBackIntoAReleaseTag(): void
    {
        foreach ((new PgProfileBuilder())->sourceUrls('pg-17.2') as $url) {
            self::assertStringContainsString('/refs/tags/REL_17_2', $url);
        }
    }

    public function testBuildReportsAnUpstreamFileItCannotRead(): void
    {
        $source = $this->createStub(LexerSource::class);
        $source->method('fetch')->willThrowException(new RuntimeException('Failed to fetch'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch');

        (new PgProfileBuilder($source))->build('pg-17.2');
    }
}
