<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\Lexical\SqliteCatalogWitnesses;
use SqlFaker\Sqlite\Lexical\SqliteLexicalSamples;

#[CoversClass(SqliteCatalogWitnesses::class)]
#[UsesClass(SqliteLexicalSamples::class)]
final class SqliteCatalogWitnessesTest extends TestCase
{
    public function testWitnessNamesTheSqlThatProvesATerminalCanBeLexed(): void
    {
        self::assertSame(
            ['id' => 'ident.bare', 'sql' => 'name', 'tokens' => ['TK_ID'], 'units' => ['CC_KYWD0']],
            (new SqliteCatalogWitnesses())->witness('ident.bare', 'name', ['TK_ID'], ['CC_KYWD0']),
        );
    }

    public function testForProfileGathersEveryWitnessUnderTheTerminalItStandsFor(): void
    {
        $terminals = (new SqliteCatalogWitnesses())->forProfile(
            ['SELECT' => ['SELECT']],
            ['sqlite.coverage.space' => [' ', [], ['CC_SPACE']]],
        );

        self::assertSame('sqlite.keyword.SELECT.0', $terminals['SELECT'][0]['id']);
        self::assertSame('sqlite.coverage.space', $terminals['@COVERAGE'][0]['id']);
    }

    public function testFromKeywordsNamesTheTokenAfterTheKeywordItself(): void
    {
        $terminals = (new SqliteCatalogWitnesses())->fromKeywords(['SELECT' => ['SELECT']]);

        self::assertSame(['TK_SELECT'], $terminals['SELECT'][0]['tokens']);
        self::assertSame(['CC_KYWD0'], $terminals['SELECT'][0]['units']);
    }

    public function testFromKeywordsAnswersWithinAsAPlainIdentifier(): void
    {
        $terminals = (new SqliteCatalogWitnesses())->fromKeywords(['WITHIN' => ['WITHIN']]);

        self::assertSame(['TK_ID'], $terminals['WITHIN'][0]['tokens']);
    }

    public function testFromSamplesNamesEachFamilyWitnessAfterItsTerminal(): void
    {
        $terminals = (new SqliteCatalogWitnesses())->fromSamples();

        $misnamed = array_filter(
            $terminals,
            static fn (array $witnesses, string $terminal): bool
                => !str_starts_with($witnesses[0]['id'], "sqlite.family.{$terminal}."),
            ARRAY_FILTER_USE_BOTH,
        );

        self::assertNotSame([], $terminals);
        self::assertSame([], array_keys($misnamed));
    }

    public function testFromCoverageSamplesGathersThemUnderOneTerminal(): void
    {
        $terminals = (new SqliteCatalogWitnesses())->fromCoverageSamples([
            'sqlite.coverage.space' => [' ', [], ['CC_SPACE']],
        ]);

        self::assertSame(['@COVERAGE'], array_keys($terminals));
        self::assertSame(['CC_SPACE'], $terminals['@COVERAGE'][0]['units']);
    }
}
