<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Source\LexerSource;
use SqlFaker\PostgreSql\Lexical\PgCatalogWitnesses;
use SqlFaker\PostgreSql\Lexical\PgLexicalCoverage;
use SqlFaker\PostgreSql\Lexical\PgLexicalSamples;
use SqlFaker\PostgreSql\Lexical\PgProfileBuilder;

#[CoversClass(PgProfileBuilder::class)]
#[UsesClass(PgLexicalSamples::class)]
#[UsesClass(PgCatalogWitnesses::class)]
#[UsesClass(PgLexicalCoverage::class)]
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

    public function testCatalogReportsAScannerThatDeclaresNoRules(): void
    {
        $this->expectException(RuntimeException::class);

        (new PgProfileBuilder())->catalog(
            ['keywords' => [], 'lookahead' => []],
            ['states' => [], 'rules' => [], 'lookahead_tokens' => []],
        );
    }

    /**
     * @return array{array{keywords: array<string, list<string>>, lookahead: array<string, array{token: string, followed_by: list<string>}>}, array{states: list<string>, rules: list<string>, lookahead_tokens: list<string>}}
     */
    public static function providerScannerModel(): array
    {
        return [
            [
                'keywords' => ['ABORT_P' => ['ABORT'], 'FORMAT' => ['FORMAT'], 'JSON' => ['JSON']],
                'lookahead' => ['FORMAT' => ['token' => 'FORMAT_LA', 'followed_by' => ['JSON']]],
            ],
            ['states' => ['INITIAL'], 'rules' => array_fill(0, 72, 'rule'), 'lookahead_tokens' => []],
        ];
    }

    /**
     * @return array<string, list<array{id: string, sql: string, context_sql?: string, tokens: list<string>, units: list<string>}>>
     */
    public static function providerCatalogTerminals(): array
    {
        [$profile, $source] = self::providerScannerModel();
        $terminals = (new PgProfileBuilder())->catalog($profile, $source)['terminals'];
        self::assertIsArray($terminals);

        /** @var array<string, list<array{id: string, sql: string, context_sql?: string, tokens: list<string>, units: list<string>}>> */
        return $terminals;
    }

    /**
     * @return list<string>
     */
    public static function providerCatalogCoverageUnits(): array
    {
        [$profile, $source] = self::providerScannerModel();
        $coverage = (new PgProfileBuilder())->catalog($profile, $source)['coverage'];
        self::assertIsArray($coverage);

        /** @var list<string> */
        return $coverage['units'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerParserMode(): iterable
    {
        yield 'type name' => ['MODE_TYPE_NAME'];
        yield 'expression' => ['MODE_PLPGSQL_EXPR'];
        yield 'first assignment' => ['MODE_PLPGSQL_ASSIGN1'];
        yield 'second assignment' => ['MODE_PLPGSQL_ASSIGN2'];
        yield 'third assignment' => ['MODE_PLPGSQL_ASSIGN3'];
    }

    #[DataProvider('providerParserMode')]
    public function testCatalogWitnessesEveryParserModeAsTextlessCoverageOfItsOwnUnit(string $mode): void
    {
        $terminals = self::providerCatalogTerminals();

        self::assertSame(
            [['id' => 'postgresql.mode.' . $mode, 'sql' => '', 'tokens' => [], 'units' => ['parser-mode:' . $mode]]],
            $terminals[$mode],
        );
    }

    public function testCatalogCountsOneUnitPerScannerRuleAndOnePerParserMode(): void
    {
        self::assertSame(
            array_merge(
                array_map(static fn (int $rule): string => 'rule:' . $rule, range(1, 73)),
                [
                    'parser-mode:MODE_TYPE_NAME',
                    'parser-mode:MODE_PLPGSQL_EXPR',
                    'parser-mode:MODE_PLPGSQL_ASSIGN1',
                    'parser-mode:MODE_PLPGSQL_ASSIGN2',
                    'parser-mode:MODE_PLPGSQL_ASSIGN3',
                ],
            ),
            self::providerCatalogCoverageUnits(),
        );
    }

    public function testCatalogCarriesEveryScannerRuleAWitnessedSampleReaches(): void
    {
        $terminals = self::providerCatalogTerminals();

        self::assertSame(
            [
                'postgresql.coverage.national-string' => ["N'text'", ['NCHAR', 'SCONST'], ['rule:12']],
                'postgresql.coverage.dollar-prefix-fallback' => ['$tag', ['$', 'IDENT'], ['rule:33']],
                'postgresql.coverage.quote-stop-other' => ["'text'x", ['SCONST', 'IDENT'], ['rule:19']],
                'postgresql.coverage.dollar-delimiter-mismatch' => ['$tag$a$other$b$tag$', ['SCONST'], []],
                'postgresql.coverage.dollar-failed-inside' => ['$tag$a$other b$tag$', ['SCONST'], ['rule:36']],
                'postgresql.coverage.dollar-character-inside' => ['$tag$a$1b$tag$', ['SCONST'], ['rule:37']],
                'postgresql.coverage.unicode-prefix-fallback' => ['U&x', ['IDENT', 'Op', 'IDENT'], ['rule:44']],
                'postgresql.coverage.numeric-range' => ['1..2', ['ICONST', 'DOT_DOT', 'ICONST'], ['rule:65']],
                'postgresql.coverage.other-character' => ['{', ['{'], ['rule:72']],
            ],
            array_map(
                static fn (array $witness): array => [$witness['sql'], $witness['tokens'], $witness['units']],
                array_column($terminals['@COVERAGE'], null, 'id'),
            ),
        );
    }

    public function testCatalogWitnessesAKeywordWithTheSpellingTheProfileGivesIt(): void
    {
        $terminals = self::providerCatalogTerminals();

        self::assertSame(
            [['id' => 'postgresql.keyword.ABORT_P.0', 'sql' => 'ABORT', 'tokens' => ['ABORT_P'], 'units' => ['rule:71']]],
            $terminals['ABORT_P'],
        );
    }

    public function testCatalogWitnessesALookaheadTokenWithTheWordsThatTriggerIt(): void
    {
        $terminals = self::providerCatalogTerminals();

        self::assertSame(
            [[
                'id' => 'postgresql.lookahead.FORMAT_LA',
                'sql' => 'FORMAT',
                'context_sql' => 'FORMAT JSON',
                'tokens' => ['FORMAT_LA'],
                'units' => ['rule:1'],
            ]],
            $terminals['FORMAT_LA'],
        );
    }

    public function testCatalogWitnessesEveryPunctuationCharacterTheScannerReadsAsItself(): void
    {
        $terminals = self::providerCatalogTerminals();

        self::assertSame(
            str_split('%()*+,-./:;<=>[]^'),
            array_values(array_filter(
                str_split('%()*+,-./:;<=>[]^'),
                static fn (string $mark): bool => isset($terminals[$mark]),
            )),
        );
    }
}
