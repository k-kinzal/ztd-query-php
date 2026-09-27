<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Preparation;

use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Call\Preparation\Modes
 */
#[CoversClass(Modes::class)]
#[UsesClass(Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(Target::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(Machine::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(Term::class)]
#[Small]
final class ModesTest extends TestCase
{
    public function testPositionCountsExpandedPositionalArgumentsAndRetainsUnknownUnpack(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $machine = new Machine($context);
        $state = new State();
        $instruction = new Instruction('arg', 'argument', $body->source, 'result', ['prepared', 'input']);

        $state->registers['array'] = Term::fromNative([1,2]);
        $call = new Instruction('arg', 'argument', $body->source, 'result', arguments: [new Argument('array', unpack: true)]);
        $modes = new Modes($machine);
        self::assertSame(2, $modes->position($call, $state));
        $state->registers['array'] = Term::opaque('unknown', 'array');
        self::assertNull($modes->position($call, $state));
    }
    public function testSelectResolvesNamedAndVariadicReferenceParameters(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $machine = new Machine($context);
        $state = new State();
        $instruction = new Instruction('arg', 'argument', $body->source, 'result', ['prepared', 'input']);

        $modes = new Modes($machine);
        $signature = new CallableGraph('consume', [new Parameter('value'), new Parameter('refs', byReference: true, variadic: true)], [], $body->source);
        $target = new Target($signature);
        self::assertFalse($modes->select($target, 0, null));
        self::assertTrue($modes->select($target, 2, null));
        self::assertTrue($modes->select($target, 0, 'refs'));
        self::assertTrue($modes->select($target, 0, 'extra'));
        self::assertNull($modes->select($target, null, null));
        self::assertNull($modes->select(new Target(), 0, null));
    }
}
