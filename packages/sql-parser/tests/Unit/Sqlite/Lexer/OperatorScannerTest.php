<?php

declare(strict_types=1);

namespace Tests\Unit\Sqlite\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;
use SqlParser\Sqlite\Lexer\KeywordTable;
use SqlParser\Sqlite\Lexer\OperatorScanner;
use SqlParser\Sqlite\Lexer\Scan;

#[CoversClass(OperatorScanner::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(Scan::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class OperatorScannerTest extends TestCase
{
    public function testScan(): void
    {
        $scanner = new OperatorScanner();
        $scan = static fn (string $sql): Scan => new Scan(new Cursor($sql), new KeywordTable([]));

        self::assertSame('PTR', $scanner->scan($scan('->> 1'))->name);
        self::assertSame('->>', $scanner->scan($scan('->> 1'))->text);
        self::assertSame('CONCAT', $scanner->scan($scan('|| x'))->name);
        self::assertSame('NE', $scanner->scan($scan('!= x'))->name);
        self::assertSame('LSHIFT', $scanner->scan($scan('<< x'))->name);
        self::assertSame('LP', $scanner->scan($scan('('))->name);
        self::assertSame('EQ', $scanner->scan($scan('=='))->name);
        self::assertSame('==', $scanner->scan($scan('=='))->text);
    }

    public function testScanRejectsAForeignCharacter(): void
    {
        $this->expectException(LexicalException::class);

        (new OperatorScanner())->scan(new Scan(new Cursor('!x'), new KeywordTable([])));
    }
}
