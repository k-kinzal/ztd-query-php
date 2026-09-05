<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\MySql\Bison\Lexer\BisonTokenType;

#[CoversClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonTokenType::class)]
final class BisonDeclarationBoundaryTest extends TestCase
{
    #[DataProvider('providerLexeme')]
    public function testContinuesWithSeparatesArgumentsFromWhatStartsSomethingElse(
        BisonTokenType $lexeme,
        bool $expected,
    ): void {
        self::assertSame($expected, (new BisonDeclarationBoundary())->continuesWith($lexeme));
    }

    /**
     * @return iterable<string, array{BisonTokenType, bool}>
     */
    public static function providerLexeme(): iterable
    {
        yield 'another directive ends it' => [BisonTokenType::Directive, false];
        yield 'a prologue ends it' => [BisonTokenType::Prologue, false];
        yield 'the section separator ends it' => [BisonTokenType::PercentPercent, false];
        yield 'the end of file ends it' => [BisonTokenType::Eof, false];
        yield 'a name continues it' => [BisonTokenType::Identifier, true];
        yield 'a number continues it' => [BisonTokenType::Number, true];
        yield 'a character literal continues it' => [BisonTokenType::CharLiteral, true];
        yield 'a string literal continues it' => [BisonTokenType::StringLiteral, true];
        yield 'a type tag continues it' => [BisonTokenType::TypeTag, true];
        yield 'a colon continues it' => [BisonTokenType::Colon, true];
        yield 'a semicolon continues it' => [BisonTokenType::Semicolon, true];
        yield 'a pipe continues it' => [BisonTokenType::Pipe, true];
        yield 'an action continues it' => [BisonTokenType::Action, true];
    }

    public function testContinuesWithAnswersForEveryLexeme(): void
    {
        $boundary = new BisonDeclarationBoundary();

        $answered = array_map(
            static fn (BisonTokenType $lexeme): bool => $boundary->continuesWith($lexeme),
            BisonTokenType::cases(),
        );

        self::assertCount(count(BisonTokenType::cases()), $answered);
    }
}
