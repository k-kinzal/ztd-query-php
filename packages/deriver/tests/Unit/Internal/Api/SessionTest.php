<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Api;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\CancellationToken::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\InvalidInputException::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ExpressionRef::class)]
#[UsesClass(\Deriver\Api\Reference\Observation::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Exceptional::class)]
#[UsesClass(\Deriver\Api\Result\Explanation::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\ResultSet::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\CallObservations::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallSiteIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
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
#[UsesClass(\Deriver\Model\Provider\DeclarationProvider::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchProvider::class)]
#[UsesClass(\Deriver\Model\Provider\EntryPointProvider::class)]
#[UsesClass(\Deriver\Model\Provider\EnvironmentProvider::class)]
#[UsesClass(\Deriver\Model\Provider\ObservationProvider::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class SessionTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDerivePreservesTheSemanticContract(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){return 42;}');
        $first = $session->derive(new \Deriver\Api\Query\ReturnQuery('target'));
        $second = $session->derive(new \Deriver\Api\Query\ReturnQuery('target'));
        self::assertSame($first->normalOutcomes[0]->values['return']->native(), $second->normalOutcomes[0]->values['return']->native());
        self::assertSame($first->statistics->transfers, $second->statistics->transfers);
        self::assertSame($first->reference->id, $second->reference->id);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDeriveManyKeepsRequestOrderAndIndependentResults(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function one(){return 1;}function two(){return 2;}');
        $results = $session->deriveMany([new \Deriver\Api\Query\ReturnQuery('two'), new \Deriver\Api\Query\ReturnQuery('one')]);
        self::assertSame(2, $results->results[0]->normalOutcomes[0]->values['return']->native());
        self::assertSame(1, $results->results[1]->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testExplainFindsTheDerivedResultAndRejectsForeignIds(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){return 1;}');
        $result = $session->derive(new \Deriver\Api\Query\ReturnQuery('target'));
        self::assertSame($result->evidence, $session->explain($result->reference)->nodes);
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $session->explain(new \Deriver\Api\Reference\ResultRef('foreign'));
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotCapturesSourceHashes(): void
    {
        $source = '<?php function target(){return 1;}';
        $session = \Tests\Fake\Analysis::session($source);
        self::assertSame(['fixture.php' => hash('sha256', $source)], $session->snapshot()->sources);
        self::assertSame($session->snapshot(), $session->snapshot());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCallsToPreservesArgumentPositions(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){observe("first", 2);}');
        $sites = $session->callsTo('observe');
        self::assertCount(1, $sites);
        self::assertCount(2, $sites[0]->arguments);
        self::assertSame('target', $sites[0]->callable);
        self::assertNotSame($sites[0]->arguments[0]->register, $sites[0]->arguments[1]->register);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEntrypointsUsesOnlyExplicitProviderContributions(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function entry(){return 1;}', new \Deriver\Api\Project\Configuration(providers: [new \Tests\Fake\MiniContainer()]));
        self::assertSame('entry', $session->entrypoints()[0]->symbol);
        self::assertSame([], \Tests\Fake\Analysis::session('<?php function entry(){}')->entrypoints());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testObservationsCreatesQueriesUsingTheCapturedEntries(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function entry(){return 1;}', new \Deriver\Api\Project\Configuration(providers: [new \Tests\Fake\MiniContainer()]));
        $query = $session->observations()['entry-return'];
        self::assertSame('entrypoint', $query->scope()->mode);
        self::assertSame('entry', $query->scope()->entries[0]->symbol);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testDeriveCancellationOverridesCachedSuccessWithoutReplacingItsExplanation(): void
    {
        $token = new \Deriver\Api\Execution\CancellationToken();
        $config = new \Deriver\Api\Project\Configuration(resources:new \Deriver\Api\Execution\ResourceLimits(cancellation:$token));
        $session = \Tests\Fake\Analysis::session('<?php function target(){return 7;}', $config);
        $query = new \Deriver\Api\Query\ReturnQuery('target');
        $completed = $session->derive($query);
        $token->cancel();
        $cancelled = $session->derive($query);
        self::assertNotSame($completed->reference->id, $cancelled->reference->id);
        self::assertSame(7, $completed->normalOutcomes[0]->values['return']->native());
        self::assertSame('CANCELLED', $cancelled->frontiers[0]->code);
        self::assertNotEmpty($cancelled->exceptionalOutcomes);
        self::assertSame([], $session->explain($completed->reference)->frontiers);
        self::assertNotSame($cancelled, $session->derive($query));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testDeriveCancellationIsObservedBeforeAnEmptyCallableCompletes(): void
    {
        $token = new \Deriver\Api\Execution\CancellationToken();
        $token->cancel();
        $config = new \Deriver\Api\Project\Configuration(resources:new \Deriver\Api\Execution\ResourceLimits(cancellation:$token));
        $result = \Tests\Fake\Analysis::session('<?php function target(){}', $config)->derive(new \Deriver\Api\Query\ReturnQuery('target'));
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->kind);
        self::assertSame('CANCELLED', $result->normalOutcomes[0]->values['return']->literal);
        self::assertSame(0, $result->statistics->transfers);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotIncludesDependencyVersionsInCacheIdentity(): void
    {
        $a = \Tests\Fake\Analysis::session('<?php function target(){return 1;}', new \Deriver\Api\Project\Configuration(dependencyVersions: ['example/library' => '1']));
        $b = \Tests\Fake\Analysis::session('<?php function target(){return 1;}', new \Deriver\Api\Project\Configuration(dependencyVersions: ['example/library' => '2']));
        self::assertNotSame($a->snapshot()->id, $b->snapshot()->id);
        self::assertSame(['example/library' => '2'], $b->snapshot()->dependencyVersions);
    }
}
