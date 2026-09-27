<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

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
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\Model\Inputs;
use Deriver\Evaluation\Call\Model\NativeArguments;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Creation;
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
use Deriver\Evaluation\Offset\Address;
use Deriver\Evaluation\Offset\Path;
use Deriver\Evaluation\Offset\Reader;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\CallableTransfer;
use Deriver\Evaluation\Transfer\CompoundAssignment;
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
use Deriver\Value\Operations;
use Deriver\Value\SecretFingerprint;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;
use Tests\Fake\SummaryFixture;

#[CoversClass(CollectionCalls::class)]
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
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
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
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(UnknownCall::class)]
#[UsesClass(Completion::class)]
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
#[UsesClass(Address::class)]
#[UsesClass(Path::class)]
#[UsesClass(Reader::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(CompoundAssignment::class)]
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
#[UsesClass(Operations::class)]
#[UsesClass(SecretFingerprint::class)]
#[UsesClass(Term::class)]
#[Small]
final class CollectionCallsTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return array_reduce([1,2,3],fn($a,$b)=>$a+$b,0);}');
        self::assertSame(6, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testElementEvaluatesCallbackEffectsInIterationOrder(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$sum=0;$values=array_map(function($n)use(&$sum){$sum+=$n;return $sum;},[2,3]);return [$values,$sum];}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([[2,5],5], $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testFilterRetainsOriginalKeysForAcceptedElements(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return array_filter(["a"=>1,"b"=>2],fn($x)=>$x>1);}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(['b' => 2], $result->normalOutcomes[0]->values['return']->native());
    }
    public function testKnownCallbackDistinguishesNullFromUnresolvedClassLookup(): void
    {
        $calls = new CollectionCalls(new Machine(SolverFixture::context()));
        self::assertTrue($calls->knownCallback(Term::constant(null)));
        self::assertTrue($calls->knownCallback(Term::constant('strlen')));
        self::assertFalse($calls->knownCallback(Term::constant('ExternalClass::method')));
    }
    public function testKnownModeRejectsSymbolicFilterArgumentSelection(): void
    {
        $calls = new CollectionCalls(new Machine(SolverFixture::context()));
        self::assertFalse($calls->knownMode('array_filter', [Term::array([]), Term::constant(null), Term::parameter('mode', 'int')]));
        self::assertTrue($calls->knownMode('array_filter', []));
        self::assertTrue($calls->knownMode('array_map', []));
    }

    /**
     * @param string $source Trusted fixture source
     * @param string $expectedJson Expected observable value and alias effects
     * @throws JsonException If expected values cannot be decoded
     */
    #[DataProvider('providerCollectionPrograms')]
    public function testApplyPreservesKeysReferencesAndOrderedCallbackEffects(string $source, string $expectedJson): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerCollectionPrograms(): array
    {
        return \Tests\Fake\Programs\CollectionPrograms::cases();
    }

    public function testFilterKeepsCorrelatedMembershipAndIndependentStorage(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->registers['accumulator'] = Term::fromNative(['previous' => 7]);
        $state->memory->write($state->local('value'), Term::constant(1));
        $element = Term::constant('selected');
        $paths = (new CollectionCalls(new Machine($context)))->filter($state, Term::parameter('predicate', 'bool'), $element, 'key', 'accumulator');
        self::assertCount(2, $paths);
        self::assertSame(['previous' => 7,'key' => 'selected'], $paths[0]->registers['accumulator']->native());
        self::assertSame(['previous' => 7], $paths[1]->registers['accumulator']->native());
        self::assertNotSame($paths[0]->guard, $paths[1]->guard);
        $paths[0]->memory->write($paths[0]->local('value'), Term::constant(2));
        self::assertSame(1, $paths[1]->snapshot()['value']->native());
        self::assertSame(['previous' => 7], $state->registers['accumulator']->native());
    }

    public function testFilterRetainsPredicateConfidentialityOnBothMembershipOutcomes(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->registers['accumulator'] = Term::array([]);
        $predicate = new Term('parameter', 'predicate', attributes:['type' => 'bool'], secret:true);
        $paths = (new CollectionCalls(new Machine($context)))->filter($state, $predicate, Term::constant('selected'), 'key', 'accumulator');
        self::assertCount(2, $paths);
        self::assertTrue($paths[0]->registers['accumulator']->isSecret());
        self::assertTrue($paths[1]->registers['accumulator']->isSecret());
    }

    public function testFilterRetainsExistingAggregateConfidentialityAfterPublicDecisions(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->registers['accumulator'] = new Term('array', operands:['previous' => Term::constant(7)], attributes:['open' => false], secret:true);
        $paths = (new CollectionCalls(new Machine($context)))->filter($state, Term::constant(true), Term::constant('selected'), 'key', 'accumulator');
        self::assertCount(1, $paths);
        self::assertTrue($paths[0]->registers['accumulator']->isSecret());
        self::assertSame(['previous' => 7,'key' => 'selected'], $paths[0]->registers['accumulator']->native());
    }

    /**
     * @param string $name Collection operation
     * @param list<Term> $values Bound inputs requiring an explicit boundary
     */
    #[DataProvider('providerUnsupportedCollections')]
    public function testApplyKeepsUnknownCollectionEffectsAndBothExits(string $name, array $values): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $state->memory->cells['global:value'] = Term::constant(7);
        $paths = (new CollectionCalls(new Machine($context)))->apply($body, new Instruction('call', 'intrinsic', $body->source, 'result', name:$name), $state, $values);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('UNSUPPORTED_MODEL_CASE', $paths[0]->registers['result']->literal);
        self::assertSame('opaque', $paths[0]->memory->cells['global:value']->kind);
        self::assertSame(['UNSUPPORTED_MODEL_CASE'], array_column($context->frontiers, 'code'));
    }

    /**
     * @return iterable<string,array{string,list<Term>}>
     */
    public static function providerUnsupportedCollections(): iterable
    {
        yield 'unknown callback' => ['array_map',[Term::parameter('callback'),Term::fromNative([1])]];
        yield 'unknown array' => ['array_map',[Term::constant(null),Term::parameter('array', 'array')]];
        yield 'open array' => ['array_map',[Term::constant(null),Term::array([], true)]];
        yield 'multiple arrays' => ['array_map',[Term::constant(null),Term::fromNative([1]),Term::fromNative([[2]])]];
        yield 'unknown filter mode' => ['array_filter',[Term::fromNative([1]),Term::constant(null),Term::parameter('mode', 'int')]];
    }

    public function testElementPassesConfidentialAggregateKeysToKeyCallbacks(): void
    {
        $context = SolverFixture::context('<?php function callback($key){return $key;}function target(){}');
        $body = SummaryFixture::body($context);
        $state = new State();
        $array = new Term('array', operands:['key' => Term::constant(7)], attributes:['open' => false], secret:true);
        $state->registers['accumulator'] = Term::array([]);
        $values = [$array,Term::constant('callback'),Term::constant(2)];
        $paths = (new CollectionCalls(new Machine($context)))->element($body, new Instruction('call', 'intrinsic', $body->source, 'result', name:'array_filter'), $state, $values[1], $array->operands['key'], 'key', 'accumulator', $values);
        self::assertCount(1, $paths);
        self::assertTrue($paths[0]->registers['result']->isSecret());
        self::assertSame('key', $paths[0]->registers['result']->native());
        self::assertTrue($paths[0]->registers['accumulator']->isSecret());
    }

    public function testCompletePublishesConfidentialResultsWithoutReplacingExceptions(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $normal = new State();
        $normal->registers['accumulator'] = Term::fromNative(['value' => 7]);
        $exceptional = new State();
        $failure = new Completion('throw', new Term('throwable', 'Error'));
        $exceptional->completion = $failure;
        $paths = (new CollectionCalls(new Machine($context)))->complete(new Instruction('call', 'intrinsic', $body->source, 'result'), [$normal,$exceptional], [Term::constant('private', true)], 'accumulator');
        self::assertCount(2, $paths);
        self::assertSame(['value' => 7], $paths[0]->registers['result']->native());
        self::assertTrue($paths[0]->registers['result']->isSecret());
        self::assertArrayNotHasKey('accumulator', $paths[0]->registers);
        self::assertSame($failure, $paths[1]->completion);
        self::assertArrayNotHasKey('result', $paths[1]->registers);
    }

    public function testCompleteRetainsTheAccumulatorWhenInputsArePublic(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $value = new Term('array', attributes:['open' => false], secret:true);
        $state->registers['accumulator'] = $value;
        $paths = (new CollectionCalls(new Machine($context)))->complete(new Instruction('call', 'intrinsic', $body->source, 'result'), [$state], [Term::constant('public')], 'accumulator');
        self::assertSame($value, $paths[0]->registers['result']);
        self::assertArrayNotHasKey('accumulator', $paths[0]->registers);
    }
}
