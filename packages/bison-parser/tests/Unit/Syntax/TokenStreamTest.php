<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use BisonParser\Ast\Location;
use BisonParser\Scanner\Token;
use BisonParser\Scanner\TokenKind;
use BisonParser\Syntax\TokenStream;
use BisonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenStream::class)]
#[UsesClass(Location::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[Small]
final class TokenStreamTest extends TestCase
{
    public function testPeek(): void
    {
        $stream = new TokenStream([new Token(TokenKind::Colon, ':', new Location(1, 1)), new Token(TokenKind::End, '', new Location(1, 2))]);

        self::assertSame(TokenKind::Colon, $stream->peek()->kind);
        self::assertSame(TokenKind::End, $stream->peek(1)->kind);
        self::assertSame(TokenKind::End, $stream->peek(5)->kind);
    }

    public function testNext(): void
    {
        $stream = new TokenStream([new Token(TokenKind::Colon, ':', new Location(1, 1)), new Token(TokenKind::End, '', new Location(1, 2))]);

        self::assertSame(TokenKind::Colon, $stream->next()->kind);
        self::assertSame(TokenKind::End, $stream->next()->kind);
        self::assertSame(TokenKind::End, $stream->next()->kind);
    }

    public function testIs(): void
    {
        $stream = new TokenStream([new Token(TokenKind::Pipe, '|', new Location(1, 1)), new Token(TokenKind::End, '', new Location(1, 2))]);

        self::assertTrue($stream->is(TokenKind::Pipe));
        self::assertFalse($stream->is(TokenKind::End));
    }

    public function testIsDirective(): void
    {
        $stream = new TokenStream([new Token(TokenKind::Directive, 'left', new Location(1, 1), '%left'), new Token(TokenKind::End, '', new Location(1, 6))]);

        self::assertTrue($stream->isDirective('left'));
        self::assertFalse($stream->isDirective('right'));
    }

    public function testAccept(): void
    {
        $stream = new TokenStream([new Token(TokenKind::Pipe, '|', new Location(1, 1)), new Token(TokenKind::End, '', new Location(1, 2))]);

        self::assertNull($stream->accept(TokenKind::Colon));
        self::assertSame('|', $stream->accept(TokenKind::Pipe)?->text);
        self::assertTrue($stream->is(TokenKind::End));
    }

    public function testExpect(): void
    {
        $stream = new TokenStream([new Token(TokenKind::Integer, '3', new Location(1, 1)), new Token(TokenKind::End, '', new Location(1, 2))]);

        self::assertSame('3', $stream->expect(TokenKind::Integer, 'a number')->text);
        self::assertTrue($stream->is(TokenKind::End));
    }

    public function testExpectRejectsAnotherToken(): void
    {
        $stream = new TokenStream([new Token(TokenKind::Identifier, 'x', new Location(2, 5)), new Token(TokenKind::End, '', new Location(2, 6))]);

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a number but found 'x' at 2:5");

        $stream->expect(TokenKind::Integer, 'a number');
    }
}
