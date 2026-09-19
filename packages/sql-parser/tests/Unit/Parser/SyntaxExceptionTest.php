<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\SourcePosition;
use SqlParser\Lexer\Token;
use SqlParser\Parser\SyntaxException;

#[CoversClass(SyntaxException::class)]
#[UsesClass(SourcePosition::class)]
#[UsesClass(Token::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
final class SyntaxExceptionTest extends TestCase
{
    public function testMessageNamesTheTokenAndTheExpectation(): void
    {
        $token = new Token(4, 'FROM', 'FROM', 7);
        $exception = new SyntaxException($token, ['IDENT', 'NUM'], 'SELECT FROM');

        self::assertSame("Unexpected 'FROM' at line 1, column 8, expected IDENT, NUM", $exception->getMessage());
        self::assertSame($token, $exception->token);
        self::assertSame(['IDENT', 'NUM'], $exception->expected);
        self::assertSame(7, $exception->offset);
    }

    public function testMessageShortensALongExpectation(): void
    {
        $exception = new SyntaxException(new Token(0, '$end', '', 6), ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'], 'SELECT');

        self::assertSame('Unexpected end of input at line 1, column 7, expected A, B, C, D, E, F, G, H, ...', $exception->getMessage());
    }

    public function testMessageWithoutExpectation(): void
    {
        self::assertSame("Unexpected 'x' at line 1, column 1", (new SyntaxException(new Token(1, 'ID', 'x', 0), [], 'x'))->getMessage());
    }
}
