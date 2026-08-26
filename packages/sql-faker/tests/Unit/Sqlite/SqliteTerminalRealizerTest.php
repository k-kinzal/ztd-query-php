<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalCatalogShape;
use SqlFaker\Grammar\LexicalCoverageCheck;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalWitnessCheck;
use SqlFaker\Grammar\LexicalWitnessShape;
use SqlFaker\Grammar\RandomCharacters;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Sqlite\SqliteTerminalRealizer;
use SqlFaker\Sqlite\SqliteTokenizer;

#[CoversClass(SqliteTerminalRealizer::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(LexicalCatalogShape::class)]
#[UsesClass(LexicalCoverageCheck::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(LexicalWitnessCheck::class)]
#[UsesClass(LexicalWitnessShape::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(SqliteTokenizer::class)]
#[UsesClass(RandomCharacters::class)]
final class SqliteTerminalRealizerTest extends TestCase
{
    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeReplaysACataloguedExample(SqliteTerminalRealizer $realizer): void
    {
        self::assertSame(['users', ['ID']], $realizer->realize('TERMINAL'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeWritesTheStrictTableOptionAsAnIdentifier(SqliteTerminalRealizer $realizer): void
    {
        self::assertSame(['STRICT', ['ID']], $realizer->realize(SqliteTerminalRealizer::STRICT_TABLE_OPTION));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeReportsATerminalTheCatalogDoesNotWitness(SqliteTerminalRealizer $realizer): void
    {
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported SQLite terminal for sqlite-3.47.2: NOT_A_TERMINAL');

        $realizer->realize('NOT_A_TERMINAL');
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeWitnessedStripsThePrefixesTheUpstreamLexerUses(
        SqliteTerminalRealizer $realizer,
    ): void {
        self::assertSame(['users', ['ID']], $realizer->realizeWitnessed('TERMINAL'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testSupportsFollowsTheCatalogAndTheStrictOption(SqliteTerminalRealizer $realizer): void
    {
        self::assertTrue($realizer->supports('TERMINAL'));
        self::assertTrue($realizer->supports(SqliteTerminalRealizer::STRICT_TABLE_OPTION));
        self::assertFalse($realizer->supports('NOT_A_TERMINAL'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeRequestedRejectsALexemeTheCatalogDoesNotWitness(
        SqliteTerminalRealizer $realizer,
    ): void {
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('SQLite lexical catalog has no TERMINAL witness for: other');

        $realizer->realizeRequested('TERMINAL', 'other');
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeFixedPrefersASpellingTheProfileLists(SqliteTerminalRealizer $realizer): void
    {
        self::assertSame(['SELECT', ['SELECT']], $realizer->realizeFixed('SELECT'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testTriviaReplaysAWitnessedSeparator(SqliteTerminalRealizer $realizer): void
    {
        self::assertSame(' ', $realizer->trivia());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testSupportsAcceptsAnythingOnceSyntheticWritingIsAllowed(
        SqliteTerminalRealizer $realizer,
    ): void {
        self::assertTrue($realizer->supports('NOT_A_TERMINAL'));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeSyntheticWritesTheOperatorTerminals(SqliteTerminalRealizer $realizer): void
    {
        self::assertSame(['(', ['LP']], $realizer->realizeSynthetic('LP'));
        self::assertSame(['||', ['CONCAT']], $realizer->realizeSynthetic('CONCAT'));
        self::assertSame(['->', ['PTR']], $realizer->realizeSynthetic('PTR'));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeSyntheticWritesTheSeparatedNumberLiteral(SqliteTerminalRealizer $realizer): void
    {
        self::assertSame(['1_0', ['QNUMBER']], $realizer->realizeSynthetic('QNUMBER'));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeRequestedAcceptsALexemeThatReadsBackAsTheTerminal(
        SqliteTerminalRealizer $realizer,
    ): void {
        self::assertSame(['(', ['LP']], $realizer->realizeRequested('LP', '('));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeRequestedRejectsALexemeThatReadsBackAsSomethingElse(
        SqliteTerminalRealizer $realizer,
    ): void {
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Requested SQLite lexeme does not realize LP: users');

        $realizer->realizeRequested('LP', 'users');
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testIdentifierUsesOneOfTheFourSpellings(SqliteTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression(
            '/^(?:".*"|`.*`|\[.*\]|[A-Za-z_][A-Za-z0-9_]*)$/s',
            $realizer->identifier(),
        );
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testStringLiteralIsWrappedInSingleQuotes(SqliteTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression("/^'.*'$/s", $realizer->stringLiteral());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testBlobLiteralTakesWholeBytes(SqliteTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression("/^X'(?:[0-9a-fA-F]{2})*'$/", $realizer->blobLiteral());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testParameterUsesOneOfTheFiveSigils(SqliteTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression('/^(?:\?[0-9]*|[:@$][A-Za-z0-9_]+)$/', $realizer->parameter());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testTriviaIsASingleSpaceWhenNothingIsWitnessed(SqliteTerminalRealizer $realizer): void
    {
        self::assertSame(' ', $realizer->trivia());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testOptionalTriviaIsNothingWhenNothingIsWitnessed(SqliteTerminalRealizer $realizer): void
    {
        self::assertSame('', $realizer->optionalTrivia());
    }

    /**
     * @return iterable<string, array{SqliteTerminalRealizer}>
     */
    public static function providerWitnessedRealizer(): iterable
    {
        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'TERMINAL' => [[
                    'id' => 'terminal.bare',
                    'sql' => 'users',
                    'tokens' => ['TK_ID'],
                    'units' => ['identifier'],
                ]],
                '@TRIVIA' => [[
                    'id' => 'trivia.space',
                    'sql' => ' ',
                    'tokens' => [],
                    'units' => ['trivia'],
                ]],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier', 'trivia'],
                'witnessed' => ['identifier' => 'terminal.bare', 'trivia' => 'trivia.space'],
                'excluded' => [],
            ],
        ];

        yield 'catalogued only' => [new SqliteTerminalRealizer(
            Factory::create(),
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            false,
        )];
    }

    /**
     * @return iterable<string, array{SqliteTerminalRealizer}>
     */
    public static function providerSyntheticRealizer(): iterable
    {
        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        yield 'synthetic allowed' => [new SqliteTerminalRealizer(
            Factory::create(),
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        )];
    }

    #[DataProvider('providerOperatorTerminal')]
    public function testRealizeSyntheticWritesEveryOperatorAsItsOwnPunctuation(
        SqliteTerminalRealizer $realizer,
        string $terminal,
        string $punctuation,
    ): void {
        self::assertSame([$punctuation, [$terminal]], $realizer->realizeSynthetic($terminal));
    }

    /**
     * @return iterable<string, array{SqliteTerminalRealizer, string, string}>
     */
    public static function providerOperatorTerminal(): iterable
    {
        $realizer = new SqliteTerminalRealizer(
            Factory::create(),
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        $operators = [
            'LP' => '(', 'RP' => ')', 'SEMI' => ';', 'COMMA' => ',', 'DOT' => '.',
            'EQ' => '=', 'LT' => '<', 'LE' => '<=', 'GT' => '>', 'GE' => '>=', 'NE' => '<>',
            'PLUS' => '+', 'MINUS' => '-', 'STAR' => '*', 'SLASH' => '/', 'REM' => '%',
            'BITAND' => '&', 'BITOR' => '|', 'BITNOT' => '~', 'LSHIFT' => '<<', 'RSHIFT' => '>>',
            'CONCAT' => '||', 'PTR' => '->',
        ];
        foreach ($operators as $terminal => $punctuation) {
            yield $terminal => [$realizer, $terminal, $punctuation];
        }
    }

    #[DataProvider('providerSyntheticTerminal')]
    public function testRealizeSyntheticWritesEveryNamedTerminalAsItsOwnKindOfToken(
        SqliteTerminalRealizer $realizer,
        string $terminal,
        string $token,
    ): void {
        self::assertSame([$token], $realizer->realizeSynthetic($terminal)[1]);
    }

    /**
     * @return iterable<string, array{SqliteTerminalRealizer, string, string}>
     */
    public static function providerSyntheticTerminal(): iterable
    {
        $realizer = new SqliteTerminalRealizer(
            Factory::create(),
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        $terminals = [
            'ID' => 'ID', 'id' => 'ID', 'idj' => 'ID',
            'ids' => 'STRING', 'STRING' => 'STRING',
            'BLOB' => 'BLOB',
            'number' => 'INTEGER', 'INTEGER' => 'INTEGER',
            'FLOAT' => 'FLOAT',
            'QNUMBER' => 'QNUMBER',
            'VARIABLE' => 'VARIABLE',
            'ANY' => 'ID',
        ];
        foreach ($terminals as $terminal => $token) {
            yield $terminal => [$realizer, $terminal, $token];
        }
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeSyntheticWritesTheOneNumberSqliteWillNotLexBackAsItself(
        SqliteTerminalRealizer $realizer,
    ): void {
        self::assertSame(['1_0', ['QNUMBER']], $realizer->realizeSynthetic('QNUMBER'));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeSyntheticWritesTheWildcardAsAnIdentifierNoKeywordCollidesWith(
        SqliteTerminalRealizer $realizer,
    ): void {
        self::assertSame(['_any', ['ID']], $realizer->realizeSynthetic('ANY'));
    }
}
