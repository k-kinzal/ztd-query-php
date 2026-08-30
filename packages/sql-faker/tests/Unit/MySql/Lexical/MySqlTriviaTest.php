<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalException;
use SqlFaker\MySql\Lexical\MySqlTrivia;

#[CoversClass(MySqlTrivia::class)]
#[UsesClass(LexicalException::class)]
final class MySqlTriviaTest extends TestCase
{
    public function testSkipTriviaReportsWhenNothingWasSkipped(): void
    {
        $offset = 0;

        self::assertFalse((new MySqlTrivia())->skipTrivia('SELECT', $offset));
        self::assertSame(0, $offset);
    }

    public function testSkipTriviaReportsAnUnterminatedBlockComment(): void
    {
        $offset = 0;

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unterminated MySQL block comment.');

        (new MySqlTrivia())->skipTrivia('/* never closed', $offset);
    }

    public function testSkipQuotedTreatsADoubledQuoteAsAnEscape(): void
    {
        $offset = 0;

        (new MySqlTrivia())->skipQuoted("'a''b' rest", $offset, "'");

        self::assertSame(6, $offset);
    }

    public function testSkipQuotedReportsARunThatNeverCloses(): void
    {
        $offset = 0;

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unterminated MySQL quoted token');

        (new MySqlTrivia())->skipQuoted("'abc", $offset, "'");
    }

    public function testSkipTriviaPassesOverWhitespace(): void
    {
        $offset = 0;

        self::assertTrue((new MySqlTrivia())->skipTrivia("  \n SELECT", $offset));
        self::assertSame(4, $offset);
    }

    public function testSkipTriviaPassesOverALineCommentUpToItsNewline(): void
    {
        $offset = 0;

        self::assertTrue((new MySqlTrivia())->skipTrivia("# note\nSELECT", $offset));
        self::assertSame(7, $offset);
    }

    public function testSkipTriviaPassesOverABlockComment(): void
    {
        $offset = 0;

        self::assertTrue((new MySqlTrivia())->skipTrivia('/* note */SELECT', $offset));
        self::assertSame(10, $offset);
    }
}
