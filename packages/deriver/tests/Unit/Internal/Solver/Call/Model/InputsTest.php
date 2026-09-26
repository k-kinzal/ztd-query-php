<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Call\Model\Inputs
 */
#[CoversClass(\Deriver\Internal\Solver\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Model\PlanCompiler::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\Binding\LocationRef::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class InputsTest extends TestCase
{
    public function testBindingsNormalizesNamedDefaultsAndVariadicKeys(): void
    {
        $descriptor = new \Deriver\Model\ModelDescriptor('sample', '1', 'sample', new \Deriver\Model\Signature\Signature([new \Deriver\Model\Signature\Parameter('id', default: \Deriver\Value\Term::constant(3)), new \Deriver\Model\Signature\Parameter('rest', variadic: true)]));
        $instruction = new \Deriver\Internal\IR\Instruction('call', 'invoke', new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1));
        $bindings = (new \Deriver\Internal\Solver\Call\Model\Inputs())->bindings($descriptor, $instruction, [new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant(2), 'named')], new \Deriver\Internal\Solver\State());
        self::assertTrue($bindings->evaluated);
        self::assertFalse($bindings->arguments['id']->supplied);
        self::assertSame(3, $bindings->arguments['id']->value->native());
        self::assertSame(['named' => 2], $bindings->arguments['rest']->value->native());
    }
    public function testBindingsReadsCurrentReferenceCellWithoutExposingItsIdentity(): void
    {
        $descriptor = new \Deriver\Model\ModelDescriptor('sample', '1', 'sample', new \Deriver\Model\Signature\Signature([new \Deriver\Model\Signature\Parameter('id', byReference: true)]));
        $instruction = new \Deriver\Internal\IR\Instruction('call', 'invoke', new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1));
        $state = new \Deriver\Internal\Solver\State();
        $address = $state->local('source');
        $state->memory->write($address, \Deriver\Value\Term::constant(4));
        $bindings = (new \Deriver\Internal\Solver\Call\Model\Inputs())->bindings($descriptor, $instruction, [new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant(1), location: $address)], $state);
        self::assertSame(4, $bindings->arguments['id']->value->native());
        self::assertSame('id', $bindings->arguments['id']->location?->name);
    }
    public function testBindingsRetainsInvalidArgumentOrder(): void
    {
        $descriptor = new \Deriver\Model\ModelDescriptor('sample', '1', 'sample');
        $instruction = new \Deriver\Internal\IR\Instruction('call', 'invoke', new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1));
        $bindings = (new \Deriver\Internal\Solver\Call\Model\Inputs())->bindings($descriptor, $instruction, [new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant(1), 'absent')], new \Deriver\Internal\Solver\State());
        self::assertSame('Error', $bindings->error);
    }
    public function testValueReadsCurrentVariadicReferenceCells(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $location = $state->local('source');
        $state->memory->write($location, \Deriver\Value\Term::constant(4));
        $actual = new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::fromNative(['key' => 1]), elements: ['key' => new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant(1), location: $location)]);
        $value = (new \Deriver\Internal\Solver\Call\Model\Inputs())->value(new \Deriver\Model\Signature\Parameter('items', byReference: true, variadic: true), $actual, $state);
        self::assertSame(['key' => 4], $value->native());
    }
}
