<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Summary;

use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Project\EntryPoint;
use Deriver\Query\Budget;
use Deriver\Query\Query;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\ExpressionRef;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Summary\Evaluation
 */
#[CoversClass(Evaluation::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\Handler::class)]
#[UsesClass(\Deriver\Evaluation\Control\LoopConvergence::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\ResidualPaths::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\Havoc::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Address::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ValueQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
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
#[UsesClass(\Deriver\Source\Compilation\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\LoopLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
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
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Increment::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class EvaluationTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testRunReusesCallsAtTheSameSiteWithoutMergingDifferentArguments(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function helper($x){return $x+1;}function target(){$r=[];for($i=0;$i<4;$i++){$r[]=helper(2);}return [$r,helper(9)];}');
        self::assertSame([[3,3,3,3],10], $result->normalOutcomes[0]->values['return']->native());
        self::assertGreaterThanOrEqual(3, $result->statistics->cacheHits);
        self::assertSame([], $result->frontiers);
    }
    public function testEligibleRejectsHiddenReferencesDespiteAPureBody(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target($x){$x[0]=2;return $x;}');
        $entry = new State();
        $entry->locals['x'] = $entry->memory->allocate(Term::array([new Term('cell', 'shared')]));
        $evaluation = new Evaluation(new Machine($context));
        self::assertFalse($evaluation->eligible(\Tests\Fake\SummaryFixture::body($context), $entry));
    }
    public function testEvaluateStoresAnIndependentCompletionAndLogicalCost(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $key = (new Invocation())->key($body, $entry, []);
        $table = $context->summaries;
        $cell = $table->register($key, $body, $entry);

        $context->demands[$body] = (new Discovery($context))->instructions($body);
        $evaluation = new Evaluation(new Machine($context));
        self::assertTrue($evaluation->evaluate($cell));
        self::assertSame('stable', $cell->status);
        self::assertGreaterThan(0, $cell->cost);
        self::assertSame(1, $evaluation->replay($cell, $entry)[0]->completion->value?->literal);
    }
    public function testCloseResolvesCyclicPendingCellsBeforeExport(): void
    {
        $context = \Tests\Fake\SummaryFixture::run('<?php function target(){return hop();}function hop(){return target();}');
        $statuses = array_unique(array_map(static fn (Cell $cell): string => $cell->status, $context->summaries->cells));
        self::assertSame(['frontier'], array_values($statuses));
        self::assertNotEmpty($context->normal);
        self::assertNotEmpty($context->exceptional);
        self::assertSame([], $context->summaries->stack);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testReplayPreservesOrdinaryExceptions(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function helper(){return 1/0;}function target(){for($i=0;$i<2;$i++){try{helper();}catch(DivisionByZeroError $e){}}return 7;}');
        self::assertSame(7, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertGreaterThan(0, $result->statistics->cacheHits);
    }
    public function testSealInvalidatesACompletedDependentAndKeepsResidualExceptions(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $key = (new Invocation())->key($body, $entry, []);
        $table = $context->summaries;
        $cell = $table->register($key, $body, $entry);

        $table->stack[] = $key->id();
        $dependency = (new Invocation())->key($body, $entry, ['site']);
        $child = $table->register($dependency, $body, $entry);
        $cell->status = 'stable';
        $evaluation = new Evaluation(new Machine($context));
        $evaluation->seal($child);
        self::assertSame('frontier', $child->status);
        self::assertSame('pending', $cell->status);
        self::assertSame(['return','throw'], array_map(static fn (State $state): string => $state->completion->kind, $evaluation->replay($child, $entry)));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerSummaryInputs')]
    public function testEligibleRequiresAnIsolatedValueForEveryLocal(Term $value, bool $expected): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $entry = new State();
        $entry->locals['first'] = $entry->memory->allocate(Term::constant(1));
        $entry->locals['second'] = $entry->memory->allocate($value);
        $evaluation = new Evaluation(new Machine($context));
        self::assertSame($expected, $evaluation->eligible(\Tests\Fake\SummaryFixture::body($context), $entry));
    }
    /**
     * @return iterable<string,array{Term,bool}>
     */
    public static function providerSummaryInputs(): iterable
    {
        yield 'scalar' => [Term::constant(1),true];
        yield 'secret scalar' => [Term::constant(1, true),true];
        yield 'closed array' => [Term::fromNative([1,2]),true];
        yield 'open array' => [Term::array([], true),false];
        yield 'object' => [new Term('object', 'one'),false];
        yield 'typed scalar' => [Term::parameter('n', 'int'),true];
        yield 'mixed input' => [Term::parameter('n'),false];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNonSummaryQuery')]
    public function testEligibleRejectsNonSymbolicReturnObservations(Query $query): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $context = new Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $evaluation = new Evaluation(new Machine($context));
        self::assertFalse($evaluation->eligible(\Tests\Fake\SummaryFixture::body($context), new State()));
        self::assertSame([], $context->summaries->isolated);
    }
    /**
     * @return iterable<string,array{Query}>
     */
    public static function providerNonSummaryQuery(): iterable
    {
        yield 'entrypoint' => [new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target')]))];
        yield 'value' => [new ValueQuery(new ExpressionRef(new SourceRef('test', 'fixture.php', 0, 1), 'target', 'r'))];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReplayStatus')]
    public function testRunReplaysAvailableApproximationsWithoutExecutingAgain(string $status, bool $solving, int $expectedCost, int $hits): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $key = (new Invocation())->key($body, $entry, []);
        $cell = $context->summaries->register($key, $body, $entry);
        $outcome = $entry->fork();
        $outcome->completion = new Completion('return', Term::constant(1));
        $record = new CompletionRecord($outcome, 0);
        $cell->outcomes = [$record->id() => $record];
        $cell->status = $status;
        $cell->cost = 3;
        $context->transfers = 5;
        $context->summaries->solving = $solving;
        $paths = (new Evaluation(new Machine($context)))->run($body, $entry);
        self::assertCount(1, $paths);
        self::assertSame(1, $paths[0]->completion->value?->native());
        self::assertSame($expectedCost, $context->transfers);
        self::assertSame($hits, $context->summaries->hits);
        self::assertSame(0, $cell->updates);
        self::assertSame($status, $cell->status);
        self::assertNotSame($outcome, $paths[0]);
        self::assertSame('normal', $entry->completion->kind);
    }
    /**
     * @return iterable<string,array{string,bool,int,int}>
     */
    public static function providerReplayStatus(): iterable
    {
        yield 'running' => ['running',false,5,0];
        yield 'pending during closure' => ['pending',true,5,0];
        yield 'frontier' => ['frontier',false,5,0];
        yield 'stable' => ['stable',false,8,1];
    }
    public function testRunKeepsAnEmptyRunningApproximationProvisional(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $key = (new Invocation())->key($body, $entry, []);
        $cell = $context->summaries->register($key, $body, $entry);
        $cell->status = 'running';
        $paths = (new Evaluation(new Machine($context)))->run($body, $entry);
        self::assertSame([], $paths);
        self::assertSame('running', $cell->status);
        self::assertSame([], $context->frontiers);
        self::assertSame(0, $context->transfers);
    }
    public function testRunSealsAnAdditionalSpecializationAtTheOwnerLimit(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $context->summaries->owners['target'] = 32;
        $paths = (new Evaluation(new Machine($context)))->run($body, new State());
        self::assertSame(['return','throw'], array_map(static fn ($path) => $path->completion->kind, $paths));
        self::assertSame(['CORRELATION_RELAXED','BUDGET_EXCEEDED'], array_column($context->frontiers, 'code'));
        self::assertSame([], $context->summaries->cells);
        self::assertSame(32, $context->summaries->owners['target']);
    }
    public function testRunAllowsTheLastAvailableSpecialization(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $context->demands[$body] = (new Discovery($context))->instructions($body);
        $context->summaries->owners['target'] = 31;
        $paths = (new Evaluation(new Machine($context)))->run($body, new State());
        self::assertCount(1, $paths);
        self::assertSame(1, $paths[0]->completion->value?->native());
        self::assertSame([], $context->frontiers);
        self::assertCount(1, $context->summaries->cells);
        self::assertSame(32, $context->summaries->owners['target']);
    }
    public function testEvaluateRestoresHistoryAndDetectsAnUnchangedApproximation(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $context->summaries->history = ['registered'];
        $key = (new Invocation())->key($body, $entry, ['registered']);
        $cell = $context->summaries->register($key, $body, $entry);
        $context->summaries->history = ['outer'];
        $context->demands[$body] = (new Discovery($context))->instructions($body);
        $evaluation = new Evaluation(new Machine($context));
        $first = $evaluation->evaluate($cell);
        $second = $evaluation->evaluate($cell);
        self::assertTrue($first);
        self::assertFalse($second);
        self::assertSame(2, $cell->updates);
        self::assertCount(1, $cell->outcomes);
        self::assertSame('stable', $cell->status);
        self::assertSame(['outer'], $context->summaries->history);
        self::assertSame([], $context->summaries->stack);
        self::assertSame([], $entry->registers);
    }
    public function testEvaluateSealsAtTheExactRecursionBudget(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(recursion:2));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $key = (new Invocation())->key($body, $entry, []);
        $cell = $context->summaries->register($key, $body, $entry);
        $cell->updates = 2;
        $evaluation = new Evaluation(new Machine($context));
        self::assertTrue($evaluation->evaluate($cell));
        self::assertSame('frontier', $cell->status);
        self::assertSame(2, $cell->updates);
        self::assertSame(0, $context->transfers);
        self::assertSame(['return','throw'], array_map(static fn ($path) => $path->completion->kind, $evaluation->replay($cell, $entry)));
    }
    public function testSealRetainsTheBoundedKnownOutcomeBeforeResiduals(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(partitions:1));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $key = (new Invocation())->key($body, $entry, []);
        $cell = $context->summaries->register($key, $body, $entry);
        $first = $entry->fork();
        $first->completion = new Completion('return', Term::constant(1));
        $second = $entry->fork();
        $second->completion = new Completion('return', Term::constant(2));
        $a = new CompletionRecord($first, 0);
        $b = new CompletionRecord($second, 0);
        $cell->outcomes = [$a->id() => $a,$b->id() => $b];
        $evaluation = new Evaluation(new Machine($context));
        $evaluation->seal($cell);
        $paths = $evaluation->replay($cell, $entry);
        self::assertCount(3, $paths);
        self::assertSame(1, $paths[0]->completion->value?->native());
        self::assertSame('BUDGET_EXCEEDED', $paths[1]->completion->value?->literal);
        self::assertSame('throw', $paths[2]->completion->kind);
        self::assertArrayHasKey($a->id(), $cell->outcomes);
        self::assertArrayNotHasKey($b->id(), $cell->outcomes);
        self::assertSame('frontier', $cell->status);
    }


    public function testRunExecutesStatefulBodiesWithoutPublishingReusableOutcomes(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target(){global $x;$x=2;return $x;}');
        $body = \Tests\Fake\SummaryFixture::body($context);
        $context->demands[$body] = (new Discovery($context))->instructions($body);
        $entry = new State();
        $paths = (new Evaluation(new Machine($context)))->run($body, $entry);
        self::assertCount(1, $paths);
        self::assertSame(2, $paths[0]->completion->value?->native());
        self::assertSame(2, $paths[0]->memory->cells['global:x']->native());
        self::assertSame([], $context->summaries->cells);
        self::assertSame(['target' => false], $context->summaries->isolated);
    }

    public function testRunSealsAtTheSharedSpecializationBudget(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(nodes:2));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $other = new Key('test', 'other', 'entry', 'value', '', '', '', '');
        $context->summaries->register($other, $body, new State());
        $context->summaries->register(new Key('test', 'another', 'entry', 'value', '', '', '', ''), $body, new State());
        $paths = (new Evaluation(new Machine($context)))->run($body, new State());
        self::assertSame(['return','throw'], array_map(static fn ($path) => $path->completion->kind, $paths));
        self::assertSame(['CORRELATION_RELAXED','BUDGET_EXCEEDED'], array_column($context->frontiers, 'code'));
        self::assertCount(2, $context->summaries->cells);
        self::assertArrayNotHasKey('target', $context->summaries->owners);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerCachedBudget')]
    public function testRunChargesCachedWorkOnlyWhenItFitsTheRemainingBudget(int $cost, int $hits, int $updates): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(transfers:10));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $key = (new Invocation())->key($body, $entry, []);
        $cell = $context->summaries->register($key, $body, $entry);
        $cell->status = 'stable';
        $cell->cost = $cost;
        $state = $entry->fork();
        $state->completion = new Completion('return', Term::constant(1));
        $record = new CompletionRecord($state, 0);
        $cell->outcomes[$record->id()] = $record;
        $context->transfers = 5;
        $context->demands[$body] = (new Discovery($context))->instructions($body);
        $paths = (new Evaluation(new Machine($context)))->run($body, $entry);
        self::assertSame($hits, $context->summaries->hits);
        self::assertSame($updates, $cell->updates);
        self::assertLessThanOrEqual(10, $context->transfers);
        self::assertCount(1, $paths);
        self::assertSame(1, $paths[0]->completion->value?->native());
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{int,int,int}>
     */
    public static function providerCachedBudget(): iterable
    {
        yield 'exact remaining budget' => [5,1,0];
        yield 'cached cost exceeds remaining budget' => [6,0,1];
    }

    public function testRunCannotReplayAClosedSummaryAfterTheContextIsSealed(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $cell = $context->summaries->register((new Invocation())->key($body, $entry, []), $body, $entry);
        $cell->status = 'stable';
        $context->sealed = true;
        $paths = (new Evaluation(new Machine($context)))->run($body, $entry);
        self::assertSame('frontier', $cell->status);
        self::assertSame(0, $cell->updates);
        self::assertSame(0, $context->summaries->hits);
        self::assertSame(['return','throw'], array_map(static fn ($path) => $path->completion->kind, $paths));
    }

    public function testEvaluateMeasuresOnlyItsOwnTransfersAndAllocationEvents(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(partitions:1));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $entry->memory->sequence = 9;
        $cell = $context->summaries->register((new Invocation())->key($body, $entry, []), $body, $entry);
        $context->demands[$body] = (new Discovery($context))->instructions($body);
        $context->transfers = 7;
        $evaluation = new Evaluation(new Machine($context));
        self::assertTrue($evaluation->evaluate($cell));
        self::assertGreaterThan(0, $cell->cost);
        self::assertSame($context->transfers - 7, $cell->cost);
        self::assertCount(1, $cell->outcomes);
        self::assertSame('stable', $cell->status);
        self::assertSame(0, array_values($cell->outcomes)[0]->allocations);
        self::assertFalse(array_values($cell->outcomes)[0]->havoc);
        self::assertSame(9, $entry->memory->sequence);
        self::assertSame(9, $evaluation->replay($cell, $entry)[0]->memory->sequence);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerExistingFrontiers')]
    public function testEvaluateSealsOnlyWhenPriorFrontiersInvalidateRemainingEffects(string $code, string $status, int $outcomes, bool $havoc): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $cell = $context->summaries->register((new Invocation())->key($body, $entry, []), $body, $entry);
        $context->demands[$body] = (new Discovery($context))->instructions($body);
        $context->frontier($code, $body->source, 'earlier-operation');
        self::assertTrue((new Evaluation(new Machine($context)))->evaluate($cell));
        self::assertSame($status, $cell->status);
        self::assertCount($outcomes, $cell->outcomes);
        self::assertSame($havoc, array_values($cell->outcomes)[0]->havoc);
    }

    /**
     * @return iterable<string,array{string,string,int,bool}>
     */
    public static function providerExistingFrontiers(): iterable
    {
        yield 'interrupted effects' => ['BUDGET_EXCEEDED','frontier',3,true];
        yield 'unrelated warning' => ['PHP_WARNING','stable',1,false];
    }

    public function testCloseEvaluatesPendingComputationsAndClearsTheSolvingFlag(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $cell = $context->summaries->register((new Invocation())->key($body, $entry, []), $body, $entry);
        $context->demands[$body] = (new Discovery($context))->instructions($body);
        $evaluation = new Evaluation(new Machine($context));
        $evaluation->close();
        self::assertSame('stable', $cell->status);
        self::assertSame(1, $cell->updates);
        self::assertFalse($context->summaries->solving);
        self::assertSame([], $context->summaries->stack);
        self::assertSame([], $context->frontiers);
        self::assertSame(1, $evaluation->replay($cell, $entry)[0]->completion->value?->native());
    }

    public function testCloseSealsARecursiveComponentWithNoProductiveOutcome(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target(){return target();}');
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $cell = $context->summaries->register((new Invocation())->key($body, $entry, []), $body, $entry);
        $context->demands[$body] = (new Discovery($context))->instructions($body);
        $evaluation = new Evaluation(new Machine($context));
        $evaluation->close();
        self::assertSame('frontier', $cell->status);
        self::assertFalse($context->summaries->solving);
        self::assertSame([], $context->summaries->stack);
        self::assertContains('BUDGET_EXCEEDED', array_column($context->frontiers, 'code'));
        self::assertSame(['return','throw'], array_map(static fn ($path) => $path->completion->kind, $evaluation->replay($cell, $entry)));
    }

    public function testEvaluateInvalidatesDependentsOnlyAfterItsApproximationGrows(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target(int $x){return 1;}');
        $body = \Tests\Fake\SummaryFixture::body($context);
        $outer = new State();
        $outer->memory->write($outer->local('x'), Term::constant(2));
        $inner = new State();
        $inner->memory->write($inner->local('x'), Term::constant(1));
        $outerKey = (new Invocation())->key($body, $outer, []);
        $parent = $context->summaries->register($outerKey, $body, $outer);
        $parent->status = 'stable';
        $context->summaries->stack = [$outerKey->id()];
        $child = $context->summaries->register((new Invocation())->key($body, $inner, []), $body, $inner);
        $context->summaries->stack = [];
        $context->demands[$body] = (new Discovery($context))->instructions($body);
        $evaluation = new Evaluation(new Machine($context));
        $changed = $evaluation->evaluate($child);
        $invalidated = $parent->status;
        $parent->status = 'stable';
        $unchanged = $evaluation->evaluate($child);
        self::assertTrue($changed);
        self::assertSame('pending', $invalidated);
        self::assertFalse($unchanged);
        self::assertSame('stable', $parent->status);
        self::assertSame('stable', $child->status);
    }
}
