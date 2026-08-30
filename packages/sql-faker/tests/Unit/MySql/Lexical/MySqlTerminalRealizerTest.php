<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Lexical;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalCatalog;
use SqlFaker\Grammar\Lexical\LexicalCatalogShape;
use SqlFaker\Grammar\Lexical\LexicalCoverageCheck;
use SqlFaker\Grammar\Lexical\LexicalException;
use SqlFaker\Grammar\Lexical\LexicalWitnessCheck;
use SqlFaker\Grammar\Lexical\LexicalWitnessShape;
use SqlFaker\Grammar\Lexical\RandomCharacters;
use SqlFaker\Grammar\Lexical\RandomStringGenerator;
use SqlFaker\MySql\Lexical\MySqlTerminalRealizer;
use SqlFaker\MySql\Lexical\MySqlTokenizer;
use SqlFaker\MySql\Lexical\MySqlTrivia;
use Tests\Fixture\SqlFaker\MySqlRealizers;
use Tests\Fixture\SqlFaker\ScriptedNumbers;

#[CoversClass(MySqlTerminalRealizer::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(LexicalCatalogShape::class)]
#[UsesClass(LexicalCoverageCheck::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(LexicalWitnessCheck::class)]
#[UsesClass(LexicalWitnessShape::class)]
#[UsesClass(MySqlTokenizer::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(RandomCharacters::class)]
#[UsesClass(MySqlTrivia::class)]
final class MySqlTerminalRealizerTest extends TestCase
{
    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeReplaysACataloguedExample(MySqlTerminalRealizer $realizer): void
    {
        self::assertSame(['users', ['IDENT']], $realizer->realize('IDENT'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeReportsATerminalTheCatalogDoesNotWitness(MySqlTerminalRealizer $realizer): void
    {
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported MySQL terminal for mysql-8.4.7: NOT_A_TERMINAL');

        $realizer->realize('NOT_A_TERMINAL');
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeAcceptsARequestedLexemeTheCatalogWitnesses(MySqlTerminalRealizer $realizer): void
    {
        self::assertSame(['users', ['IDENT']], $realizer->realize('IDENT', 'users'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeWitnessedReplaysTheExampleText(MySqlTerminalRealizer $realizer): void
    {
        self::assertSame(['users', ['IDENT']], $realizer->realizeWitnessed('IDENT'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testSupportsFollowsTheCatalog(MySqlTerminalRealizer $realizer): void
    {
        self::assertTrue($realizer->supports('IDENT'));
        self::assertFalse($realizer->supports('NOT_A_TERMINAL'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeRequestedRejectsALexemeTheCatalogDoesNotWitness(
        MySqlTerminalRealizer $realizer,
    ): void {
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('MySQL lexical catalog has no IDENT witness for: other');

        $realizer->realizeRequested('IDENT', 'other');
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeFixedPrefersASpellingTheProfileLists(MySqlTerminalRealizer $realizer): void
    {
        self::assertSame(['SELECT', ['SELECT_SYM']], $realizer->realizeFixed('SELECT_SYM'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testTriviaReplaysAWitnessedSeparator(MySqlTerminalRealizer $realizer): void
    {
        self::assertSame(' ', $realizer->trivia());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testSupportsAcceptsAnythingOnceSyntheticWritingIsAllowed(
        MySqlTerminalRealizer $realizer,
    ): void {
        self::assertTrue($realizer->supports('NOT_A_TERMINAL'));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeSkipsTheTerminalsThatStandForNoText(MySqlTerminalRealizer $realizer): void
    {
        self::assertSame([null, []], $realizer->realize('END_OF_INPUT'));
        self::assertSame([null, []], $realizer->realize('GRAMMAR_SELECTOR_EXPR'));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeSyntheticWritesTerminalsNoCatalogWitnesses(
        MySqlTerminalRealizer $realizer,
    ): void {
        self::assertSame(['?', ['PARAM_MARKER']], $realizer->realizeSynthetic('PARAM_MARKER'));
        self::assertSame(['||', ['OR2_SYM']], $realizer->realizeSynthetic('OR2_SYM'));
        self::assertSame(['_utf8mb4', ['UNDERSCORE_CHARSET']], $realizer->realizeSynthetic('UNDERSCORE_CHARSET'));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeRequestedAcceptsALexemeThatReadsBackAsTheTerminal(
        MySqlTerminalRealizer $realizer,
    ): void {
        self::assertSame(['?', ['PARAM_MARKER']], $realizer->realizeRequested('PARAM_MARKER', '?'));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeRequestedRejectsALexemeThatReadsBackAsSomethingElse(
        MySqlTerminalRealizer $realizer,
    ): void {
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Requested MySQL lexeme does not realize PARAM_MARKER: users');

        $realizer->realizeRequested('PARAM_MARKER', 'users');
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testIdentifierCannotCollideWithAKeyword(MySqlTerminalRealizer $realizer): void
    {
        self::assertStringStartsWith('_', $realizer->identifier());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testQuotedIdentifierIsWrappedInBackticks(MySqlTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression('/^`.*`$/', $realizer->quotedIdentifier());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testStringLiteralIsWrappedInSingleQuotes(MySqlTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression("/^'.*'$/s", $realizer->stringLiteral());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testDollarQuotedStringIsWrappedInDoubleDollars(MySqlTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression('/^\$\$.*\$\$$/s', $realizer->dollarQuotedString());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testHexadecimalLiteralUsesOneOfItsTwoSpellings(MySqlTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression(
            "/^(?:0x[0-9a-fA-F]*|X'[0-9a-fA-F]*')$/",
            $realizer->hexadecimalLiteral(),
        );
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testBinaryLiteralUsesOneOfItsTwoSpellings(MySqlTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression("/^(?:0b[01]*|B'[01]*')$/", $realizer->binaryLiteral());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testTriviaIsASingleSpaceWhenNothingIsWitnessed(MySqlTerminalRealizer $realizer): void
    {
        self::assertSame(' ', $realizer->trivia());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testOptionalTriviaIsNothingWhenNothingIsWitnessed(MySqlTerminalRealizer $realizer): void
    {
        self::assertSame('', $realizer->optionalTrivia());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testSyntheticSpellingWritesOperatorsByNameAndKeywordsBySuffix(
        MySqlTerminalRealizer $realizer,
    ): void {
        self::assertSame('=', $realizer->syntheticSpelling('EQ'));
        self::assertSame('<=>', $realizer->syntheticSpelling('EQUAL_SYM'));
        self::assertSame('->', $realizer->syntheticSpelling('JSON_SEPARATOR_SYM'));
        self::assertSame('SELECT', $realizer->syntheticSpelling('SELECT_SYM'));
        self::assertSame('IDENT', $realizer->syntheticSpelling('IDENT'));
    }

    /**
     * @return iterable<string, array{MySqlTerminalRealizer}>
     */
    public static function providerWitnessedRealizer(): iterable
    {
        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'IDENT' => [[
                    'id' => 'ident.bare',
                    'sql' => 'users',
                    'tokens' => ['IDENT'],
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
                'witnessed' => ['identifier' => 'ident.bare', 'trivia' => 'trivia.space'],
                'excluded' => [],
            ],
        ];

        yield 'catalogued only' => [new MySqlTerminalRealizer(
            Factory::create(),
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], false),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            false,
        )];
    }

    /**
     * @return iterable<string, array{MySqlTerminalRealizer}>
     */
    public static function providerSyntheticRealizer(): iterable
    {
        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        yield 'synthetic allowed' => [new MySqlTerminalRealizer(
            Factory::create(),
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        )];
    }

    #[DataProvider('providerSyntheticTerminal')]
    public function testRealizeSyntheticWritesEveryNamedTerminalAsItsOwnKindOfToken(
        MySqlTerminalRealizer $realizer,
        string $terminal,
        string $token,
    ): void {
        self::assertSame([$token], $realizer->realizeSynthetic($terminal)[1]);
    }

    /**
     * @return iterable<string, array{MySqlTerminalRealizer, string, string}>
     */
    public static function providerSyntheticTerminal(): iterable
    {
        $realizer = new MySqlTerminalRealizer(
            Factory::create(),
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        $terminals = [
            'IDENT' => 'IDENT',
            'IDENT_QUOTED' => 'IDENT_QUOTED',
            'TEXT_STRING' => 'TEXT_STRING',
            'NCHAR_STRING' => 'NCHAR_STRING',
            'DOLLAR_QUOTED_STRING_SYM' => 'DOLLAR_QUOTED_STRING_SYM',
            'NUM' => 'NUM',
            'LONG_NUM' => 'LONG_NUM',
            'ULONGLONG_NUM' => 'ULONGLONG_NUM',
            'DECIMAL_NUM' => 'DECIMAL_NUM',
            'FLOAT_NUM' => 'FLOAT_NUM',
            'HEX_NUM' => 'HEX_NUM',
            'BIN_NUM' => 'BIN_NUM',
            'LEX_HOSTNAME' => 'IDENT',
            'PARAM_MARKER' => 'PARAM_MARKER',
            'OR2_SYM' => 'OR2_SYM',
            'WITH_ROLLUP_SYM' => 'WITH_ROLLUP_SYM',
            'UNDERSCORE_CHARSET' => 'UNDERSCORE_CHARSET',
        ];
        foreach ($terminals as $terminal => $token) {
            yield $terminal => [$realizer, $terminal, $token];
        }
    }

    #[DataProvider('providerFixedSyntheticTerminal')]
    public function testRealizeSyntheticWritesEveryFixedTerminalAsItsOneSpelling(
        MySqlTerminalRealizer $realizer,
        string $terminal,
        string $lexeme,
    ): void {
        self::assertSame($lexeme, $realizer->realizeSynthetic($terminal)[0]);
    }

    /**
     * @return iterable<string, array{MySqlTerminalRealizer, string, string}>
     */
    public static function providerFixedSyntheticTerminal(): iterable
    {
        $realizer = new MySqlTerminalRealizer(
            Factory::create(),
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        $spellings = [
            'ULONGLONG_NUM' => '18446744073709551615',
            'LEX_HOSTNAME' => 'localhost',
            'PARAM_MARKER' => '?',
            'OR2_SYM' => '||',
            'WITH_ROLLUP_SYM' => 'WITH ROLLUP',
            'UNDERSCORE_CHARSET' => '_utf8mb4',
        ];
        foreach ($spellings as $terminal => $lexeme) {
            yield $terminal => [$realizer, $terminal, $lexeme];
        }
    }

    #[DataProvider('providerSyntheticSpelling')]
    public function testSyntheticSpellingWritesTheOperatorATerminalStandsFor(
        string $terminal,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            MySqlRealizers::synthetic(ScriptedNumbers::answering())->syntheticSpelling($terminal),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerSyntheticSpelling(): iterable
    {
        yield 'EQ' => ['EQ', '='];
        yield 'EQUAL_SYM' => ['EQUAL_SYM', '<=>'];
        yield 'LT' => ['LT', '<'];
        yield 'GT_SYM' => ['GT_SYM', '>'];
        yield 'LE' => ['LE', '<='];
        yield 'GE' => ['GE', '>='];
        yield 'NE' => ['NE', '<>'];
        yield 'SHIFT_LEFT' => ['SHIFT_LEFT', '<<'];
        yield 'SHIFT_RIGHT' => ['SHIFT_RIGHT', '>>'];
        yield 'AND_AND_SYM' => ['AND_AND_SYM', '&&'];
        yield 'OR_OR_SYM' => ['OR_OR_SYM', '||'];
        yield 'OR2_SYM' => ['OR2_SYM', '||'];
        yield 'NOT2_SYM' => ['NOT2_SYM', 'NOT'];
        yield 'SET_VAR' => ['SET_VAR', ':='];
        yield 'JSON_SEPARATOR_SYM' => ['JSON_SEPARATOR_SYM', '->'];
        yield 'JSON_UNQUOTED_SEPARATOR_SYM' => ['JSON_UNQUOTED_SEPARATOR_SYM', '->>'];
        yield 'NEG' => ['NEG', '-'];
        yield 'a keyword terminal drops its suffix' => ['SELECT_SYM', 'SELECT'];
        yield 'a terminal without the suffix stands for itself' => ['IDENT', 'IDENT'];
    }

    public function testRealizeFixedFallsBackToTheTerminalItselfWhereNothingSpellsIt(): void
    {
        $faker = ScriptedNumbers::answering();

        self::assertSame('OTHER', MySqlRealizers::witnessed($faker)->realizeFixed('OTHER')[0]);
        self::assertSame([], $faker->numberBetweenCalls);
    }

    public function testRealizeFixedWritesTheSpellingATerminalNameStandsForWhereSyntheticIsAllowed(): void
    {
        $faker = ScriptedNumbers::answering();

        self::assertSame(['||', ['OR2_SYM']], MySqlRealizers::synthetic($faker)->realizeFixed('OR_OR_SYM'));
        self::assertSame([], $faker->numberBetweenCalls);
    }

    public function testRealizeFixedChoosesFromEverySpellingAndNoFurther(): void
    {
        $faker = ScriptedNumbers::answering(1);

        $realized = MySqlRealizers::witnessed($faker)->realizeFixed('SELECT_SYM');

        self::assertSame(['select', ['SELECT_SYM']], $realized);
        self::assertSame([[0, 1]], $faker->numberBetweenCalls);
    }

    public function testRealizeFixedReadsAFunctionSpellingTheSameWayAsASymbolOne(): void
    {
        $faker = ScriptedNumbers::answering(0);

        self::assertSame(['COUNT', ['COUNT_SYM']], MySqlRealizers::witnessed($faker)->realizeFixed('COUNT_SYM'));
    }

    public function testIdentifierIsKeptFromCollidingWithAKeyword(): void
    {
        self::assertStringStartsWith('_', MySqlRealizers::synthetic(ScriptedNumbers::answering())->identifier());
    }

    public function testQuotedIdentifierWritesAKeywordOnOneOfFourDraws(): void
    {
        $faker = ScriptedNumbers::answering(0, 1);

        self::assertSame('`select`', MySqlRealizers::synthetic($faker)->quotedIdentifier());
        self::assertSame([[0, 3], [0, 7]], $faker->numberBetweenCalls);
    }

    public function testQuotedIdentifierDoublesABacktickWrittenIntoTheBody(): void
    {
        $faker = ScriptedNumbers::answering(0, 0);

        $identifier = MySqlRealizers::synthetic($faker)->quotedIdentifier();

        self::assertStringStartsWith('`select``', $identifier);
        self::assertSame([[0, 3], [0, 7]], array_slice($faker->numberBetweenCalls, 0, 2));
    }

    public function testQuotedIdentifierIsAnOrdinaryIdentifierOnEveryOtherDraw(): void
    {
        $faker = ScriptedNumbers::answering(1, 1);

        self::assertStringStartsWith('`_', MySqlRealizers::synthetic($faker)->quotedIdentifier());
    }

    public function testStringLiteralDoublesAQuoteInsideTheBody(): void
    {
        $faker = ScriptedNumbers::answering(2);

        self::assertSame("'a''b'", MySqlRealizers::synthetic($faker)->stringLiteral());
        self::assertSame([[0, 6]], $faker->numberBetweenCalls);
    }

    public function testStringLiteralWritesABackslashOnItsFourthDraw(): void
    {
        $faker = ScriptedNumbers::answering(3);

        self::assertSame("'a\\b'", MySqlRealizers::synthetic($faker)->stringLiteral());
    }

    #[DataProvider('providerLexicalSequenceDraw')]
    public function testStringLiteralWritesALexicalSequenceOnItsFirstTwoDraws(int $draw): void
    {
        $literal = MySqlRealizers::synthetic(ScriptedNumbers::answering($draw))->stringLiteral();

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

    public function testDollarQuotedStringIsWrittenBetweenBareDollarPairs(): void
    {
        $literal = MySqlRealizers::synthetic(ScriptedNumbers::answering())->dollarQuotedString();

        self::assertStringStartsWith('$$', $literal);
        self::assertStringEndsWith('$$', $literal);
    }

    public function testHexadecimalLiteralIsWrittenWithThePrefixOnOneOfTwoDraws(): void
    {
        $faker = ScriptedNumbers::answering(0);

        self::assertStringStartsWith('0x', MySqlRealizers::synthetic($faker)->hexadecimalLiteral());
        self::assertSame([0, 1], $faker->numberBetweenCalls[0]);
    }

    public function testHexadecimalLiteralIsWrittenQuotedOnEveryOtherDraw(): void
    {
        $faker = ScriptedNumbers::answering(1, 2);

        $literal = MySqlRealizers::synthetic($faker)->hexadecimalLiteral();

        self::assertSame(4, strlen($literal) - 3);
        self::assertStringStartsWith("X'", $literal);
        self::assertSame([0, 8], $faker->numberBetweenCalls[1]);
    }

    public function testBinaryLiteralIsWrittenWithThePrefixOnOneOfTwoDraws(): void
    {
        $faker = ScriptedNumbers::answering(0);

        self::assertStringStartsWith('0b', MySqlRealizers::synthetic($faker)->binaryLiteral());
        self::assertSame([0, 1], $faker->numberBetweenCalls[0]);
    }

    public function testBinaryLiteralIsWrittenQuotedOnEveryOtherDraw(): void
    {
        $faker = ScriptedNumbers::answering(1);

        self::assertStringStartsWith("B'", MySqlRealizers::synthetic($faker)->binaryLiteral());
    }

    public function testRealizeWitnessedChoosesFromEveryWitnessAndNoFurther(): void
    {
        $faker = ScriptedNumbers::answering(1);

        self::assertSame(['orders', ['IDENT']], MySqlRealizers::witnessed($faker)->realizeWitnessed('IDENT'));
        self::assertSame([[0, 1]], $faker->numberBetweenCalls);
    }

    public function testTriviaIsASpaceWhereTerminalsMayBeWrittenWithoutAWitness(): void
    {
        $faker = ScriptedNumbers::answering();

        self::assertSame(' ', MySqlRealizers::synthetic($faker)->trivia());
        self::assertSame([], $faker->numberBetweenCalls);
    }

    public function testTriviaIsChosenFromEveryWitnessAndNoFurther(): void
    {
        $faker = ScriptedNumbers::answering(1);

        self::assertSame('/* c */', MySqlRealizers::witnessed($faker)->trivia());
        self::assertSame([[0, 1]], $faker->numberBetweenCalls);
    }

    public function testOptionalTriviaIsNothingWhereTerminalsMayBeWrittenWithoutAWitness(): void
    {
        $faker = ScriptedNumbers::answering();

        self::assertSame('', MySqlRealizers::synthetic($faker)->optionalTrivia());
        self::assertSame([], $faker->numberBetweenCalls);
    }

    public function testOptionalTriviaIsNothingOnOneOfTwoDraws(): void
    {
        $faker = ScriptedNumbers::answering(0);

        self::assertSame('', MySqlRealizers::witnessed($faker)->optionalTrivia());
        self::assertSame([[0, 1]], $faker->numberBetweenCalls);
    }

    public function testOptionalTriviaIsTriviaOnEveryOtherDraw(): void
    {
        $faker = ScriptedNumbers::answering(1, 0);

        self::assertSame(' ', MySqlRealizers::witnessed($faker)->optionalTrivia());
    }
    #[DataProvider('providerSyntheticTerminalAndSpelling')]
    public function testRealizeSyntheticWritesEveryNamedTerminalInItsOwnSpelling(string $terminal, string $pattern): void
    {
        self::assertMatchesRegularExpression($pattern, (string) self::providerSeededRealizer()->realizeSynthetic($terminal)[0]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerSyntheticTerminalAndSpelling(): iterable
    {
        yield 'IDENT' => ['IDENT', '/^_\w+$/'];
        yield 'IDENT_QUOTED' => ['IDENT_QUOTED', '/^`.*`$/s'];
        yield 'TEXT_STRING' => ['TEXT_STRING', "/^'.*'\$/s"];
        yield 'NCHAR_STRING' => ['NCHAR_STRING', "/^N'.*'\$/s"];
        yield 'DOLLAR_QUOTED_STRING_SYM' => ['DOLLAR_QUOTED_STRING_SYM', '/^\$\$.*\$\$$/s'];
        yield 'NUM' => ['NUM', '/^\d+$/'];
        yield 'LONG_NUM' => ['LONG_NUM', '/^\d{10,}$/'];
        yield 'ULONGLONG_NUM' => ['ULONGLONG_NUM', '/^18446744073709551615$/'];
        yield 'DECIMAL_NUM' => ['DECIMAL_NUM', '/^[\d.]+$/'];
        yield 'HEX_NUM' => ['HEX_NUM', "/^(0x[0-9a-fA-F]*|X'[0-9a-fA-F]*')\$/"];
    }

    public function testALongNumberIsTooWideForAThirtyTwoBitInteger(): void
    {
        $written = (string) self::providerSeededRealizer()->realizeSynthetic('LONG_NUM')[0];

        self::assertGreaterThan(2147483647, (int) $written);
    }

    public function testAQuotedIdentifierDoublesEveryBacktickItCarries(): void
    {
        $realizer = self::providerSeededRealizer();
        $malformed = array_values(array_filter(
            array_map(static fn (int $draw): string => $realizer->quotedIdentifier(), range(1, 200)),
            static fn (string $written): bool => preg_match('/^`([^`]|``)*`$/s', $written) !== 1,
        ));

        self::assertSame([], $malformed);
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

    public function testADollarQuotedStringIsWrappedInDoubledDollars(): void
    {
        self::assertMatchesRegularExpression('/^\$\$\w{0,24}\$\$$/', self::providerSeededRealizer()->dollarQuotedString());
    }

    public function testAHexadecimalLiteralIsWrittenInOneOfTheTwoSpellingsMysqlReads(): void
    {
        $realizer = self::providerSeededRealizer();
        $spellings = array_map(
            static fn (int $draw): string => str_starts_with($realizer->hexadecimalLiteral(), '0x') ? '0x' : "X'",
            range(1, 200),
        );
        sort($spellings);

        self::assertSame(['0x', "X'"], array_values(array_unique($spellings)));
    }

    public function testAHexadecimalLiteralInQuotesCarriesAnEvenNumberOfDigits(): void
    {
        $realizer = self::providerSeededRealizer();
        $odd = array_values(array_filter(
            array_map(static fn (int $draw): string => $realizer->hexadecimalLiteral(), range(1, 200)),
            static fn (string $written): bool => str_starts_with($written, "X'")
                && strlen($written) % 2 !== 1,
        ));

        self::assertSame([], $odd);
    }

    public function testABinaryLiteralIsWrittenInOneOfTheTwoSpellingsMysqlReads(): void
    {
        $realizer = self::providerSeededRealizer();
        $spellings = array_map(
            static fn (int $draw): string => str_starts_with($realizer->binaryLiteral(), '0b') ? '0b' : "B'",
            range(1, 200),
        );
        sort($spellings);

        self::assertSame(['0b', "B'"], array_values(array_unique($spellings)));
    }

    /**
     * @return MySqlTerminalRealizer A realizer that writes every terminal from its name, drawing the same way each run
     */
    public static function providerSeededRealizer(): MySqlTerminalRealizer
    {
        $faker = Factory::create();
        $faker->seed(20260827);

        return new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );
    }
}
