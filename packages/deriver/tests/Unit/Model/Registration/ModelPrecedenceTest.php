<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Registration;

use Deriver\Exception\InvalidInputException;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Registration\ModelPrecedence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Registration\ModelPrecedence
 */
#[CoversClass(ModelPrecedence::class)]
#[UsesClass(InvalidInputException::class)]
#[UsesClass(ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[Small]
final class ModelPrecedenceTest extends TestCase
{
    public function testSelectUsesTransitiveReplacementIndependentlyOfOrder(): void
    {
        $a = new ModelDescriptor('a', '1', 'target');
        $b = new ModelDescriptor('b', '1', 'target', replaces: ['a']);
        $c = new ModelDescriptor('c', '1', 'target', replaces: ['b']);
        $precedence = new ModelPrecedence();
        self::assertSame('c', $precedence->select(['a' => $a, 'c' => $c, 'b' => $b]));
        self::assertSame('c', $precedence->select(['b' => $b, 'c' => $c, 'a' => $a]));
    }
    public function testSelectRejectsEqualMaxima(): void
    {
        $this->expectException(InvalidInputException::class);
        (new ModelPrecedence())->select(['a' => new ModelDescriptor('a', '1', 'target'), 'b' => new ModelDescriptor('b', '1', 'target')]);
    }
    public function testSelectAllowsOneHigherPriorityOverAnEqualLowerTier(): void
    {
        $a = new ModelDescriptor('a', '1', 'target');
        $b = new ModelDescriptor('b', '1', 'target');
        $c = new ModelDescriptor('c', '1', 'target', priority: 1);
        self::assertSame('c', (new ModelPrecedence())->select(['b' => $b, 'a' => $a, 'c' => $c]));
    }
    public function testAcyclicRejectsThreeNodeCycles(): void
    {
        $this->expectException(InvalidInputException::class);
        (new ModelPrecedence())->acyclic(['a' => ['b' => true], 'b' => ['c' => true], 'c' => ['a' => true]]);
    }
    public function testAcyclicAcceptsSharedDependencies(): void
    {
        (new ModelPrecedence())->acyclic(['a' => ['c' => true], 'b' => ['c' => true], 'c' => []]);
        self::assertSame('a', (new ModelPrecedence())->select(['a' => new ModelDescriptor('a', '1', 'target')]));
    }
}
