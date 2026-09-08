<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\Sqlite\SqliteProfileBuilder;

#[CoversClass(SqliteProfileBuilder::class)]
#[UsesClass(\SqlFaker\Sqlite\LexicalProfileCompiler::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\UpstreamLexerSource::class)]
final class SqliteProfileBuilderTest extends TestCase
{
    public function testSourceUrlsReadsTheKeywordHashAndTheTokenizer(): void
    {
        $urls = (new SqliteProfileBuilder())->sourceUrls('sqlite-3.47.2');

        self::assertStringEndsWith('/tool/mkkeywordhash.c', $urls['keywords']);
        self::assertStringEndsWith('/src/tokenize.c', $urls['scanner']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSourceFile(): array
    {
        return ['keywords' => ['keywords'], 'scanner' => ['scanner']];
    }

    #[DataProvider('providerSourceFile')]
    public function testSourceUrlsSpellsTheVersionBackIntoAReleaseTag(string $file): void
    {
        self::assertStringContainsString(
            '/refs/tags/version-3.47.2',
            (new SqliteProfileBuilder())->sourceUrls('sqlite-3.47.2')[$file],
        );
    }

    public function testBuildReportsAnUpstreamFileItCannotRead(): void
    {
        $source = self::createStub(LexerSource::class);
        $source->method('fetch')->willThrowException(new RuntimeException('Failed to fetch'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch');

        (new SqliteProfileBuilder($source))->build('sqlite-3.47.2');
    }













    public function testBuildRetainsSourceHashesAndOnlyRegistrationFacts(): void
    {
        $builder = new SqliteProfileBuilder();
        $urls = $builder->sourceUrls('sqlite-3.47.2');
        $table = '{ "SELECT", "TK_SELECT", ALWAYS, 10 },';
        $source = $this->createMock(LexerSource::class);
        $source->method('fetch')->willReturnCallback(static fn (string $url): string => $url === $urls['keywords'] ? $table : 'reviewed scanner source');
        $profile = (new SqliteProfileBuilder($source))->build('sqlite-3.47.2');
        self::assertSame('sqlite', $profile['dialect']);
        self::assertSame('sqlite-3.47.2', $profile['version']);
        self::assertIsArray($profile['sources']);
        self::assertSame(hash('sha256', $table), $profile['sources'][$urls['keywords']]);
        self::assertArrayNotHasKey('catalog', $profile);
        self::assertArrayNotHasKey('lookahead', $profile);
    }
}
