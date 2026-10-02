<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Query\Budget;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StateJoin::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\ResidualPaths::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\Havoc::class)]
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
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
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
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
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
#[UsesClass(\Deriver\Evaluation\Control\PathJoin::class)]
#[UsesClass(\Deriver\Value\Lattice::class)]
#[UsesClass(\Deriver\Value\StringPrefix::class)]
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
        self::assertSame('abstract', $states[0]->completion->value?->kind);
        self::assertTrue((new \Deriver\Value\Lattice())->contains($states[0]->completion->value, Term::constant(1)));
        self::assertTrue((new \Deriver\Value\Lattice())->contains($states[0]->completion->value, Term::constant('last')));
        self::assertCount(2, $context->frontiers);
    }

    public function testCompletedHavocsLateExceptionsThatCannotBeJoined(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget: new Budget(partitions: 1));
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $a = new State();
        $a->completion = new Completion('throw', new Term('throwable', 'LogicException'));
        $b = new State();
        $b->completion = new Completion('throw', new Term('throwable', 'RuntimeException'));
        $states = (new StateJoin($context))->completed([$a, $b], $body);
        self::assertCount(1, $states);
        self::assertSame(['Throwable', true], [$states[0]->completion->value?->literal, $states[0]->completion->value?->attributes['uncertain'] ?? null]);
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

    public function testLimitKeepsClustersWithinTheBudgetAndSealsTheStorageOfTheRest(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(partitions:1));
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
        self::assertSame(['b'], array_keys($states[1]->locals));
        self::assertSame(['opaque'], array_column($states[1]->memory->cells, 'kind'));
        self::assertSame('BUDGET_EXCEEDED', $states[1]->memory->unknownShared);
        self::assertSame('Throwable', $states[2]->completion->value?->literal);
        self::assertSame(1, $a->memory->cells['a']->native());
        self::assertSame(2, $b->memory->cells['b']->native());
        self::assertContains('CORRELATION_RELAXED', array_column($context->frontiers, 'code'));
    }

    public function testLimitJoinsExcessPathsThatDifferOnlyInPlainValues(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(partitions:2));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $base = new State();
        $base->block = 1;
        $base->memory->write($base->local('sql'), Term::constant(''));
        $paths = array_map(static function (string $sql) use ($base): State {
            $path = $base->fork();
            $path->memory->write($path->local('sql'), Term::constant($sql));
            return $path;
        }, ['SELECT a', 'SELECT b', 'SELECT c']);
        $states = (new StateJoin($context))->limit($paths, $body);
        self::assertCount(2, $states);
        self::assertSame($paths[0], $states[0]);
        self::assertSame('normal', $states[1]->completion->kind);
        $joined = $states[1]->memory->read($states[1]->locals['sql']);
        self::assertSame('SELECT ', $joined->operands[0]->native());
        self::assertSame(['BUDGET_EXCEEDED', 'CORRELATION_RELAXED'], array_column(array_values($context->frontiers), 'code'));
    }

    public function testLimitPassesCompletedResidualsThrough(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(partitions:1));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $a = new State();
        $a->completion = new Completion('return', Term::constant(1));
        $b = new State();
        $b->completion = new Completion('throw', new Term('throwable', 'Throwable'));
        self::assertSame([$a, $b], (new StateJoin($context))->limit([$a, $b], $body));
        self::assertSame([], $context->frontiers);
    }

    public function testClustersJoinCompatiblePathsAndKeepOtherStructures(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        [$a, $b] = \Tests\Fake\JoinPaths::pair(Term::constant('x'), Term::constant('y'));
        [$c] = \Tests\Fake\JoinPaths::pair(Term::constant('z'), Term::constant('z'));
        $c->locals['extra'] = new Location('extra');
        $clusters = (new StateJoin($context))->clusters([$a, $c, $b]);
        self::assertCount(2, $clusters);
        self::assertSame('WIDENED', $clusters[0]->memory->read($clusters[0]->locals['sql'])->literal);
        self::assertSame($c, $clusters[1]);
    }

    public function testSealKeepsTheRootsOfEverySealedPath(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $a = new State();
        $a->locals['a'] = new Location('a');
        $a->memory->cells['a'] = Term::constant(1);
        $b = new State();
        $b->locals['b'] = new Location('b');
        $b->memory->cells['b'] = Term::constant(2);
        $paths = (new StateJoin($context))->seal([$a, $b], $body);
        self::assertSame(['return', 'throw'], array_map(static fn (State $state): string => $state->completion->kind, $paths));
        self::assertSame(['a', 'b'], array_keys($paths[0]->locals));
        self::assertSame(['opaque', 'opaque'], array_column($paths[0]->memory->cells, 'kind'));
    }

    public function testEncodeSharesTermsAndDistinguishesValues(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $join = new StateJoin($context);
        $shared = \Tests\Fake\ValueDocument::shared(40, Term::constant(1));
        self::assertSame($join->encode(new Location('x'), $context->identity), $join->encode(new Location('x'), $context->identity));
        self::assertNotSame($join->encode(new Location('x'), $context->identity), $join->encode(new Location('y'), $context->identity));
        $state = new State();
        $state->registers['r'] = $shared;
        self::assertSame($join->encode($state, $context->identity), $join->encode($state->fork(), $context->identity));
    }

    public function testCandidateKeysSeparateStatesWithDifferentStorage(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $join = new StateJoin($context);
        $a = new State();
        $a->registers['order'] = Term::parameter('order', 'bool');
        $a->memory->write($a->local('x'), Term::constant(1));
        $b = $a->fork();
        $b->guard[$context->identity->key(Term::parameter('order', 'bool'))] = true;
        self::assertSame($join->candidateKey($a, 'order', $context->identity), $join->candidateKey($b, 'order', $context->identity));
        $b->memory->write($b->local('x'), Term::constant(2));
        self::assertNotSame($join->candidateKey($a, 'order', $context->identity), $join->candidateKey($b, 'order', $context->identity));
        self::assertNotSame($join->orderKey($a, 'order'), $join->orderKey($b, 'order'));
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
    public function testOrderKeyIgnoresEvidenceMultiplicityButRetainsScalarTypes(): void
    {
        $join = new StateJoin(\Tests\Fake\SolverFixture::context());
        $a = new State();
        $a->registers['order'] = Term::parameter('order', 'bool');
        $a->registers['value'] = Term::constant(1);
        $a->evidence = ['a','b','a'];
        $b = $a->fork();
        $b->evidence = ['b','a'];
        self::assertSame($join->orderKey($a, 'order'), $join->orderKey($b, 'order'));
        $b->registers['value'] = Term::constant('1');
        self::assertNotSame($join->orderKey($a, 'order'), $join->orderKey($b, 'order'));
    }

    public function testFingerprintIgnoresObjectSharingAndKeepsArrayOrder(): void
    {
        $join = new StateJoin(\Tests\Fake\SolverFixture::context());
        $value = Term::constant(1);
        $a = new State();
        $a->registers = ['a' => $value, 'b' => $value];
        $b = new State();
        $b->registers = ['a' => Term::constant(1), 'b' => Term::constant(1)];
        self::assertSame($join->fingerprint($a), $join->fingerprint($b));
        $a->registers['array'] = Term::fromNative(['a' => 1, 'b' => 2]);
        $b->registers['array'] = Term::fromNative(['b' => 2, 'a' => 1]);
        self::assertNotSame($join->fingerprint($a), $join->fingerprint($b));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testOrdersCoalescesPureCallsWithoutDroppingEffectAlternatives(): void
    {
        $pure = \Tests\Fake\Analysis::returns('<?php function target(){$a=[1,2,3,4,5,6,7,8,9,10];for($i=0;$i<count($a);$i++){}return $i;}');
        self::assertCount(1, $pure->normalOutcomes);
        self::assertSame(10, $pure->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $pure->frontiers);
        $effects = \Tests\Fake\Analysis::returns('<?php function target(){$i=1;return $i+++$i;}');
        self::assertEqualsCanonicalizing([2,3], array_map(static fn ($outcome) => $outcome->values['return']->native(), $effects->normalOutcomes));
    }

}
