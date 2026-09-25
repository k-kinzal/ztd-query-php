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
final class RulesTest extends TestCase
{
    public function testApplyRemovesOnlyDefaultsOwnedByTheSelectedGrammarRule(): void
    {
        $distinct = new \SqlParser\Lexer\Token(1, 'DISTINCT', 'DISTINCT', 0);
        $quantifier = new \SqlParser\Parser\Node('set_quantifier', 1, [$distinct]);
        self::assertSame([], (new \SqlFormatter\Platform\PostgreSql\Dialect())->compactRules()->rules->apply($quantifier, [$distinct], new \SqlParser\Parser\Node('simple_select', 0, [])));
        self::assertSame([$distinct], (new \SqlFormatter\Platform\PostgreSql\Dialect())->compactRules()->rules->apply($quantifier, [$distinct], new \SqlParser\Parser\Node('group_clause', 0, [])));
    }

    public function testApplyDoesNotRemoveCastAsInsideAProjection(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('SELECT CAST(a AS text)');
        $target = $tree->find('target_el')[0];
        self::assertSame($target->tokens(), (new \SqlFormatter\Platform\PostgreSql\Dialect())->compactRules()->rules->apply($target, $target->tokens(), null));
    }

    public function testDefaultDoesNotDropSetAllOrDescendingOrdering(): void
    {
        self::assertFalse((new \SqlFormatter\Platform\MySql\Dialect())->compactRules()->rules->default('union_option', ['ALL'], null));
        self::assertFalse((new \SqlFormatter\Platform\Sqlite\Dialect())->compactRules()->rules->default('sortorder', ['DESC'], null));
        self::assertTrue((new \SqlFormatter\Platform\Sqlite\Dialect())->compactRules()->rules->default('sortorder', ['ASC'], null));
    }
}
