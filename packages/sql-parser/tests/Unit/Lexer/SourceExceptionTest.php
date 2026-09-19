<?php

declare(strict_types=1);

namespace Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\LexicalException;
use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\SourcePosition;

#[CoversClass(SourceException::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(SourcePosition::class)]
#[Small]
final class SourceExceptionTest extends TestCase
{
    public function testCarriesTheOffsetAndPosition(): void
    {
        $exception = LexicalException::unterminated('string', "SELECT\n'abc", 7);

        self::assertSame('Unterminated string at line 2, column 1', $exception->getMessage());
        self::assertSame(7, $exception->offset);
        self::assertSame(2, $exception->position->line);
        self::assertSame(1, $exception->position->column);
    }
}
