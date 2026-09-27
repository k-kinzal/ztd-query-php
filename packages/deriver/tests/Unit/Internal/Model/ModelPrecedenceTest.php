<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Model\ModelPrecedence
 */
#[CoversClass(\Deriver\Internal\Model\ModelPrecedence::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[Small]
final class ModelPrecedenceTest extends TestCase
{
    public function testSelectUsesTransitiveReplacementIndependentlyOfOrder(): void
    {
        $a = new \Deriver\Model\ModelDescriptor('a', '1', 'target');
        $b = new \Deriver\Model\ModelDescriptor('b', '1', 'target', replaces: ['a']);
        $c = new \Deriver\Model\ModelDescriptor('c', '1', 'target', replaces: ['b']);
        $precedence = new \Deriver\Internal\Model\ModelPrecedence();
        self::assertSame('c', $precedence->select(['a' => $a, 'c' => $c, 'b' => $b]));
        self::assertSame('c', $precedence->select(['b' => $b, 'c' => $c, 'a' => $a]));
    }
    public function testSelectRejectsEqualMaxima(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Internal\Model\ModelPrecedence())->select(['a' => new \Deriver\Model\ModelDescriptor('a', '1', 'target'), 'b' => new \Deriver\Model\ModelDescriptor('b', '1', 'target')]);
    }
    public function testSelectAllowsOneHigherPriorityOverAnEqualLowerTier(): void
    {
        $a = new \Deriver\Model\ModelDescriptor('a', '1', 'target');
        $b = new \Deriver\Model\ModelDescriptor('b', '1', 'target');
        $c = new \Deriver\Model\ModelDescriptor('c', '1', 'target', priority: 1);
        self::assertSame('c', (new \Deriver\Internal\Model\ModelPrecedence())->select(['b' => $b, 'a' => $a, 'c' => $c]));
    }
    public function testAcyclicRejectsThreeNodeCycles(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Internal\Model\ModelPrecedence())->acyclic(['a' => ['b' => true], 'b' => ['c' => true], 'c' => ['a' => true]]);
    }
    public function testAcyclicAcceptsSharedDependencies(): void
    {
        (new \Deriver\Internal\Model\ModelPrecedence())->acyclic(['a' => ['c' => true], 'b' => ['c' => true], 'c' => []]);
        self::assertSame('a', (new \Deriver\Internal\Model\ModelPrecedence())->select(['a' => new \Deriver\Model\ModelDescriptor('a', '1', 'target')]));
    }
}
