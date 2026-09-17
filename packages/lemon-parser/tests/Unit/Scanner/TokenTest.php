<?php

declare(strict_types=1);

namespace Tests\Unit\Scanner;

use LemonParser\Ast\Location;
use LemonParser\Scanner\Token;
use LemonParser\Scanner\TokenKind;
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
        $token = new Token(TokenKind::Arrow, '::=', new Location(1, 1), '::=');

        self::assertTrue($token->is(TokenKind::Arrow));
        self::assertFalse($token->is(TokenKind::Word));
    }

    public function testIsPunctuation(): void
    {
        self::assertTrue((new Token(TokenKind::Punctuation, '.', new Location(1, 1), '.'))->isPunctuation('.'));
        self::assertFalse((new Token(TokenKind::Punctuation, '.', new Location(1, 1), '.'))->isPunctuation('%'));
        self::assertFalse((new Token(TokenKind::Word, '.', new Location(1, 1), '.'))->isPunctuation('.'));
    }

    public function testIsUpperWord(): void
    {
        self::assertTrue((new Token(TokenKind::Word, 'PLUS', new Location(1, 1), 'PLUS'))->isUpperWord());
        self::assertFalse((new Token(TokenKind::Word, 'expr', new Location(1, 1), 'expr'))->isUpperWord());
        self::assertFalse((new Token(TokenKind::Compound, 'PLUS', new Location(1, 1), '|PLUS'))->isUpperWord());
    }

    public function testIsLowerWord(): void
    {
        self::assertTrue((new Token(TokenKind::Word, 'expr', new Location(1, 1), 'expr'))->isLowerWord());
        self::assertFalse((new Token(TokenKind::Word, 'PLUS', new Location(1, 1), 'PLUS'))->isLowerWord());
        self::assertFalse((new Token(TokenKind::Word, '1st', new Location(1, 1), '1st'))->isLowerWord());
    }

    public function testIsAlphaWord(): void
    {
        self::assertTrue((new Token(TokenKind::Word, 'expr', new Location(1, 1), 'expr'))->isAlphaWord());
        self::assertTrue((new Token(TokenKind::Word, 'PLUS', new Location(1, 1), 'PLUS'))->isAlphaWord());
        self::assertFalse((new Token(TokenKind::Word, '1st', new Location(1, 1), '1st'))->isAlphaWord());
        self::assertFalse((new Token(TokenKind::String, 'abc', new Location(1, 1), '"abc"'))->isAlphaWord());
    }
}
