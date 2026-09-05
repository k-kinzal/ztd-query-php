<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\BisonTokenType;

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

    public function testAsStringReadsTextAsItIs(): void
    {
        self::assertSame('IDENT', (new BisonToken(BisonTokenType::Identifier, 'IDENT', 0))->asString());
    }

    public function testAsStringRendersANumberAsItsDigits(): void
    {
        self::assertSame('42', (new BisonToken(BisonTokenType::Number, 42, 0))->asString());
    }

    public function testAsIntReadsANumberAsItIs(): void
    {
        self::assertSame(42, (new BisonToken(BisonTokenType::Number, 42, 0))->asInt());
    }

    public function testAsIntReadsTextThatNamesNoNumberAsZero(): void
    {
        self::assertSame(0, (new BisonToken(BisonTokenType::Identifier, 'IDENT', 0))->asInt());
    }
}
