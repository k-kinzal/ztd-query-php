<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\SourceLimits;
use Deriver\Source\SyntaxSize;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\SyntaxSize
 */
#[CoversClass(SyntaxSize::class)]
#[UsesClass(SourceLimits::class)]
#[Small]
final class SyntaxSizeTest extends TestCase
{
    public function testMeasureCountsDepthBeforeRecursiveVisitorsRun(): void
    {
        $node = new \PhpParser\Node\Stmt\Return_(new \PhpParser\Node\Expr\UnaryMinus(new \PhpParser\Node\Scalar\Int_(1)));
        self::assertSame([3,3], (new SyntaxSize())->measure([$node], new SourceLimits()));
    }
    public function testMeasureRejectsExcessiveDepth(): void
    {
        $node = new \PhpParser\Node\Stmt\Return_(new \PhpParser\Node\Expr\UnaryMinus(new \PhpParser\Node\Scalar\Int_(1)));
        $this->expectException(InvalidInputException::class);
        (new SyntaxSize())->measure([$node], new SourceLimits(depth: 2));
    }
    public function testMeasureRejectsExcessiveNodeCount(): void
    {
        $node = new \PhpParser\Node\Stmt\Return_(new \PhpParser\Node\Scalar\Int_(1));
        $this->expectException(InvalidInputException::class);
        (new SyntaxSize())->measure([$node], new SourceLimits(nodes: 1));
    }
}
