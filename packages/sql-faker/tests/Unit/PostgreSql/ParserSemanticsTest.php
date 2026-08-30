<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalCatalog;
use SqlFaker\Grammar\Lexical\LexicalCatalogShape;
use SqlFaker\Grammar\Lexical\LexicalCoverageCheck;
use SqlFaker\Grammar\Lexical\LexicalKeywordIndex;
use SqlFaker\Grammar\Lexical\LexicalProfileSource;
use SqlFaker\Grammar\Lexical\LexicalWitnessCheck;
use SqlFaker\Grammar\Lexical\LexicalWitnessShape;
use SqlFaker\Grammar\Lexical\RandomCharacters;
use SqlFaker\Grammar\Lexical\RandomStringGenerator;
use SqlFaker\Grammar\Source\SqlVersion;
use SqlFaker\Grammar\Source\SqlVersionRegistry;
use SqlFaker\PostgreSql\Lexical\LexicalGrammar;
use SqlFaker\PostgreSql\Lexical\PgLookahead;
use SqlFaker\PostgreSql\Lexical\PgTerminalRealizer;
use SqlFaker\PostgreSql\Lexical\PgTokenizer;
use SqlFaker\PostgreSql\ParserSemantics;

#[CoversClass(ParserSemantics::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(LexicalGrammar::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(LexicalCatalogShape::class)]
#[UsesClass(LexicalCoverageCheck::class)]
#[UsesClass(LexicalKeywordIndex::class)]
#[UsesClass(LexicalProfileSource::class)]
#[UsesClass(LexicalWitnessCheck::class)]
#[UsesClass(LexicalWitnessShape::class)]
#[UsesClass(RandomCharacters::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(SqlVersionRegistry::class)]
#[UsesClass(PgLookahead::class)]
#[UsesClass(PgTerminalRealizer::class)]
#[UsesClass(PgTokenizer::class)]
final class ParserSemanticsTest extends TestCase
{
    public function testAppliedGivesEveryOptionInASetListAValue(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertSame(
            ['SET', '(', 'IDENT', '=', 'NONE', ')'],
            $semantics->applied(['SET', '(', 'IDENT', ')']),
        );
    }

    public function testAppliedGivesAnOperatorItsMissingArgument(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertSame(
            ['OPERATOR', 'IDENT', '(', 'NONE', ',', 'IDENT', ')'],
            $semantics->applied(['OPERATOR', 'IDENT', '(', 'IDENT', ')']),
        );
    }

    public function testTruncateQualifiedNamesCutsANameToTheDepthTheParserAccepts(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertSame(
            ['IDENT', '.', 'IDENT', '.', 'IDENT'],
            $semantics->truncateQualifiedNames(['IDENT', '.', 'IDENT', '.', 'IDENT', '.', 'IDENT']),
        );
    }

    public function testTruncateQualifiedNamesKeepsAStarThatEndsTheName(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertSame(['IDENT'], $semantics->truncateQualifiedNames(['IDENT', '.', '*']));
    }

    public function testMatchingParenAnswersWhereTheOpenedParenthesisCloses(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertSame(4, $semantics->matchingParen(['(', '(', 'IDENT', ')', ')'], 0));
    }

    public function testMatchingParenAnswersNothingWhenItNeverCloses(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertNull($semantics->matchingParen(['(', 'IDENT'], 0));
    }

    public function testIsIdentifierTerminalTellsANameApartFromAKeyword(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertTrue($semantics->isIdentifierTerminal('IDENT'));
        self::assertTrue($semantics->isIdentifierTerminal('users'));
        self::assertFalse($semantics->isIdentifierTerminal('SELECT'));
    }

