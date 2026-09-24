<?php

declare(strict_types=1);

namespace Tests\Unit\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Layout\Writer;
use SqlParser\Lexer\Token;

#[CoversClass(Writer::class)]
final class WriterTest extends TestCase
{
    public function testTokenPreservesCommentsAndLiteralNewlines(): void
    {
        $writer = new Writer();
        $writer->token(new Token(1, 'SELECT', 'SELECT', 0), "\n");
        $writer->token(new Token(2, 'STRING', "'a\nb'", 20, " -- note\n"), ' ');
        self::assertSame("SELECT -- note\n'a\nb' /* tail */", $writer->finish(' /* tail */'));
    }

    public function testFinishDropsOnlyWhitespaceTrivia(): void
    {
        $writer = new Writer();
        $writer->token(new Token(1, 'NUM', '1', 3, '   '), '');
        $writer->token(new Token(0, 'EOF', '', 4, "  \n"), ' ');
        self::assertSame('1', $writer->finish(" \n"));
    }
}
