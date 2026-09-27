<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Transfer;

use Deriver\Api\Project\Configuration;
use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
use Deriver\Internal\Solver\Transfer\IntrinsicTransfer;
use Deriver\Model\Intrinsic\IntrinsicDescriptor;
use Deriver\Model\Intrinsic\PureIntrinsic;
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
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\PlanActions::class)]
#[UsesClass(\Deriver\Internal\Model\PlanCompiler::class)]
#[UsesClass(\Deriver\Internal\Model\PlanFootprints::class)]
#[UsesClass(\Deriver\Internal\Model\PlanValidation::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\NativeArguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\CollectionCalls::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Cell::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Components::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\Havoc::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ExternalTransfer::class)]
#[UsesClass(IntrinsicTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(IntrinsicDescriptor::class)]
#[UsesClass(PureIntrinsic::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\FunctionModel::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(\Deriver\Standard\ScalarFunctions::class)]
#[UsesClass(\Deriver\Standard\StringFunctions::class)]
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
        $target = new \Deriver\Api\Project\TargetProfile();
        $intrinsic = self::createMock(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('second', '1', 'custom', 2, [0,1]));
        $intrinsic->expects(self::once())->method('evaluate')->with([$a,$b], self::identicalTo($target))->willReturn($result);
        $context = SolverFixture::context(configuration:new Configuration(target:$target, intrinsics:[$first,$intrinsic]));
        $source = new SourceRef('snapshot', 'model:intrinsic', 9, 15);
        $instruction = new Instruction('intrinsic', 'intrinsic', $source, 'result', ['a','b'], 'custom');
        $state = new State();
        $state->registers['a'] = $a;
        $state->registers['b'] = $b;
        $paths = (new IntrinsicTransfer(new Machine($context)))->apply(new CallableIR('model', [], [], $source), $instruction, $state);
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
        $paths = (new IntrinsicTransfer(new Machine($context)))->apply(new CallableIR('model', [], [], $source), $instruction, $state);
        self::assertSame([$state], $paths);
        self::assertSame($result, $state->registers['result']);
        self::assertCount($frontiers, $context->frontiers);
    }

    /**
     * @return iterable<string,array{Term,int}>
     */
    public static function providerIntrinsicResiduals(): iterable
    {
        yield 'contract failure' => [Term::opaque('MODEL_CONTRACT_VIOLATION'),1];
        yield 'ordinary residual' => [Term::opaque('CUSTOM_UNKNOWN'),0];
        yield 'literal with failure spelling' => [Term::constant('MODEL_CONTRACT_VIOLATION'),0];
    }

    public function testApplyAttachesContractFailureToTheInstructionAndItsDependencies(): void
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
        (new IntrinsicTransfer(new Machine($context)))->apply(new CallableIR('model', [], [], $source), $instruction, $state);
        self::assertSame('opaque', $state->registers['result']->kind);
        self::assertSame('MODEL_CONTRACT_VIOLATION', $state->registers['result']->literal);
        self::assertTrue($state->registers['result']->isSecret());
        self::assertCount(1, $context->frontiers);
        $frontier = array_values($context->frontiers)[0];
        self::assertSame($source, $frontier->at);
        self::assertSame('custom', $frontier->operation);
        self::assertNotNull($frontier->residual);
        self::assertSame([$input], $frontier->residual->operands);
    }

    #[DataProvider('providerScalarSecrecy')]
    public function testApplyPreservesScalarResultMetadataAndArgumentConfidentiality(bool $secret): void
    {
        $context = SolverFixture::context();
        $source = new SourceRef('snapshot', 'model:scalar', 2, 5);
        $instruction = new Instruction('intrinsic', 'intrinsic', $source, 'result', ['input'], 'strlen');
        $state = new State();
        $state->registers['input'] = Term::constant('bytes', $secret);
        self::assertSame([$state], (new IntrinsicTransfer(new Machine($context)))->apply(new CallableIR('model', [], [], $source), $instruction, $state));
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
        $caller = new CallableIR('model', [new \Deriver\Internal\IR\Parameter('reference', byReference:true),new \Deriver\Internal\IR\Parameter('value'),new \Deriver\Internal\IR\Parameter('missing')], [], $source);
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
        $paths = (new IntrinsicTransfer(new Machine($context)))->apply(new CallableIR('model', [], [], $source), $instruction, $state);
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
