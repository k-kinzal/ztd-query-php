<?php

declare(strict_types=1);

namespace Tests\Unit\MySql\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\LexicalException;
use SqlParser\MySql\Lexer\KeywordTable;
use SqlParser\MySql\Lexer\Scan;
use SqlParser\MySql\Lexer\TriviaScanner;
use SqlParser\MySql\MySqlVersion;
use SqlParser\MySql\SqlMode;

#[CoversClass(TriviaScanner::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(MySqlVersion::class)]
#[UsesClass(Scan::class)]
#[UsesClass(SqlMode::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Resource\SqlVersion::class)]
#[UsesClass(\SqlParser\Resource\VersionRegistry::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class TriviaScannerTest extends TestCase
{
    public function testSkipPassesWhitespaceAndComments(): void
    {
        $scan = new Scan(new Cursor("  # hash\n-- dash \n\t/* block */ --x"), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());
        (new TriviaScanner())->skip($scan);

        self::assertSame('--x', $scan->cursor->take(3));
    }

    public function testSkipReadsAVersionCommentBodyAsSql(): void
    {
        $scan = new Scan(new Cursor('/*!80000 FORCE */ x'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve('mysql-8.4.7'));
        $trivia = new TriviaScanner();
        $trivia->skip($scan);

        self::assertSame('FORCE', $scan->cursor->take(5));
        self::assertTrue($scan->inVersionComment);
        $trivia->skip($scan);
        self::assertSame('x', $scan->cursor->take(1));
        self::assertFalse($scan->inVersionComment);
    }

    public function testSkipDropsAVersionCommentForANewerRelease(): void
    {
        $scan = new Scan(new Cursor('/*!90000 FORCE */ x'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve('mysql-8.4.7'));
        (new TriviaScanner())->skip($scan);

        self::assertSame('x', $scan->cursor->take(1));
        self::assertFalse($scan->inVersionComment);
    }

    public function testBlockComment(): void
    {
        $scan = new Scan(new Cursor('/*+ hint */x'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());
        (new TriviaScanner())->blockComment($scan);

        self::assertSame('x', $scan->cursor->take(1));
    }

    public function testBlockCommentRejectsAnUnterminatedComment(): void
    {
        $scan = new Scan(new Cursor('/* open'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());

        $this->expectException(LexicalException::class);

        (new TriviaScanner())->blockComment($scan);
    }
}
