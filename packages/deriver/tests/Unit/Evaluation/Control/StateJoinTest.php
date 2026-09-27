<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\ResidualPaths;
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
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
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
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
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

#[CoversClass(StateJoin::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(ResidualPaths::class)]
#[UsesClass(Resources::class)]
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
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
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
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
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
final class StateJoinTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testLimitPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(bool $admin){if($admin){$table="admins";$column="admin_id";}else{$table="users";$column="user_id";}return [$table,$column];}');
        self::assertCount(2, $result->normalOutcomes);
        self::assertSame(['admins', 'admin_id'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame(['users', 'user_id'], $result->normalOutcomes[1]->values['return']->native());
        self::assertNotSame($result->normalOutcomes[0]->guard, $result->normalOutcomes[1]->guard);
    }
    public function testCompletedIncludesLateReturnsAfterThePartitionLimit(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget: new Budget(partitions: 1));
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $a = new State();
        $a->completion = new Completion('return', Term::constant(1));
        $b = new State();
        $b->completion = new Completion('return', Term::constant('last'));
        $states = (new StateJoin($context))->completed([$a, $b], $body);
        self::assertCount(1, $states);
        self::assertSame('opaque', $states[0]->completion->value?->kind);
        self::assertCount(2, $context->frontiers);
    }

    public function testLimitGroupsIndependentProgramPointsBeforeApplyingTheBudget(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(partitions:2));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $a = new State();
        $a->block = 1;
        $b = new State();
        $b->block = 2;
        $c = new State();
        $c->block = 1;
        $states = (new StateJoin($context))->limit([$a,$b,$c], $body);
        self::assertSame([$a,$c,$b], $states);
        self::assertSame([], $context->frontiers);
    }

    public function testLimitKeepsKnownPartitionsAndSealsEveryRemainingStorageRoot(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(partitions:2));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $a = new State();
        $a->observed = true;
        $a->guard = ['first' => true];
        $a->locals['a'] = new Location('a');
        $a->memory->cells['a'] = Term::constant(1);
        $b = new State();
        $b->observed = false;
        $b->locals['b'] = new Location('b');
        $b->memory->cells['b'] = Term::constant(2);
        $c = new State();
        $c->observed = true;
        $states = (new StateJoin($context))->limit([$a,$b,$c], $body);
        self::assertCount(3, $states);
        self::assertSame($a, $states[0]);
        self::assertSame(['normal','return','throw'], array_map(static fn ($state) => $state->completion->kind, $states));
        self::assertFalse($states[1]->observed);
        self::assertFalse($states[2]->observed);
        self::assertSame([], $states[1]->guard);
        self::assertSame([], $states[1]->constraints);
        self::assertSame(['a','b'], array_keys($states[1]->locals));
        self::assertSame(['opaque','opaque'], array_column($states[1]->memory->cells, 'kind'));
        self::assertSame('BUDGET_EXCEEDED', $states[1]->memory->unknownShared);
        self::assertSame('Throwable', $states[2]->completion->value?->literal);
        self::assertSame(1, $a->memory->cells['a']->native());
        self::assertSame(2, $b->memory->cells['b']->native());
        self::assertContains('CORRELATION_RELAXED', array_column($context->frontiers, 'code'));
    }

    public function testCompletedKeepsNormalAndExceptionalGroupsIndependentAtTheExactLimit(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(partitions:1));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $normal = new State();
        $normal->completion = new Completion('return', Term::constant(3));
        $error = new State();
        $error->completion = new Completion('throw', new Term('throwable', 'Error'));
        $states = (new StateJoin($context))->completed([$normal,$error], $body);
        self::assertSame([$normal,$error], $states);
        self::assertSame([], $context->frontiers);
    }

    public function testCompletedRetainsUnobservedExceptionalStateFromLaterPartitions(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(partitions:1));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $first = new State();
        $first->completion = new Completion('throw', new Term('throwable', 'Error'));
        $first->observed = true;
        $first->guard = ['first' => true];
        $first->constraints = ['n' => ['min' => 1, 'max' => null, 'equal' => null, 'excluded' => []]];
        $first->locals['a'] = new Location('first');
        $first->memory->cells['first'] = Term::constant(1);
        $last = new State();
        $last->completion = new Completion('throw', new Term('throwable', 'RuntimeException'));
        $last->locals['z'] = new Location('last');
        $last->memory->cells['last'] = Term::constant(2);
        $states = (new StateJoin($context))->completed([$first,$last], $body);
        self::assertCount(1, $states);
        self::assertSame('throw', $states[0]->completion->kind);
        self::assertSame('Throwable', $states[0]->completion->value?->literal);
        self::assertSame(['uncertain' => true], $states[0]->completion->value->attributes);
        self::assertFalse($states[0]->observed);
        self::assertSame([], $states[0]->guard);
        self::assertSame([], $states[0]->constraints);
        self::assertSame(['a','z'], array_keys($states[0]->locals));
        self::assertSame(['first','last'], array_keys($states[0]->memory->cells));
        self::assertSame(['opaque','opaque'], array_column($states[0]->memory->cells, 'kind'));
        self::assertSame('BUDGET_EXCEEDED', $states[0]->memory->unknownShared);
        self::assertTrue($first->observed);
        self::assertSame(1, $first->memory->cells['first']->native());
        self::assertSame(2, $last->memory->cells['last']->native());
    }
}
