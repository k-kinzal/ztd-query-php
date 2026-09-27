<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\ResidualPaths;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
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
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Provider\Provider;
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
use Deriver\Query\CancellationToken;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Assessment;
use Deriver\Result\Derivation;
use Deriver\Result\DerivationResult;
use Deriver\Result\Exceptional;
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
use Deriver\Value\Identity;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Context::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(ResidualPaths::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
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
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
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
#[UsesClass(CancellationToken::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(Exceptional::class)]
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
#[UsesClass(Identity::class)]
#[UsesClass(Term::class)]
#[Small]
final class ContextTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testFrontierPreservesTheSemanticContract(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){ $x=1; $x++; return $x; }');
        $result = $session->derive(new ReturnQuery('target', budget: new Budget(transfers: 1)));
        self::assertSame('open', $result->assessment->closure);
        self::assertSame('BUDGET_EXCEEDED', $result->frontiers[0]->code);
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->kind);
    }
    public function testAdmitSealsTheContextAtTheLogicalWorkLimit(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(transfers:1));
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        self::assertTrue($context->admit($source));
        self::assertFalse($context->admit($source));
        self::assertTrue($context->sealed);
        self::assertSame(1, $context->transfers);
    }
    public function testAvailableSealsCancelledQueriesWithoutAddingLogicalBudgetReasons(): void
    {
        $token = new CancellationToken();
        $token->cancel();
        $fixture = \Tests\Fake\SolverFixture::context();
        $config = new Configuration(resources:new ResourceLimits(cancellation:$token));
        $context = new Context($fixture->program, $fixture->query, $config, new Registry($config));
        $source = \Tests\Fake\SummaryFixture::body($context)->source;
        self::assertFalse($context->available($source));
        self::assertSame('CANCELLED', $context->stopReason);
        self::assertTrue($context->sealed);
        self::assertSame(0, $context->transfers);
        self::assertSame(['CANCELLED'], array_values(array_map(static fn (Frontier $frontier): string => $frontier->code, $context->frontiers)));
    }

    public function testAvailableRetainsScopeWorldEnvironmentAndVersionedProviderAssumptions(): void
    {
        $provider = self::createStub(Provider::class);
        $provider->method('id')->willReturn('project.contracts');
        $provider->method('version')->willReturn('4');
        $config = new Configuration(closedWorld:true, environmentVersion:'deployment-7', providers:[$provider]);
        $fixture = \Tests\Fake\SolverFixture::context();
        $query = new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target')]));
        $context = new Context($fixture->program, $query, $config, new Registry($config));
        self::assertTrue($context->available(new SourceRef('test', 'fixture.php', 0, 1)));
        self::assertSame(['target:php-8.3-64bit','scope:entrypoint','world:closed','environment:deployment-7','provider:project.contracts@4'], $context->assumptions);
        self::assertSame([], $context->frontiers);
        self::assertSame([], $context->normal);
        self::assertSame([], $context->exceptional);
    }

    public function testFrontierPreservesBoundDependenciesAndDeduplicatesTheSameCause(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'fixture.php', 0, 10);
        $secret = Term::constant('private', true);
        $first = $context->frontier('INCOMPLETE_SOURCE', $source, 'missing-call', [$secret], 'string');
        $replacement = $context->frontier('INCOMPLETE_SOURCE', $source, 'missing-call', [$secret], 'array');
        self::assertSame('string', $first->attributes['type']);
        self::assertTrue($replacement->isSecret());
        self::assertSame([$secret], $replacement->operands);
        self::assertCount(1, $context->frontiers);
        $frontier = array_values($context->frontiers)[0];
        self::assertSame('INCOMPLETE_SOURCE', $frontier->code);
        self::assertSame($source, $frontier->at);
        self::assertSame('missing-call', $frontier->operation);
        self::assertSame('missing-call', $frontier->missingCapability);
        self::assertSame(['value','state'], $frontier->affectedProjections);
        self::assertSame($replacement, $frontier->residual);
    }

    public function testFrontierKeepsDifferentSitesCodesAndOperationsSeparate(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $one = new SourceRef('test', 'fixture.php', 0, 1);
        $two = new SourceRef('test', 'fixture.php', 1, 2);
        $context->frontier('INCOMPLETE_SOURCE', $one, 'first');
        $context->frontier('INCOMPLETE_SOURCE', $two, 'first');
        $context->frontier('INCOMPLETE_SOURCE', $one, 'second');
        $context->frontier('OPEN_DISPATCH', $one, 'first');
        self::assertCount(4, $context->frontiers);
    }

    public function testAdmitCountsTransfersOnlyAfterBothBudgetsPermitWork(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(transfers:10, nodes:2));
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $context->evidence['one'] = new Derivation('one', 'data', $source);
        self::assertTrue($context->admit($source));
        self::assertSame(1, $context->transfers);
        $context->evidence['two'] = new Derivation('two', 'data', $source);
        self::assertFalse($context->admit($source));
        self::assertSame(1, $context->transfers);
        self::assertTrue($context->sealed);
        self::assertSame('logical-work', array_values($context->frontiers)[0]->operation);
    }

    public function testAdmitDoesNotReopenASealedQuery(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $context->sealed = true;
        self::assertFalse($context->admit(new SourceRef('test', 'fixture.php', 0, 1)));
        self::assertSame(0, $context->transfers);
        self::assertSame('BUDGET_EXCEEDED', array_values($context->frontiers)[0]->code);
    }

    public function testAdmitChecksCancellationBeforeChargingLogicalWork(): void
    {
        $token = new CancellationToken();
        $config = new Configuration(resources:new ResourceLimits(cancellation:$token));
        $context = \Tests\Fake\SolverFixture::context(configuration:$config);
        $token->cancel();
        self::assertFalse($context->admit(new SourceRef('test', 'fixture.php', 0, 1)));
        self::assertSame(0, $context->transfers);
        self::assertSame(['CANCELLED'], array_column($context->frontiers, 'code'));
    }

    public function testAvailableRejectsAnticipatedMemoryAndRetainsTheFirstCause(): void
    {
        $token = new CancellationToken();
        $config = new Configuration(resources:new ResourceLimits(memoryBytes:1048576, cancellation:$token));
        $context = \Tests\Fake\SolverFixture::context(configuration:$config);
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        self::assertTrue($context->available($source));
        self::assertFalse($context->available($source, 2097152));
        $token->cancel();
        self::assertFalse($context->available(new SourceRef('test', 'fixture.php', 1, 2)));
        self::assertSame('MEMORY_LIMIT', $context->stopReason);
        self::assertTrue($context->sealed);
        self::assertSame(0, $context->transfers);
        self::assertCount(1, $context->frontiers);
        self::assertSame($source, array_values($context->frontiers)[0]->at);
        self::assertSame('runtime-resources', array_values($context->frontiers)[0]->operation);
    }
}
