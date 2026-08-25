<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testCatalogReportsATokenizerThatDeclaresNoCharacterClasses(): void
    {
        $this->expectException(RuntimeException::class);

        (new SqliteProfileBuilder())->catalog(['keywords' => []], []);
    }

    public function testIdentifierSamplesCoversEveryWayAnIdentifierIsQuoted(): void
    {
        self::assertSame(
            [
                ['name', ['TK_ID'], ['CC_KYWD0']],
                ['"select"', ['TK_ID'], ['CC_QUOTE']],
                ['`select`', ['TK_ID'], ['CC_QUOTE']],
                ['[select]', ['TK_ID'], ['CC_QUOTE2']],
            ],
            (new SqliteProfileBuilder())->identifierSamples(),
        );
    }

    public function testWitnessNamesTheSqlThatProvesATerminalCanBeLexed(): void
    {
        self::assertSame(
            ['id' => 'ident.bare', 'sql' => 'name', 'tokens' => ['TK_ID'], 'units' => ['CC_KYWD0']],
            (new SqliteProfileBuilder())->witness('ident.bare', 'name', ['TK_ID'], ['CC_KYWD0']),
        );
    }
}
