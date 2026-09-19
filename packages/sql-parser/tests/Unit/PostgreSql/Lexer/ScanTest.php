<?php

declare(strict_types=1);

namespace Tests\Unit\PostgreSql\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\PostgreSql\Lexer\KeywordTable;
use SqlParser\PostgreSql\Lexer\Scan;

#[CoversClass(Scan::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(KeywordTable::class)]
#[Small]
final class ScanTest extends TestCase
{
    public function testLexeme(): void
    {
        $scan = new Scan(new Cursor('SELECT 1'), new KeywordTable([]));
        $scan->cursor->take(6);

        self::assertSame('SELECT', $scan->lexeme('SELECT', 0)->text);
    }

    public function testStartsIdentifier(): void
    {
        self::assertTrue(Scan::startsIdentifier('a'));
        self::assertTrue(Scan::startsIdentifier('_'));
        self::assertTrue(Scan::startsIdentifier("\xC3"));
        self::assertFalse(Scan::startsIdentifier('1'));
        self::assertFalse(Scan::startsIdentifier('$'));
        self::assertFalse(Scan::startsIdentifier(''));
    }
}
