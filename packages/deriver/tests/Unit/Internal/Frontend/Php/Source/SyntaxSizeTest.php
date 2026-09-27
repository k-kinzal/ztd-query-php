<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Frontend\Php\Source\SyntaxSize
 */
#[CoversClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[Small]
final class SyntaxSizeTest extends TestCase
{
    public function testMeasureCountsDepthBeforeRecursiveVisitorsRun(): void
    {
        $node = new \PhpParser\Node\Stmt\Return_(new \PhpParser\Node\Expr\UnaryMinus(new \PhpParser\Node\Scalar\Int_(1)));
        self::assertSame([3,3], (new \Deriver\Internal\Frontend\Php\Source\SyntaxSize())->measure([$node], new \Deriver\Api\Execution\SourceLimits()));
    }
    public function testMeasureRejectsExcessiveDepth(): void
    {
        $node = new \PhpParser\Node\Stmt\Return_(new \PhpParser\Node\Expr\UnaryMinus(new \PhpParser\Node\Scalar\Int_(1)));
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Internal\Frontend\Php\Source\SyntaxSize())->measure([$node], new \Deriver\Api\Execution\SourceLimits(depth: 2));
    }
    public function testMeasureRejectsExcessiveNodeCount(): void
    {
        $node = new \PhpParser\Node\Stmt\Return_(new \PhpParser\Node\Scalar\Int_(1));
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Internal\Frontend\Php\Source\SyntaxSize())->measure([$node], new \Deriver\Api\Execution\SourceLimits(nodes: 1));
    }
}
