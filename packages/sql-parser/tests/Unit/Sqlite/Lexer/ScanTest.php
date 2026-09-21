<?php

declare(strict_types=1);

namespace Tests\Unit\Sqlite\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\Sqlite\Lexer\KeywordTable;
use SqlParser\Sqlite\Lexer\Scan;

#[CoversClass(Scan::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(KeywordTable::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class ScanTest extends TestCase
{
    public function testLexeme(): void
    {
        $scan = new Scan(new Cursor('SELECT 1'), new KeywordTable([]));
        $scan->cursor->take(6);

        self::assertSame('SELECT', $scan->lexeme('SELECT', 0)->text);
    }

    public function testLast(): void
    {
        $scan = new Scan(new Cursor(''), new KeywordTable([]));

        self::assertNull($scan->last());
        $scan->lexemes[] = new Lexeme('RP', ')', 0);
        self::assertSame('RP', $scan->last()?->name);
    }

    public function testIsIdentifierByte(): void
    {
        self::assertTrue(Scan::isIdentifierByte('a'));
        self::assertTrue(Scan::isIdentifierByte('$'));
        self::assertTrue(Scan::isIdentifierByte("\xE7"));
        self::assertFalse(Scan::isIdentifierByte('.'));
        self::assertFalse(Scan::isIdentifierByte(''));
    }
}
