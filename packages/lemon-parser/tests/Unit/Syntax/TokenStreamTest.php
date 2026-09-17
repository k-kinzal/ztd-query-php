<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use LemonParser\Ast\Location;
use LemonParser\Scanner\Token;
use LemonParser\Scanner\TokenKind;
use LemonParser\Syntax\TokenStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenStream::class)]
#[UsesClass(Location::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[Small]
final class TokenStreamTest extends TestCase
{
    public function testPeek(): void
    {
        $stream = new TokenStream([new Token(TokenKind::Word, 'a', new Location(1, 1), 'a'), new Token(TokenKind::End, '', new Location(1, 2), '')]);

        self::assertSame('a', $stream->peek()->text);
        self::assertSame('a', $stream->peek()->text);
    }

    public function testNext(): void
    {
        $stream = new TokenStream([new Token(TokenKind::Word, 'a', new Location(1, 1), 'a'), new Token(TokenKind::End, '', new Location(1, 2), '')]);

        self::assertSame('a', $stream->next()->text);
        self::assertSame(TokenKind::End, $stream->next()->kind);
        self::assertSame(TokenKind::End, $stream->next()->kind);
    }

    public function testEof(): void
    {
        $stream = new TokenStream([new Token(TokenKind::Word, 'a', new Location(1, 1), 'a'), new Token(TokenKind::End, '', new Location(1, 2), '')]);

        self::assertFalse($stream->eof());
        $stream->next();
        self::assertTrue($stream->eof());
    }
}
