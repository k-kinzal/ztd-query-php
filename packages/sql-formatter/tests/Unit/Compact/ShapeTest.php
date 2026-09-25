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
final class ShapeTest extends TestCase
{
    public function testOfRetainsOperandNestingButIgnoresTransparentWrappers(): void
    {
        $a = new \SqlParser\Lexer\Token(1, 'ID', 'a', 0);
        $b = new \SqlParser\Lexer\Token(1, 'ID', 'b', 2);
        $c = new \SqlParser\Lexer\Token(1, 'ID', 'c', 4);
        $left = new \SqlParser\Parser\Node('expr', 0, [new \SqlParser\Parser\Node('expr', 0, [$a, $b]), $c]);
        $right = new \SqlParser\Parser\Node('expr', 0, [$a, new \SqlParser\Parser\Node('expr', 0, [$b, $c])]);
        $shape = new \SqlFormatter\Compact\Shape([0 => $a, 2 => $b, 4 => $c], []);
        self::assertNotSame($shape->of($left), $shape->of($right));
        self::assertSame($shape->of($a), $shape->of(new \SqlParser\Parser\Node('expr', 7, [$a])));
        self::assertNull($shape->of(new \SqlParser\Parser\Node('empty', 0, [])));
    }
    public function testOfDistinguishesAliasAndStringConcatenationGrammarOwners(): void
    {
        $parser = new \SqlParser\MySql\MySqlParser();
        $alias = new \SqlFormatter\Compact\Document($parser->parse("SELECT 'a' AS 'b'"), true, false);
        $concatenation = new \SqlFormatter\Compact\Document($parser->parse("SELECT 'a' 'b'"), true, false);
        self::assertNotSame($alias->signature(), $concatenation->signature());
    }
}
