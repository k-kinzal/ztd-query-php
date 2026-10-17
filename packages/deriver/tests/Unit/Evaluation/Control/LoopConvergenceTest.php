<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\Evaluation\Control\IteratorCursor;
use Deriver\Evaluation\Control\LoopConvergence;
use Deriver\Evaluation\State;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoopConvergence::class)]
#[UsesClass(\Deriver\Analysis\Execution\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\Selection\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\Execution\ResultAssessment::class)]
#[UsesClass(\Deriver\Session\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Evaluation\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(IteratorCursor::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Query\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\CompoundAssignment::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
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
#[UsesClass(\Deriver\Diagnostic\Frontier::class)]
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
#[UsesClass(\Deriver\Source\Lowering\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\CallableLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\Control\LoopLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Lowering\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\InstructionEmitter::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringScope::class)]
#[UsesClass(\Deriver\Source\Lowering\StatementLowering::class)]
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
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Increment::class)]
#[UsesClass(\Deriver\Model\Operations\Lattice::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\StringPrefix::class)]
#[UsesClass(Term::class)]
#[Small]
final class LoopConvergenceTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testWidenPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(int $n){$s="";for($i=0;$i<$n;$i++){$s.="x";}return $s;}');
        self::assertSame('closed', $result->assessment->closure);
        self::assertSame('abstract', $result->assessment->precision);
        self::assertSame('WIDENED', $result->frontiers[0]->code);
        self::assertSame('not-enumerated', $result->assessment->enumeration);
    }
    public function testStructureHashesSharedArrayGraphsWithoutExpansion(): void
    {
        $state = new State();
        $state->iterators['i'] = new IteratorCursor(\Tests\Fake\ValueDocument::shared(30, Term::constant(1)));
        $solver = new LoopConvergence(\Tests\Fake\SolverFixture::context());
        self::assertSame($solver->structure($state), $solver->structure($state->fork()));
    }

    public function testPositionDistinguishesOnlyKnownEntries(): void
    {
        $solver = new LoopConvergence(\Tests\Fake\SolverFixture::context());
        $closed = Term::array([Term::constant('a'), Term::constant('b')]);
        self::assertSame(5, $solver->position(new IteratorCursor($closed, position: 5)));
        $unknown = Term::parameter('rows', 'array');
        self::assertSame(0, $solver->position(new IteratorCursor($unknown, position: 0)));
        self::assertSame(0, $solver->position(new IteratorCursor($unknown, position: 7)));
        $merge = new Term('array-merge', operands: [$closed, $unknown], attributes: ['type' => 'array']);
        self::assertSame(1, $solver->position(new IteratorCursor($merge, position: 1)));
        self::assertSame(3, $solver->position(new IteratorCursor($merge, position: 9)));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testLoopsOverUnknownIterablesReachAFixedPointWithTheirInitialPrefix(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(array $xs){$s="SELECT * FROM t WHERE 1";foreach($xs as $x){$s.=" AND c = ?";}return $s;}');
        self::assertSame('closed', $result->assessment->closure);
        self::assertSame(['WIDENED'], array_column($result->frontiers, 'code'));
        $values = array_map(static fn ($outcome): Term => $outcome->values['return'], $result->normalOutcomes);
        self::assertSame('SELECT * FROM t WHERE 1', $values[0]->native());
        self::assertSame('SELECT * FROM t WHERE 1' . str_repeat(' AND c = ?', 15), $values[15]->native());
        $widened = array_values(array_filter($values, static fn (Term $value): bool => !$value->isConcrete()));
        self::assertNotEmpty($widened);
        self::assertSame([['SELECT * FROM t WHERE 1', 'WIDENED']], array_values(array_unique(array_map(static fn (Term $value): array => [$value->operands[0]->literal, $value->operands[1]->literal], $widened), SORT_REGULAR)));
    }
}
