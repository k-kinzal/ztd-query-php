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
use Deriver\Evaluation\Operation\CallablePredicate;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\CallableTransfer;
use Deriver\Evaluation\Transfer\ConstantTransfer;
use Deriver\Evaluation\Transfer\IntrinsicTransfer;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\ObjectAccess;
use Deriver\Evaluation\Transfer\PropertyAccessCheck;
use Deriver\Evaluation\Transfer\PropertyLookup;
use Deriver\Evaluation\Transfer\PropertyReference;
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
use Deriver\Model\Binding\LocationRef;
use Deriver\Model\Builtin\FunctionModel;
use Deriver\Model\Builtin\Library;
use Deriver\Model\Builtin\ScalarFunctions;
use Deriver\Model\Builtin\StringFunctions;
use Deriver\Model\Builtin\TypePredicates;
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
use Deriver\Model\Provider\DispatchRequest;
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
use Deriver\Source\Compilation\Control\ConditionalLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\ExceptionLowering;
use Deriver\Source\Compilation\EffectInspection;
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
use Deriver\Value\Arithmetic;
use Deriver\Value\Arrays;
use Deriver\Value\Comparison;
use Deriver\Value\Identity;
use Deriver\Value\Increment;
use Deriver\Value\NumericString;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(CallExecutor::class)]
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
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Allocation::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Binding::class)]
#[UsesClass(Capture::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(MethodInvocation::class)]
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
#[UsesClass(CallablePredicate::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(IntrinsicTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertyReference::class)]
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
#[UsesClass(LocationRef::class)]
#[UsesClass(FunctionModel::class)]
#[UsesClass(Library::class)]
#[UsesClass(ScalarFunctions::class)]
#[UsesClass(StringFunctions::class)]
#[UsesClass(TypePredicates::class)]
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
#[UsesClass(ConditionalLowering::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(EffectInspection::class)]
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
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Increment::class)]
#[UsesClass(NumericString::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class CallExecutorTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testInstructionPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function tagged(string $tag,int $id) { return $tag . ":" . $id; } function target() { return [tagged("user",1),tagged("item",2)]; }');
        self::assertSame(['user:1', 'item:2'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testInvokeBindsCapturedClosureValues(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$x=2;$f=fn($n)=>$x+$n;$x=9;return $f(3);}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(5, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSymbolPropagatesACompletedReferenceWriteBackToTheCaller(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function f(&$x){$x=2;return 3;}function target(){$x=1;$r=f($x);return [$x,$r];}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([2,3], $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testObjectInvokesAnObjectCallable(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{function __invoke($x){return $x+1;}}function target(){$f=new B;return $f(3);}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(4, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @param string $source Independently verified callable fixture
     * @param string $expectedJson Complete normal return observations
     * @throws JsonException If fixture observations cannot be decoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCallableForms')]
    public function testInvokePreservesCallableBindingIdentityAndCallingContext(string $source, string $expectedJson): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        $expected = json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($expected);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected[0], $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerCallableForms(): array
    {
        return array_map(static fn (array $fixture): array => [$fixture[0],$fixture[1]], \Tests\Fake\Programs\CallablePrograms::cases());
    }

    public function testInvokeUnwrapsAResolvedCallableAndPropagatesStrictArgumentErrors(): void
    {
        $context = SolverFixture::context('<?php declare(strict_types=1);function helper(int $x){return $x;}function target(){}');
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $site = new Instruction('call', 'invoke', $caller->source, 'result');
        $target = new Term('callable', operands:[Term::constant('helper')]);
        $paths = (new CallExecutor(new Machine($context)))->invoke($target, [new PassedArgument(Term::constant('2'))], new State(), $site, $caller);
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('TypeError', $paths[0]->completion->value?->literal);
    }

    public function testSymbolRestoresCallerLocalsAndKeepsEveryExitEffectAndExplanation(): void
    {
        $context = SolverFixture::context('<?php function helper(&$x,$flag){$x=2;if($flag){return 3;}$x=4;throw new Exception;}function target(){}');
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $state->memory->write($state->local('callerValue'), Term::constant(1));
        $state->registers['old'] = Term::constant('preserved');
        $state->evidence = ['caller:first','caller:second'];
        $state->controls = ['control:first','control:second'];
        $state->guard = ['outer' => true];
        $state->constraints = ['input' => ['min' => 0,'max' => 10,'equal' => null,'excluded' => []]];
        $state->observed = true;
        $context->summaries->history = ['outer-call'];
        $site = new Instruction('call', 'invoke', $caller->source, 'result');
        $paths = (new CallExecutor(new Machine($context)))->symbol('helper', [new PassedArgument(Term::constant(1), location:$state->local('callerValue')),new PassedArgument(Term::parameter('flag', 'bool'))], $state, $site);
        self::assertCount(2, $paths);
        self::assertEqualsCanonicalizing(['normal','throw'], array_column(array_column($paths, 'completion'), 'kind'));
        self::assertEqualsCanonicalizing([2,4], array_map(static fn (State $path): int|float|string|bool|null => $path->memory->read($path->local('callerValue'))->literal, $paths));
        self::assertSame(['callerValue'], array_keys($paths[0]->locals));
        self::assertSame('preserved', $paths[0]->registers['old']->native());
        self::assertTrue($paths[0]->observed);
        self::assertTrue($paths[1]->observed);
        self::assertTrue($paths[0]->guard['outer']);
        self::assertNotSame($paths[0]->guard, $paths[1]->guard);
        self::assertSame($state->constraints['input'], $paths[0]->constraints['input']);
        self::assertContains('caller:first', $paths[0]->evidence);
        self::assertContains('caller:second', $paths[0]->evidence);
        self::assertGreaterThan(2, count($paths[0]->evidence));
        self::assertSame(array_values(array_unique($paths[0]->evidence)), $paths[0]->evidence);
        self::assertContains('control:first', $paths[0]->controls);
        self::assertContains('control:second', $paths[0]->controls);
        self::assertSame(array_values(array_unique($paths[0]->controls)), $paths[0]->controls);
        self::assertSame(['outer-call'], $context->summaries->history);
        self::assertSame(1, $state->memory->read($state->local('callerValue'))->native());
        self::assertSame('normal', $state->completion->kind);
        self::assertNotSame($paths[0]->memory, $paths[1]->memory);
    }

    public function testSymbolUsesWeakCoercionByDefaultAndOverridesOnlyCalleeLateStaticBinding(): void
    {
        $context = SolverFixture::context('<?php class Base{static function helper(int $x){return [$x,static::class,isset($this)];}}function target(){}');
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $state->lateStaticClass = 'Caller';
        $receiver = new Term('object', 'box', attributes:['class' => 'Base']);
        $paths = (new CallExecutor(new Machine($context)))->symbol('Base::helper', [new PassedArgument(Term::constant('2'))], $state, new Instruction('call', 'invoke', $caller->source, 'result'), $receiver, calledClass:'Child');
        self::assertCount(1, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame([2,'Child',false], $paths[0]->registers['result']->native());
        self::assertSame('Caller', $paths[0]->lateStaticClass);
        self::assertSame('Caller', $state->lateStaticClass);
    }

    public function testObjectDispatchesReferencedArrayCallableElements(): void
    {
        $context = SolverFixture::context('<?php class Box{static function run($x){return $x+1;}}function target(){}');
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $class = $state->memory->allocate(Term::constant('Box'));
        $method = $state->memory->allocate(Term::constant('run'));
        $target = Term::array([new Term('cell', $class->root),new Term('cell', $method->root)]);
        $paths = (new CallExecutor(new Machine($context)))->object($target, [new PassedArgument(Term::constant(2))], $state, new Instruction('call', 'invoke', $caller->source, 'result'), $caller);
        self::assertNotNull($paths);
        self::assertCount(1, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame(3, $paths[0]->registers['result']->native());
    }

    /**
     * @param Term $target Value without a resolved object callable
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNonObjectTargets')]
    public function testObjectLeavesOtherCallableFormsToTheirOwnResolution(Term $target): void
    {
        $context = SolverFixture::context('<?php class Box{}function target(){}');
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        self::assertNull((new CallExecutor(new Machine($context)))->object($target, [], new State(), new Instruction('call', 'invoke', $caller->source, 'result'), $caller));
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return array<string,array{Term}>
     */
    public static function providerNonObjectTargets(): array
    {
        return ['plain function' => [Term::constant('target')],'number' => [Term::constant(1)],'symbolic spelling' => [Term::parameter('Box::run')],'object without invoke' => [new Term('object', 'box', attributes:['class' => 'Box'])],'opaque' => [Term::opaque('unknown')]];
    }

    public function testInvokeRetainsAnOpenDispatchBoundaryForAnUnresolvedTarget(): void
    {
        $context = SolverFixture::context();
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $paths = (new CallExecutor(new Machine($context)))->invoke(Term::parameter('callback'), [], new State(), new Instruction('call', 'invoke', $caller->source, 'result'), $caller);
        self::assertCount(2, $paths);
        self::assertSame(['OPEN_DISPATCH'], array_column($context->frontiers, 'code'));
        self::assertSame('OPEN_DISPATCH', $paths[0]->registers['result']->literal);
        self::assertSame('throw', $paths[1]->completion->kind);
    }
}
