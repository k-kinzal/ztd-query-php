<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\BisonTokenType;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BisonToken::class)]
final class BisonTokenTest extends TestCase
{
    public function testType(): void
    {
        $token = new BisonToken(BisonTokenType::Identifier, 'SELECT', 0);

        self::assertSame(BisonTokenType::Identifier, $token->type);
    }

    public function testValueString(): void
    {
        $token = new BisonToken(BisonTokenType::StringLiteral, '"hello"', 5);

        self::assertSame('"hello"', $token->value);
    }

    public function testValueInt(): void
    {
        $token = new BisonToken(BisonTokenType::Number, 42, 10);

        self::assertSame(42, $token->value);
    }

    public function testOffset(): void
    {
        $token = new BisonToken(BisonTokenType::Colon, ':', 99);

        self::assertSame(99, $token->offset);
    }
}
