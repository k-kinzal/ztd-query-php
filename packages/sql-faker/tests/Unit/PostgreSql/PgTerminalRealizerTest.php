<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

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
use SqlFaker\PostgreSql\PgLookahead;
use SqlFaker\PostgreSql\PgTerminalRealizer;
use SqlFaker\PostgreSql\PgTokenizer;
use Tests\Fixture\SqlFaker\PgRealizers;
use Tests\Fixture\SqlFaker\ScriptedNumbers;

#[CoversClass(PgTerminalRealizer::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(LexicalCatalogShape::class)]
#[UsesClass(LexicalCoverageCheck::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(LexicalWitnessCheck::class)]
#[UsesClass(LexicalWitnessShape::class)]
#[UsesClass(PgLookahead::class)]
#[UsesClass(PgTokenizer::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(RandomCharacters::class)]
#[UsesClass(PgRealizers::class)]
#[UsesClass(ScriptedNumbers::class)]
final class PgTerminalRealizerTest extends TestCase
{
    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeReplaysACataloguedExample(PgTerminalRealizer $realizer): void
    {
        self::assertSame(['users', ['TOKENS']], $realizer->realize('TERMINAL'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeReportsATerminalTheCatalogDoesNotWitness(PgTerminalRealizer $realizer): void
    {
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported PostgreSQL terminal for pg-17.2: NOT_A_TERMINAL');

        $realizer->realize('NOT_A_TERMINAL');
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeWitnessedReplaysTheExampleText(PgTerminalRealizer $realizer): void
    {
        self::assertSame(['users', ['TOKENS']], $realizer->realizeWitnessed('TERMINAL'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testSupportsFollowsTheCatalog(PgTerminalRealizer $realizer): void
    {
        self::assertTrue($realizer->supports('TERMINAL'));
        self::assertFalse($realizer->supports('NOT_A_TERMINAL'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeRequestedRejectsALexemeTheCatalogDoesNotWitness(PgTerminalRealizer $realizer): void
    {
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('PostgreSQL lexical catalog has no TERMINAL witness for: other');

        $realizer->realizeRequested('TERMINAL', 'other');
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeFixedPrefersASpellingTheProfileLists(PgTerminalRealizer $realizer): void
    {
        self::assertSame(['SELECT', ['SELECT']], $realizer->realizeFixed('SELECT'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeFixedFollowsALookaheadSubstitutionBackToItsKeyword(
        PgTerminalRealizer $realizer,
    ): void {
        self::assertSame(['NOT', ['NOT_LA']], $realizer->realizeFixed('NOT_LA'));
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testRealizeFixedDropsTheSuffixOfATerminalTheProfileDoesNotSpell(
        PgTerminalRealizer $realizer,
    ): void {
        self::assertSame('users', $realizer->realizeFixed('users_P')[0]);
    }

    #[DataProvider('providerWitnessedRealizer')]
    public function testTriviaReplaysAWitnessedSeparator(PgTerminalRealizer $realizer): void
    {
        self::assertSame(' ', $realizer->trivia());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testSupportsAcceptsAnythingOnceSyntheticWritingIsAllowed(PgTerminalRealizer $realizer): void
    {
        self::assertTrue($realizer->supports('NOT_A_TERMINAL'));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeSkipsTheModeTerminalsThatStandForNoText(PgTerminalRealizer $realizer): void
    {
        self::assertSame([null, []], $realizer->realize('MODE_TYPE_NAME'));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeSyntheticWritesTheOperatorsThatHaveTokens(PgTerminalRealizer $realizer): void
    {
        self::assertSame(['::', ['TYPECAST']], $realizer->realizeSynthetic('TYPECAST'));
        self::assertSame(['..', ['DOT_DOT']], $realizer->realizeSynthetic('DOT_DOT'));
        self::assertSame(['<=', ['LESS_EQUALS']], $realizer->realizeSynthetic('LESS_EQUALS'));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeRequestedAcceptsALexemeThatReadsBackAsTheTerminal(
        PgTerminalRealizer $realizer,
    ): void {
        self::assertSame(['::', ['TYPECAST']], $realizer->realizeRequested('TYPECAST', '::'));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeRequestedRejectsALexemeThatReadsBackAsSomethingElse(
        PgTerminalRealizer $realizer,
    ): void {
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Requested PostgreSQL lexeme does not realize TYPECAST: users');

        $realizer->realizeRequested('TYPECAST', 'users');
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testIdentifierIsBareOrQuoted(PgTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression('/^(?:_.+|".*")$/', $realizer->identifier());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testQuotedIdentifierTakesTheUnicodePrefixWhenAsked(PgTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression('/^U&".*"$/', $realizer->quotedIdentifier(true));
        self::assertMatchesRegularExpression('/^".*"$/', $realizer->quotedIdentifier(false));
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testStringLiteralUsesOneOfItsSpellings(PgTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression('/^(?:E?\'.*\'|\$.*\$)/s', $realizer->stringLiteral());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testStandardStringLiteralIsWrappedInSingleQuotes(PgTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression('/^\'.*\'$/s', $realizer->standardStringLiteral());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testDollarQuotedStringRepeatsItsDelimiter(PgTerminalRealizer $realizer): void
    {
        $literal = $realizer->dollarQuotedString();

        self::assertMatchesRegularExpression('/^\$[A-Za-z0-9_]*\$.*\$[A-Za-z0-9_]*\$$/s', $literal);
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testUnicodeStringLiteralTakesTheUnicodePrefix(PgTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression('/^U&\'.*\'$/s', $realizer->unicodeStringLiteral());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testDecimalLiteralIsAFloatPostgreSqlAccepts(PgTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression('/^(?:\.\d+|\d+\.\d*|\d+e-?\d+)$/', $realizer->decimalLiteral());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testOperatorIsNeverSomethingWithATokenOfItsOwn(PgTerminalRealizer $realizer): void
    {
        $operator = $realizer->operator();

        self::assertStringNotContainsString('--', $operator);
        self::assertStringNotContainsString('/*', $operator);
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRandomOperatorUsesOnlyOperatorCharacters(PgTerminalRealizer $realizer): void
    {
        self::assertMatchesRegularExpression('/^[+\-*\/<>=~!@#%^&|`?]{2,4}$/', $realizer->randomOperator());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testTriviaIsASingleSpaceWhenNothingIsWitnessed(PgTerminalRealizer $realizer): void
    {
        self::assertSame(' ', $realizer->trivia());
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testOptionalTriviaIsNothingWhenNothingIsWitnessed(PgTerminalRealizer $realizer): void
    {
        self::assertSame('', $realizer->optionalTrivia());
    }

    /**
     * @return iterable<string, array{PgTerminalRealizer}>
     */
    public static function providerWitnessedRealizer(): iterable
    {
        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'TERMINAL' => [[
                    'id' => 'terminal.bare',
                    'sql' => 'users',
                    'tokens' => ['TOKENS'],
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

        $lookahead = new PgLookahead(['NOT' => ['token' => 'NOT_LA', 'followed_by' => ['NULL_P']]]);

        yield 'catalogued only' => [new PgTerminalRealizer(
            Factory::create(),
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT'], 'NOT' => ['NOT']],
            'pg-17.2',
            false,
        )];
    }

    /**
     * @return iterable<string, array{PgTerminalRealizer}>
     */
    public static function providerSyntheticRealizer(): iterable
    {
        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $lookahead = new PgLookahead([]);

        yield 'synthetic allowed' => [new PgTerminalRealizer(
            Factory::create(),
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        )];
    }

    #[DataProvider('providerSyntheticTerminal')]
    public function testRealizeSyntheticWritesEveryNamedTerminalAsItsOwnKindOfToken(
        PgTerminalRealizer $realizer,
        string $terminal,
    ): void {
        self::assertSame([$terminal], $realizer->realizeSynthetic($terminal)[1]);
    }

    /**
     * @return iterable<string, array{PgTerminalRealizer, string}>
     */
    public static function providerSyntheticTerminal(): iterable
    {
        $lookahead = new PgLookahead([]);
        $realizer = new PgTerminalRealizer(
            Factory::create(),
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        $terminals = [
            'IDENT', 'UIDENT', 'SCONST', 'USCONST', 'ICONST', 'FCONST', 'BCONST', 'XCONST',
            'Op', 'PARAM', 'TYPECAST', 'DOT_DOT', 'COLON_EQUALS', 'EQUALS_GREATER',
            'NOT_EQUALS', 'LESS_EQUALS', 'GREATER_EQUALS',
        ];
        foreach ($terminals as $terminal) {
            yield $terminal => [$realizer, $terminal];
        }
    }

    #[DataProvider('providerFixedSyntheticTerminal')]
    public function testRealizeSyntheticWritesEveryFixedOperatorAsItsOneSpelling(
        PgTerminalRealizer $realizer,
        string $terminal,
        string $lexeme,
    ): void {
        self::assertSame($lexeme, $realizer->realizeSynthetic($terminal)[0]);
    }

    /**
     * @return iterable<string, array{PgTerminalRealizer, string, string}>
     */
    public static function providerFixedSyntheticTerminal(): iterable
    {
        $lookahead = new PgLookahead([]);
        $realizer = new PgTerminalRealizer(
            Factory::create(),
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        $spellings = [
            'TYPECAST' => '::',
            'DOT_DOT' => '..',
            'COLON_EQUALS' => ':=',
            'EQUALS_GREATER' => '=>',
            'LESS_EQUALS' => '<=',
            'GREATER_EQUALS' => '>=',
        ];
        foreach ($spellings as $terminal => $lexeme) {
            yield $terminal => [$realizer, $terminal, $lexeme];
        }
    }

    #[DataProvider('providerSyntheticRealizer')]
    public function testRealizeSyntheticWritesInequalityEitherWayRoundPostgresAcceptsIt(
        PgTerminalRealizer $realizer,
    ): void {
        self::assertContains($realizer->realizeSynthetic('NOT_EQUALS')[0], ['<>', '!=']);
    }

    public function testRealizeWitnessedChoosesFromEveryWitnessAndNoFurther(): void
    {
        $faker = ScriptedNumbers::answering(1);

        $realized = PgRealizers::witnessed($faker)->realizeWitnessed('TERMINAL');

        self::assertSame(['orders', ['TOKENS']], $realized);
        self::assertSame([[0, 1]], $faker->numberBetweenCalls);
    }

    public function testRealizeWitnessedReadsAnEmptyWitnessAsWritingNothing(): void
    {
        $realizer = PgRealizers::witnessed(ScriptedNumbers::answering(0));

        self::assertSame([null, []], $realizer->realizeWitnessed('NOTHING'));
    }

    public function testRealizeFixedChoosesFromEverySpellingAndNoFurther(): void
    {
        $faker = ScriptedNumbers::answering(1);

        $realized = PgRealizers::synthetic($faker)->realizeFixed('SELECT');

        self::assertSame(['select', ['SELECT']], $realized);
        self::assertSame([[0, 1]], $faker->numberBetweenCalls);
    }

    public function testRealizeFixedFallsBackToTheTerminalNameWithoutItsSuffix(): void
    {
        $faker = ScriptedNumbers::answering();

        self::assertSame('VALUES', PgRealizers::synthetic($faker)->realizeFixed('VALUES_P')[0]);
        self::assertSame([], $faker->numberBetweenCalls);
    }

    public function testIdentifierIsQuotedOnOneOfFourDraws(): void
    {
        $faker = ScriptedNumbers::answering(0, 1, 1);

        $identifier = PgRealizers::synthetic($faker)->identifier();

        self::assertStringStartsWith('"', $identifier);
        self::assertSame([0, 3], $faker->numberBetweenCalls[0]);
    }

    public function testIdentifierIsBareOnEveryOtherDraw(): void
    {
        $faker = ScriptedNumbers::answering(1);

        self::assertStringStartsWith('_', PgRealizers::synthetic($faker)->identifier());
    }

    public function testQuotedIdentifierWritesAKeywordOnOneOfFourDraws(): void
    {
        $faker = ScriptedNumbers::answering(0, 1);

        $identifier = PgRealizers::synthetic($faker)->quotedIdentifier(false);

        self::assertSame('"values"', $identifier);
        self::assertSame([[0, 3], [0, 7]], $faker->numberBetweenCalls);
    }

    public function testQuotedIdentifierDoublesAQuoteWrittenIntoTheBody(): void
    {
        $faker = ScriptedNumbers::answering(0, 0);

        $identifier = PgRealizers::synthetic($faker)->quotedIdentifier(false);

        self::assertStringStartsWith('"values""', $identifier);
        self::assertSame([[0, 3], [0, 7]], array_slice($faker->numberBetweenCalls, 0, 2));
    }

    public function testQuotedIdentifierWritesTheUnicodePrefixWhenItIsAskedTo(): void
    {
        $faker = ScriptedNumbers::answering(0, 1);

        self::assertSame('U&"values"', PgRealizers::synthetic($faker)->quotedIdentifier(true));
    }

    public function testStringLiteralWritesTheEscapeSpellingOnTheFirstDraw(): void
    {
        $faker = ScriptedNumbers::answering(0);

        self::assertSame("E'a\\\\b'", PgRealizers::synthetic($faker)->stringLiteral());
        self::assertSame([[0, 3]], $faker->numberBetweenCalls);
    }

    public function testStringLiteralWritesTheDollarQuotedSpellingOnTheSecondDraw(): void
    {
        $faker = ScriptedNumbers::answering(1, 0);

        self::assertStringStartsWith('$$', PgRealizers::synthetic($faker)->stringLiteral());
    }

    public function testStringLiteralWritesTheStandardSpellingOnEveryOtherDraw(): void
    {
        $faker = ScriptedNumbers::answering(2, 2);

        self::assertSame("'a''b'", PgRealizers::synthetic($faker)->stringLiteral());
    }

    public function testStandardStringLiteralDoublesAQuoteInsideTheBody(): void
    {
        $faker = ScriptedNumbers::answering(2);

        self::assertSame("'a''b'", PgRealizers::synthetic($faker)->standardStringLiteral());
        self::assertSame([[0, 4]], $faker->numberBetweenCalls);
    }

    #[DataProvider('providerLexicalSequenceDraw')]
    public function testStandardStringLiteralWritesALexicalSequenceOnItsFirstTwoDraws(int $draw): void
    {
        $literal = PgRealizers::synthetic(ScriptedNumbers::answering($draw))->standardStringLiteral();

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

    public function testDollarQuotedStringIsUntaggedOnTheFirstDraw(): void
    {
        $faker = ScriptedNumbers::answering(0);

        $literal = PgRealizers::synthetic($faker)->dollarQuotedString();

        self::assertStringStartsWith('$$', $literal);
        self::assertStringEndsWith('$$', $literal);
        self::assertSame([0, 1], $faker->numberBetweenCalls[0]);
    }

    public function testDollarQuotedStringIsTaggedOnEveryOtherDraw(): void
    {
        $literal = PgRealizers::synthetic(ScriptedNumbers::answering(1))->dollarQuotedString();

        self::assertStringStartsWith('$', $literal);
        self::assertStringNotContainsString('$$', $literal);
    }

    public function testDecimalLiteralWritesADifferentShapeForEachOfItsFirstThreeDraws(): void
    {
        self::assertSame('.5', PgRealizers::synthetic(ScriptedNumbers::answering(0))->decimalLiteral());
        self::assertSame('1.', PgRealizers::synthetic(ScriptedNumbers::answering(1))->decimalLiteral());
        self::assertSame('1e-1', PgRealizers::synthetic(ScriptedNumbers::answering(2))->decimalLiteral());
    }

    public function testDecimalLiteralDrawsFromFourShapes(): void
    {
        $faker = ScriptedNumbers::answering(3);

        PgRealizers::synthetic($faker)->decimalLiteral();

        self::assertSame([0, 3], $faker->numberBetweenCalls[0]);
    }

    public function testOperatorWritesACommonSpellingOnOneOfEightDraws(): void
    {
        $faker = ScriptedNumbers::answering(0);

        self::assertSame('?', PgRealizers::synthetic($faker)->operator());
        self::assertSame([[0, 7]], $faker->numberBetweenCalls);
    }

    public function testOperatorWritesEachOfTheThreeCommonSpellings(): void
    {
        self::assertSame('?', PgRealizers::synthetic(ScriptedNumbers::answering(0))->operator());
        self::assertSame('?|', PgRealizers::synthetic(ScriptedNumbers::answering(1))->operator());
        self::assertSame('?&', PgRealizers::synthetic(ScriptedNumbers::answering(2))->operator());
    }

    public function testRandomOperatorIsBetweenTwoAndFourCharactersLong(): void
    {
        $faker = ScriptedNumbers::answering(2, 0, 0);

        $operator = PgRealizers::synthetic($faker)->randomOperator();

        self::assertSame('++', $operator);
        self::assertSame([2, 4], $faker->numberBetweenCalls[0]);
    }

    public function testRandomOperatorDrawsEachCharacterFromTheWholeOperatorAlphabet(): void
    {
        $faker = ScriptedNumbers::answering(2, 0, 16);

        self::assertSame('+?', PgRealizers::synthetic($faker)->randomOperator());
        self::assertSame([0, 16], $faker->numberBetweenCalls[1]);
    }

    public function testTriviaIsASpaceWhereTerminalsMayBeWrittenWithoutAWitness(): void
    {
        $faker = ScriptedNumbers::answering();

        self::assertSame(' ', PgRealizers::synthetic($faker)->trivia());
        self::assertSame([], $faker->numberBetweenCalls);
    }

    public function testTriviaIsChosenFromEveryWitnessAndNoFurther(): void
    {
        $faker = ScriptedNumbers::answering(1);

        self::assertSame('/* c */', PgRealizers::witnessed($faker)->trivia());
        self::assertSame([[0, 1]], $faker->numberBetweenCalls);
    }

    public function testOptionalTriviaIsNothingWhereTerminalsMayBeWrittenWithoutAWitness(): void
    {
        $faker = ScriptedNumbers::answering();

        self::assertSame('', PgRealizers::synthetic($faker)->optionalTrivia());
        self::assertSame([], $faker->numberBetweenCalls);
    }

    public function testOptionalTriviaIsNothingOnOneOfTwoDraws(): void
    {
        $faker = ScriptedNumbers::answering(0);

        self::assertSame('', PgRealizers::witnessed($faker)->optionalTrivia());
        self::assertSame([[0, 1]], $faker->numberBetweenCalls);
    }

    public function testOptionalTriviaIsTriviaOnEveryOtherDraw(): void
    {
        $faker = ScriptedNumbers::answering(1, 0);

        self::assertSame(' ', PgRealizers::witnessed($faker)->optionalTrivia());
    }
}
