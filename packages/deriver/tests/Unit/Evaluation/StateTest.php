<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use Deriver\Evaluation\State;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(State::class)]
#[UsesClass(\Deriver\Analysis\Execution\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\Selection\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\Execution\ResultAssessment::class)]
#[UsesClass(\Deriver\Session\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
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
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Query\Resources::class)]
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
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Location::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Materialization::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Memory::class)]
#[UsesClass(\Deriver\Evaluation\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Evaluation\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderExpansion::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Project\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Execution\Alternative::class)]
#[UsesClass(\Deriver\Result\Execution\Assessment::class)]
#[UsesClass(\Deriver\Result\Execution\Derivation::class)]
#[UsesClass(\Deriver\Result\Execution\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(\Deriver\Result\Execution\StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringCache::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweredCallable::class)]
#[UsesClass(\Deriver\Source\Lowering\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Parsing\ParseCache::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\ParsedFile::class)]
#[UsesClass(\Deriver\Source\Lowering\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\CallLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\CallableLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\InstructionEmitter::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringScope::class)]
#[UsesClass(\Deriver\Source\Lowering\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\Lowering\LineMap::class)]
#[UsesClass(\Deriver\Source\Parsing\MagicContext::class)]
#[UsesClass(\Deriver\Source\Parsing\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Parsing\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Parsing\ClassScope::class)]
#[UsesClass(\Deriver\Source\Parsing\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class StateTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testForkPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php final class Box {public string $value="initial";} function target() {$a=new Box;$b=$a;$b->value="shared";$c=clone $a;$c->value="cloned";return [$a->value,$b->value,$c->value];}');
        self::assertSame(['shared', 'shared', 'cloned'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testLocalUsesDistinctStorageForOrdinaryNamesAndStableSuperglobalInputs(): void
    {
        $state = new State();
        self::assertSame($state->local('x')->root, $state->local('x')->root);
        self::assertNotSame($state->local('x')->root, $state->local('y')->root);
        self::assertSame('external', $state->memory->read($state->local('_GET'))->kind);
    }
    public function testAliasRebindingDoesNotRetargetExistingAliases(): void
    {
        $state = new State();
        $state->memory->write($state->local('x'), Term::constant(1));
        $state->memory->write($state->local('z'), Term::constant(2));
        $state->alias($state->local('y'), $state->local('x'));
        $state->alias($state->local('x'), $state->local('z'));
        self::assertSame(1, $state->snapshot()['y']->native());
        self::assertSame(2, $state->snapshot()['x']->native());
    }
    public function testUnknownAliasMakesWritesThroughTheLocalInvalidateTheStorage(): void
    {
        $state = new State();
        $array = $state->local('a');
        $state->memory->write($array, Term::fromNative([1, 2]));
        $state->unknownAlias($state->local('v'), new \Deriver\Evaluation\Memory\Location($array->root, unknown: true));
        self::assertTrue($state->local('v')->unknown);
        self::assertSame([1, 2], $state->snapshot()['a']->native());
        $state->memory->write($state->local('v'), Term::constant(9));
        self::assertSame('opaque', $state->memory->read($state->local('a'))->kind);
    }
    public function testAliasToAnUnknownSlotNeverAliasesTheWholeStorage(): void
    {
        $state = new State();
        $state->memory->write($state->local('a'), Term::fromNative([1, 2]));
        $state->alias($state->local('v'), new \Deriver\Evaluation\Memory\Location($state->local('a')->root, unknown: true));
        $state->memory->write($state->local('v'), Term::constant(9));
        self::assertNotSame(9, $state->memory->read($state->local('a'))->literal);
        self::assertSame('opaque', $state->memory->read($state->local('a'))->kind);
        $state->alias(new \Deriver\Evaluation\Memory\Location($state->local('b')->root, ['k']), new \Deriver\Evaluation\Memory\Location($state->local('a')->root, unknown: true));
        self::assertSame('UNKNOWN_REFERENCE', $state->memory->read(new \Deriver\Evaluation\Memory\Location($state->local('b')->root, ['k']))->literal);
    }
    public function testValueKeepsUncomputedDistinctFromAnEvaluatedNull(): void
    {
        $state = new State();
        $state->registers['null'] = Term::constant(null);
        self::assertSame('opaque', $state->value('missing')->kind);
        self::assertSame('constant', $state->value('null')->kind);
    }
    public function testSnapshotSortsNamesAndMaterializesSharedCells(): void
    {
        $state = new State();
        $state->memory->write($state->local('z'), Term::constant(1));
        $state->alias($state->local('a'), $state->local('z'));
        self::assertSame(['a', 'z'], array_keys($state->snapshot()));
        self::assertSame(1, $state->snapshot()['a']->native());
    }
}
