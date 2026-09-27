<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Model;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\Model\Inputs;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\State;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Signature\Signature;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Call\Model\Inputs
 */
#[CoversClass(Inputs::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\Binding\LocationRef::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanCompiler::class)]
#[UsesClass(ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Term::class)]
#[Small]
final class InputsTest extends TestCase
{
    public function testBindingsNormalizesNamedDefaultsAndVariadicKeys(): void
    {
        $descriptor = new ModelDescriptor('sample', '1', 'sample', new Signature([new \Deriver\Model\Signature\Parameter('id', default: Term::constant(3)), new \Deriver\Model\Signature\Parameter('rest', variadic: true)]));
        $instruction = new Instruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1));
        $bindings = (new Inputs())->bindings($descriptor, $instruction, [new PassedArgument(Term::constant(2), 'named')], new State());
        self::assertTrue($bindings->evaluated);
        self::assertFalse($bindings->arguments['id']->supplied);
        self::assertSame(3, $bindings->arguments['id']->value->native());
        self::assertSame(['named' => 2], $bindings->arguments['rest']->value->native());
    }
    public function testBindingsReadsCurrentReferenceCellWithoutExposingItsIdentity(): void
    {
        $descriptor = new ModelDescriptor('sample', '1', 'sample', new Signature([new \Deriver\Model\Signature\Parameter('id', byReference: true)]));
        $instruction = new Instruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1));
        $state = new State();
        $address = $state->local('source');
        $state->memory->write($address, Term::constant(4));
        $bindings = (new Inputs())->bindings($descriptor, $instruction, [new PassedArgument(Term::constant(1), location: $address)], $state);
        self::assertSame(4, $bindings->arguments['id']->value->native());
        self::assertSame('id', $bindings->arguments['id']->location?->name);
    }
    public function testBindingsRetainsInvalidArgumentOrder(): void
    {
        $descriptor = new ModelDescriptor('sample', '1', 'sample');
        $instruction = new Instruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1));
        $bindings = (new Inputs())->bindings($descriptor, $instruction, [new PassedArgument(Term::constant(1), 'absent')], new State());
        self::assertSame('Error', $bindings->error);
    }
    public function testValueReadsCurrentVariadicReferenceCells(): void
    {
        $state = new State();
        $location = $state->local('source');
        $state->memory->write($location, Term::constant(4));
        $actual = new PassedArgument(Term::fromNative(['key' => 1]), elements: ['key' => new PassedArgument(Term::constant(1), location: $location)]);
        $value = (new Inputs())->value(new \Deriver\Model\Signature\Parameter('items', byReference: true, variadic: true), $actual, $state);
        self::assertSame(['key' => 4], $value->native());
    }
}
