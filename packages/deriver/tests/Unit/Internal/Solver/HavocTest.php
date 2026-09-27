<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver;

use Deriver\Internal\Memory\Location;
use Deriver\Internal\Solver\Havoc;
use Deriver\Internal\Solver\State;
use Deriver\Model\State\StateSlot;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Havoc::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Exceptional::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
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
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Allocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(Havoc::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyMagic::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(StateSlot::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
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