    public function testWithStorageParameterValuesWritesAValueAfterABareParameterName(): void
    {
        $terminals = (new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2')))->withStorageParameterValues(['SET', '(', 'fillfactor', ')']);

        self::assertSame(['SET', '(', 'fillfactor', '=', 'NONE', ')'], $terminals);
    }

    public function testWithStorageParameterValuesLeavesAParameterThatAlreadyHasOne(): void
    {
        $terminals = (new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2')))->withStorageParameterValues(['SET', '(', 'fillfactor', '=', '70', ')']);

        self::assertSame(['SET', '(', 'fillfactor', '=', '70', ')'], $terminals);
    }

    public function testWithOperatorArgumentWritesTheSecondOneAsNone(): void
    {
        $terminals = (new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2')))->withOperatorArgument(['OPERATOR', 'x', '(', 'int', ')']);

        self::assertSame(['OPERATOR', 'x', '(', 'NONE', ',', 'int', ')'], $terminals);
    }

    public function testWithOperatorArgumentLeavesAPairThatIsAlreadyWrittenOut(): void
    {
        $terminals = (new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2')))->withOperatorArgument(['OPERATOR', 'x', '(', 'int', ',', 'int', ')']);

        self::assertSame(['OPERATOR', 'x', '(', 'int', ',', 'int', ')'], $terminals);
    }

    /**
     * @param list<string> $terminals
     * @param list<string> $expected
     */
    #[DataProvider('providerStorageParameters')]
    public function testWithStorageParameterValuesWritesAValueAfterEveryBareName(array $terminals, array $expected): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertSame($expected, $semantics->withStorageParameterValues($terminals));
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function providerStorageParameters(): iterable
    {
        yield 'one bare name' => [
            ['SET', '(', 'IDENT', ')'],
            ['SET', '(', 'IDENT', '=', 'NONE', ')'],
        ];
        yield 'two bare names' => [
            ['SET', '(', 'IDENT', ',', 'IDENT', ')'],
            ['SET', '(', 'IDENT', '=', 'NONE', ',', 'IDENT', '=', 'NONE', ')'],
        ];
        yield 'already valued' => [
            ['SET', '(', 'IDENT', '=', 'ICONST', ')'],
            ['SET', '(', 'IDENT', '=', 'ICONST', ')'],
        ];
        yield 'one of each' => [
            ['SET', '(', 'IDENT', '=', 'ICONST', ',', 'IDENT', ')'],
            ['SET', '(', 'IDENT', '=', 'ICONST', ',', 'IDENT', '=', 'NONE', ')'],
        ];
        yield 'no list at all' => [['SET', 'IDENT'], ['SET', 'IDENT']];
        yield 'list never closed' => [['SET', '(', 'IDENT'], ['SET', '(', 'IDENT']];
        yield 'not a SET' => [['RESET', '(', 'IDENT', ')'], ['RESET', '(', 'IDENT', ')']];
        yield 'something is written before it' => [
            ['ALTER', 'SET', '(', 'IDENT', ')'],
            ['ALTER', 'SET', '(', 'IDENT', '=', 'NONE', ')'],
        ];
        yield 'two lists' => [
            ['SET', '(', 'IDENT', ')', 'SET', '(', 'IDENT', ')'],
            ['SET', '(', 'IDENT', '=', 'NONE', ')', 'SET', '(', 'IDENT', '=', 'NONE', ')'],
        ];
    }

    /**
     * @param list<string> $terminals
     * @param list<string> $expected
     */
    #[DataProvider('providerOperatorArguments')]
    public function testWithOperatorArgumentWritesTheSecondOneOnlyWhereItIsMissing(array $terminals, array $expected): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertSame($expected, $semantics->withOperatorArgument($terminals));
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function providerOperatorArguments(): iterable
    {
        yield 'one argument' => [
            ['OPERATOR', 'IDENT', '(', 'IDENT', ')'],
            ['OPERATOR', 'IDENT', '(', 'NONE', ',', 'IDENT', ')'],
        ];
        yield 'both arguments' => [
            ['OPERATOR', 'IDENT', '(', 'IDENT', ',', 'IDENT', ')'],
            ['OPERATOR', 'IDENT', '(', 'IDENT', ',', 'IDENT', ')'],
        ];
        yield 'the parenthesis follows the keyword' => [
            ['OPERATOR', '(', 'IDENT', ')'],
            ['OPERATOR', '(', 'IDENT', ')'],
        ];
        yield 'no parenthesis at all' => [['OPERATOR', 'IDENT'], ['OPERATOR', 'IDENT']];
        yield 'nothing to do' => [['SELECT', 'IDENT'], ['SELECT', 'IDENT']];
        yield 'something is written before it' => [
            ['SELECT', 'OPERATOR', 'IDENT', '(', 'IDENT', ')'],
            ['SELECT', 'OPERATOR', 'IDENT', '(', 'NONE', ',', 'IDENT', ')'],
        ];
        yield 'two of them' => [
            ['OPERATOR', 'IDENT', '(', 'IDENT', ')', 'OPERATOR', 'IDENT', '(', 'IDENT', ')'],
            ['OPERATOR', 'IDENT', '(', 'NONE', ',', 'IDENT', ')', 'OPERATOR', 'IDENT', '(', 'NONE', ',', 'IDENT', ')'],
        ];
    }
}
