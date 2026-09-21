<?php

declare(strict_types=1);

namespace Tests\Unit\PostgreSql\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;
use SqlParser\PostgreSql\Lexer\KeywordTable;
use SqlParser\PostgreSql\Lexer\QuotedScanner;
use SqlParser\PostgreSql\Lexer\Scan;

#[CoversClass(QuotedScanner::class)]
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
final class QuotedScannerTest extends TestCase
{
    public function testScanReadsEverySpellingOfAString(): void
    {
        $scanner = new QuotedScanner();
        $scan = static fn (string $sql): Scan => new Scan(new Cursor($sql), new KeywordTable(['NCHAR' => 'NCHAR']));

        self::assertSame('SCONST', $scanner->scan($scan("'it''s' x"))?->name);
        self::assertSame("E'a\\'b'", $scanner->scan($scan("E'a\\'b' x"))?->text);
        self::assertSame('BCONST', $scanner->scan($scan("b'01'"))?->name);
        self::assertSame('XCONST', $scanner->scan($scan("X'ff'"))?->name);
        self::assertSame('USCONST', $scanner->scan($scan("U&'d\\0061t'"))?->name);
        self::assertSame('UIDENT', $scanner->scan($scan('u&"x"'))?->name);
        self::assertSame('IDENT', $scanner->scan($scan('"x""y"'))?->name);
        self::assertSame('SCONST', $scanner->scan($scan('$tag$ a $b$ $tag$'))?->name);
        self::assertNull($scanner->scan($scan('abc')));
    }

    public function testScanReadsTheNationalPrefixAsAKeyword(): void
    {
        $scan = new Scan(new Cursor("n'x'"), new KeywordTable(['NCHAR' => 'NCHAR']));
        $lexeme = (new QuotedScanner())->scan($scan);

        self::assertNotNull($lexeme);
        self::assertSame('NCHAR', $lexeme->name);
        self::assertSame('n', $lexeme->text);
        self::assertSame("'", $scan->cursor->peek());
    }

    public function testString(): void
    {
        $scan = new Scan(new Cursor("'a' -- c\n  'b' 'c'"), new KeywordTable([]));

        self::assertSame("'a' -- c\n  'b'", (new QuotedScanner())->string($scan, 'SCONST', 0, false)->text);
    }

    public function testStringRejectsAnUnterminatedString(): void
    {
        $this->expectException(LexicalException::class);

        (new QuotedScanner())->string(new Scan(new Cursor("'open"), new KeywordTable([])), 'SCONST', 0, false);
    }

    public function testBody(): void
    {
        $scanner = new QuotedScanner();
        $doubled = new Scan(new Cursor("a''b' rest"), new KeywordTable([]));
        $escaped = new Scan(new Cursor("a\\'b' rest"), new KeywordTable([]));
        $plain = new Scan(new Cursor("a'' rest"), new KeywordTable([]));
        $scanner->body($doubled, 0, false, true);
        $scanner->body($escaped, 0, true, true);
        $scanner->body($plain, 0, false, false);

        self::assertSame(' rest', $doubled->cursor->take(5));
        self::assertSame(' rest', $escaped->cursor->take(5));
        self::assertSame("' rest", $plain->cursor->take(6));
    }

    public function testIdentifier(): void
    {
        self::assertSame('"a""b"', (new QuotedScanner())->identifier(new Scan(new Cursor('"a""b" c'), new KeywordTable([])), 'IDENT', 0)->text);
    }

    public function testIdentifierRejectsAnEmptyIdentifier(): void
    {
        $this->expectException(LexicalException::class);

        (new QuotedScanner())->identifier(new Scan(new Cursor('""'), new KeywordTable([])), 'IDENT', 0);
    }

    public function testIdentifierRejectsAnUnterminatedIdentifier(): void
    {
        $this->expectException(LexicalException::class);

        (new QuotedScanner())->identifier(new Scan(new Cursor('"open'), new KeywordTable([])), 'IDENT', 0);
    }

    public function testDollarQuoted(): void
    {
        $scanner = new QuotedScanner();

        self::assertSame('$$a$$', $scanner->dollarQuoted(new Scan(new Cursor('$$a$$ b'), new KeywordTable([])))?->text);
        self::assertNull($scanner->dollarQuoted(new Scan(new Cursor('$1'), new KeywordTable([]))));
        self::assertNull($scanner->dollarQuoted(new Scan(new Cursor('$abc x'), new KeywordTable([]))));
    }

    public function testDollarQuotedRejectsAnUnterminatedString(): void
    {
        $this->expectException(LexicalException::class);

        (new QuotedScanner())->dollarQuoted(new Scan(new Cursor('$a$ open $b$'), new KeywordTable([])));
    }
}
