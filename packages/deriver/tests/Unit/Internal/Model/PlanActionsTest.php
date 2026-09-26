<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Model\PlanActions
 */
#[CoversClass(\Deriver\Internal\Model\PlanActions::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Model\PlanCompiler::class)]
#[UsesClass(\Deriver\Internal\Model\PlanLocations::class)]
#[UsesClass(\Deriver\Model\Binding\LocationRef::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[Small]
final class PlanActionsTest extends TestCase
{
    public function testApplyReturnsAReferenceThroughCoreMemory(): void
    {
        $compiler = new \Deriver\Internal\Model\PlanCompiler(new \Deriver\Api\Reference\SourceRef('test', 'model:test', 0, 1));
        self::assertTrue((new \Deriver\Internal\Model\PlanActions($compiler))->apply(\Deriver\Model\Plan\Action::returnReference(\Deriver\Model\Binding\LocationRef::parameter('value'))));
        self::assertSame(['local', 'reference'], array_column($compiler->instructions[0], 'operation'));
        self::assertSame('return', $compiler->terminators[0]->kind);
    }
    public function testApplyRejectsUnknownActions(): void
    {
        $compiler = new \Deriver\Internal\Model\PlanCompiler(new \Deriver\Api\Reference\SourceRef('test', 'model:test', 0, 1));
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Internal\Model\PlanActions($compiler))->apply(new \Deriver\Model\Plan\Action('invalid'));
    }
}
