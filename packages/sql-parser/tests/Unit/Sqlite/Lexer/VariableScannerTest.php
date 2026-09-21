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
use SqlParser\Sqlite\Lexer\Scan;
use SqlParser\Sqlite\Lexer\VariableScanner;

#[CoversClass(VariableScanner::class)]
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
final class VariableScannerTest extends TestCase
{
    public function testScan(): void
    {
        $scanner = new VariableScanner();
        $scan = static fn (string $sql): Scan => new Scan(new Cursor($sql), new KeywordTable([]));

        self::assertSame('?', $scanner->scan($scan('? '))?->text);
        self::assertSame('?12', $scanner->scan($scan('?12 '))?->text);
        self::assertSame(':name', $scanner->scan($scan(':name x'))?->text);
        self::assertSame('@a::b', $scanner->scan($scan('@a::b x'))?->text);
        self::assertSame('$x$y', $scanner->scan($scan('$x$y '))?->text);
        self::assertSame('#v(1)', $scanner->scan($scan('#v(1) x'))?->text);
        self::assertSame('VARIABLE', $scanner->scan($scan('$a'))?->name);
        self::assertNull($scanner->scan($scan('abc')));
    }

    public function testScanRejectsASigilWithoutAName(): void
    {
        $this->expectException(LexicalException::class);

        (new VariableScanner())->scan(new Scan(new Cursor(': x'), new KeywordTable([])));
    }

    public function testScanRejectsAnUnterminatedSuffix(): void
    {
        $this->expectException(LexicalException::class);

        (new VariableScanner())->scan(new Scan(new Cursor('$v(1 2)'), new KeywordTable([])));
    }
}
