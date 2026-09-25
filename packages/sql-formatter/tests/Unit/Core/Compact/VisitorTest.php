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
final class VisitorTest extends TestCase
{
    public function testTokensPreservesIdentifierRolesAndExecutableBodies(): void
    {
        $name = new \SqlParser\Lexer\Token(1, 'ACTION', 'Action', 0);
        $keyword = new \SqlParser\Lexer\Token(2, 'SELECT', 'select', 10);
        $tree = new \SqlParser\Parser\Node('root', 0, [new \SqlParser\Parser\Node('ident', 0, [$name]), $keyword]);
        $visitor = new \SqlFormatter\Core\Compact\Visitor([0 => $name, 10 => $keyword], [], [], (new \SqlFormatter\Platform\MySql\Dialect())->compactRules());
        self::assertSame(['Action', 'SELECT'], array_column($visitor->tokens($tree), 'text'));
        $protected = new \SqlFormatter\Core\Compact\Visitor([0 => $name, 10 => $keyword], [10 => true], [10 => true], (new \SqlFormatter\Platform\MySql\Dialect())->compactRules());
        self::assertSame(['Action', 'select'], array_column($protected->tokens($tree), 'text'));
    }
}
