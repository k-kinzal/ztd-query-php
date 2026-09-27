<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Summary;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Summary\Evaluation
 */
#[CoversClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
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
#[UsesClass(\Deriver\Api\Query\ValueQuery::class)]
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
#[UsesClass(\Deriver\Internal\Constraint\Constraints::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\LoopLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
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
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Handler::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\LoopConvergence::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ResidualPaths::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\Increment::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
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
        $entry = new \Deriver\Internal\Solver\State();
        $entry->locals['x'] = $entry->memory->allocate(\Deriver\Value\Term::array([new \Deriver\Value\Term('cell', 'shared')]));
        $evaluation = new \Deriver\Internal\Solver\Summary\Evaluation(new \Deriver\Internal\Solver\Machine($context));
        self::assertFalse($evaluation->eligible(\Tests\Fake\SummaryFixture::body($context), $entry));
    }
    public function testEvaluateStoresAnIndependentCompletionAndLogicalCost(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new \Deriver\Internal\Solver\State();
        $key = (new \Deriver\Internal\Solver\Summary\Invocation())->key($body, $entry, []);
        $table = $context->summaries;
        $cell = $table->register($key, $body, $entry);

        $context->demands[$body] = (new \Deriver\Internal\Solver\Demand\Discovery($context))->instructions($body);
        $evaluation = new \Deriver\Internal\Solver\Summary\Evaluation(new \Deriver\Internal\Solver\Machine($context));
        self::assertTrue($evaluation->evaluate($cell));
        self::assertSame('stable', $cell->status);
        self::assertGreaterThan(0, $cell->cost);
        self::assertSame(1, $evaluation->replay($cell, $entry)[0]->completion->value?->literal);
    }
    public function testCloseResolvesCyclicPendingCellsBeforeExport(): void
    {
        $context = \Tests\Fake\SummaryFixture::run('<?php function target(){return hop();}function hop(){return target();}');
        $statuses = array_unique(array_map(static fn (\Deriver\Internal\Solver\Demand\Cell $cell): string => $cell->status, $context->summaries->cells));
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
        $entry = new \Deriver\Internal\Solver\State();
        $key = (new \Deriver\Internal\Solver\Summary\Invocation())->key($body, $entry, []);
        $table = $context->summaries;
        $cell = $table->register($key, $body, $entry);

        $table->stack[] = $key->id();
        $dependency = (new \Deriver\Internal\Solver\Summary\Invocation())->key($body, $entry, ['site']);
        $child = $table->register($dependency, $body, $entry);
        $cell->status = 'stable';
        $evaluation = new \Deriver\Internal\Solver\Summary\Evaluation(new \Deriver\Internal\Solver\Machine($context));
        $evaluation->seal($child);
        self::assertSame('frontier', $child->status);
        self::assertSame('pending', $cell->status);
        self::assertSame(['return','throw'], array_map(static fn (\Deriver\Internal\Solver\State $state): string => $state->completion->kind, $evaluation->replay($child, $entry)));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerSummaryInputs')]
    public function testEligibleRequiresAnIsolatedValueForEveryLocal(\Deriver\Value\Term $value, bool $expected): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $entry = new \Deriver\Internal\Solver\State();
        $entry->locals['first'] = $entry->memory->allocate(\Deriver\Value\Term::constant(1));
        $entry->locals['second'] = $entry->memory->allocate($value);
        $evaluation = new \Deriver\Internal\Solver\Summary\Evaluation(new \Deriver\Internal\Solver\Machine($context));
        self::assertSame($expected, $evaluation->eligible(\Tests\Fake\SummaryFixture::body($context), $entry));
    }
    /**
     * @return iterable<string,array{\Deriver\Value\Term,bool}>
     */
    public static function providerSummaryInputs(): iterable
    {
        yield 'scalar' => [\Deriver\Value\Term::constant(1),true];
        yield 'secret scalar' => [\Deriver\Value\Term::constant(1, true),true];
        yield 'closed array' => [\Deriver\Value\Term::fromNative([1,2]),true];
        yield 'open array' => [\Deriver\Value\Term::array([], true),false];
        yield 'object' => [new \Deriver\Value\Term('object', 'one'),false];
        yield 'typed scalar' => [\Deriver\Value\Term::parameter('n', 'int'),true];
        yield 'mixed input' => [\Deriver\Value\Term::parameter('n'),false];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNonSummaryQuery')]
    public function testEligibleRejectsNonSymbolicReturnObservations(\Deriver\Api\Query\Query $query): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $context = new \Deriver\Internal\Solver\Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $evaluation = new \Deriver\Internal\Solver\Summary\Evaluation(new \Deriver\Internal\Solver\Machine($context));
        self::assertFalse($evaluation->eligible(\Tests\Fake\SummaryFixture::body($context), new \Deriver\Internal\Solver\State()));
        self::assertSame([], $context->summaries->isolated);
    }
    /**
     * @return iterable<string,array{\Deriver\Api\Query\Query}>
     */
    public static function providerNonSummaryQuery(): iterable
    {
        yield 'entrypoint' => [new \Deriver\Api\Query\ReturnQuery('target', \Deriver\Api\Query\QueryScope::fromEntrypoints([new \Deriver\Api\Project\EntryPoint('target')]))];
        yield 'value' => [new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef(new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1), 'target', 'r'))];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReplayStatus')]
    public function testRunReplaysAvailableApproximationsWithoutExecutingAgain(string $status, bool $solving, int $expectedCost, int $hits): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new \Deriver\Internal\Solver\State();
        $key = (new \Deriver\Internal\Solver\Summary\Invocation())->key($body, $entry, []);
        $cell = $context->summaries->register($key, $body, $entry);
        $outcome = $entry->fork();
        $outcome->completion = new \Deriver\Internal\Solver\Completion('return', \Deriver\Value\Term::constant(1));
        $record = new \Deriver\Internal\Solver\Summary\CompletionRecord($outcome, 0);
        $cell->outcomes = [$record->id() => $record];
        $cell->status = $status;
        $cell->cost = 3;
        $context->transfers = 5;
        $context->summaries->solving = $solving;
        $paths = (new \Deriver\Internal\Solver\Summary\Evaluation(new \Deriver\Internal\Solver\Machine($context)))->run($body, $entry);
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
        $entry = new \Deriver\Internal\Solver\State();
        $key = (new \Deriver\Internal\Solver\Summary\Invocation())->key($body, $entry, []);
        $cell = $context->summaries->register($key, $body, $entry);
        $cell->status = 'running';
        $paths = (new \Deriver\Internal\Solver\Summary\Evaluation(new \Deriver\Internal\Solver\Machine($context)))->run($body, $entry);
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
        $paths = (new \Deriver\Internal\Solver\Summary\Evaluation(new \Deriver\Internal\Solver\Machine($context)))->run($body, new \Deriver\Internal\Solver\State());
        self::assertSame(['return','throw'], array_map(static fn ($path) => $path->completion->kind, $paths));
        self::assertSame(['CORRELATION_RELAXED','BUDGET_EXCEEDED'], array_column($context->frontiers, 'code'));
        self::assertSame([], $context->summaries->cells);
        self::assertSame(32, $context->summaries->owners['target']);
    }
    public function testRunAllowsTheLastAvailableSpecialization(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $context->demands[$body] = (new \Deriver\Internal\Solver\Demand\Discovery($context))->instructions($body);
        $context->summaries->owners['target'] = 31;
        $paths = (new \Deriver\Internal\Solver\Summary\Evaluation(new \Deriver\Internal\Solver\Machine($context)))->run($body, new \Deriver\Internal\Solver\State());
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
        $entry = new \Deriver\Internal\Solver\State();
        $context->summaries->history = ['registered'];
        $key = (new \Deriver\Internal\Solver\Summary\Invocation())->key($body, $entry, ['registered']);
        $cell = $context->summaries->register($key, $body, $entry);
        $context->summaries->history = ['outer'];
        $context->demands[$body] = (new \Deriver\Internal\Solver\Demand\Discovery($context))->instructions($body);
        $evaluation = new \Deriver\Internal\Solver\Summary\Evaluation(new \Deriver\Internal\Solver\Machine($context));
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
        $context = \Tests\Fake\SolverFixture::context(budget:new \Deriver\Api\Query\Budget(recursion:2));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new \Deriver\Internal\Solver\State();
        $key = (new \Deriver\Internal\Solver\Summary\Invocation())->key($body, $entry, []);
        $cell = $context->summaries->register($key, $body, $entry);
        $cell->updates = 2;
        $evaluation = new \Deriver\Internal\Solver\Summary\Evaluation(new \Deriver\Internal\Solver\Machine($context));
        self::assertTrue($evaluation->evaluate($cell));
        self::assertSame('frontier', $cell->status);
        self::assertSame(2, $cell->updates);
        self::assertSame(0, $context->transfers);
        self::assertSame(['return','throw'], array_map(static fn ($path) => $path->completion->kind, $evaluation->replay($cell, $entry)));
    }
    public function testSealRetainsTheBoundedKnownOutcomeBeforeResiduals(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new \Deriver\Api\Query\Budget(partitions:1));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new \Deriver\Internal\Solver\State();
        $key = (new \Deriver\Internal\Solver\Summary\Invocation())->key($body, $entry, []);
        $cell = $context->summaries->register($key, $body, $entry);
        $first = $entry->fork();
        $first->completion = new \Deriver\Internal\Solver\Completion('return', \Deriver\Value\Term::constant(1));
        $second = $entry->fork();
        $second->completion = new \Deriver\Internal\Solver\Completion('return', \Deriver\Value\Term::constant(2));
        $a = new \Deriver\Internal\Solver\Summary\CompletionRecord($first, 0);
        $b = new \Deriver\Internal\Solver\Summary\CompletionRecord($second, 0);
        $cell->outcomes = [$a->id() => $a,$b->id() => $b];
        $evaluation = new \Deriver\Internal\Solver\Summary\Evaluation(new \Deriver\Internal\Solver\Machine($context));
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

}
