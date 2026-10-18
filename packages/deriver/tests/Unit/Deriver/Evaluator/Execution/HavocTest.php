<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution;

use Deriver\Deriver;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Builtin\Library;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\State\InvalidationPolicy;
use Deriver\Deriver\Evaluator\Call\State\StateSlot;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\Allocation;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\CallableCheck;
use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\CallResolution;
use Deriver\Deriver\Evaluator\Execution\Call\Creation\Access;
use Deriver\Deriver\Evaluator\Execution\Call\Member\Invocation;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Invocation as NativeInvocation;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Properties;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Signatures;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Arguments;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Creation;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Modes;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Resolution;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Target;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Transfer;
use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Call\TypeCheck;
use Deriver\Deriver\Evaluator\Execution\Call\UnknownCall;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Assessment;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Completion\Exceptional;
use Deriver\Deriver\Evaluator\Execution\Completion\Quality\DependencyClosure;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Control\ExceptionMatch;
use Deriver\Deriver\Evaluator\Execution\Control\ObservationLimit;
use Deriver\Deriver\Evaluator\Execution\Control\StateJoin;
use Deriver\Deriver\Evaluator\Execution\Control\Unwinding;
use Deriver\Deriver\Evaluator\Execution\Demand\Discovery;
use Deriver\Deriver\Evaluator\Execution\Dependencies;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Execution\Havoc;
use Deriver\Deriver\Evaluator\Execution\InstructionTransfer;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Materialization;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
use Deriver\Deriver\Evaluator\Execution\Memory\ReferenceConstraint;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\SlotReference;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\StateStorage;
use Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture;
use Deriver\Deriver\Evaluator\Execution\ObservationCollector;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ObjectAccess;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyAccessCheck;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyLookup;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyMagic;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertySlot;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Arrays;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Execution\DerivationResult;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Execution\ResultAssessment;
use Deriver\Deriver\Identity\QueryEncoding;
use Deriver\Deriver\Identity\ValueGraph;
use Deriver\Deriver\Lowerer\AggregateLowering;
use Deriver\Deriver\Lowerer\AssignmentLowering;
use Deriver\Deriver\Lowerer\CallLowering;
use Deriver\Deriver\Lowerer\Control\DestructuringLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\ConstantSignatures;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\Argument;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\Hierarchy\Builtins;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;
use Deriver\Deriver\Lowerer\InstructionEmitter;
use Deriver\Deriver\Lowerer\Lowerer;
use Deriver\Deriver\Lowerer\LoweringScope;
use Deriver\Deriver\Lowerer\StatementLowering;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Selection\QueryValidation;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(Havoc::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(Instruction::class)]
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
#[UsesClass(NativeInvocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
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
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Evaluator::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertyMagic::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PropertyTransfer::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(Library::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderExpansion::class)]
#[UsesClass(Evaluators::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(StateSlot::class)]
#[UsesClass(DeriverConfig::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(Sources::class)]
#[UsesClass(Path::class)]
#[UsesClass(Snapshot::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(Exceptional::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(AggregateLowering::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class HavocTest extends TestCase
{
    public function testCallPreservesTheSemanticContract(): void
    {
        $source = '<?php class Box{public $value="known";} function target(){$box=new Box; $local="unrelated"; externalMutation($box);return [$box->value,$local];}';
        $symbol = 'target';
        $input = new Sources([new Source('fixture.php', $source)]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        self::assertSame(DependencyClosure::Open, $result->assessment->closure);
        self::assertSame('MISSING_CALL_MODEL', $result->frontiers[0]->code);
        self::assertSame('unrelated', $result->normalOutcomes[0]->values['return']->operands[1]->native());
        self::assertFalse($result->normalOutcomes[0]->values['return']->operands[0]->isConcrete());
    }
    public function testReachableFollowsNestedObjectReferences(): void
    {
        $state = new State();
        $object = new Term(TermKind::Object, 'a');
        $state->memory->cells['object:a'] = Term::array(['child' => new Term(TermKind::Object, 'b')]);
        $state->memory->cells['object:b'] = Term::fromNative(['x' => 1]);
        $seen = [];
        (new Havoc())->reachable($state, $object, 'UNKNOWN_EFFECT', $seen);
        self::assertSame(TermKind::Opaque, $state->memory->cells['object:b']->operands['x']->kind);
        self::assertSame(['object:a' => true, 'object:b' => true], $seen);
    }
    public function testAllInvalidatesEveryExistingStorageRoot(): void
    {
        $state = new State();
        $state->memory->write($state->local('x'), Term::constant(1));
        $state->memory->cells['global:y'] = Term::constant(2);
        (new Havoc())->all($state, 'EVAL');
        self::assertSame(TermKind::Opaque, $state->snapshot()['x']->kind);
        self::assertSame(TermKind::Opaque, $state->memory->cells['global:y']->kind);
    }
    public function testSlotsHonorsExplicitStableContracts(): void
    {
        $state = new State();
        $state->memory->slotContracts['example.stable'] = new StateSlot('example.stable', 'int', invalidation: InvalidationPolicy::Preserve);
        $state->memory->slotContracts['example.mutable'] = new StateSlot('example.mutable', 'int');
        $state->memory->cells['evaluator:a'] = Term::fromNative(['example.stable' => 1, 'example.mutable' => 2]);
        $seen = [];
        (new Havoc())->slots($state, 'a', 'UNKNOWN', $seen);
        self::assertSame(1, $state->memory->read(new Location('evaluator:a', ['example.stable']))->native());
        self::assertSame(TermKind::Opaque, $state->memory->read(new Location('evaluator:a', ['example.mutable']))->kind);
    }
    public function testReachableHandlesSharedGraphsWithoutExpandingEveryPath(): void
    {
        $state = new State();
        $state->memory->cells['object:box'] = Term::fromNative(['x' => 1]);
        $depth = 2000;
        $leaf = new Term(TermKind::Object, 'box');
        $leaf = array_reduce(array_fill(0, $depth, null), static fn (Term $leaf): Term => Term::array([$leaf, $leaf]), $leaf);
        $value = $leaf;
        $seen = [];
        (new Havoc())->reachable($state, $value, 'TEST', $seen);
        self::assertSame(TermKind::Opaque, $state->memory->cells['object:box']->operands['x']->kind);
    }
    public function testCallInvalidatesObjectsReachableThroughGlobalStorage(): void
    {
        $state = new State();
        $state->memory->cells['global:box'] = new Term(TermKind::Object, 'box');
        $state->memory->cells['object:box'] = Term::fromNative(['x' => 1]);
        (new Havoc())->call($state, [], [], 'TEST');
        self::assertSame(TermKind::Opaque, $state->memory->cells['object:box']->operands['x']->kind);
    }
    public function testSlotValuesRespectsStableContractsWhileReturningMutableDescendants(): void
    {
        $state = new State();
        $state->memory->slotContracts = ['test:value' => new StateSlot('test:value'), 'test:stable' => new StateSlot('test:stable', invalidation: InvalidationPolicy::Preserve)];
        $state->memory->cells['evaluator:box'] = Term::fromNative(['test:value' => 1, 'test:stable' => 2]);
        $seen = [];
        $values = (new Havoc())->slotValues($state, 'box', 'TEST', $seen);
        self::assertCount(1, $values);
        self::assertSame(1, $values[0]->native());
        self::assertSame(2, $state->memory->cells['evaluator:box']->operands['test:stable']->native());
    }
    public function testCallInvalidatesReferenceReachabilityAndSharedStorageWithoutTouchingIndependentLocals(): void
    {
        $state = new State();
        $leaf = Term::constant(7);
        $object = new Term(TermKind::Object, 'box');
        $reference = $state->memory->allocate(Term::array(['object' => $object]));
        $independent = $state->memory->allocate(Term::constant(9));
        $state->memory->cells['object:box'] = Term::array(['value' => $leaf]);
        $state->memory->cells['global:exposed'] = Term::constant(3);
        $state->memory->cells['static:Counter:value'] = Term::constant(4);
        (new Havoc())->call($state, [], [$reference], 'MISSING_CALL_MODEL');
        self::assertSame('MISSING_CALL_MODEL', $state->memory->unknownShared);
        self::assertSame(TermKind::Opaque, $state->memory->read($reference)->kind);
        self::assertSame('MISSING_CALL_MODEL', $state->memory->read($reference)->literal);
        self::assertSame(9, $state->memory->read($independent)->literal);
        $field = $state->memory->cells['object:box']->operands['value'];
        self::assertSame(TermKind::Opaque, $field->kind);
        self::assertSame('MISSING_CALL_MODEL', $field->literal);
        self::assertSame([$leaf], $field->operands);
        self::assertTrue($field->attributes['maybeUninitialized']);
        self::assertTrue($state->memory->cells['object:box']->attributes['open']);
        self::assertSame('MISSING_CALL_MODEL', $state->memory->cells['global:exposed']->literal);
        self::assertSame(3, $state->memory->cells['global:exposed']->operands[0]->literal);
        self::assertSame('MISSING_CALL_MODEL', $state->memory->cells['static:Counter:value']->literal);
        self::assertSame(4, $state->memory->cells['static:Counter:value']->operands[0]->literal);
    }
    public function testReachableFollowsCapturedCellsAndTerminatesObjectCycles(): void
    {
        $state = new State();
        $a = new Term(TermKind::Object, 'a');
        $b = new Term(TermKind::Object, 'b');
        $state->memory->cells['object:a'] = Term::array(['child' => $b]);
        $state->memory->cells['object:b'] = Term::array(['parent' => $a]);
        $state->memory->cells['captured'] = $a;
        $closure = new Term(TermKind::Closure, 'callable', ['capture' => new Term(TermKind::Cell, 'captured')]);
        $seen = [];
        $havoc = new Havoc();
        $havoc->reachable($state, $closure, 'BOUNDARY', $seen);
        self::assertSame(['captured' => true, 'object:a' => true, 'object:b' => true], $seen);
        self::assertSame([$a], $state->memory->cells['captured']->operands);
        self::assertSame([$b], $state->memory->cells['object:a']->operands['child']->operands);
        self::assertSame([$a], $state->memory->cells['object:b']->operands['parent']->operands);
        $before = $state->memory->cells;
        $havoc->reachable($state, $closure, 'SECOND', $seen);
        self::assertSame($before, $state->memory->cells);
    }
    public function testSlotsTraversesMutableDescendantsAndPreservesStableValues(): void
    {
        $state = new State();
        $object = new Term(TermKind::Object, 'child');
        $stable = Term::constant(7);
        $state->memory->slotContracts = ['example.mutable' => new StateSlot('example.mutable', 'object'), 'example.stable' => new StateSlot('example.stable', 'int', invalidation: InvalidationPolicy::Preserve)];
        $state->memory->cells['evaluator:receiver'] = Term::array(['example.mutable' => $object, 'example.stable' => $stable]);
        $state->memory->cells['object:child'] = Term::fromNative(['value' => 3]);
        $seen = [];
        (new Havoc())->slots($state, 'receiver', 'EXTERNAL_EFFECT', $seen);
        $mutable = $state->memory->cells['evaluator:receiver']->operands['example.mutable'];
        self::assertSame(TermKind::Opaque, $mutable->kind);
        self::assertSame('EXTERNAL_EFFECT', $mutable->literal);
        self::assertSame('object', $mutable->attributes['type']);
        self::assertSame([$object], $mutable->operands);
        self::assertSame($stable, $state->memory->cells['evaluator:receiver']->operands['example.stable']);
        self::assertSame('EXTERNAL_EFFECT', $state->memory->cells['object:child']->operands['value']->literal);
        self::assertSame(['evaluator:receiver' => true, 'object:child' => true], $seen);
        self::assertSame([], (new Havoc())->slotValues($state, 'receiver', 'SECOND', $seen));
        self::assertSame([], (new Havoc())->slotValues($state, 'absent', 'SECOND', $seen));
        self::assertArrayNotHasKey('evaluator:absent', $state->memory->cells);
    }
    #[DataProvider('providerSymbolicReceivers')]
    public function testReachableInvalidatesModelStateForSymbolicObjectIdentities(string $kind): void
    {
        $state = new State();
        $state->memory->slotContracts['example.value'] = new StateSlot('example.value', 'int');
        $state->memory->cells['evaluator:receiver'] = Term::fromNative(['example.value' => 1]);
        $seen = [];
        (new Havoc())->reachable($state, new Term(TermKind::from($kind), 'receiver', attributes: ['type' => 'Box']), 'EXTERNAL_EFFECT', $seen);
        self::assertSame('EXTERNAL_EFFECT', $state->memory->cells['evaluator:receiver']->operands['example.value']->literal);
        self::assertSame('int', $state->memory->cells['evaluator:receiver']->operands['example.value']->attributes['type']);
        self::assertSame('EXTERNAL_EFFECT', $state->memory->cells['object:receiver']->literal);
        self::assertSame(['evaluator:receiver' => true, 'object:receiver' => true], $seen);
    }
    /**
     * @return iterable<string,array{string}>
     */
    public static function providerSymbolicReceivers(): iterable
    {
        yield 'parameter' => ['parameter'];
        yield 'external' => ['external'];
    }
    public function testAllRetainsThePreviousValuesAsResidualDependencies(): void
    {
        $state = new State();
        $local = $state->memory->allocate(Term::constant(1));
        $global = Term::constant(2);
        $state->memory->cells['global:value'] = $global;
        $before = $state->memory->read($local);
        (new Havoc())->all($state, 'EVAL');
        self::assertSame('EVAL', $state->memory->unknownShared);
        self::assertSame('EVAL', $state->memory->read($local)->literal);
        self::assertSame([$before], $state->memory->read($local)->operands);
        self::assertSame('EVAL', $state->memory->cells['global:value']->literal);
        self::assertSame([$global], $state->memory->cells['global:value']->operands);
    }
    public function testSymbolsInvalidateExistingAndFutureLocalBindings(): void
    {
        $state = new State();
        $state->memory->write($state->local('sql'), Term::constant('old'));
        (new Havoc())->symbols($state, 'DYNAMIC_VARIABLE_WRITE');
        self::assertSame(TermKind::Opaque, $state->memory->read($state->local('sql'))->kind);
        self::assertSame(TermKind::Opaque, $state->memory->read($state->local('new'))->kind);
    }
}
