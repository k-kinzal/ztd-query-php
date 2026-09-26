<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Model\PlanInvocations
 */
#[CoversClass(\Deriver\Internal\Model\PlanInvocations::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\Model\PlanCompiler::class)]
#[UsesClass(\Deriver\Internal\Model\PlanLocations::class)]
#[UsesClass(\Deriver\Model\Binding\LocationRef::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\CallArgument::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class PlanInvocationsTest extends TestCase
{
    public function testApplyPreparesCallBeforeItsArguments(): void
    {
        $compiler = new \Deriver\Internal\Model\PlanCompiler(new \Deriver\Api\Reference\SourceRef('test', 'model:test', 0, 1));
        (new \Deriver\Internal\Model\PlanInvocations($compiler))->apply(\Deriver\Model\Plan\Action::invoke('@result', \Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant('consume')), [new \Deriver\Model\Plan\CallArgument(\Deriver\Model\Binding\LocationRef::parameter('value'))], true));
        self::assertSame(['constant', 'call-prepare', 'local', 'argument', 'invoke', 'local', 'returned-address', 'alias'], array_column($compiler->instructions[0], 'operation'));
        self::assertTrue($compiler->instructions[0][3]->attributes['address']);
    }
    public function testArgumentsPreservesNamedUnpackAndLocationMetadata(): void
    {
        $compiler = new \Deriver\Internal\Model\PlanCompiler(new \Deriver\Api\Reference\SourceRef('test', 'model:test', 0, 1));
        $arguments = (new \Deriver\Internal\Model\PlanInvocations($compiler))->arguments([new \Deriver\Model\Plan\CallArgument(\Deriver\Model\Binding\LocationRef::parameter('items'), unpack: true)], 'prepared');
        self::assertTrue($arguments[0]->unpack);
        self::assertSame($arguments[0]->register, $arguments[0]->location);
        self::assertTrue($compiler->instructions[0][1]->attributes['unpack-variable']);
    }
}
