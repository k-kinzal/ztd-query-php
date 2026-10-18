<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Call\CallDescriptor;
use Deriver\Deriver\Evaluator\Call\Precedence;
use Deriver\Exception\InvalidInputException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Call\Precedence
 */
#[CoversClass(Precedence::class)]
#[UsesClass(InvalidInputException::class)]
#[UsesClass(CallDescriptor::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Declaration\Signature::class)]
#[Small]
final class PrecedenceTest extends TestCase
{
    public function testSelectUsesTransitiveReplacementIndependentlyOfOrder(): void
    {
        $a = new CallDescriptor('a', '1', 'target');
        $b = new CallDescriptor('b', '1', 'target', replaces: ['a']);
        $c = new CallDescriptor('c', '1', 'target', replaces: ['b']);
        $precedence = new Precedence();
        self::assertSame('c', $precedence->select(['a' => $a, 'c' => $c, 'b' => $b]));
        self::assertSame('c', $precedence->select(['b' => $b, 'c' => $c, 'a' => $a]));
    }
    public function testSelectRejectsEqualMaxima(): void
    {
        $this->expectException(InvalidInputException::class);
        (new Precedence())->select(['a' => new CallDescriptor('a', '1', 'target'), 'b' => new CallDescriptor('b', '1', 'target')]);
    }
    public function testSelectAllowsOneHigherPriorityOverAnEqualLowerTier(): void
    {
        $a = new CallDescriptor('a', '1', 'target');
        $b = new CallDescriptor('b', '1', 'target');
        $c = new CallDescriptor('c', '1', 'target', priority: 1);
        self::assertSame('c', (new Precedence())->select(['b' => $b, 'a' => $a, 'c' => $c]));
    }
    public function testAcyclicRejectsThreeNodeCycles(): void
    {
        $this->expectException(InvalidInputException::class);
        (new Precedence())->acyclic(['a' => ['b' => true], 'b' => ['c' => true], 'c' => ['a' => true]]);
    }
    public function testAcyclicAcceptsSharedDependencies(): void
    {
        (new Precedence())->acyclic(['a' => ['c' => true], 'b' => ['c' => true], 'c' => []]);
        self::assertSame('a', (new Precedence())->select(['a' => new CallDescriptor('a', '1', 'target')]));
    }
}
