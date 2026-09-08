<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\PostgreSql\PgProfileBuilder;

#[CoversClass(PgProfileBuilder::class)]
#[UsesClass(\SqlFaker\PostgreSql\LexicalProfileCompiler::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\UpstreamLexerSource::class)]
final class PgProfileBuilderTest extends TestCase
{
    public function testSourceUrlsReadsTheKeywordListTheScannerAndTheParser(): void
    {
        $urls = (new PgProfileBuilder())->sourceUrls('pg-17.2');

        self::assertStringEndsWith('/src/include/parser/kwlist.h', $urls['keywords']);
        self::assertStringEndsWith('/src/backend/parser/scan.l', $urls['scanner']);
        self::assertStringEndsWith('/src/backend/parser/parser.c', $urls['parser']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSourceFile(): array
    {
        return ['keywords' => ['keywords'], 'scanner' => ['scanner'], 'parser' => ['parser']];
    }

    #[DataProvider('providerSourceFile')]
    public function testSourceUrlsSpellsTheVersionBackIntoAReleaseTag(string $file): void
    {
        self::assertStringContainsString('/refs/tags/REL_17_2', (new PgProfileBuilder())->sourceUrls('pg-17.2')[$file]);
    }

    public function testBuildReportsAnUpstreamFileItCannotRead(): void
    {
        $source = self::createStub(LexerSource::class);
        $source->method('fetch')->willThrowException(new RuntimeException('Failed to fetch'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch');

        (new PgProfileBuilder($source))->build('pg-17.2');
    }























    public function testBuildRetainsSourceHashesAndOnlyRegistrationFacts(): void
    {
        $builder = new PgProfileBuilder();
        $urls = $builder->sourceUrls('pg-17.2');
        $table = 'PG_KEYWORD("select", SELECT, RESERVED_KEYWORD, AS_LABEL)';
        $source = $this->createMock(LexerSource::class);
        $source->method('fetch')->willReturnCallback(static fn (string $url): string => $url === $urls['keywords'] ? $table : 'reviewed scanner source');
        $profile = (new PgProfileBuilder($source))->build('pg-17.2');
        self::assertSame('postgresql', $profile['dialect']);
        self::assertSame('pg-17.2', $profile['version']);
        self::assertIsArray($profile['sources']);
        self::assertSame(hash('sha256', $table), $profile['sources'][$urls['keywords']]);
        self::assertArrayNotHasKey('catalog', $profile);
        self::assertArrayNotHasKey('lookahead', $profile);
    }
}
