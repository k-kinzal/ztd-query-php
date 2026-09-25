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
final class KeywordsTest extends TestCase
{
    public function testTextPreservesKeywordIdentifiersAndLiteralBytes(): void
    {
        $keyword = new \SqlParser\Lexer\Token(1, 'ACTION', 'Action', 0);
        self::assertSame('Action', \SqlFormatter\Compact\Keywords::text($keyword, true, true));
        self::assertSame('ACTION', \SqlFormatter\Compact\Keywords::text($keyword, false, true));
        $literal = new \SqlParser\Lexer\Token(2, 'SCONST', "'MiXeD'", 0);
        self::assertSame("'MiXeD'", \SqlFormatter\Compact\Keywords::text($literal, false, false));
        self::assertTrue(\SqlFormatter\Compact\Keywords::identifier('ColLabel'));
        self::assertFalse(\SqlFormatter\Compact\Keywords::identifier('expr'));
    }

    public function testIdentifierRecognizesGrammarNameRoles(): void
    {
        self::assertTrue(\SqlFormatter\Compact\Keywords::identifier('ColLabel'));
        self::assertFalse(\SqlFormatter\Compact\Keywords::identifier('expr'));
    }

    public function testFallbackPreservesSqliteIdentifierSpelling(): void
    {
        $identifier = new \SqlParser\Lexer\Token(1, 'ACTION', 'Action', 0);
        self::assertTrue(\SqlFormatter\Compact\Keywords::fallback($identifier, new \SqlParser\Parser\Node('expr', 2, [$identifier])));
        $constant = new \SqlParser\Lexer\Token(2, 'CTIME_KW', 'current_timestamp', 0);
        self::assertFalse(\SqlFormatter\Compact\Keywords::fallback($constant, new \SqlParser\Parser\Node('expr', 0, [$constant])));
    }
}
