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
final class RulesTest extends TestCase
{
    public function testApplyRemovesOnlyDefaultsOwnedByTheSelectedGrammarRule(): void
    {
        $distinct = new \SqlParser\Lexer\Token(1, 'DISTINCT', 'DISTINCT', 0);
        $quantifier = new \SqlParser\Parser\Node('set_quantifier', 1, [$distinct]);
        self::assertSame([], \SqlFormatter\Compact\Rules::apply($quantifier, [$distinct], new \SqlParser\Parser\Node('simple_select', 0, [])));
        self::assertSame([$distinct], \SqlFormatter\Compact\Rules::apply($quantifier, [$distinct], new \SqlParser\Parser\Node('group_clause', 0, [])));
    }

    public function testApplyDoesNotRemoveCastAsInsideAProjection(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('SELECT CAST(a AS text)');
        $target = $tree->find('target_el')[0];
        self::assertSame($target->tokens(), \SqlFormatter\Compact\Rules::apply($target, $target->tokens(), null));
    }

    public function testDefaultDoesNotDropSetAllOrDescendingOrdering(): void
    {
        self::assertFalse(\SqlFormatter\Compact\Rules::default('union_option', ['ALL'], null));
        self::assertFalse(\SqlFormatter\Compact\Rules::default('sortorder', ['DESC'], null));
        self::assertTrue(\SqlFormatter\Compact\Rules::default('sortorder', ['ASC'], null));
    }
}
