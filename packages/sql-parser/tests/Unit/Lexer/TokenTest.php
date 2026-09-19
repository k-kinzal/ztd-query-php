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
}
