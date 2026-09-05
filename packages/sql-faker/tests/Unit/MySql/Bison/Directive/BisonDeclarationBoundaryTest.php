<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;

#[CoversClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonLexeme::class)]
final class BisonDeclarationBoundaryTest extends TestCase
{
    #[DataProvider('providerLexeme')]
    public function testContinuesWithSeparatesArgumentsFromWhatStartsSomethingElse(
        BisonLexeme $lexeme,
        bool $expected,
    ): void {
        self::assertSame($expected, (new BisonDeclarationBoundary())->continuesWith($lexeme));
    }

    /**
     * @return iterable<string, array{BisonLexeme, bool}>
     */
    public static function providerLexeme(): iterable
    {
        yield 'another directive ends it' => [BisonLexeme::Directive, false];
        yield 'a prologue ends it' => [BisonLexeme::Prologue, false];
        yield 'the section separator ends it' => [BisonLexeme::PercentPercent, false];
        yield 'the end of file ends it' => [BisonLexeme::Eof, false];
        yield 'a name continues it' => [BisonLexeme::Identifier, true];
        yield 'a number continues it' => [BisonLexeme::Number, true];
        yield 'a character literal continues it' => [BisonLexeme::CharLiteral, true];
        yield 'a string literal continues it' => [BisonLexeme::StringLiteral, true];
        yield 'a type tag continues it' => [BisonLexeme::TypeTag, true];
        yield 'a colon continues it' => [BisonLexeme::Colon, true];
        yield 'a semicolon continues it' => [BisonLexeme::Semicolon, true];
        yield 'a pipe continues it' => [BisonLexeme::Pipe, true];
        yield 'an action continues it' => [BisonLexeme::Action, true];
    }

    public function testContinuesWithAnswersForEveryLexeme(): void
    {
        $boundary = new BisonDeclarationBoundary();

        $answered = array_map(
            static fn (BisonLexeme $lexeme): bool => $boundary->continuesWith($lexeme),
            BisonLexeme::cases(),
        );

        self::assertCount(count(BisonLexeme::cases()), $answered);
    }
}
