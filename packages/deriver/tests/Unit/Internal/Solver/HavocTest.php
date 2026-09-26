<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Solver\Havoc::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
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
#[UsesClass(\Deriver\Model\State\StateSlot::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(\Deriver\Value\Term::class)]
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
        $state = new \Deriver\Internal\Solver\State();
        $object = new \Deriver\Value\Term('object', 'a');
        $state->memory->cells['object:a'] = \Deriver\Value\Term::array(['child' => new \Deriver\Value\Term('object', 'b')]);
        $state->memory->cells['object:b'] = \Deriver\Value\Term::fromNative(['x' => 1]);
        $seen = [];
        (new \Deriver\Internal\Solver\Havoc())->reachable($state, $object, 'UNKNOWN_EFFECT', $seen);
        self::assertSame('opaque', $state->memory->cells['object:b']->operands['x']->kind);
        self::assertSame(['object:a' => true,'object:b' => true], $seen);
    }
    public function testAllInvalidatesEveryExistingStorageRoot(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->write($state->local('x'), \Deriver\Value\Term::constant(1));
        $state->memory->cells['global:y'] = \Deriver\Value\Term::constant(2);
        (new \Deriver\Internal\Solver\Havoc())->all($state, 'EVAL');
        self::assertSame('opaque', $state->snapshot()['x']->kind);
        self::assertSame('opaque', $state->memory->cells['global:y']->kind);
    }
    public function testSlotsHonorsExplicitStableContracts(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->slotContracts['example.stable'] = new \Deriver\Model\State\StateSlot('example.stable', 'int', invalidation: 'preserve');
        $state->memory->slotContracts['example.mutable'] = new \Deriver\Model\State\StateSlot('example.mutable', 'int');
        $state->memory->cells['model:a'] = \Deriver\Value\Term::fromNative(['example.stable' => 1,'example.mutable' => 2]);
        $seen = [];
        (new \Deriver\Internal\Solver\Havoc())->slots($state, 'a', 'UNKNOWN', $seen);
        self::assertSame(1, $state->memory->read(new \Deriver\Internal\Memory\Location('model:a', ['example.stable']))->native());
        self::assertSame('opaque', $state->memory->read(new \Deriver\Internal\Memory\Location('model:a', ['example.mutable']))->kind);
    }
    public function testReachableHandlesSharedGraphsWithoutExpandingEveryPath(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->cells['object:box'] = \Deriver\Value\Term::fromNative(['x' => 1]);
        $value = \Tests\Fake\ValueDocument::shared(2000, new \Deriver\Value\Term('object', 'box'));
        $seen = [];
        (new \Deriver\Internal\Solver\Havoc())->reachable($state, $value, 'TEST', $seen);
        self::assertSame('opaque', $state->memory->cells['object:box']->operands['x']->kind);
    }
    public function testCallInvalidatesObjectsReachableThroughGlobalStorage(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->cells['global:box'] = new \Deriver\Value\Term('object', 'box');
        $state->memory->cells['object:box'] = \Deriver\Value\Term::fromNative(['x' => 1]);
        (new \Deriver\Internal\Solver\Havoc())->call($state, [], [], 'TEST');
        self::assertSame('opaque', $state->memory->cells['object:box']->operands['x']->kind);
    }
    public function testSlotValuesRespectsStableContractsWhileReturningMutableDescendants(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->slotContracts = ['test:value' => new \Deriver\Model\State\StateSlot('test:value'), 'test:stable' => new \Deriver\Model\State\StateSlot('test:stable', invalidation:'preserve')];
        $state->memory->cells['model:box'] = \Deriver\Value\Term::fromNative(['test:value' => 1,'test:stable' => 2]);
        $seen = [];
        $values = (new \Deriver\Internal\Solver\Havoc())->slotValues($state, 'box', 'TEST', $seen);
        self::assertCount(1, $values);
        self::assertSame(1, $values[0]->native());
        self::assertSame(2, $state->memory->cells['model:box']->operands['test:stable']->native());
    }
}
