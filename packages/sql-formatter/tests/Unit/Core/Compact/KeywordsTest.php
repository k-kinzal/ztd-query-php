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
final class KeywordsTest extends TestCase
{
    public function testTextPreservesKeywordIdentifiersAndLiteralBytes(): void
    {
        $keyword = new \SqlParser\Lexer\Token(1, 'ACTION', 'Action', 0);
        self::assertSame('Action', (new \SqlFormatter\Platform\MySql\Dialect())->compactRules()->keywords->text($keyword, true));
        self::assertSame('ACTION', (new \SqlFormatter\Platform\MySql\Dialect())->compactRules()->keywords->text($keyword, false));
        $literal = new \SqlParser\Lexer\Token(2, 'SCONST', "'MiXeD'", 0);
        self::assertSame("'MiXeD'", (new \SqlFormatter\Platform\MySql\Dialect())->compactRules()->keywords->text($literal, false));
        self::assertTrue((new \SqlFormatter\Platform\PostgreSql\Dialect())->compactRules()->keywords->identifier('ColLabel'));
        self::assertFalse((new \SqlFormatter\Platform\PostgreSql\Dialect())->compactRules()->keywords->identifier('expr'));
    }

    public function testIdentifierRecognizesGrammarNameRoles(): void
    {
        self::assertTrue((new \SqlFormatter\Platform\PostgreSql\Dialect())->compactRules()->keywords->identifier('ColLabel'));
        self::assertFalse((new \SqlFormatter\Platform\PostgreSql\Dialect())->compactRules()->keywords->identifier('expr'));
    }

    public function testFallbackPreservesSqliteIdentifierSpelling(): void
    {
        $identifier = new \SqlParser\Lexer\Token(1, 'ACTION', 'Action', 0);
        self::assertTrue((new \SqlFormatter\Platform\Sqlite\Dialect())->compactRules()->keywords->fallback($identifier, new \SqlParser\Parser\Node('expr', 2, [$identifier])));
        $constant = new \SqlParser\Lexer\Token(2, 'CTIME_KW', 'current_timestamp', 0);
        self::assertFalse((new \SqlFormatter\Platform\Sqlite\Dialect())->compactRules()->keywords->fallback($constant, new \SqlParser\Parser\Node('expr', 0, [$constant])));
    }
}
