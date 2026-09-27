<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Control;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
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
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Constraint\Constraints::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ResidualPaths::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Cell::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Components::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\Havoc::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
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
        $context = \Tests\Fake\SolverFixture::context(budget: new \Deriver\Api\Query\Budget(partitions: 1));
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $a = new \Deriver\Internal\Solver\State();
        $a->completion = new \Deriver\Internal\Solver\Completion('return', \Deriver\Value\Term::constant(1));
        $b = new \Deriver\Internal\Solver\State();
        $b->completion = new \Deriver\Internal\Solver\Completion('return', \Deriver\Value\Term::constant('last'));
        $states = (new \Deriver\Internal\Solver\Control\StateJoin($context))->completed([$a, $b], $body);
        self::assertCount(1, $states);
        self::assertSame('opaque', $states[0]->completion->value?->kind);
        self::assertCount(2, $context->frontiers);
    }

    public function testLimitGroupsIndependentProgramPointsBeforeApplyingTheBudget(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new \Deriver\Api\Query\Budget(partitions:2));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $a = new \Deriver\Internal\Solver\State();
        $a->block = 1;
        $b = new \Deriver\Internal\Solver\State();
        $b->block = 2;
        $c = new \Deriver\Internal\Solver\State();
        $c->block = 1;
        $states = (new \Deriver\Internal\Solver\Control\StateJoin($context))->limit([$a,$b,$c], $body);
        self::assertSame([$a,$c,$b], $states);
        self::assertSame([], $context->frontiers);
    }

    public function testLimitKeepsKnownPartitionsAndSealsEveryRemainingStorageRoot(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new \Deriver\Api\Query\Budget(partitions:2));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $a = new \Deriver\Internal\Solver\State();
        $a->observed = true;
        $a->guard = ['first' => true];
        $a->locals['a'] = new \Deriver\Internal\Memory\Location('a');
        $a->memory->cells['a'] = \Deriver\Value\Term::constant(1);
        $b = new \Deriver\Internal\Solver\State();
        $b->observed = false;
        $b->locals['b'] = new \Deriver\Internal\Memory\Location('b');
        $b->memory->cells['b'] = \Deriver\Value\Term::constant(2);
        $c = new \Deriver\Internal\Solver\State();
        $c->observed = true;
        $states = (new \Deriver\Internal\Solver\Control\StateJoin($context))->limit([$a,$b,$c], $body);
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
        $context = \Tests\Fake\SolverFixture::context(budget:new \Deriver\Api\Query\Budget(partitions:1));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $normal = new \Deriver\Internal\Solver\State();
        $normal->completion = new \Deriver\Internal\Solver\Completion('return', \Deriver\Value\Term::constant(3));
        $error = new \Deriver\Internal\Solver\State();
        $error->completion = new \Deriver\Internal\Solver\Completion('throw', new \Deriver\Value\Term('throwable', 'Error'));
        $states = (new \Deriver\Internal\Solver\Control\StateJoin($context))->completed([$normal,$error], $body);
        self::assertSame([$normal,$error], $states);
        self::assertSame([], $context->frontiers);
    }

    public function testCompletedRetainsUnobservedExceptionalStateFromLaterPartitions(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new \Deriver\Api\Query\Budget(partitions:1));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $first = new \Deriver\Internal\Solver\State();
        $first->completion = new \Deriver\Internal\Solver\Completion('throw', new \Deriver\Value\Term('throwable', 'Error'));
        $first->observed = true;
        $first->guard = ['first' => true];
        $first->constraints = ['n' => ['min' => 1, 'max' => null, 'equal' => null, 'excluded' => []]];
        $first->locals['a'] = new \Deriver\Internal\Memory\Location('first');
        $first->memory->cells['first'] = \Deriver\Value\Term::constant(1);
        $last = new \Deriver\Internal\Solver\State();
        $last->completion = new \Deriver\Internal\Solver\Completion('throw', new \Deriver\Value\Term('throwable', 'RuntimeException'));
        $last->locals['z'] = new \Deriver\Internal\Memory\Location('last');
        $last->memory->cells['last'] = \Deriver\Value\Term::constant(2);
        $states = (new \Deriver\Internal\Solver\Control\StateJoin($context))->completed([$first,$last], $body);
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
