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
use SqlParser\PostgreSql\Lexer\WordScanner;

#[CoversClass(WordScanner::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(Scan::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class WordScannerTest extends TestCase
{
    public function testScan(): void
    {
        $scanner = new WordScanner();
        $keywords = new KeywordTable(['SELECT' => 'SELECT']);

        self::assertSame('SELECT', $scanner->scan(new Scan(new Cursor('Select 1'), $keywords))?->name);
        $identifier = $scanner->scan(new Scan(new Cursor('users$1 x'), $keywords));
        self::assertNotNull($identifier);
        self::assertSame('IDENT', $identifier->name);
        self::assertSame('users$1', $identifier->text);
        self::assertSame('IDENT', $scanner->scan(new Scan(new Cursor('猫'), $keywords))?->name);
        self::assertNull($scanner->scan(new Scan(new Cursor('1a'), $keywords)));
    }
}
