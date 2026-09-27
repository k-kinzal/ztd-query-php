<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Frontend\Php\Cache\SyntaxTree
 */
#[CoversClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[Small]
final class SyntaxTreeTest extends TestCase
{
    public function testCopyPreventsSharedParserNodeMutation(): void
    {
        $tree = (new \Deriver\Internal\Frontend\Php\Cache\SyntaxCache())->parse('<?php return 1;', new \Deriver\Api\Project\TargetProfile());
        $copy = $tree->copy();
        self::assertNotSame($tree->nodes[0], $copy->nodes[0]);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Return_::class, $copy->nodes[0]);
        $changed = $copy->nodes[0];
        $changed->expr = new \PhpParser\Node\Scalar\Int_(99);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Return_::class, $tree->nodes[0]);
        self::assertInstanceOf(\PhpParser\Node\Scalar\Int_::class, $tree->nodes[0]->expr);
        self::assertSame(1, $tree->nodes[0]->expr->value);
    }
}
