<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\Sqlite\SqliteCoverageSamples;
use SqlFaker\Sqlite\SqliteProfileBuilder;

#[UsesClass(\SqlFaker\Grammar\LexicalCatalogShape::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalWitnessShape::class)]
#[CoversClass(SqliteProfileBuilder::class)]
#[UsesClass(SqliteCoverageSamples::class)]
#[UsesClass(\SqlFaker\Sqlite\SqliteLexicalSamples::class)]
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

    public function testWitnessNamesTheSqlThatProvesATerminalCanBeLexed(): void
    {
        self::assertSame(
            ['id' => 'ident.bare', 'sql' => 'name', 'tokens' => ['TK_ID'], 'units' => ['CC_KYWD0']],
            (new SqliteProfileBuilder())->witness('ident.bare', 'name', ['TK_ID'], ['CC_KYWD0']),
        );
    }

    public function testCatalogWitnessesEveryCharacterClassTheTokenizerDeclares(): void
    {
        $catalog = (new SqliteProfileBuilder())->catalog(['keywords' => ['SELECT' => ['SELECT']]], [
            'CC_AND',
            'CC_BANG',
            'CC_BOM',
            'CC_COMMA',
            'CC_DIGIT',
            'CC_DOLLAR',
            'CC_DOT',
            'CC_EQ',
            'CC_GT',
            'CC_ID',
            'CC_ILLEGAL',
            'CC_KYWD',
            'CC_KYWD0',
            'CC_LP',
            'CC_LT',
            'CC_MINUS',
            'CC_NUL',
            'CC_PERCENT',
            'CC_PIPE',
            'CC_PLUS',
            'CC_QUOTE',
            'CC_QUOTE2',
            'CC_RP',
            'CC_SEMI',
            'CC_SLASH',
            'CC_SPACE',
            'CC_STAR',
            'CC_TILDA',
            'CC_VARALPHA',
            'CC_VARNUM',
            'CC_X',
        ]);

        /** @var array<string, list<array{id: string}>> $terminals */
        $terminals = $catalog['terminals'];
        /** @var list<string> $ids */
        $ids = array_merge(...array_map(
            static fn (array $witnesses): array => array_column($witnesses, 'id'),
            array_values($terminals),
        ));
        sort($ids);

        self::assertSame(
            [
                'cc-and',
                'cc-bang',
                'cc-bom',
                'cc-bracket',
                'cc-comma',
                'cc-digit',
                'cc-dollar',
                'cc-dot',
                'cc-equal',
                'cc-greater',
                'cc-id',
                'cc-illegal',
                'cc-keyword',
                'cc-keyword-start',
                'cc-left-parenthesis',
                'cc-less',
                'cc-minus',
                'cc-percent',
                'cc-pipe',
                'cc-plus',
                'cc-quote',
                'cc-right-parenthesis',
                'cc-semicolon',
                'cc-slash',
                'cc-space',
                'cc-star',
                'cc-tilde',
                'cc-variable-alpha',
                'cc-variable-number',
                'cc-x',
                'sqlite.family.@TRIVIA.0',
                'sqlite.family.@TRIVIA.1',
                'sqlite.family.@TRIVIA.2',
                'sqlite.family.ANY.0',
                'sqlite.family.BITAND.0',
                'sqlite.family.BITNOT.0',
                'sqlite.family.BITOR.0',
                'sqlite.family.BLOB.0',
                'sqlite.family.COMMA.0',
                'sqlite.family.CONCAT.0',
                'sqlite.family.DOT.0',
                'sqlite.family.EQ.0',
                'sqlite.family.EQ.1',
                'sqlite.family.FLOAT.0',
                'sqlite.family.FLOAT.1',
                'sqlite.family.GE.0',
                'sqlite.family.GT.0',
                'sqlite.family.ID.0',
                'sqlite.family.ID.1',
                'sqlite.family.ID.2',
                'sqlite.family.ID.3',
                'sqlite.family.INTEGER.0',
                'sqlite.family.INTEGER.1',
                'sqlite.family.LE.0',
                'sqlite.family.LP.0',
                'sqlite.family.LSHIFT.0',
                'sqlite.family.LT.0',
                'sqlite.family.MINUS.0',
                'sqlite.family.NE.0',
                'sqlite.family.NE.1',
                'sqlite.family.PLUS.0',
                'sqlite.family.PTR.0',
                'sqlite.family.PTR.1',
                'sqlite.family.QNUMBER.0',
                'sqlite.family.REM.0',
                'sqlite.family.RP.0',
                'sqlite.family.RSHIFT.0',
                'sqlite.family.SEMI.0',
                'sqlite.family.SLASH.0',
                'sqlite.family.STAR.0',
                'sqlite.family.STRING.0',
                'sqlite.family.STRING.1',
                'sqlite.family.STRING.2',
                'sqlite.family.VARIABLE.0',
                'sqlite.family.VARIABLE.1',
                'sqlite.family.VARIABLE.2',
                'sqlite.family.VARIABLE.3',
                'sqlite.family.VARIABLE.4',
                'sqlite.family.id.0',
                'sqlite.family.id.1',
                'sqlite.family.id.2',
                'sqlite.family.id.3',
                'sqlite.family.idj.0',
                'sqlite.family.idj.1',
                'sqlite.family.idj.2',
                'sqlite.family.idj.3',
                'sqlite.family.ids.0',
                'sqlite.family.ids.1',
                'sqlite.family.number.0',
                'sqlite.family.number.1',
                'sqlite.family.number.2',
                'sqlite.family.number.3',
                'sqlite.keyword.SELECT.0',
            ],
            $ids,
        );
    }

    public function testCatalogPreservesNumericAndTriviaWitnessesFromTheShippedProfile(): void
    {
        $profile = require __DIR__ . '/../../../resources/lexical/sqlite-3.47.2.php';
        self::assertIsArray($profile);
        self::assertIsArray($profile['catalog']);
        self::assertIsArray($profile['catalog']['source']);
        self::assertIsArray($profile['catalog']['source']['character_classes']);
        $classes = array_values(array_filter($profile['catalog']['source']['character_classes'], is_string(...)));
        self::assertSame($profile['catalog']['source']['character_classes'], $classes);
        $shape = new \SqlFaker\Grammar\LexicalCatalogShape();
        $expected = $shape->of(array_filter($profile['catalog'], is_string(...), ARRAY_FILTER_USE_KEY));
        $actual = $shape->of((new SqliteProfileBuilder())->catalog(array_filter($profile, is_string(...), ARRAY_FILTER_USE_KEY), $classes));

        self::assertSame($expected['terminals']['INTEGER'], $actual['terminals']['INTEGER']);
        self::assertSame($expected['terminals']['@TRIVIA'], $actual['terminals']['@TRIVIA']);
    }

    public function testCatalogPreservesUnicodeIdentifierWitness(): void
    {
        $profile = require __DIR__ . '/../../../resources/lexical/sqlite-3.47.2.php';
        self::assertIsArray($profile);
        self::assertIsArray($profile['catalog']);
        self::assertIsArray($profile['catalog']['source']);
        self::assertIsArray($profile['catalog']['source']['character_classes']);
        $classes = array_values(array_filter($profile['catalog']['source']['character_classes'], is_string(...)));
        self::assertSame($profile['catalog']['source']['character_classes'], $classes);
        $catalog = (new \SqlFaker\Grammar\LexicalCatalogShape())->of(
            (new SqliteProfileBuilder())->catalog(['keywords' => []], $classes),
        );
        $witnesses = array_column($catalog['terminals']['@COVERAGE'], null, 'id');

        self::assertArrayHasKey('cc-id', $witnesses);
        self::assertSame([
            'id' => 'cc-id',
            'sql' => 'é',
            'tokens' => ['TK_ID'],
            'units' => ['CC_ID'],
        ], $witnesses['cc-id']);
    }
}
