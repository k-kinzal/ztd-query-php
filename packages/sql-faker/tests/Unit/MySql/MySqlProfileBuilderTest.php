<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\Grammar\Lexical\TerminalInventory;
use SqlFaker\Grammar\Resource\SqlVersionRegistry;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\MySql\Grammar\MySqlGrammar;
use SqlFaker\MySql\MySqlProfileBuilder;

#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalCatalogShape::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalWitnessShape::class)]
#[CoversClass(MySqlProfileBuilder::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(SqlVersionRegistry::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(TerminalInventory::class)]
#[UsesClass(\SqlFaker\MySql\MySqlLexicalSamples::class)]
#[UsesClass(MySqlGrammar::class)]
final class MySqlProfileBuilderTest extends TestCase
{
    public function testSourceUrlsReadsTheKeywordTableAndTheScanner(): void
    {
        $urls = (new MySqlProfileBuilder())->sourceUrls('mysql-8.4.7');

        self::assertStringEndsWith('/sql/lex.h', $urls['table']);
        self::assertStringEndsWith('/sql/sql_lex.cc', $urls['scanner']);
    }

    public function testSourceUrlsFollowsTheStateHeaderWhereEachReleaseKeepsIt(): void
    {
        $builder = new MySqlProfileBuilder();

        self::assertStringEndsWith('/include/m_ctype.h', $builder->sourceUrls('mysql-5.6.51')['state']);
        self::assertStringEndsWith('/include/sql_chars.h', $builder->sourceUrls('mysql-8.0.44')['state']);
        self::assertStringEndsWith('/strings/sql_chars.h', $builder->sourceUrls('mysql-8.4.7')['state']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSourceFile(): array
    {
        return ['table' => ['table'], 'scanner' => ['scanner'], 'state' => ['state']];
    }

    #[DataProvider('providerSourceFile')]
    public function testSourceUrlsPointsEveryFileAtTheReleaseTag(string $file): void
    {
        self::assertStringContainsString(
            '/refs/tags/mysql-8.4.7',
            (new MySqlProfileBuilder())->sourceUrls('mysql-8.4.7')[$file],
        );
    }

    public function testBuildReportsAnUpstreamFileItCannotRead(): void
    {
        $source = self::createStub(LexerSource::class);
        $source->method('fetch')->willThrowException(new RuntimeException('Failed to fetch'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch');

        (new MySqlProfileBuilder($source))->build('mysql-8.4.7', MySqlGrammar::load('mysql-8.4.7'));
    }

    public function testCatalogNamesTheLexerTheWitnessesWereReadThrough(): void
    {
        $catalog = (new MySqlProfileBuilder())->catalog(
            'mysql-8.4.7',
            ['symbols' => [], 'functions' => [], 'features' => ['dollar_quoted_strings' => false]],
            [],
            MySqlGrammar::load('mysql-8.4.7'),
        );

        self::assertSame(['engine' => 'mysql', 'entrypoint' => 'my_sql_parser_lex'], $catalog['source']);
        self::assertSame(['source', 'terminals', 'terminal_exclusions', 'coverage'], array_keys($catalog));
    }

    public function testWitnessNamesTheSqlThatProvesATerminalCanBeLexed(): void
    {
        self::assertSame(
            ['id' => 'ident.bare', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => ['identifier']],
            (new MySqlProfileBuilder())->witness('ident.bare', 'users', ['IDENT'], ['identifier']),
        );
    }

    public function testWitnessKeepsTheContextATerminalNeedsAroundIt(): void
    {
        self::assertSame(
            [
                'id' => 'ident.bare',
                'sql' => 'users',
                'tokens' => ['IDENT'],
                'units' => ['identifier'],
                'context_sql' => 'SELECT %s',
            ],
            (new MySqlProfileBuilder())->witness('ident.bare', 'users', ['IDENT'], ['identifier'], 'SELECT %s'),
        );
    }

    public function testCatalogWitnessesEveryTerminalItsOwnTablesName(): void
    {
        $catalog = (new MySqlProfileBuilder())->catalog(
            'mysql-8.4.7',
            ['symbols' => [], 'functions' => [], 'features' => ['dollar_quoted_strings' => false]],
            [],
            MySqlGrammar::load('mysql-8.4.7'),
        );

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
                'mysql.family.@COVERAGE.0',
                'mysql.family.@COVERAGE.1',
                'mysql.family.@COVERAGE.2',
                'mysql.family.@COVERAGE.3',
                'mysql.family.@COVERAGE.4',
                'mysql.family.@COVERAGE.5',
                'mysql.family.@COVERAGE.6',
                'mysql.family.@COVERAGE.7',
                'mysql.family.@COVERAGE.8',
                'mysql.family.@TRIVIA.0',
                'mysql.family.@TRIVIA.1',
                'mysql.family.@TRIVIA.2',
                'mysql.family.@TRIVIA.3',
                'mysql.family.BIN_NUM.0',
                'mysql.family.BIN_NUM.1',
                'mysql.family.DECIMAL_NUM.0',
                'mysql.family.FLOAT_NUM.0',
                'mysql.family.HEX_NUM.0',
                'mysql.family.HEX_NUM.1',
                'mysql.family.IDENT.0',
                'mysql.family.IDENT_QUOTED.0',
                'mysql.family.JSON_SEPARATOR_SYM.0',
                'mysql.family.JSON_UNQUOTED_SEPARATOR_SYM.0',
                'mysql.family.LEX_HOSTNAME.0',
                'mysql.family.LONG_NUM.0',
                'mysql.family.NCHAR_STRING.0',
                'mysql.family.NUM.0',
                'mysql.family.OR2_SYM.0',
                'mysql.family.PARAM_MARKER.0',
                'mysql.family.SET_VAR.0',
                'mysql.family.TEXT_STRING.0',
                'mysql.family.TEXT_STRING.1',
                'mysql.family.ULONGLONG_NUM.0',
                'mysql.family.UNDERSCORE_CHARSET.0',
                'mysql.family.WITH_ROLLUP_SYM.0',
                'mysql.parser.END_OF_INPUT',
                'mysql.parser.GRAMMAR_SELECTOR_CTE',
                'mysql.parser.GRAMMAR_SELECTOR_DERIVED_EXPR',
                'mysql.parser.GRAMMAR_SELECTOR_EXPR',
                'mysql.parser.GRAMMAR_SELECTOR_GCOL',
                'mysql.parser.GRAMMAR_SELECTOR_PART',
                'mysql.punctuation.21',
                'mysql.punctuation.25',
                'mysql.punctuation.26',
                'mysql.punctuation.28',
                'mysql.punctuation.29',
                'mysql.punctuation.2a',
                'mysql.punctuation.2b',
                'mysql.punctuation.2c',
                'mysql.punctuation.2d',
                'mysql.punctuation.2e',
                'mysql.punctuation.2f',
                'mysql.punctuation.3a',
                'mysql.punctuation.3b',
                'mysql.punctuation.3c',
                'mysql.punctuation.3d',
                'mysql.punctuation.3e',
                'mysql.punctuation.3f',
                'mysql.punctuation.40',
                'mysql.punctuation.5b',
                'mysql.punctuation.5d',
                'mysql.punctuation.5e',
                'mysql.punctuation.7b',
                'mysql.punctuation.7c',
                'mysql.punctuation.7d',
                'mysql.punctuation.7e',
            ],
            $ids,
        );
    }

    public function testCatalogNamesEveryTerminalTheDefaultServerModeWillNotProduce(): void
    {
        $catalog = (new MySqlProfileBuilder())->catalog(
            'mysql-8.4.7',
            ['symbols' => [], 'functions' => [], 'features' => ['dollar_quoted_strings' => false]],
            [],
            MySqlGrammar::load('mysql-8.4.7'),
        );

        self::assertSame(
            [
                'NOT2_SYM' => 'Default sql_mode emits NOT_SYM; NOT2_SYM requires HIGH_NOT_PRECEDENCE.',
                'OR_OR_SYM' => 'Default sql_mode normalizes double-pipe to OR2_SYM; '
                    . 'OR_OR_SYM requires PIPES_AS_CONCAT.',
                'UDF_RETURNS_SYM' => 'The token is declared by legacy grammars but has no mapping '
                    . 'in the official lexer table.',
            ],
            $catalog['terminal_exclusions'],
        );
    }

    public function testCatalogWitnessesEveryEntryPointTheParserDeclares(): void
    {
        $catalog = (new MySqlProfileBuilder())->catalog(
            'mysql-8.4.7',
            ['symbols' => [], 'functions' => [], 'features' => ['dollar_quoted_strings' => false]],
            [],
            MySqlGrammar::load('mysql-8.4.7'),
        );

        self::assertSame(
            [
                'units' => [
                    'parser-entry:END_OF_INPUT',
                    'parser-entry:GRAMMAR_SELECTOR_CTE',
                    'parser-entry:GRAMMAR_SELECTOR_DERIVED_EXPR',
                    'parser-entry:GRAMMAR_SELECTOR_EXPR',
                    'parser-entry:GRAMMAR_SELECTOR_GCOL',
                    'parser-entry:GRAMMAR_SELECTOR_PART',
                ],
                'witnessed' => [
                    'parser-entry:END_OF_INPUT' => 'mysql.parser.END_OF_INPUT',
                    'parser-entry:GRAMMAR_SELECTOR_CTE' => 'mysql.parser.GRAMMAR_SELECTOR_CTE',
                    'parser-entry:GRAMMAR_SELECTOR_DERIVED_EXPR' => 'mysql.parser.GRAMMAR_SELECTOR_DERIVED_EXPR',
                    'parser-entry:GRAMMAR_SELECTOR_EXPR' => 'mysql.parser.GRAMMAR_SELECTOR_EXPR',
                    'parser-entry:GRAMMAR_SELECTOR_GCOL' => 'mysql.parser.GRAMMAR_SELECTOR_GCOL',
                    'parser-entry:GRAMMAR_SELECTOR_PART' => 'mysql.parser.GRAMMAR_SELECTOR_PART',
                ],
                'excluded' => [],
            ],
            $catalog['coverage'],
        );
    }

    public function testCatalogPreservesNumericAndTriviaWitnessesFromTheShippedProfile(): void
    {
        $profile = require __DIR__ . '/../../../resources/lexical/mysql-8.4.7.php';
        self::assertIsArray($profile);
        self::assertIsArray($profile['catalog']);

        $shape = new \SqlFaker\Grammar\Lexical\LexicalCatalogShape();
        $expected = $shape->of(array_filter($profile['catalog'], is_string(...), ARRAY_FILTER_USE_KEY));
        $actual = $shape->of((new MySqlProfileBuilder())->catalog('mysql-8.4.7', ['symbols' => [], 'functions' => [], 'features' => ['dollar_quoted_strings' => false]], [], new Grammar('start', [])));

        self::assertSame($expected['terminals']['HEX_NUM'], $actual['terminals']['HEX_NUM']);
        self::assertSame($expected['terminals']['@TRIVIA'], $actual['terminals']['@TRIVIA']);
    }

    #[DataProvider('providerPunctuation')]
    public function testCatalogPreservesPunctuation(string $punctuation): void
    {
        $catalog = (new \SqlFaker\Grammar\Lexical\LexicalCatalogShape())->of((new MySqlProfileBuilder())->catalog(
            'mysql-8.4.7',
            ['symbols' => [], 'functions' => [], 'features' => ['dollar_quoted_strings' => false]],
            [],
            new Grammar('start', []),
        ));

        self::assertArrayHasKey($punctuation, $catalog['terminals']);
        self::assertSame($punctuation, $catalog['terminals'][$punctuation][0]['sql']);
        self::assertSame([$punctuation], $catalog['terminals'][$punctuation][0]['tokens']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerPunctuation(): iterable
    {
        foreach (['!', '%', '&', '(', ')', '*', '+', ',', '-', '.', '/', ':', ';', '<', '=', '>', '?', '@', '[', ']', '^', '{', '}', '|', '~'] as $punctuation) {
            yield $punctuation => [$punctuation];
        }
    }

    public function testKeywordWitnessesRetainsFunctionContextAndSkipsSqlModeOnlyTokens(): void
    {
        $terminals = (new MySqlProfileBuilder())->keywordWitnesses(
            ['SELECT' => ['SELECT'], 'NOT2_SYM' => ['!'], 'OR_OR_SYM' => ['||']],
            ['COUNT_SYM' => ['COUNT']],
        );

        self::assertSame(['SELECT', 'COUNT_SYM'], array_keys($terminals));
        self::assertSame('mysql.symbol.SELECT.0', $terminals['SELECT'][0]['id']);
        self::assertSame('COUNT(', $terminals['COUNT_SYM'][0]['context_sql'] ?? null);
    }

    public function testLexicalSamplesRequiresTheDollarQuotedStateWhenTheFeatureIsEnabled(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MySQL dollar-quoted string state was not found.');

        (new MySqlProfileBuilder())->lexicalSamples([], true);
    }

    public function testAddLexicalWitnessesPreservesExistingPunctuationAndAppendsFamilies(): void
    {
        $builder = new MySqlProfileBuilder();
        $existing = $builder->witness('existing', '+', ['+'], []);
        $terminals = ['+' => [$existing]];
        $builder->addLexicalWitnesses($terminals, ['IDENT' => [['name', ['IDENT'], ['MY_LEX_IDENT'], 'name(']]]);

        self::assertSame([$existing], $terminals['+']);
        self::assertSame('name(', $terminals['IDENT'][0]['context_sql'] ?? null);
        self::assertSame('mysql.punctuation.21', $terminals['!'][0]['id']);
    }

    public function testAddParserWitnessesKeepsEntryTokensEmptyAndLegacyCubeSpelling(): void
    {
        $terminals = [];
        $tokens = (new MySqlProfileBuilder())->addParserWitnesses($terminals, 'mysql-5.7.44', new Grammar('stmt', []));

        self::assertSame(['END_OF_INPUT'], $tokens);
        self::assertSame('', $terminals['END_OF_INPUT'][0]['sql']);
        self::assertSame(['parser-entry:END_OF_INPUT'], $terminals['END_OF_INPUT'][0]['units']);
        self::assertSame('WITH CUBE', $terminals['WITH_CUBE_SYM'][0]['sql']);
    }

    public function testCoverageAddsEndStatesAndRejectsUnwitnessedStates(): void
    {
        $builder = new MySqlProfileBuilder();
        $terminals = [];

        self::assertSame([
            'units' => ['MY_LEX_EOL', 'MY_LEX_END'],
            'witnessed' => ['MY_LEX_END' => 'mysql.coverage.MY_LEX_END', 'MY_LEX_EOL' => 'mysql.coverage.MY_LEX_EOL'],
            'excluded' => [],
        ], $builder->coverage($terminals, ['MY_LEX_EOL', 'MY_LEX_END'], []));
        self::assertCount(2, $terminals['@COVERAGE']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MySQL source model misses lexical states: UNKNOWN');

        $builder->coverage($terminals, ['UNKNOWN'], []);
    }
}
