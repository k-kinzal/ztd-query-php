<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Cache;

use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\Cache\SyntaxTree
 */
#[CoversClass(SyntaxTree::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[Small]
final class SyntaxTreeTest extends TestCase
{
    public function testCopyPreventsSharedParserNodeMutation(): void
    {
        $tree = (new SyntaxCache())->parse('<?php return 1;', new TargetProfile());
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
