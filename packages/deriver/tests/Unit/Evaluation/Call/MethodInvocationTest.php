<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\CatchTarget;
use Deriver\ControlFlow\ClassConstant;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Allocation;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Closure\Binding;
use Deriver\Evaluation\Call\Closure\Capture;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Constants;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\MethodInvocation;
use Deriver\Evaluation\Call\Model\Inputs;
use Deriver\Evaluation\Call\Model\NativeArguments;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Creation;
use Deriver\Evaluation\Call\Preparation\Methods;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\ProviderDispatch;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Constant\ClassNames;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionChain;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\Handler;
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
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\ConstantTransfer;
use Deriver\Evaluation\Transfer\IntrinsicTransfer;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\ObjectAccess;
use Deriver\Evaluation\Transfer\PropertyAccessCheck;
use Deriver\Evaluation\Transfer\PropertyLookup;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Evaluation\Transfer\PropertyTransfer;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Binding\ArgumentBindings;
use Deriver\Model\Binding\BoundArgument;
use Deriver\Model\Builtin\FunctionModel;
use Deriver\Model\Builtin\Library;
use Deriver\Model\Builtin\ScalarFunctions;
use Deriver\Model\CallDescription;
use Deriver\Model\Compilation\PlanActions;
use Deriver\Model\Compilation\PlanCompiler;
use Deriver\Model\Compilation\PlanFootprints;
use Deriver\Model\Compilation\PlanValidation;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Provider\DispatchProvider;
use Deriver\Model\Provider\DispatchRequest;
use Deriver\Model\Provider\DispatchTarget;
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
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\ExceptionLowering;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\ConstantSignatures;
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
use Deriver\Value\Arrays;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(MethodInvocation::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
#[UsesClass(ClassConstant::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Allocation::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Binding::class)]
#[UsesClass(Capture::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(Constants::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Inputs::class)]
#[UsesClass(NativeArguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Methods::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(ProviderDispatch::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(UnknownCall::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
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
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(IntrinsicTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PropertyTransfer::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(ArgumentBindings::class)]
#[UsesClass(BoundArgument::class)]
#[UsesClass(FunctionModel::class)]
#[UsesClass(Library::class)]
#[UsesClass(ScalarFunctions::class)]
#[UsesClass(CallDescription::class)]
#[UsesClass(PlanActions::class)]
#[UsesClass(PlanCompiler::class)]
#[UsesClass(PlanFootprints::class)]
#[UsesClass(PlanValidation::class)]
#[UsesClass(ModelDecision::class)]
#[UsesClass(ModelDescriptor::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(DispatchDecision::class)]
#[UsesClass(DispatchRequest::class)]
#[UsesClass(DispatchTarget::class)]
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
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
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
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class MethodInvocationTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class Box{public $value="first";function __clone(){$this->value="clone";}}function target(){$a=new Box;$b=clone $a;return [$a->value,$b->value];}');
        self::assertSame(['first', 'clone'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCandidatesRetainsEachClosedWorldImplementation(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php interface I{function run();}final class A implements I{function run(){return 1;}}final class B implements I{function run(){return 2;}}function target(I $value){return $value->run();}', new Configuration(closedWorld:true));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame([], $result->frontiers);
        self::assertSame([1,2], array_map(static fn (Alternative $a): int|float|string|bool|null => $a->values['return']->literal, $result->normalOutcomes));
    }
    public function testReceiverPreservesForwardedLateStaticCalls(): void
    {
        $state = new State();
        $state->lateStaticClass = 'Child';
        $invocation = new MethodInvocation(new Machine(SolverFixture::context()));
        self::assertNull($invocation->receiver(Term::constant('self'), $state, 'ParentClass', true));
        $invocation->receiver(Term::constant('Other'), $state, 'Other', true);
        self::assertSame('Child', $state->lateStaticClass);
    }
    public function testCalledClassForwardsRelativeNamesWithoutChangingCallerState(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $machine = new Machine($context);
        $state = new State();
        $instruction = new Instruction('arg', 'argument', $body->source, 'result', ['prepared', 'input']);

        $state->lateStaticClass = 'Child';
        $invocation = new MethodInvocation($machine);
        self::assertSame('Child', $invocation->calledClass(Term::constant('self'), $state, 'Base', true));
        self::assertSame('Other', $invocation->calledClass(Term::constant('Other'), $state, 'Other', true));
        self::assertSame('Child', $state->lateStaticClass);
    }
    /**
     * @param Term $name Unknown method spelling
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnknownNames')]
    public function testApplyRetainsUnresolvedMethodNamesAndBothCompletions(Term $name): void
    {
        $context = SolverFixture::context();
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $state->registers = ['receiver' => Term::parameter('object', 'object'),'name' => $name];
        $paths = (new MethodInvocation(new Machine($context)))->apply($caller, new Instruction('call', 'invoke-method', $caller->source, 'result', ['receiver','name']), $state, []);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame(['OPEN_DISPATCH'], array_column($context->frontiers, 'code'));
    }

    /**
     * @return array<string,array{Term}>
     */
    public static function providerUnknownNames(): array
    {
        return ['symbolic' => [Term::parameter('method', 'string')],'nonstring' => [Term::constant(1)]];
    }

    /**
     * @param Term $receiver Invalid instance receiver
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidReceivers')]
    public function testApplyRaisesAnErrorForConcreteNonObjectReceivers(Term $receiver): void
    {
        $context = SolverFixture::context();
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $state->registers = ['receiver' => $receiver,'name' => Term::constant('run')];
        $paths = (new MethodInvocation(new Machine($context)))->apply($caller, new Instruction('call', 'invoke-method', $caller->source, 'result', ['receiver','name']), $state, []);
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return array<string,array{Term}>
     */
    public static function providerInvalidReceivers(): array
    {
        return ['integer' => [Term::constant(1)],'array' => [Term::array([])],'null' => [Term::constant(null)]];
    }

    public function testCandidatesKeepsApplicableProviderTargetsAndTheirReceiverOverrides(): void
    {
        $context = SolverFixture::context('<?php class Impl{public int $value;function run(){return $this->value;}}function target(){}');
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $original = new Term('object', 'original', attributes:['class' => 'Impl']);
        $replacement = new Term('object', 'replacement', attributes:['class' => 'Impl']);
        $state->memory->write(new Location('object:original', ['value']), Term::constant(1));
        $state->memory->write(new Location('object:replacement', ['value']), Term::constant(7));
        $decision = new DispatchDecision([
            new DispatchTarget('missing', condition:Term::constant(false)),
            new DispatchTarget('Impl::run', $replacement),
            new DispatchTarget('Impl::run', condition:Term::constant(true)),
        ], true);
        $paths = (new MethodInvocation(new Machine($context)))->candidates($caller, new Instruction('call', 'invoke-method', $caller->source, 'result'), $state, [], $original, 'Service', 'run', $decision);
        self::assertCount(2, $paths);
        self::assertSame([7,1], array_map(static fn (State $path): int|float|string|bool|null => $path->registers['result']->literal, $paths));
        self::assertSame(['normal','normal'], array_column(array_column($paths, 'completion'), 'kind'));
        self::assertSame([], $context->frontiers);
        self::assertNotSame($paths[0]->memory, $paths[1]->memory);
    }

    /**
     * @param bool $closedWorld Closed project assumption
     * @param bool $exhaustive Provider completeness
     * @param string $modifier Captured class finality
     * @param int $expectedCount Source result plus any open normal and exceptional paths
     * @param list<string> $codes Expected boundaries
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerWorldBounds')]
    public function testCandidatesUsesOnlyExplicitCompletenessBounds(bool $closedWorld, bool $exhaustive, string $modifier, int $expectedCount, array $codes): void
    {
        $context = SolverFixture::context('<?php '.$modifier.' class Box{function run(){return 1;}}function target(){}', configuration:new Configuration(closedWorld:$closedWorld));
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $paths = (new MethodInvocation(new Machine($context)))->candidates($caller, new Instruction('call', 'invoke-method', $caller->source, 'result'), new State(), [], Term::parameter('box', 'Box'), 'BoX', 'run', new DispatchDecision(exhaustive:$exhaustive));
        self::assertCount($expectedCount, $paths);
        self::assertSame(1, $paths[0]->registers['result']->native());
        self::assertSame($codes, array_column($context->frontiers, 'code'));
    }

    /**
     * @return array<string,array{bool,bool,string,int,list<string>}>
     */
    public static function providerWorldBounds(): array
    {
        return ['open' => [false,false,'',3,['OPEN_DISPATCH']],'closed project' => [true,false,'',1,[]],'complete provider' => [false,true,'',1,[]],'final declaration' => [false,false,'final',1,[]]];
    }

    /**
     * @param string $operation Method syntax
     * @param Term $receiver Concrete or unresolved runtime receiver
     * @param int $expected Selected source or provider result
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerProviderSelection')]
    public function testApplyUsesProvidersForUnresolvedTargetsAndKeepsKnownSourceTargets(string $operation, Term $receiver, int $expected): void
    {
        $provider = self::createStub(DispatchProvider::class);
        $provider->method('id')->willReturn('example.dispatch');
        $provider->method('version')->willReturn('1');
        $provider->method('resolve')->willReturn(new DispatchDecision([new DispatchTarget('provided')], true));
        $context = SolverFixture::context('<?php class Box{static function run(){return 1;}}function provided(){return 2;}function target(){}', configuration:new Configuration(providers:[$provider]));
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $state->registers = ['receiver' => $receiver,'name' => Term::constant('run')];
        $paths = (new MethodInvocation(new Machine($context)))->apply($caller, new Instruction('call', $operation, $caller->source, 'result', ['receiver','name']), $state, []);
        self::assertCount(1, $paths);
        self::assertSame($expected, $paths[0]->registers['result']->native());
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return array<string,array{string,Term,int}>
     */
    public static function providerProviderSelection(): array
    {
        return [
            'known static' => ['invoke-static',Term::constant('Box'),1],
            'known object' => ['invoke-method',new Term('object', 'box', attributes:['class' => 'Box']),1],
            'unknown static' => ['invoke-static',Term::constant('Missing'),2],
            'unknown object' => ['invoke-method',new Term('object', 'box', attributes:['class' => 'Missing']),2],
            'symbolic receiver' => ['invoke-method',Term::parameter('receiver', 'Missing'),2],
        ];
    }

    public function testCandidatesKeepsAnUnresolvedBoundaryWhenNoFeasibleImplementationExists(): void
    {
        $context = SolverFixture::context(configuration:new Configuration(closedWorld:true));
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $paths = (new MethodInvocation(new Machine($context)))->candidates($caller, new Instruction('call', 'invoke-method', $caller->source, 'result'), new State(), [], Term::parameter('receiver', 'Missing'), 'Missing', 'run', new DispatchDecision([new DispatchTarget('missing', condition:Term::constant(false))], true));
        self::assertCount(2, $paths);
        self::assertSame(['OPEN_DISPATCH'], array_column($context->frontiers, 'code'));
    }

    public function testReceiverBindsTheCurrentInstanceForStaticSyntaxOnlyWhenOneExists(): void
    {
        $invocation = new MethodInvocation(new Machine(SolverFixture::context()));
        $state = new State();
        $receiver = new Term('object', 'explicit', attributes:['class' => 'Box']);
        $current = new Term('object', 'current', attributes:['class' => 'Box']);
        $state->memory->write($state->local('this'), $current);
        self::assertSame($receiver, $invocation->receiver($receiver, $state, 'Box', false));
        self::assertSame($current, $invocation->receiver(Term::constant('self'), $state, 'Box', true));
        self::assertNull($invocation->receiver(Term::constant('Box'), new State(), 'Box', true));
    }

    /**
     * @param Term $receiver Spelled target
     * @param bool $static Invocation syntax
     * @param string $expected Called class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCalledClasses')]
    public function testCalledClassForwardsEveryRelativeSpellingWithoutRebindingInstanceCalls(Term $receiver, bool $static, string $expected): void
    {
        $state = new State();
        $state->lateStaticClass = 'Child';
        self::assertSame($expected, (new MethodInvocation(new Machine(SolverFixture::context())))->calledClass($receiver, $state, 'Resolved', $static));
    }

    /**
     * @return array<string,array{Term,bool,string}>
     */
    public static function providerCalledClasses(): array
    {
        return ['self' => [Term::constant('SELF'),true,'Child'],'parent' => [Term::constant('PARENT'),true,'Child'],'static' => [Term::constant('STATIC'),true,'Child'],'explicit' => [Term::constant('Box'),true,'Resolved'],'instance' => [Term::constant('self'),false,'Resolved'],'symbolic class' => [new Term('opaque'),true,'Resolved']];
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProviderExternal(\Tests\Fake\Programs\DynamicClassPrograms::class, 'methods')]
    public function testApplySeparatesLiteralAndRuntimeClassOperands(string $source, string $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->projectDiagnostics);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expected, true, flags:JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }
}
