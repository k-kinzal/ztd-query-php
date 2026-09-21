<?php

declare(strict_types=1);

namespace Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\LexicalException;
use SqlParser\Lexer\SourcePosition;

#[CoversClass(LexicalException::class)]
#[UsesClass(SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class LexicalExceptionTest extends TestCase
{
    public function testUnterminated(): void
    {
        $exception = LexicalException::unterminated('string', "SELECT 'abc", 7);

        self::assertSame('Unterminated string at line 1, column 8', $exception->getMessage());
        self::assertSame(7, $exception->offset);
    }

    public function testUnexpectedCharacter(): void
    {
        self::assertSame("Unexpected '#' at line 1, column 8", LexicalException::unexpectedCharacter('SELECT #', 7)->getMessage());
        self::assertSame('Unexpected end of input at line 1, column 7', LexicalException::unexpectedCharacter('SELECT', 6)->getMessage());
    }

    public function testUnknownTerminal(): void
    {
        self::assertSame('Terminal FOO is not part of the grammar at line 1, column 1', LexicalException::unknownTerminal('FOO', 'foo', 0)->getMessage());
    }
}
