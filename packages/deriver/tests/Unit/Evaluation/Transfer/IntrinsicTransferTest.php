<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Model\Inputs;
use Deriver\Evaluation\Call\Model\NativeArguments;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\CollectionCalls;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Components;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\CallableTransfer;
use Deriver\Evaluation\Transfer\ExternalTransfer;
use Deriver\Evaluation\Transfer\IntrinsicTransfer;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Binding\ArgumentBindings;
use Deriver\Model\Binding\BoundArgument;
use Deriver\Model\Builtin\FunctionModel;
use Deriver\Model\Builtin\Library;
use Deriver\Model\Builtin\ScalarFunctions;
use Deriver\Model\Builtin\StringFunctions;
use Deriver\Model\CallDescription;
use Deriver\Model\Compilation\PlanActions;
use Deriver\Model\Compilation\PlanCompiler;
use Deriver\Model\Compilation\PlanFootprints;
use Deriver\Model\Compilation\PlanValidation;
use Deriver\Model\Intrinsic\IntrinsicDescriptor;
use Deriver\Model\Intrinsic\PureIntrinsic;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Assessment;
use Deriver\Result\Derivation;
use Deriver\Result\DerivationResult;
use Deriver\Result\Frontier;
use Deriver\Result\Serialization\JsonText;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Result\Serialization\ValueGraph;
use Deriver\Result\Statistics;
use Deriver\Result\StorageSnapshot;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\AggregateLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\EffectInspection;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Arithmetic;
use Deriver\Value\Arrays;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
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
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Inputs::class)]
#[UsesClass(NativeArguments::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(UnknownCall::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(CollectionCalls::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(Havoc::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(ExternalTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(ArgumentBindings::class)]
#[UsesClass(BoundArgument::class)]
#[UsesClass(FunctionModel::class)]
#[UsesClass(Library::class)]
#[UsesClass(ScalarFunctions::class)]
#[UsesClass(StringFunctions::class)]
#[UsesClass(CallDescription::class)]
#[UsesClass(PlanActions::class)]
#[UsesClass(PlanCompiler::class)]
#[UsesClass(PlanFootprints::class)]
#[UsesClass(PlanValidation::class)]
#[UsesClass(IntrinsicDescriptor::class)]
#[UsesClass(\Deriver\Model\Intrinsic\IntrinsicEvaluation::class)]
#[UsesClass(ModelDecision::class)]
#[UsesClass(ModelDescriptor::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(ProjectSnapshot::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(JsonText::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(AggregateLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
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
