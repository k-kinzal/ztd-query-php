<?php

declare(strict_types=1);

namespace Tests\Unit\Sqlite\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Cursor;
use SqlParser\Sqlite\Lexer\KeywordTable;
use SqlParser\Sqlite\Lexer\Scan;
use SqlParser\Sqlite\Lexer\TriviaScanner;

#[CoversClass(TriviaScanner::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(Scan::class)]
#[Small]
final class TriviaScannerTest extends TestCase
{
    public function testSkip(): void
    {
        $scan = new Scan(new Cursor("\xEF\xBB\xBF \t-- c\n/* b */ x"), new KeywordTable([]));
        (new TriviaScanner())->skip($scan);

        self::assertSame('x', $scan->cursor->take(1));
    }

    public function testSkipRunsAnUnterminatedCommentToTheEnd(): void
    {
        $scan = new Scan(new Cursor('/* open'), new KeywordTable([]));
        (new TriviaScanner())->skip($scan);

        self::assertTrue($scan->cursor->eof());
    }

    public function testSkipLeavesASlashBeforeTheEndAlone(): void
    {
        $scan = new Scan(new Cursor('/*'), new KeywordTable([]));
        (new TriviaScanner())->skip($scan);

        self::assertSame('/', $scan->cursor->peek());
    }
}
