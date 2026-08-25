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
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\PostgreSql\PgLookahead;
use SqlFaker\PostgreSql\PgTerminalRealizer;
use SqlFaker\PostgreSql\PgTokenizer;

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
}
