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
use Tests\Fixture\SqlFaker\ScriptedNumbers;
use Tests\Fixture\SqlFaker\SqliteRealizers;

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

    public function testRealizeWitnessedChoosesFromEveryWitnessAndNoFurther(): void
    {
        $faker = ScriptedNumbers::answering(1);

        self::assertSame(['orders', ['ID']], SqliteRealizers::witnessed($faker)->realizeWitnessed('ID'));
        self::assertSame([[0, 1]], $faker->numberBetweenCalls);
    }

    public function testRealizeFixedChoosesFromEverySpellingAndNoFurther(): void
    {
        $faker = ScriptedNumbers::answering(1);

        self::assertSame(['select', ['SELECT']], SqliteRealizers::synthetic($faker)->realizeFixed('SELECT'));
        self::assertSame([[0, 1]], $faker->numberBetweenCalls);
    }

    public function testRealizeFixedFallsBackToTheTerminalItselfWhereNothingSpellsIt(): void
    {
        $faker = ScriptedNumbers::answering();

        self::assertSame('OTHER', SqliteRealizers::synthetic($faker)->realizeFixed('OTHER')[0]);
        self::assertSame([], $faker->numberBetweenCalls);
    }

    public function testIdentifierWritesAKeywordBodyOnOneOfFourDraws(): void
    {
        $faker = ScriptedNumbers::answering(0, 3);

        self::assertSame('select', SqliteRealizers::synthetic($faker)->identifier());
        self::assertSame([[0, 3], [0, 7]], $faker->numberBetweenCalls);
    }

    public function testIdentifierIsDoubleQuotedOnTheFirstOfEightDraws(): void
    {
        $faker = ScriptedNumbers::answering(0, 0);

        self::assertSame('"select""quoted"', SqliteRealizers::synthetic($faker)->identifier());
    }

    public function testIdentifierIsBacktickQuotedOnTheSecondOfEightDraws(): void
    {
        $faker = ScriptedNumbers::answering(0, 1);

        self::assertSame('`select``quoted`', SqliteRealizers::synthetic($faker)->identifier());
    }

    public function testIdentifierIsBracketQuotedOnTheThirdOfEightDraws(): void
    {
        $faker = ScriptedNumbers::answering(0, 2);

        self::assertSame('[selectquoted]', SqliteRealizers::synthetic($faker)->identifier());
    }

    public function testIdentifierWritesAGeneratedBodyOnEveryOtherDraw(): void
    {
        $faker = ScriptedNumbers::answering(1);

        self::assertStringContainsString('_', SqliteRealizers::synthetic($faker)->identifier());
        self::assertSame([0, 3], $faker->numberBetweenCalls[0]);
    }

    public function testIdentifierIsUnquotedOnEveryDrawPastTheThird(): void
    {
        $faker = ScriptedNumbers::answering(0, 3);

        self::assertSame('select', SqliteRealizers::synthetic($faker)->identifier());
        self::assertSame([[0, 3], [0, 7]], $faker->numberBetweenCalls);
    }

    public function testStringLiteralDoublesAQuoteInsideTheBody(): void
    {
        $faker = ScriptedNumbers::answering(2);

        self::assertSame("'a''b'", SqliteRealizers::synthetic($faker)->stringLiteral());
        self::assertSame([[0, 5]], $faker->numberBetweenCalls);
    }

    public function testStringLiteralWritesABackslashOnItsFourthDraw(): void
    {
        $faker = ScriptedNumbers::answering(3);

        self::assertSame("'a\\b'", SqliteRealizers::synthetic($faker)->stringLiteral());
    }

    #[DataProvider('providerLexicalSequenceDraw')]
    public function testStringLiteralWritesALexicalSequenceOnItsFirstTwoDraws(int $draw): void
    {
        $literal = SqliteRealizers::synthetic(ScriptedNumbers::answering($draw))->stringLiteral();

        self::assertStringStartsWith("'", $literal);
        self::assertStringEndsWith("'", $literal);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerLexicalSequenceDraw(): iterable
    {
        yield 'first draw' => [0];
        yield 'second draw' => [1];
    }

    public function testBlobLiteralTakesAWholeNumberOfBytes(): void
    {
        $faker = ScriptedNumbers::answering(3);

        $literal = SqliteRealizers::synthetic($faker)->blobLiteral();

        self::assertSame(6, strlen($literal) - 3);
        self::assertSame([0, 8], $faker->numberBetweenCalls[0]);
    }

    public function testParameterIsWrittenBareOnTheFirstOfFiveDraws(): void
    {
        $faker = ScriptedNumbers::answering(0);

        self::assertSame('?', SqliteRealizers::synthetic($faker)->parameter());
        self::assertSame([[0, 4]], $faker->numberBetweenCalls);
    }

    public function testParameterIsNumberedOnTheSecondOfFiveDraws(): void
    {
        $faker = ScriptedNumbers::answering(1, 7);

        self::assertSame('?7', SqliteRealizers::synthetic($faker)->parameter());
        self::assertSame([[0, 4], [1, 10]], $faker->numberBetweenCalls);
    }

    public function testParameterIsColonNamedOnTheThirdOfFiveDraws(): void
    {
        $faker = ScriptedNumbers::answering(2);

        self::assertStringStartsWith(':', SqliteRealizers::synthetic($faker)->parameter());
    }

    public function testParameterIsAtNamedOnTheFourthOfFiveDraws(): void
    {
        $faker = ScriptedNumbers::answering(3);

        self::assertStringStartsWith('@', SqliteRealizers::synthetic($faker)->parameter());
    }

    public function testParameterIsDollarNamedOnEveryOtherDraw(): void
    {
        $faker = ScriptedNumbers::answering(4);

        self::assertStringStartsWith('$', SqliteRealizers::synthetic($faker)->parameter());
    }

    public function testTriviaIsASpaceWhereTerminalsMayBeWrittenWithoutAWitness(): void
    {
        $faker = ScriptedNumbers::answering();

        self::assertSame(' ', SqliteRealizers::synthetic($faker)->trivia());
        self::assertSame([], $faker->numberBetweenCalls);
    }

    public function testTriviaIsChosenFromEveryWitnessAndNoFurther(): void
    {
        $faker = ScriptedNumbers::answering(1);

        self::assertSame('/* c */', SqliteRealizers::witnessed($faker)->trivia());
        self::assertSame([[0, 1]], $faker->numberBetweenCalls);
    }

    public function testOptionalTriviaIsNothingWhereTerminalsMayBeWrittenWithoutAWitness(): void
    {
        $faker = ScriptedNumbers::answering();

        self::assertSame('', SqliteRealizers::synthetic($faker)->optionalTrivia());
        self::assertSame([], $faker->numberBetweenCalls);
    }

    public function testOptionalTriviaIsNothingOnOneOfTwoDraws(): void
    {
        $faker = ScriptedNumbers::answering(0);

        self::assertSame('', SqliteRealizers::witnessed($faker)->optionalTrivia());
        self::assertSame([[0, 1]], $faker->numberBetweenCalls);
    }

    public function testOptionalTriviaIsTriviaOnEveryOtherDraw(): void
    {
        $faker = ScriptedNumbers::answering(1, 0);

        self::assertSame(' ', SqliteRealizers::witnessed($faker)->optionalTrivia());
    }
    #[DataProvider('providerSyntheticTerminalAndSpelling')]
    public function testRealizeSyntheticWritesEveryNamedTerminalInItsOwnSpelling(string $terminal, string $pattern): void
    {
        self::assertMatchesRegularExpression($pattern, self::providerSeededRealizer()->realizeSynthetic($terminal)[0]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerSyntheticTerminalAndSpelling(): iterable
    {
        yield 'BLOB' => ['BLOB', "/^X'[0-9a-fA-F]*'\$/"];
        yield 'number' => ['number', '/^\d+$/'];
        yield 'INTEGER' => ['INTEGER', '/^\d+$/'];
        yield 'FLOAT' => ['FLOAT', '/^[\d.]+$/'];
        yield 'QNUMBER' => ['QNUMBER', '/^1_0$/'];
        yield 'ANY' => ['ANY', '/^_any$/'];
        yield 'VARIABLE' => ['VARIABLE', '/^[?:@$]/'];
    }

    public function testABlobLiteralCarriesAnEvenNumberOfDigits(): void
    {
        $realizer = self::providerSeededRealizer();
        $odd = array_values(array_filter(
            array_map(static fn (int $draw): string => $realizer->blobLiteral(), range(1, 200)),
            static fn (string $written): bool => strlen($written) % 2 !== 1,
        ));

        self::assertSame([], $odd);
    }

    public function testAStringLiteralDoublesEveryQuoteItCarries(): void
    {
        $realizer = self::providerSeededRealizer();
        $malformed = array_values(array_filter(
            array_map(static fn (int $draw): string => $realizer->stringLiteral(), range(1, 200)),
            static fn (string $written): bool => preg_match("/^'([^']|'')*'\$/s", $written) !== 1,
        ));

        self::assertSame([], $malformed);
    }

    public function testAParameterIsWrittenInEverySpellingSqliteReads(): void
    {
        $realizer = self::providerSeededRealizer();
        $marks = array_map(
            static fn (int $draw): string => substr($realizer->parameter(), 0, 1),
            range(1, 300),
        );
        sort($marks);

        self::assertSame(['$', ':', '?', '@'], array_values(array_unique($marks)));
    }

    public function testANumberedParameterIsNumberedFromOneToTen(): void
    {
        $realizer = self::providerSeededRealizer();
        $numbers = array_values(array_filter(array_map(
            static fn (int $draw): string => $realizer->parameter(),
            range(1, 600),
        ), static fn (string $written): bool => preg_match('/^\?\d+$/', $written) === 1));
        $values = array_map(static fn (string $written): int => (int) substr($written, 1), $numbers);
        sort($values);

        self::assertSame(range(1, 10), array_values(array_unique($values)));
    }

    public function testAnIdentifierIsWrittenInEverySpellingSqliteQuotesWith(): void
    {
        $realizer = self::providerSeededRealizer();
        $quotes = array_map(
            static fn (int $draw): string => substr($realizer->identifier(), 0, 1),
            range(1, 300),
        );
        sort($quotes);

        self::assertSame(['"', '[', '_', '`', 's'], array_values(array_unique($quotes)));
    }

    /**
     * @return SqliteTerminalRealizer A realizer that writes every terminal from its name, drawing the same way each run
     */
    public static function providerSeededRealizer(): SqliteTerminalRealizer
    {
        $faker = Factory::create();
        $faker->seed(20260827);

        return new SqliteTerminalRealizer(
            $faker,
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
    }
}
