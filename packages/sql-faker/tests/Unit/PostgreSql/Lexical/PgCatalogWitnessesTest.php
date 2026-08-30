<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\PostgreSql\Lexical\PgCatalogWitnesses;
use SqlFaker\PostgreSql\Lexical\PgLexicalSamples;

#[CoversClass(PgCatalogWitnesses::class)]
#[UsesClass(PgLexicalSamples::class)]
final class PgCatalogWitnessesTest extends TestCase
{
    public function testWitnessNamesTheSqlThatProvesATerminalCanBeLexed(): void
    {
        self::assertSame(
            ['id' => 'ident.bare', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => []],
            (new PgCatalogWitnesses())->witness('ident.bare', 'users', ['IDENT']),
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
            (new PgCatalogWitnesses())->ruleWitnesses(),
        );
    }

    public function testAttachUnitRecordsTheUnitAgainstTheWitnessThatNamesIt(): void
    {
        $terminals = ['IDENT' => [['id' => 'ident.bare', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => []]]];

        (new PgCatalogWitnesses())->attachUnit($terminals, 'ident.bare', 'identifier');

        self::assertSame(['identifier'], $terminals['IDENT'][0]['units']);
    }

    public function testAttachUnitReportsAWitnessNoTerminalCarries(): void
    {
        $terminals = [];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unknown witness: ident.bare');

        (new PgCatalogWitnesses())->attachUnit($terminals, 'ident.bare', 'identifier');
    }

    public function testForProfileGathersEveryWitnessUnderTheTerminalItStandsFor(): void
    {
        $terminals = (new PgCatalogWitnesses())->forProfile(
            ['ABORT_P' => ['ABORT'], 'FORMAT' => ['FORMAT'], 'JSON' => ['JSON']],
            ['FORMAT' => ['token' => 'FORMAT_LA', 'followed_by' => ['JSON']]],
        );

        self::assertSame('postgresql.keyword.ABORT_P.0', $terminals['ABORT_P'][0]['id']);
        self::assertSame('postgresql.lookahead.FORMAT_LA', $terminals['FORMAT_LA'][0]['id']);
        self::assertSame('postgresql.mode.MODE_TYPE_NAME', $terminals['MODE_TYPE_NAME'][0]['id']);
    }

    public function testFromKeywordsWritesOneWitnessPerLexemeAKeywordIsSpelled(): void
    {
        $terminals = (new PgCatalogWitnesses())->fromKeywords(['ABORT_P' => ['ABORT', 'abort']]);

        self::assertSame(
            ['postgresql.keyword.ABORT_P.0', 'postgresql.keyword.ABORT_P.1'],
            array_column($terminals['ABORT_P'], 'id'),
        );
    }

    public function testFromLookaheadCarriesThePairThatMakesTheFrontendRewriteTheToken(): void
    {
        $terminals = (new PgCatalogWitnesses())->fromLookahead(
            ['FORMAT' => ['token' => 'FORMAT_LA', 'followed_by' => ['JSON']]],
            ['FORMAT' => ['FORMAT'], 'JSON' => ['JSON']],
        );

        self::assertSame('FORMAT', $terminals['FORMAT_LA'][0]['sql']);
        self::assertSame('FORMAT JSON', $terminals['FORMAT_LA'][0]['context_sql']);
    }

    public function testFromSamplesExpectsTriviaToProduceNoTokenAtAll(): void
    {
        $terminals = (new PgCatalogWitnesses())->fromSamples();

        self::assertSame([], $terminals['@TRIVIA'][0]['tokens']);
        self::assertSame(['%'], $terminals['%'][0]['tokens']);
    }

    public function testCoverageSamplesGatherTheBranchesNothingElseReaches(): void
    {
        $terminals = (new PgCatalogWitnesses())->coverageSamples();

        self::assertContains('postgresql.coverage.national-string', array_column($terminals['@COVERAGE'], 'id'));
    }

    public function testParserModesStandForTheUnitTheyReachAndCarryNoText(): void
    {
        $terminals = (new PgCatalogWitnesses())->parserModes();

        self::assertSame('', $terminals['MODE_TYPE_NAME'][0]['sql']);
        self::assertSame(['parser-mode:MODE_TYPE_NAME'], $terminals['MODE_TYPE_NAME'][0]['units']);
    }
}
