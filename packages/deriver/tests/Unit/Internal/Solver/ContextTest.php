<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\CancellationToken::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ResidualPaths::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ContextTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testFrontierPreservesTheSemanticContract(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){ $x=1; $x++; return $x; }');
        $result = $session->derive(new \Deriver\Api\Query\ReturnQuery('target', budget: new \Deriver\Api\Query\Budget(transfers: 1)));
        self::assertSame('open', $result->assessment->closure);
        self::assertSame('BUDGET_EXCEEDED', $result->frontiers[0]->code);
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->kind);
    }
    public function testAdmitSealsTheContextAtTheLogicalWorkLimit(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new \Deriver\Api\Query\Budget(transfers:1));
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        self::assertTrue($context->admit($source));
        self::assertFalse($context->admit($source));
        self::assertTrue($context->sealed);
        self::assertSame(1, $context->transfers);
    }
    public function testAvailableSealsCancelledQueriesWithoutAddingLogicalBudgetReasons(): void
    {
        $token = new \Deriver\Api\Execution\CancellationToken();
        $token->cancel();
        $fixture = \Tests\Fake\SolverFixture::context();
        $config = new \Deriver\Api\Project\Configuration(resources:new \Deriver\Api\Execution\ResourceLimits(cancellation:$token));
        $context = new \Deriver\Internal\Solver\Context($fixture->program, $fixture->query, $config, new \Deriver\Internal\Model\Registry($config));
        $source = \Tests\Fake\SummaryFixture::body($context)->source;
        self::assertFalse($context->available($source));
        self::assertSame('CANCELLED', $context->stopReason);
        self::assertTrue($context->sealed);
        self::assertSame(0, $context->transfers);
        self::assertSame(['CANCELLED'], array_values(array_map(static fn (\Deriver\Api\Result\Frontier $frontier): string => $frontier->code, $context->frontiers)));
    }

    public function testAvailableRetainsScopeWorldEnvironmentAndVersionedProviderAssumptions(): void
    {
        $provider = self::createStub(\Deriver\Model\Provider\Provider::class);
        $provider->method('id')->willReturn('project.contracts');
        $provider->method('version')->willReturn('4');
        $config = new \Deriver\Api\Project\Configuration(closedWorld:true, environmentVersion:'deployment-7', providers:[$provider]);
        $fixture = \Tests\Fake\SolverFixture::context();
        $query = new \Deriver\Api\Query\ReturnQuery('target', \Deriver\Api\Query\QueryScope::fromEntrypoints([new \Deriver\Api\Project\EntryPoint('target')]));
        $context = new \Deriver\Internal\Solver\Context($fixture->program, $query, $config, new \Deriver\Internal\Model\Registry($config));
        self::assertTrue($context->available(new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1)));
        self::assertSame(['target:php-8.3-64bit','scope:entrypoint','world:closed','environment:deployment-7','provider:project.contracts@4'], $context->assumptions);
        self::assertSame([], $context->frontiers);
        self::assertSame([], $context->normal);
        self::assertSame([], $context->exceptional);
    }

    public function testFrontierPreservesBoundDependenciesAndDeduplicatesTheSameCause(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 10);
        $secret = \Deriver\Value\Term::constant('private', true);
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
        $one = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $two = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 1, 2);
        $context->frontier('INCOMPLETE_SOURCE', $one, 'first');
        $context->frontier('INCOMPLETE_SOURCE', $two, 'first');
        $context->frontier('INCOMPLETE_SOURCE', $one, 'second');
        $context->frontier('OPEN_DISPATCH', $one, 'first');
        self::assertCount(4, $context->frontiers);
    }

    public function testAdmitCountsTransfersOnlyAfterBothBudgetsPermitWork(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new \Deriver\Api\Query\Budget(transfers:10, nodes:2));
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $context->evidence['one'] = new \Deriver\Api\Result\Derivation('one', 'data', $source);
        self::assertTrue($context->admit($source));
        self::assertSame(1, $context->transfers);
        $context->evidence['two'] = new \Deriver\Api\Result\Derivation('two', 'data', $source);
        self::assertFalse($context->admit($source));
        self::assertSame(1, $context->transfers);
        self::assertTrue($context->sealed);
        self::assertSame('logical-work', array_values($context->frontiers)[0]->operation);
    }

    public function testAdmitDoesNotReopenASealedQuery(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $context->sealed = true;
        self::assertFalse($context->admit(new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1)));
        self::assertSame(0, $context->transfers);
        self::assertSame('BUDGET_EXCEEDED', array_values($context->frontiers)[0]->code);
    }

    public function testAdmitChecksCancellationBeforeChargingLogicalWork(): void
    {
        $token = new \Deriver\Api\Execution\CancellationToken();
        $config = new \Deriver\Api\Project\Configuration(resources:new \Deriver\Api\Execution\ResourceLimits(cancellation:$token));
        $context = \Tests\Fake\SolverFixture::context(configuration:$config);
        $token->cancel();
        self::assertFalse($context->admit(new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1)));
        self::assertSame(0, $context->transfers);
        self::assertSame(['CANCELLED'], array_column($context->frontiers, 'code'));
    }

    public function testAvailableRejectsAnticipatedMemoryAndRetainsTheFirstCause(): void
    {
        $token = new \Deriver\Api\Execution\CancellationToken();
        $config = new \Deriver\Api\Project\Configuration(resources:new \Deriver\Api\Execution\ResourceLimits(memoryBytes:1048576, cancellation:$token));
        $context = \Tests\Fake\SolverFixture::context(configuration:$config);
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        self::assertTrue($context->available($source));
        self::assertFalse($context->available($source, 2097152));
        $token->cancel();
        self::assertFalse($context->available(new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 1, 2)));
        self::assertSame('MEMORY_LIMIT', $context->stopReason);
        self::assertTrue($context->sealed);
        self::assertSame(0, $context->transfers);
        self::assertCount(1, $context->frontiers);
        self::assertSame($source, array_values($context->frontiers)[0]->at);
        self::assertSame('runtime-resources', array_values($context->frontiers)[0]->operation);
    }
}
