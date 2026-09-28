<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Model\State\StateSlot;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Havoc::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Allocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyMagic::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(StateSlot::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Exceptional::class)]
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
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
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
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
    public function testSymbolsInvalidateExistingAndFutureLocalBindings(): void
    {
        $state = new State();
        $state->memory->write($state->local('sql'), Term::constant('old'));
        (new Havoc())->symbols($state, 'DYNAMIC_VARIABLE_WRITE');
        self::assertSame('opaque', $state->memory->read($state->local('sql'))->kind);
        self::assertSame('opaque', $state->memory->read($state->local('new'))->kind);
    }

}
