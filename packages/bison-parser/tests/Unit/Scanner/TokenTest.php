<?php

declare(strict_types=1);

namespace Tests\Unit\Scanner;

use BisonParser\Ast\Location;
use BisonParser\Scanner\Token;
use BisonParser\Scanner\TokenKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Token::class)]
#[UsesClass(Location::class)]
#[UsesClass(TokenKind::class)]
#[Small]
final class TokenTest extends TestCase
{
    public function testIs(): void
    {
        $token = new Token(TokenKind::Pipe, '|', new Location(1, 1));

        self::assertTrue($token->is(TokenKind::Pipe));
        self::assertFalse($token->is(TokenKind::Colon));
        self::assertSame('', $token->raw);
    }

    public function testIsDirective(): void
    {
        $directive = new Token(TokenKind::Directive, 'token', new Location(1, 1), '%term');

        self::assertTrue($directive->isDirective('token'));
        self::assertFalse($directive->isDirective('term'));
        self::assertFalse((new Token(TokenKind::Identifier, 'token', new Location(1, 1)))->isDirective('token'));
    }

    public function testDescribe(): void
    {
        self::assertSame("'%token'", (new Token(TokenKind::Directive, 'token', new Location(1, 1), '%token'))->describe());
        self::assertSame("'expr'", (new Token(TokenKind::IdentifierColon, 'expr', new Location(1, 1)))->describe());
        self::assertSame("'expr'", (new Token(TokenKind::Identifier, 'expr', new Location(1, 1)))->describe());
        self::assertSame("';'", (new Token(TokenKind::Semicolon, ';', new Location(1, 1)))->describe());
        self::assertSame("'%%'", (new Token(TokenKind::Section, '%%', new Location(1, 1)))->describe());
        self::assertSame('braced code', (new Token(TokenKind::Code, ' x ', new Location(1, 1)))->describe());
        self::assertSame('end of file', (new Token(TokenKind::End, '', new Location(1, 1)))->describe());
    }
}
