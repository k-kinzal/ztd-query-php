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
final class VisitorTest extends TestCase
{
    public function testTokensPreservesIdentifierRolesAndExecutableBodies(): void
    {
        $name = new \SqlParser\Lexer\Token(1, 'ACTION', 'Action', 0);
        $keyword = new \SqlParser\Lexer\Token(2, 'SELECT', 'select', 10);
        $tree = new \SqlParser\Parser\Node('root', 0, [new \SqlParser\Parser\Node('ident', 0, [$name]), $keyword]);
        $visitor = new \SqlFormatter\Compact\Visitor([0 => $name, 10 => $keyword], [], [], true);
        self::assertSame(['Action', 'SELECT'], array_column($visitor->tokens($tree), 'text'));
        $protected = new \SqlFormatter\Compact\Visitor([0 => $name, 10 => $keyword], [10 => true], [10 => true], true);
        self::assertSame(['Action', 'select'], array_column($protected->tokens($tree), 'text'));
    }
}
