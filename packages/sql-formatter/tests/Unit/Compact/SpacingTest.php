<?php

declare(strict_types=1);

namespace Tests\Unit\Compact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFormatter\Compact\Reductions::class)]
#[CoversClass(\SqlFormatter\Compact\Document::class)]
#[CoversClass(\SqlFormatter\Compact\Grouping::class)]
#[CoversClass(\SqlFormatter\Compact\Keywords::class)]
#[CoversClass(\SqlFormatter\Compact\Renderer::class)]
#[CoversClass(\SqlFormatter\Compact\Rules::class)]
#[CoversClass(\SqlFormatter\Compact\Shape::class)]
#[CoversClass(\SqlFormatter\Compact\Spacing::class)]
#[CoversClass(\SqlFormatter\Compact\Trivia::class)]
#[CoversClass(\SqlFormatter\Compact\Visitor::class)]
final class SpacingTest extends TestCase
{
    public function testBetweenProtectsNumberExponentAndCommentBoundaries(): void
    {
        $spacing = new \SqlFormatter\Compact\Spacing(new \SqlParser\PostgreSql\PostgreSqlParser());
        self::assertSame(' ', $spacing->between(new \SqlParser\Lexer\Token(1, 'ICONST', '1', 0), new \SqlParser\Lexer\Token(2, 'IDENT', 'e2', 2), null));
        self::assertSame(' ', $spacing->between(new \SqlParser\Lexer\Token(1, '-', '-', 0), new \SqlParser\Lexer\Token(1, '-', '-', 2), null));
        self::assertSame('', $spacing->between(new \SqlParser\Lexer\Token(1, 'IDENT', 'a', 0), new \SqlParser\Lexer\Token(2, '+', '+', 2), null));
    }

    public function testBetweenDoesNotMergeQuotedTokensOrOperatorTokens(): void
    {
        $spacing = new \SqlFormatter\Compact\Spacing(new \SqlParser\PostgreSql\PostgreSqlParser());
        self::assertSame(' ', $spacing->between(new \SqlParser\Lexer\Token(1, 'SCONST', "'a'", 0), new \SqlParser\Lexer\Token(1, 'SCONST', "'b'", 4), null));
        self::assertSame(' ', $spacing->between(new \SqlParser\Lexer\Token(1, '/', '/', 0), new \SqlParser\Lexer\Token(2, '*', '*', 2), null));
    }

    public function testSignatureDistinguishesTokenMerging(): void
    {
        $spacing = new \SqlFormatter\Compact\Spacing(new \SqlParser\PostgreSql\PostgreSqlParser());
        self::assertNotSame($spacing->signature('a b'), $spacing->signature('ab'));
        self::assertNotSame($spacing->signature("'a' 'b'"), $spacing->signature("'a''b'"));
        self::assertSame($spacing->signature('a + b'), $spacing->signature('a+b'));
    }
}
