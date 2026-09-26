<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Compact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFormatter\Core\Compact\Reductions::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Document::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Grouping::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Keywords::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Renderer::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Rules::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Shape::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Spacing::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Trivia::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Visitor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
final class SpacingTest extends TestCase
{
    public function testBetweenProtectsNumberExponentAndCommentBoundaries(): void
    {
        $spacing = new \SqlFormatter\Core\Compact\Spacing(new \SqlParser\PostgreSql\PostgreSqlParser(), \SqlFormatter\Facade\DialectFactory::forParser(new \SqlParser\PostgreSql\PostgreSqlParser())->compactRules());
        self::assertSame(' ', $spacing->between(new \SqlParser\Lexer\Token(1, 'ICONST', '1', 0), new \SqlParser\Lexer\Token(2, 'IDENT', 'e2', 2), null));
        self::assertSame(' ', $spacing->between(new \SqlParser\Lexer\Token(1, '-', '-', 0), new \SqlParser\Lexer\Token(1, '-', '-', 2), null));
        self::assertSame('', $spacing->between(new \SqlParser\Lexer\Token(1, 'IDENT', 'a', 0), new \SqlParser\Lexer\Token(2, '+', '+', 2), null));
    }

    public function testBetweenDoesNotMergeQuotedTokensOrOperatorTokens(): void
    {
        $spacing = new \SqlFormatter\Core\Compact\Spacing(new \SqlParser\PostgreSql\PostgreSqlParser(), \SqlFormatter\Facade\DialectFactory::forParser(new \SqlParser\PostgreSql\PostgreSqlParser())->compactRules());
        self::assertSame(' ', $spacing->between(new \SqlParser\Lexer\Token(1, 'SCONST', "'a'", 0), new \SqlParser\Lexer\Token(1, 'SCONST', "'b'", 4), null));
        self::assertSame(' ', $spacing->between(new \SqlParser\Lexer\Token(1, '/', '/', 0), new \SqlParser\Lexer\Token(2, '*', '*', 2), null));
    }

    public function testSignatureDistinguishesTokenMerging(): void
    {
        $spacing = new \SqlFormatter\Core\Compact\Spacing(new \SqlParser\PostgreSql\PostgreSqlParser(), \SqlFormatter\Facade\DialectFactory::forParser(new \SqlParser\PostgreSql\PostgreSqlParser())->compactRules());
        self::assertNotSame($spacing->signature('a b'), $spacing->signature('ab'));
        self::assertNotSame($spacing->signature("'a' 'b'"), $spacing->signature("'a''b'"));
        self::assertSame($spacing->signature('a + b'), $spacing->signature('a+b'));
    }
}
