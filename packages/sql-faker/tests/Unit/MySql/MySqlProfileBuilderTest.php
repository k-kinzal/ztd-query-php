<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\SqlVersionRegistry;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\TerminalInventory;
use SqlFaker\MySql\MySqlProfileBuilder;

#[CoversClass(MySqlProfileBuilder::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(SqlVersionRegistry::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(TerminalInventory::class)]
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

        (new MySqlProfileBuilder($source))->build('mysql-8.4.7', Grammar::load('mysql-8.4.7'));
    }

    public function testCatalogNamesTheLexerTheWitnessesWereReadThrough(): void
    {
        $catalog = (new MySqlProfileBuilder())->catalog(
            'mysql-8.4.7',
            ['symbols' => [], 'functions' => [], 'features' => ['dollar_quoted_strings' => false]],
            [],
            Grammar::load('mysql-8.4.7'),
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
            Grammar::load('mysql-8.4.7'),
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
}
