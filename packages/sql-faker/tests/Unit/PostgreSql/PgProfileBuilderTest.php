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

#[UsesClass(\SqlFaker\Grammar\LexicalCatalogShape::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalWitnessShape::class)]
#[CoversClass(PgProfileBuilder::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgLexicalSamples::class)]
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

    public function testWitnessNamesTheSqlThatProvesATerminalCanBeLexed(): void
    {
        self::assertSame(
            ['id' => 'ident.bare', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => []],
            (new PgProfileBuilder())->witness('ident.bare', 'users', ['IDENT']),
        );
    }

    public function testRuleWitnessesAnswersTheWitnessEveryScannerRuleIsCoveredBy(): void
    {
        self::assertSame(
            [
            1 => 'postgresql.lookahead.FORMAT_LA',
            2 => 'postgresql.family.@TRIVIA.3',
            3 => 'postgresql.family.@TRIVIA.3',
            4 => 'postgresql.family.@TRIVIA.3',
            5 => 'postgresql.family.@TRIVIA.3',
            6 => 'postgresql.family.@TRIVIA.4',
            7 => 'postgresql.family.@TRIVIA.4',
            8 => 'postgresql.family.BCONST.0',
            9 => 'postgresql.family.XCONST.0',
            10 => 'postgresql.family.BCONST.0',
            11 => 'postgresql.family.XCONST.0',
            12 => 'postgresql.coverage.national-string',
            13 => 'postgresql.family.SCONST.0',
            14 => 'postgresql.family.SCONST.4',
            15 => 'postgresql.family.SCONST.9',
            16 => 'postgresql.family.SCONST.0',
            17 => 'postgresql.family.SCONST.2',
            18 => 'postgresql.family.SCONST.3',
            19 => 'postgresql.coverage.quote-stop-other',
            20 => 'postgresql.family.SCONST.1',
            21 => 'postgresql.family.SCONST.0',
            22 => 'postgresql.family.SCONST.4',
            23 => 'postgresql.family.SCONST.5',
            24 => 'postgresql.family.SCONST.6',
            28 => 'postgresql.family.SCONST.4',
            29 => 'postgresql.family.SCONST.7',
            30 => 'postgresql.family.SCONST.8',
            32 => 'postgresql.family.SCONST.10',
            33 => 'postgresql.coverage.dollar-prefix-fallback',
            34 => 'postgresql.family.SCONST.10',
            35 => 'postgresql.family.SCONST.10',
            36 => 'postgresql.coverage.dollar-failed-inside',
            37 => 'postgresql.coverage.dollar-character-inside',
            38 => 'postgresql.family.IDENT.1',
            39 => 'postgresql.family.IDENT.2',
            40 => 'postgresql.family.IDENT.1',
            41 => 'postgresql.family.IDENT.2',
            42 => 'postgresql.family.IDENT.3',
            43 => 'postgresql.family.IDENT.1',
            44 => 'postgresql.coverage.unicode-prefix-fallback',
            45 => 'postgresql.family.TYPECAST.0',
            46 => 'postgresql.family.DOT_DOT.0',
            47 => 'postgresql.family.COLON_EQUALS.0',
            48 => 'postgresql.family.EQUALS_GREATER.0',
            49 => 'postgresql.family.LESS_EQUALS.0',
            50 => 'postgresql.family.GREATER_EQUALS.0',
            51 => 'postgresql.family.NOT_EQUALS.0',
            52 => 'postgresql.family.NOT_EQUALS.1',
            53 => 'postgresql.family.%.0',
            54 => 'postgresql.family.Op.0',
            55 => 'postgresql.family.PARAM.0',
            57 => 'postgresql.family.ICONST.0',
            58 => 'postgresql.family.ICONST.1',
            59 => 'postgresql.family.ICONST.2',
            60 => 'postgresql.family.ICONST.3',
            64 => 'postgresql.family.FCONST.0',
            65 => 'postgresql.coverage.numeric-range',
            66 => 'postgresql.family.FCONST.2',
            71 => 'postgresql.keyword.ABORT_P.0',
            72 => 'postgresql.coverage.other-character',
            ],
            (new PgProfileBuilder())->ruleWitnesses(),
        );
    }

    public function testAttachUnitRecordsTheUnitAgainstTheWitnessThatNamesIt(): void
    {
        $terminals = ['IDENT' => [['id' => 'ident.bare', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => []]]];

        (new PgProfileBuilder())->attachUnit($terminals, 'ident.bare', 'identifier');

        self::assertSame(['identifier'], $terminals['IDENT'][0]['units']);
    }

    public function testAttachUnitReportsAWitnessNoTerminalCarries(): void
    {
        $terminals = [];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unknown witness: ident.bare');

        (new PgProfileBuilder())->attachUnit($terminals, 'ident.bare', 'identifier');
    }

    public function testCatalogPreservesNumericAndTriviaWitnessesFromTheShippedProfile(): void
    {
        $profile = require __DIR__ . '/../../../resources/lexical/pg-17.2.php';
        self::assertIsArray($profile);
        self::assertIsArray($profile['catalog']);
        self::assertIsArray($profile['catalog']['source']);
        self::assertIsArray($profile['catalog']['source']['states']);
        $states = array_values(array_filter($profile['catalog']['source']['states'], is_string(...)));
        self::assertSame($profile['catalog']['source']['states'], $states);
        self::assertIsArray($profile['catalog']['source']['rules']);
        $rules = array_values(array_filter($profile['catalog']['source']['rules'], is_string(...)));
        self::assertSame($profile['catalog']['source']['rules'], $rules);
        $shape = new \SqlFaker\Grammar\LexicalCatalogShape();
        $expected = $shape->of(array_filter($profile['catalog'], is_string(...), ARRAY_FILTER_USE_KEY));
        $actual = $shape->of((new PgProfileBuilder())->catalog(array_filter($profile, is_string(...), ARRAY_FILTER_USE_KEY), ['states' => $states, 'rules' => $rules, 'lookahead_tokens' => []]));

        self::assertSame($expected['terminals']['ICONST'], $actual['terminals']['ICONST']);
        self::assertSame($expected['terminals']['@TRIVIA'], $actual['terminals']['@TRIVIA']);
    }
}
