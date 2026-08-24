<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\TokenJoiner;
use SqlFaker\MySql\LexicalGrammar;

#[CoversClass(LexicalException::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(LexicalGrammar::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(TokenJoiner::class)]
final class LexicalExceptionTest extends TestCase
{
    public function testTokenizingUnsupportedInputReportsTheOffsetAndTheInput(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported MySQL lexical input at offset 0:');

        $lexical->tokenize("\x00");
    }

    public function testRealizingAnUnknownTerminalReportsTheTerminal(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported MySQL terminal for mysql-8.4.7: NOT_A_TERMINAL');

        $lexical->realize(['NOT_A_TERMINAL']);
    }

    public function testUnsupportedTerminalNamesTheTerminalAndTheProfile(): void
    {
        self::assertSame(
            'Unsupported MySQL terminal for mysql-8.4.7: NOT_A_TERMINAL',
            LexicalException::unsupportedTerminal('MySQL', 'mysql-8.4.7', 'NOT_A_TERMINAL')->getMessage(),
        );
    }

    public function testUnsupportedInputNamesTheOffsetAndTheText(): void
    {
        self::assertSame(
            'Unsupported SQLite lexical input at offset 3: abc',
            LexicalException::unsupportedInput('SQLite', 3, 'abc')->getMessage(),
        );
    }

    public function testUnterminatedQuotedTokenNamesTheText(): void
    {
        self::assertSame(
            "Unterminated MySQL quoted token: 'abc",
            LexicalException::unterminatedQuotedToken('MySQL', "'abc")->getMessage(),
        );
    }

    public function testUnterminatedBracketIdentifierNamesTheDialect(): void
    {
        self::assertSame(
            'Unterminated SQLite bracket identifier.',
            LexicalException::unterminatedBracketIdentifier('SQLite')->getMessage(),
        );
    }

    public function testUnterminatedBlockCommentNamesTheDialect(): void
    {
        self::assertSame(
            'Unterminated PostgreSQL block comment.',
            LexicalException::unterminatedBlockComment('PostgreSQL')->getMessage(),
        );
    }

    public function testUnterminatedDollarQuotedStringNamesTheDialect(): void
    {
        self::assertSame(
            'Unterminated MySQL dollar-quoted string.',
            LexicalException::unterminatedDollarQuotedString('MySQL')->getMessage(),
        );
    }

    public function testLexemeDoesNotRealizeTerminalNamesBoth(): void
    {
        self::assertSame(
            'Requested MySQL lexeme does not realize IDENT: 42',
            LexicalException::lexemeDoesNotRealizeTerminal('MySQL', 'IDENT', '42')->getMessage(),
        );
    }

    public function testNoWitnessForLexemeNamesBoth(): void
    {
        self::assertSame(
            'MySQL lexical catalog has no IDENT witness for: 42',
            LexicalException::noWitnessForLexeme('MySQL', 'IDENT', '42')->getMessage(),
        );
    }

    public function testRenderedSubstitutesBytesJsonHasNoEncodingFor(): void
    {
        self::assertSame('["\\ufffd"]', LexicalException::rendered(["\xB1"]));
    }

    public function testRenderedWritesAnOrdinarySequenceAsJson(): void
    {
        self::assertSame('["IDENT"]', LexicalException::rendered(['IDENT']));
    }

    public function testRoundTripMismatchCarriesBothSequencesAndTheText(): void
    {
        $message = LexicalException::roundTripMismatch(
            'MySQL',
            'mysql-8.4.7',
            ['SELECT_SYM'],
            ['IDENT'],
            'select',
        )->getMessage();

        self::assertSame(
            "MySQL lexical round-trip failed for mysql-8.4.7.\n"
            . "Expected: [\"SELECT_SYM\"]\n"
            . "Actual: [\"IDENT\"]\n"
            . 'SQL: select',
            $message,
        );
    }
}
