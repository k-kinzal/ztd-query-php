<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use Deriver\Evaluation\Context;
use Deriver\Model\Provider\Provider;
use Deriver\Model\Registration\Registry;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Query\Budget;
use Deriver\Query\CancellationToken;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\SourceRef;
use Deriver\Result\Derivation;
use Deriver\Result\Frontier;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Context::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\ResidualPaths::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
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
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(CancellationToken::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Exceptional::class)]
#[UsesClass(Frontier::class)]
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
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(Term::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
