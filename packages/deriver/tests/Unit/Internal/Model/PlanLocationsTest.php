<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Model\PlanLocations
 */
#[CoversClass(\Deriver\Internal\Model\PlanLocations::class)]
#[UsesClass(\Deriver\Api\InvalidInputException::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\Model\PlanCompiler::class)]
#[UsesClass(\Deriver\Model\Binding\LocationRef::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class PlanLocationsTest extends TestCase
{
    public function testAddressPreservesAppendWithoutReadingStorage(): void
    {
        $compiler = new \Deriver\Internal\Model\PlanCompiler(new \Deriver\Api\Reference\SourceRef('test', 'model:test', 0, 1));
        $register = (new \Deriver\Internal\Model\PlanLocations($compiler))->address(\Deriver\Model\Binding\LocationRef::element(\Deriver\Model\Binding\LocationRef::parameter('items')));
        self::assertSame('m1', $register);
        self::assertSame(['local', 'element-address'], array_column($compiler->instructions[0], 'operation'));
        self::assertSame(['m0', ''], $compiler->instructions[0][1]->operands);
    }
    public function testAddressRejectsMalformedDescriptors(): void
    {
        $compiler = new \Deriver\Internal\Model\PlanCompiler(new \Deriver\Api\Reference\SourceRef('test', 'model:test', 0, 1));
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Internal\Model\PlanLocations($compiler))->address(new \Deriver\Model\Binding\LocationRef('invalid'));
    }
    public function testFromExpressionRecognizesOnlyAddressableExpressions(): void
    {
        $compiler = new \Deriver\Internal\Model\PlanCompiler(new \Deriver\Api\Reference\SourceRef('test', 'model:test', 0, 1));
        $locations = new \Deriver\Internal\Model\PlanLocations($compiler);
        self::assertSame('state', $locations->fromExpression(\Deriver\Model\Plan\Expression::state('domain.slot'))?->kind);
        self::assertNull($locations->fromExpression(\Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant(1))));
    }
}
