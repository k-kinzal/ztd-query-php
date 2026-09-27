<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\Instruction;
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
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
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
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Discovery;
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
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\ObjectAccess;
use Deriver\Evaluation\Transfer\PropertyAccessCheck;
use Deriver\Evaluation\Transfer\PropertyLookup;
use Deriver\Evaluation\Transfer\PropertyMagic;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Evaluation\Transfer\PropertyTransfer;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Builtin\Library;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Model\State\StateSlot;
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
use Deriver\Result\Exceptional;
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

#[CoversClass(Havoc::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
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
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
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
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
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
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(StateSlot::class)]
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
#[UsesClass(Exceptional::class)]
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
final class HavocTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCallPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class Box{public $value="known";} function target(){$box=new Box; $local="unrelated"; externalMutation($box);return [$box->value,$local];}');
        self::assertSame('open', $result->assessment->closure);
        self::assertSame('MISSING_CALL_MODEL', $result->frontiers[0]->code);
        self::assertSame('unrelated', $result->normalOutcomes[0]->values['return']->operands[1]->native());
        self::assertFalse($result->normalOutcomes[0]->values['return']->operands[0]->isConcrete());
    }
    public function testReachableFollowsNestedObjectReferences(): void
    {
        $state = new State();
        $object = new Term('object', 'a');
        $state->memory->cells['object:a'] = Term::array(['child' => new Term('object', 'b')]);
        $state->memory->cells['object:b'] = Term::fromNative(['x' => 1]);
        $seen = [];
        (new Havoc())->reachable($state, $object, 'UNKNOWN_EFFECT', $seen);
        self::assertSame('opaque', $state->memory->cells['object:b']->operands['x']->kind);
        self::assertSame(['object:a' => true,'object:b' => true], $seen);
    }
    public function testAllInvalidatesEveryExistingStorageRoot(): void
    {
        $state = new State();
        $state->memory->write($state->local('x'), Term::constant(1));
        $state->memory->cells['global:y'] = Term::constant(2);
        (new Havoc())->all($state, 'EVAL');
        self::assertSame('opaque', $state->snapshot()['x']->kind);
        self::assertSame('opaque', $state->memory->cells['global:y']->kind);
    }
    public function testSlotsHonorsExplicitStableContracts(): void
    {
        $state = new State();
        $state->memory->slotContracts['example.stable'] = new StateSlot('example.stable', 'int', invalidation: 'preserve');
        $state->memory->slotContracts['example.mutable'] = new StateSlot('example.mutable', 'int');
        $state->memory->cells['model:a'] = Term::fromNative(['example.stable' => 1,'example.mutable' => 2]);
        $seen = [];
        (new Havoc())->slots($state, 'a', 'UNKNOWN', $seen);
        self::assertSame(1, $state->memory->read(new Location('model:a', ['example.stable']))->native());
        self::assertSame('opaque', $state->memory->read(new Location('model:a', ['example.mutable']))->kind);
    }
    public function testReachableHandlesSharedGraphsWithoutExpandingEveryPath(): void
    {
        $state = new State();
        $state->memory->cells['object:box'] = Term::fromNative(['x' => 1]);
        $value = \Tests\Fake\ValueDocument::shared(2000, new Term('object', 'box'));
        $seen = [];
        (new Havoc())->reachable($state, $value, 'TEST', $seen);
        self::assertSame('opaque', $state->memory->cells['object:box']->operands['x']->kind);
    }
    public function testCallInvalidatesObjectsReachableThroughGlobalStorage(): void
    {
        $state = new State();
        $state->memory->cells['global:box'] = new Term('object', 'box');
        $state->memory->cells['object:box'] = Term::fromNative(['x' => 1]);
        (new Havoc())->call($state, [], [], 'TEST');
        self::assertSame('opaque', $state->memory->cells['object:box']->operands['x']->kind);
    }
    public function testSlotValuesRespectsStableContractsWhileReturningMutableDescendants(): void
    {
        $state = new State();
        $state->memory->slotContracts = ['test:value' => new StateSlot('test:value'), 'test:stable' => new StateSlot('test:stable', invalidation:'preserve')];
        $state->memory->cells['model:box'] = Term::fromNative(['test:value' => 1,'test:stable' => 2]);
        $seen = [];
        $values = (new Havoc())->slotValues($state, 'box', 'TEST', $seen);
        self::assertCount(1, $values);
        self::assertSame(1, $values[0]->native());
        self::assertSame(2, $state->memory->cells['model:box']->operands['test:stable']->native());
    }

    public function testCallInvalidatesReferenceReachabilityAndSharedStorageWithoutTouchingIndependentLocals(): void
    {
        $state = new State();
        $leaf = Term::constant(7);
        $object = new Term('object', 'box');
        $reference = $state->memory->allocate(Term::array(['object' => $object]));
        $independent = $state->memory->allocate(Term::constant(9));
        $state->memory->cells['object:box'] = Term::array(['value' => $leaf]);
        $state->memory->cells['global:exposed'] = Term::constant(3);
        $state->memory->cells['static:Counter:value'] = Term::constant(4);
        (new Havoc())->call($state, [], [$reference], 'MISSING_CALL_MODEL');
        self::assertSame('MISSING_CALL_MODEL', $state->memory->unknownShared);
        self::assertSame('opaque', $state->memory->read($reference)->kind);
        self::assertSame('MISSING_CALL_MODEL', $state->memory->read($reference)->literal);
        self::assertSame(9, $state->memory->read($independent)->literal);
        $field = $state->memory->cells['object:box']->operands['value'];
        self::assertSame('opaque', $field->kind);
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
        $a = new Term('object', 'a');
        $b = new Term('object', 'b');
        $state->memory->cells['object:a'] = Term::array(['child' => $b]);
        $state->memory->cells['object:b'] = Term::array(['parent' => $a]);
        $state->memory->cells['captured'] = $a;
        $closure = new Term('closure', 'callable', ['capture' => new Term('cell', 'captured')]);
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
        $object = new Term('object', 'child');
        $stable = Term::constant(7);
        $state->memory->slotContracts = ['example.mutable' => new StateSlot('example.mutable', 'object'), 'example.stable' => new StateSlot('example.stable', 'int', invalidation:'preserve')];
        $state->memory->cells['model:receiver'] = Term::array(['example.mutable' => $object, 'example.stable' => $stable]);
        $state->memory->cells['object:child'] = Term::fromNative(['value' => 3]);
        $seen = [];
        (new Havoc())->slots($state, 'receiver', 'EXTERNAL_EFFECT', $seen);
        $mutable = $state->memory->cells['model:receiver']->operands['example.mutable'];
        self::assertSame('opaque', $mutable->kind);
        self::assertSame('EXTERNAL_EFFECT', $mutable->literal);
        self::assertSame('object', $mutable->attributes['type']);
        self::assertSame([$object], $mutable->operands);
        self::assertSame($stable, $state->memory->cells['model:receiver']->operands['example.stable']);
        self::assertSame('EXTERNAL_EFFECT', $state->memory->cells['object:child']->operands['value']->literal);
        self::assertSame(['model:receiver' => true, 'object:child' => true], $seen);
        self::assertSame([], (new Havoc())->slotValues($state, 'receiver', 'SECOND', $seen));
        self::assertSame([], (new Havoc())->slotValues($state, 'absent', 'SECOND', $seen));
        self::assertArrayNotHasKey('model:absent', $state->memory->cells);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerSymbolicReceivers')]
    public function testReachableInvalidatesModelStateForSymbolicObjectIdentities(string $kind): void
    {
        $state = new State();
        $state->memory->slotContracts['example.value'] = new StateSlot('example.value', 'int');
        $state->memory->cells['model:receiver'] = Term::fromNative(['example.value' => 1]);
        $seen = [];
        (new Havoc())->reachable($state, new Term($kind, 'receiver', attributes:['type' => 'Box']), 'EXTERNAL_EFFECT', $seen);
        self::assertSame('EXTERNAL_EFFECT', $state->memory->cells['model:receiver']->operands['example.value']->literal);
        self::assertSame('int', $state->memory->cells['model:receiver']->operands['example.value']->attributes['type']);
        self::assertSame('EXTERNAL_EFFECT', $state->memory->cells['object:receiver']->literal);
        self::assertSame(['model:receiver' => true, 'object:receiver' => true], $seen);
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
        $local = $state->memory->allocate(Term::constant(1, true));
        $global = Term::constant(2);
        $state->memory->cells['global:value'] = $global;
        $before = $state->memory->read($local);
        (new Havoc())->all($state, 'EVAL');
        self::assertSame('EVAL', $state->memory->unknownShared);
        self::assertSame('EVAL', $state->memory->read($local)->literal);
        self::assertSame([$before], $state->memory->read($local)->operands);
        self::assertTrue($state->memory->read($local)->isSecret());
        self::assertSame('EVAL', $state->memory->cells['global:value']->literal);
        self::assertSame([$global], $state->memory->cells['global:value']->operands);
    }
}
