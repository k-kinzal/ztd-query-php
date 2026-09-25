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
final class ShapeTest extends TestCase
{
    public function testOfRetainsOperandNestingButIgnoresTransparentWrappers(): void
    {
        $a = new \SqlParser\Lexer\Token(1, 'ID', 'a', 0);
        $b = new \SqlParser\Lexer\Token(1, 'ID', 'b', 2);
        $c = new \SqlParser\Lexer\Token(1, 'ID', 'c', 4);
        $left = new \SqlParser\Parser\Node('expr', 0, [new \SqlParser\Parser\Node('expr', 0, [$a, $b]), $c]);
        $right = new \SqlParser\Parser\Node('expr', 0, [$a, new \SqlParser\Parser\Node('expr', 0, [$b, $c])]);
        $shape = new \SqlFormatter\Core\Compact\Shape([0 => $a, 2 => $b, 4 => $c], []);
        self::assertNotSame($shape->of($left), $shape->of($right));
        self::assertSame($shape->of($a), $shape->of(new \SqlParser\Parser\Node('expr', 7, [$a])));
        self::assertNull($shape->of(new \SqlParser\Parser\Node('empty', 0, [])));
    }
    public function testOfDistinguishesAliasAndStringConcatenationGrammarOwners(): void
    {
        $parser = new \SqlParser\MySql\MySqlParser();
        $alias = new \SqlFormatter\Core\Compact\Document($parser->parse("SELECT 'a' AS 'b'"), (new \SqlFormatter\Platform\MySql\Dialect())->compactRules());
        $concatenation = new \SqlFormatter\Core\Compact\Document($parser->parse("SELECT 'a' 'b'"), (new \SqlFormatter\Platform\MySql\Dialect())->compactRules());
        self::assertNotSame($alias->signature(), $concatenation->signature());
    }
}
