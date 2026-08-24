<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonToken;

#[CoversClass(BisonToken::class)]
final class BisonTokenTest extends TestCase
{
    public function testType(): void
    {
        $token = new BisonToken(BisonLexeme::Identifier, 'SELECT', 0);

        self::assertSame(BisonLexeme::Identifier, $token->type);
    }

    public function testValueString(): void
    {
        $token = new BisonToken(BisonLexeme::StringLiteral, '"hello"', 5);

        self::assertSame('"hello"', $token->value);
    }

    public function testValueInt(): void
    {
        $token = new BisonToken(BisonLexeme::Number, 42, 10);

        self::assertSame(42, $token->value);
    }

    public function testOffset(): void
    {
        $token = new BisonToken(BisonLexeme::Colon, ':', 99);

        self::assertSame(99, $token->offset);
    }
}
