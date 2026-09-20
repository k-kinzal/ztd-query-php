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
use SqlParser\Sqlite\Lexer\OperatorScanner;
use SqlParser\Sqlite\Lexer\Scan;
use SqlParser\Sqlite\Lexer\TriviaScanner;
use SqlParser\Sqlite\Lexer\WordScanner;

#[CoversClass(WordScanner::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(OperatorScanner::class)]
#[UsesClass(Scan::class)]
#[UsesClass(TriviaScanner::class)]
#[Small]
final class WordScannerTest extends TestCase
{
    public function testScan(): void
    {
        $scanner = new WordScanner();
        $keywords = new KeywordTable(['SELECT' => 'SELECT', 'WINDOW' => 'WINDOW', 'AS' => 'AS']);
        $trivia = new TriviaScanner();

        self::assertSame('SELECT', $scanner->scan(new Scan(new Cursor('select 1'), $keywords), $trivia)?->name);
        self::assertSame('a$b', $scanner->scan(new Scan(new Cursor('a$b '), $keywords), $trivia)?->text);
        self::assertSame('WINDOW', $scanner->scan(new Scan(new Cursor('WINDOW w AS ()'), $keywords), $trivia)?->name);
        self::assertSame('ID', $scanner->scan(new Scan(new Cursor('WINDOW FROM'), $keywords), $trivia)?->name);
        self::assertNull($scanner->scan(new Scan(new Cursor('1a'), $keywords), $trivia));
    }

    public function testContextual(): void
    {
        $scanner = new WordScanner();
        $keywords = new KeywordTable(['WINDOW' => 'WINDOW', 'AS' => 'AS', 'OVER' => 'OVER', 'FILTER' => 'FILTER']);
        $trivia = new TriviaScanner();
        $afterParen = new Scan(new Cursor(' (x)'), $keywords);
        $afterParen->lexemes[] = new Lexeme('RP', ')', 0);
        $afterName = new Scan(new Cursor(' (x)'), $keywords);
        $afterName->lexemes[] = new Lexeme('ID', 'x', 0);
        $beforeName = new Scan(new Cursor(' w'), $keywords);
        $beforeName->lexemes[] = new Lexeme('RP', ')', 0);

        self::assertSame('OVER', $scanner->contextual($afterParen, 'OVER', $trivia));
        self::assertSame('FILTER', $scanner->contextual($afterParen, 'FILTER', $trivia));
        self::assertSame('ID', $scanner->contextual($afterName, 'OVER', $trivia));
        self::assertSame('OVER', $scanner->contextual($beforeName, 'OVER', $trivia));
        self::assertSame('ID', $scanner->contextual($beforeName, 'FILTER', $trivia));
        self::assertSame('WINDOW', $scanner->contextual(new Scan(new Cursor(" 'w' AS"), $keywords), 'WINDOW', $trivia));
        self::assertSame('ID', $scanner->contextual(new Scan(new Cursor(' w'), $keywords), 'WINDOW', $trivia));
    }

    public function testAhead(): void
    {
        $scanner = new WordScanner();
        $scan = new Scan(new Cursor("x /* c */ 'y' AS 1"), new KeywordTable(['AS' => 'AS']));
        $scan->cursor->take(1);

        self::assertSame(['ID', 'AS', 'LITERAL'], $scanner->ahead($scan, new TriviaScanner(), 3));
        self::assertSame(['ID', 'AS', 'LITERAL', null], $scanner->ahead($scan, new TriviaScanner(), 4));
        self::assertSame(1, $scan->cursor->offset());
    }

    public function testProbe(): void
    {
        $scanner = new WordScanner();
        $keywords = new KeywordTable(['ABORT' => 'ABORT', 'SELECT' => 'SELECT', 'LEFT' => 'JOIN_KW']);
        $probe = static fn (string $sql): Scan => new Scan(new Cursor($sql), $keywords, ['ABORT' => true]);

        self::assertSame('ID', $scanner->probe($probe('abort')));
        self::assertSame('ID', $scanner->probe($probe('left')));
        self::assertSame('SELECT', $scanner->probe($probe('select')));
        self::assertSame('ID', $scanner->probe($probe('"q"')));
        self::assertSame('ID', $scanner->probe($probe('[q]')));
        self::assertSame('BLOB', $scanner->probe($probe("x'ff'")));
        self::assertSame('LITERAL', $scanner->probe($probe('1.5')));
        self::assertSame('LITERAL', $scanner->probe($probe(':v')));
        self::assertSame('LP', $scanner->probe($probe('(')));
        self::assertSame('ILLEGAL', $scanner->probe($probe('!')));
    }

    public function testSkipQuoted(): void
    {
        $scanner = new WordScanner();
        $closed = new Cursor("'a''b' c");
        $open = new Cursor("'a");
        $scanner->skipQuoted($closed, "'");
        $scanner->skipQuoted($open, "'");

        self::assertSame(' c', $closed->take(2));
        self::assertTrue($open->eof());
    }
}
