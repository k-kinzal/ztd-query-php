<?php

declare(strict_types=1);

namespace Tests\Unit\PostgreSql\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\LexicalException;
use SqlParser\PostgreSql\Lexer\KeywordTable;
use SqlParser\PostgreSql\Lexer\Scan;
use SqlParser\PostgreSql\Lexer\TriviaScanner;

#[CoversClass(TriviaScanner::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(Scan::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
final class TriviaScannerTest extends TestCase
{
    public function testSkip(): void
    {
        $scan = new Scan(new Cursor(" \t-- note\n/* outer /* inner */ still */x"), new KeywordTable([]));
        (new TriviaScanner())->skip($scan);

        self::assertSame('x', $scan->cursor->take(1));
    }

    public function testBlockComment(): void
    {
        $scan = new Scan(new Cursor('/* a */b'), new KeywordTable([]));
        (new TriviaScanner())->blockComment($scan);

        self::assertSame('b', $scan->cursor->take(1));
    }

    public function testBlockCommentRejectsAnUnterminatedComment(): void
    {
        $this->expectException(LexicalException::class);

        (new TriviaScanner())->blockComment(new Scan(new Cursor('/* /* */'), new KeywordTable([])));
    }
}
