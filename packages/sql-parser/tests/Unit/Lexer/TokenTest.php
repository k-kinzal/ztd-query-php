<?php

declare(strict_types=1);

namespace Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;

#[CoversClass(Token::class)]
#[Small]
final class TokenTest extends TestCase
{
    public function testEnd(): void
    {
        self::assertSame(9, (new Token(3, 'SELECT_SYM', 'SELECT', 3))->end());
        self::assertSame(3, (new Token(0, '$end', '', 3))->end());
    }

    public function testIs(): void
    {
        $token = new Token(3, 'SELECT_SYM', 'select', 0);

        self::assertTrue($token->is('SELECT_SYM'));
        self::assertFalse($token->is('select'));
    }

    public function testIsDetached(): void
    {
        self::assertFalse((new Token(3, 'SELECT_SYM', 'SELECT', 0))->isDetached());
        self::assertTrue((new Token(3, 'SELECT_SYM', 'SELECT', Token::DETACHED))->isDetached());
        self::assertTrue((new Token(3, 'SELECT_SYM', 'SELECT', -7))->isDetached());
    }

    public function testDetached(): void
    {
        $token = new Token(3, 'SELECT_SYM', 'SELECT', 12);
        $detached = $token->detached();

        self::assertSame(Token::DETACHED, $detached->offset);
        self::assertSame(3, $detached->symbol);
        self::assertSame('SELECT_SYM', $detached->name);
        self::assertSame('SELECT', $detached->text);
        self::assertSame(12, $token->offset);
    }

    public function testDetachedKeepsAnAlreadyDetachedToken(): void
    {
        $token = (new Token(3, 'SELECT_SYM', 'SELECT', 12))->detached();

        self::assertSame($token, $token->detached());
    }

    public function testDetachedLosesTheTriviaItStoodAfter(): void
    {
        $token = new Token(3, 'SELECT_SYM', 'SELECT', 12, '  ');

        self::assertSame('', $token->detached()->leading);
    }

    public function testToStringWritesTheTriviaBeforeTheText(): void
    {
        self::assertSame(' /* one */ 1', (new Token(7, 'NUM', '1', 11, ' /* one */ '))->toString());
        self::assertSame('SELECT', (new Token(3, 'SELECT_SYM', 'SELECT', 0))->toString());
        self::assertSame("\n-- done\n", (new Token(0, '$end', '', 9, "\n-- done\n"))->toString());
    }
}
