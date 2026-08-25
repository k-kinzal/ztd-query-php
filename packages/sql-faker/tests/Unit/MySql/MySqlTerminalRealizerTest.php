<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

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
use SqlFaker\MySql\MySqlTerminalRealizer;
use SqlFaker\MySql\MySqlTokenizer;

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
}
