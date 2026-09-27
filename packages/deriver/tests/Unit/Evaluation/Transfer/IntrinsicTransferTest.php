<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\IntrinsicTransfer;
use Deriver\Model\Intrinsic\IntrinsicDescriptor;
use Deriver\Model\Intrinsic\PureIntrinsic;
use Deriver\Project\Configuration;
use Deriver\Project\TargetProfile;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fake\SolverFixture;

#[CoversClass(IntrinsicTransfer::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\NativeArguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\CollectionCalls::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\Havoc::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ExternalTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\Builtin\FunctionModel::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Builtin\ScalarFunctions::class)]
#[UsesClass(\Deriver\Model\Builtin\StringFunctions::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanActions::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanCompiler::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanFootprints::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanValidation::class)]
#[UsesClass(IntrinsicDescriptor::class)]
#[UsesClass(\Deriver\Model\Intrinsic\IntrinsicEvaluation::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(\Deriver\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
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
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class IntrinsicTransferTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return array_map(fn($x)=>$x*2,[1,2,3]);}');
        self::assertSame([2, 4, 6], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    public function testApplySelectsTheRegisteredIntrinsicAndPassesOrderedValuesAndTarget(): void
    {
        $first = self::createStub(PureIntrinsic::class);
        $first->method('descriptor')->willReturn(new IntrinsicDescriptor('first', '1', 'other', 0, []));
        $a = Term::constant(17);
        $b = Term::constant(5);
        $result = new Term('domain', 'custom', ['left' => $a,'right' => $b], ['type' => 'int']);
        $target = new TargetProfile();
        $intrinsic = self::createMock(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('second', '1', 'custom', 2, [0,1]));
        $intrinsic->expects(self::once())->method('evaluate')->with([$a,$b], self::identicalTo($target))->willReturn($result);
        $context = SolverFixture::context(configuration:new Configuration(target:$target, intrinsics:[$first,$intrinsic]));
        $source = new SourceRef('snapshot', 'model:intrinsic', 9, 15);
        $instruction = new Instruction('intrinsic', 'intrinsic', $source, 'result', ['a','b'], 'custom');
        $state = new State();
        $state->registers['a'] = $a;
        $state->registers['b'] = $b;
        $paths = (new IntrinsicTransfer(new Machine($context)))->apply(new CallableGraph('model', [], [], $source), $instruction, $state);
        self::assertSame([$state], $paths);
        self::assertSame($result, $state->registers['result']);
        self::assertSame('normal', $state->completion->kind);
        self::assertSame([], $context->frontiers);
    }

    #[DataProvider('providerIntrinsicResiduals')]
    public function testApplyRecordsOnlyContractFailuresAsModelFrontiers(Term $result, int $frontiers): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('model', '1', 'custom', 1, [0]));
        $intrinsic->method('evaluate')->willReturn($result);
        $context = SolverFixture::context(configuration:new Configuration(intrinsics:[$intrinsic]));
        $source = new SourceRef('snapshot', 'model:intrinsic', 9, 15);
        $instruction = new Instruction('intrinsic', 'intrinsic', $source, 'result', ['input'], 'custom');
        $input = Term::constant(17);
        $state = new State();
        $state->registers['input'] = $input;
        $paths = (new IntrinsicTransfer(new Machine($context)))->apply(new CallableGraph('model', [], [], $source), $instruction, $state);
        self::assertSame([$state], $paths);
        self::assertSame($result, $state->registers['result']);
        self::assertCount($frontiers, $context->frontiers);
    }

    /**
     * @return iterable<string,array{Term,int}>
     */
    public static function providerIntrinsicResiduals(): iterable
    {
        yield 'opaque reason is not an exception' => [Term::opaque('MODEL_CONTRACT_VIOLATION'),0];
        yield 'ordinary residual' => [Term::opaque('CUSTOM_UNKNOWN'),0];
        yield 'literal with failure spelling' => [Term::constant('MODEL_CONTRACT_VIOLATION'),0];
    }

    public function testApplyPropagatesIntrinsicFailures(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('broken', '1', 'custom', 1, [0]));
        $intrinsic->method('evaluate')->willThrowException(new RuntimeException('failed'));
        $context = SolverFixture::context(configuration:new Configuration(intrinsics:[$intrinsic]));
        $source = new SourceRef('snapshot', 'model:intrinsic', 9, 15);
        $instruction = new Instruction('intrinsic', 'intrinsic', $source, 'result', ['input'], 'custom');
        $input = Term::constant(17, true);
        $state = new State();
        $state->registers['input'] = $input;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('failed');
        (new IntrinsicTransfer(new Machine($context)))->apply(new CallableGraph('model', [], [], $source), $instruction, $state);
    }

    #[DataProvider('providerScalarSecrecy')]
    public function testApplyPreservesScalarResultMetadataAndArgumentConfidentiality(bool $secret): void
    {
        $context = SolverFixture::context();
        $source = new SourceRef('snapshot', 'model:scalar', 2, 5);
        $instruction = new Instruction('intrinsic', 'intrinsic', $source, 'result', ['input'], 'strlen');
        $state = new State();
        $state->registers['input'] = Term::constant('bytes', $secret);
        self::assertSame([$state], (new IntrinsicTransfer(new Machine($context)))->apply(new CallableGraph('model', [], [], $source), $instruction, $state));
        self::assertSame('constant', $state->registers['result']->kind);
        self::assertSame(5, $state->registers['result']->literal);
        self::assertSame($secret, $state->registers['result']->isSecret());
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{bool}>
     */
    public static function providerScalarSecrecy(): iterable
    {
        yield 'public' => [false];
        yield 'confidential' => [true];
    }

    public function testApplyUnknownOverloadsRetainPossibleReferenceEffectsAndExceptions(): void
    {
        $context = SolverFixture::context();
        $source = new SourceRef('snapshot', 'model:unsupported', 2, 5);
        $instruction = new Instruction('intrinsic', 'intrinsic', $source, 'result', name:'custom_missing');
        $state = new State();
        $state->locals['reference'] = $state->memory->allocate(Term::constant(7));
        $state->locals['value'] = $state->memory->allocate(Term::constant(11));
        $caller = new CallableGraph('model', [new Parameter('reference', byReference:true),new Parameter('value'),new Parameter('missing')], [], $source);
        $paths = (new IntrinsicTransfer(new Machine($context)))->apply($caller, $instruction, $state);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('Throwable', $paths[1]->completion->value?->literal);
        self::assertSame('opaque', $paths[0]->memory->read($state->locals['reference'])->kind);
        self::assertSame(11, $paths[0]->memory->read($state->locals['value'])->literal);
        self::assertSame('UNSUPPORTED_MODEL_CASE', $paths[0]->registers['result']->literal);
        self::assertSame([7,11,'UNKNOWN_ARGUMENT'], array_column($paths[0]->registers['result']->operands, 'literal'));
        self::assertSame(['UNSUPPORTED_MODEL_CASE'], array_column(array_values($context->frontiers), 'code'));
    }

    /**
     * @param array<string,Term> $inputs Ordered bound intrinsic arguments
     */
    #[DataProvider('providerExternalInputs')]
    public function testApplyRoutesEnvironmentAndRandomOperationsThroughTheirDeclaredSemantics(string $name, array $inputs, int|string $expected): void
    {
        $context = SolverFixture::context(configuration:new Configuration(environment:['time' => Term::constant(123),'microtime' => Term::constant('captured'),'env:KEY' => Term::constant('value')]));
        $source = new SourceRef('snapshot', 'model:external', 2, 5);
        $state = new State();
        $state->registers = $inputs;
        $instruction = new Instruction('intrinsic', 'intrinsic', $source, 'result', array_keys($inputs), $name);
        $paths = (new IntrinsicTransfer(new Machine($context)))->apply(new CallableGraph('model', [], [], $source), $instruction, $state);
        self::assertSame([$state], $paths);
        self::assertSame($expected, $state->registers['result']->literal);
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{string,array<string,Term>,int|string}>
     */
    public static function providerExternalInputs(): iterable
    {
        yield 'time' => ['time',[],123];
        yield 'microtime' => ['microtime',[],'captured'];
        yield 'environment' => ['getenv',['key' => Term::constant('KEY')],'value'];
        yield 'random_int' => ['random_int',['min' => Term::constant(7),'max' => Term::constant(7)],7];
        yield 'rand' => ['rand',['min' => Term::constant(7),'max' => Term::constant(7)],7];
        yield 'mt_rand' => ['mt_rand',['min' => Term::constant(7),'max' => Term::constant(7)],7];
    }
}
