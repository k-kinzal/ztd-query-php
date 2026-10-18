<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Call\Contract;

use Deriver\Deriver\Evaluator\Call\CallDescriptor;
use Deriver\Deriver\Evaluator\Declaration\Signature;
use Deriver\Deriver\Evaluator\Execution\Call\Contract\Inputs;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Sources\SourceRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Execution\Call\Contract\Inputs
 */
#[CoversClass(Inputs::class)]
#[UsesClass(\Deriver\Deriver\Lowerer\Graph\BasicBlock::class)]
#[UsesClass(\Deriver\Deriver\Lowerer\Graph\CallableGraph::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Deriver\Lowerer\Graph\Parameter::class)]
#[UsesClass(\Deriver\Deriver\Lowerer\Graph\Terminator::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Execution\Completion\Completion::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Execution\Memory\Location::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Execution\Memory\Materialization::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Execution\Memory\Memory::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Call\ArgumentBindings::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Call\BoundArgument::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationRef::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Call\Plan\PlanCompiler::class)]
#[UsesClass(CallDescriptor::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Call\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Declaration\Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Term::class)]
#[Small]
final class InputsTest extends TestCase
{
    public function testBindingsNormalizesNamedDefaultsAndVariadicKeys(): void
    {
        $descriptor = new CallDescriptor('sample', '1', 'sample', new Signature([new \Deriver\Deriver\Evaluator\Declaration\Parameter('id', default: Term::constant(3)), new \Deriver\Deriver\Evaluator\Declaration\Parameter('rest', variadic: true)]));
        $instruction = new \Deriver\Deriver\Evaluator\Call\BoundInstruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1));
        $bindings = (new Inputs())->bindings($descriptor, $instruction, [new PassedArgument(Term::constant(2), 'named')], new State());
        self::assertTrue($bindings->evaluated);
        self::assertFalse($bindings->arguments['id']->supplied);
        self::assertSame(3, $bindings->arguments['id']->value->native());
        self::assertSame(['named' => 2], $bindings->arguments['rest']->value->native());
    }
    public function testBindingsReadsCurrentReferenceCellWithoutExposingItsIdentity(): void
    {
        $descriptor = new CallDescriptor('sample', '1', 'sample', new Signature([new \Deriver\Deriver\Evaluator\Declaration\Parameter('id', byReference: true)]));
        $instruction = new \Deriver\Deriver\Evaluator\Call\BoundInstruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1));
        $state = new State();
        $address = $state->local('source');
        $state->memory->write($address, Term::constant(4));
        $bindings = (new Inputs())->bindings($descriptor, $instruction, [new PassedArgument(Term::constant(1), location: $address)], $state);
        self::assertSame(4, $bindings->arguments['id']->value->native());
        self::assertSame('id', $bindings->arguments['id']->location?->name);
    }
    public function testBindingsRetainsInvalidArgumentOrder(): void
    {
        $descriptor = new CallDescriptor('sample', '1', 'sample');
        $instruction = new \Deriver\Deriver\Evaluator\Call\BoundInstruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1));
        $bindings = (new Inputs())->bindings($descriptor, $instruction, [new PassedArgument(Term::constant(1), 'absent')], new State());
        self::assertSame('Error', $bindings->error);
    }
    public function testValueReadsCurrentVariadicReferenceCells(): void
    {
        $state = new State();
        $location = $state->local('source');
        $state->memory->write($location, Term::constant(4));
        $actual = new PassedArgument(Term::fromNative(['key' => 1]), elements: ['key' => new PassedArgument(Term::constant(1), location: $location)]);
        $value = (new Inputs())->value(new \Deriver\Deriver\Evaluator\Declaration\Parameter('items', byReference: true, variadic: true), $actual, $state);
        self::assertSame(['key' => 4], $value->native());
    }
}
