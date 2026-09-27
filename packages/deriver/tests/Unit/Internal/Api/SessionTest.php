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
#[UsesClass(\Deriver\Api\AnalysisSession::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Program::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\ModelPrecedence::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\SignatureIdentity::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
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
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Internal\Value\SecretFingerprint::class)]
#[UsesClass(\Deriver\Model\CallModel::class)]
#[UsesClass(\Deriver\Model\Contract\DomainLaws::class)]
#[UsesClass(\Deriver\Model\Domain\AbstractDomain::class)]
#[UsesClass(\Deriver\Model\Domain\DomainFact::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Provider\DeclarationProvider::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchProvider::class)]
#[UsesClass(\Deriver\Model\Provider\EntryPointProvider::class)]
#[UsesClass(\Deriver\Model\Provider\EnvironmentProvider::class)]
#[UsesClass(\Deriver\Model\Provider\ObservationProvider::class)]
#[UsesClass(\Deriver\Model\Provider\Provider::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
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

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSemanticConfiguration')]
    public function testSnapshotSeparatesEveryCapturedSemanticAssumption(\Deriver\Api\Project\Configuration $configuration): void
    {
        $input = new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('a.php', '<?php function target(){return 1;}')]);
        $baseline = new \Deriver\Internal\Api\Session($input, new \Deriver\Api\Project\Configuration());
        $changed = new \Deriver\Internal\Api\Session($input, $configuration);
        self::assertNotSame($baseline->snapshot()->id, $changed->snapshot()->id);
        self::assertSame($configuration->closedWorld, $changed->snapshot()->closedWorld);
        self::assertSame($configuration->environmentVersion, $changed->snapshot()->environmentVersion);
        self::assertSame($configuration->dependencyVersions, $changed->snapshot()->dependencyVersions);
    }
    /**
     * @return iterable<string,array{\Deriver\Api\Project\Configuration}>
     */
    public static function providerSemanticConfiguration(): iterable
    {
        yield 'closed world' => [new \Deriver\Api\Project\Configuration(closedWorld:true)];
        yield 'environment version' => [new \Deriver\Api\Project\Configuration(environmentVersion:'revision-2')];
        yield 'environment value' => [new \Deriver\Api\Project\Configuration(environment:['constant:EXAMPLE' => \Deriver\Value\Term::constant(2)])];
        yield 'secret environment' => [new \Deriver\Api\Project\Configuration(environment:['constant:EXAMPLE' => \Deriver\Value\Term::constant(2, true)])];
        yield 'dependency version' => [new \Deriver\Api\Project\Configuration(dependencyVersions:['vendor/library' => '2'])];
        yield 'standard models' => [new \Deriver\Api\Project\Configuration(standardModels:false)];
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotNormalizesSourceAndAssumptionOrder(): void
    {
        $a = new \Deriver\Api\Project\SourceFile('a.php', '<?php function a(){return 1;}');
        $b = new \Deriver\Api\Project\SourceFile('b.php', '<?php function b(){return 2;}');
        $first = new \Deriver\Internal\Api\Session(new \Deriver\Api\Project\ProjectInput([$b,$a]), new \Deriver\Api\Project\Configuration(environment:['b' => \Deriver\Value\Term::constant(2),'a' => \Deriver\Value\Term::constant(1)], dependencyVersions:['b' => '2','a' => '1']));
        $second = new \Deriver\Internal\Api\Session(new \Deriver\Api\Project\ProjectInput([$a,$b]), new \Deriver\Api\Project\Configuration(environment:['a' => \Deriver\Value\Term::constant(1),'b' => \Deriver\Value\Term::constant(2)], dependencyVersions:['a' => '1','b' => '2']));
        self::assertSame($first->snapshot()->id, $second->snapshot()->id);
        self::assertSame(['a.php' => hash('sha256', $a->contents),'b.php' => hash('sha256', $b->contents)], $first->snapshot()->sources);
        self::assertSame(['a' => '1','b' => '2'], $first->snapshot()->dependencyVersions);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotDistinguishesDeclarationsOnlyFromExecutableSources(): void
    {
        $source = '<?php function target(){return 1;}';
        $executable = new \Deriver\Internal\Api\Session(new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('a.php', $source)]), new \Deriver\Api\Project\Configuration());
        $declarations = new \Deriver\Internal\Api\Session(new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('a.php', $source, true)]), new \Deriver\Api\Project\Configuration());
        self::assertNotSame($executable->snapshot()->id, $declarations->snapshot()->id);
        self::assertSame([], $executable->snapshot()->sourceModes);
        self::assertSame(['a.php' => 'declarations'], $declarations->snapshot()->sourceModes);
        self::assertSame($executable->snapshot()->sources, $declarations->snapshot()->sources);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotIncludesConfidentialityInEnvironmentIdentity(): void
    {
        $source = '<?php function target(){return 1;}';
        $public = \Tests\Fake\Analysis::session($source, new \Deriver\Api\Project\Configuration(environment:['example' => \Deriver\Value\Term::constant('same')]));
        $secret = \Tests\Fake\Analysis::session($source, new \Deriver\Api\Project\Configuration(environment:['example' => \Deriver\Value\Term::constant('same', true)]));
        self::assertNotSame($public->snapshot()->id, $secret->snapshot()->id);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDeriveReusesOnlyTheSameNormalizedQuery(): void
    {
        $session = new \Deriver\Internal\Api\Session(new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('a.php', '<?php function target(){return 1;}')]), new \Deriver\Api\Project\Configuration());
        $query = new \Deriver\Api\Query\ReturnQuery('target');
        $first = $session->derive($query);
        $again = $session->derive(new \Deriver\Api\Query\ReturnQuery('target'));
        $different = $session->derive(new \Deriver\Api\Query\ReturnQuery('target', budget:new \Deriver\Api\Query\Budget(transfers:5000)));
        self::assertSame($first, $again);
        self::assertNotSame($first, $different);
        self::assertNotSame($first->reference->id, $different->reference->id);
        self::assertCount(2, $session->cache);
        self::assertSame([$first->reference->id => $first,$different->reference->id => $different], $session->results);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testExplainPreservesFrontiersAndAssumptionsAlongsideEvidence(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){return external_call();}');
        $result = $session->derive(new \Deriver\Api\Query\ReturnQuery('target'));
        $explanation = $session->explain($result->reference);
        self::assertNotEmpty($result->frontiers);
        self::assertNotEmpty($result->evidence);
        self::assertSame($result->frontiers, $explanation->frontiers);
        self::assertSame($result->evidence, $explanation->nodes);
        self::assertSame($result->assumptions, $explanation->assumptions);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testObservationsSortsContributionsAndIgnoresProvidersWithoutQueries(): void
    {
        $queries = self::createStub(\Deriver\Model\Provider\ObservationProvider::class);
        $queries->method('id')->willReturn('example.queries');
        $queries->method('version')->willReturn('1');
        $a = new \Deriver\Api\Query\ReturnQuery('a');
        $b = new \Deriver\Api\Query\ReturnQuery('b');
        $queries->method('queries')->willReturn(['z' => $b,'a' => $a]);
        $ordinary = self::createStub(\Deriver\Model\Provider\Provider::class);
        $ordinary->method('id')->willReturn('example.ordinary');
        $ordinary->method('version')->willReturn('1');
        $session = \Tests\Fake\Analysis::session('<?php function a(){}function b(){}', new \Deriver\Api\Project\Configuration(providers:[$ordinary,$queries]));
        self::assertSame(['a' => $a,'z' => $b], $session->observations());
        self::assertSame([], $session->entrypoints());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testObservationsRejectsConflictingNamesAcrossProviders(): void
    {
        $first = self::createStub(\Deriver\Model\Provider\ObservationProvider::class);
        $first->method('id')->willReturn('example.first');
        $first->method('version')->willReturn('1');
        $first->method('queries')->willReturn(['same' => new \Deriver\Api\Query\ReturnQuery('a')]);
        $second = self::createStub(\Deriver\Model\Provider\ObservationProvider::class);
        $second->method('id')->willReturn('example.second');
        $second->method('version')->willReturn('1');
        $second->method('queries')->willReturn(['same' => new \Deriver\Api\Query\ReturnQuery('b')]);
        $session = \Tests\Fake\Analysis::session('<?php function a(){}function b(){}', new \Deriver\Api\Project\Configuration(providers:[$first,$second]));
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONFLICT: repeated observation name same');
        $session->observations();
    }


    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerModelIdentityPairs')]
    public function testSnapshotSeparatesModelContractsWithoutDelimiterCollisions(\Deriver\Model\ModelDescriptor $firstDescriptor, \Deriver\Model\ModelDescriptor $secondDescriptor): void
    {
        $plan = new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant(1)))]);
        $first = new \Tests\Fake\PlanModel($firstDescriptor, $plan);
        $second = new \Tests\Fake\PlanModel($secondDescriptor, $plan);
        $source = '<?php function target(){return 1;}';
        $a = \Tests\Fake\Analysis::session($source, new \Deriver\Api\Project\Configuration(models:[$first]));
        $b = \Tests\Fake\Analysis::session($source, new \Deriver\Api\Project\Configuration(models:[$second]));
        self::assertNotSame($a->snapshot()->id, $b->snapshot()->id);
        self::assertNotSame($a->snapshot()->models, $b->snapshot()->models);
    }
    /**
     * @return iterable<string,array{\Deriver\Model\ModelDescriptor,\Deriver\Model\ModelDescriptor}>
     */
    public static function providerModelIdentityPairs(): iterable
    {
        $base = new \Deriver\Model\ModelDescriptor('example.model', '1', 'run');
        yield 'version separator' => [new \Deriver\Model\ModelDescriptor('example.model', '1:Box:', 'run'),new \Deriver\Model\ModelDescriptor('example.model', '1', 'Box::run')];
        yield 'replacement list separator' => [new \Deriver\Model\ModelDescriptor('example.model', '1', 'run', replaces:['one,two']),new \Deriver\Model\ModelDescriptor('example.model', '1', 'run', replaces:['one','two'])];
        yield 'model id' => [$base,new \Deriver\Model\ModelDescriptor('example.other', '1', 'run')];
        yield 'version' => [$base,new \Deriver\Model\ModelDescriptor('example.model', '2', 'run')];
        yield 'symbol' => [$base,new \Deriver\Model\ModelDescriptor('example.model', '1', 'other')];
        yield 'priority' => [$base,new \Deriver\Model\ModelDescriptor('example.model', '1', 'run', priority:10)];
        yield 'replacement declaration' => [$base,new \Deriver\Model\ModelDescriptor('example.model', '1', 'run', replaces:['legacy'])];
        yield 'source replacement' => [$base,new \Deriver\Model\ModelDescriptor('example.model', '1', 'run', replaceSource:true)];
        yield 'signature' => [$base,new \Deriver\Model\ModelDescriptor('example.model', '1', 'run', new \Deriver\Model\Signature\Signature([new \Deriver\Model\Signature\Parameter('value', 'int')]))];
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotNormalizesModelRegistrationOrder(): void
    {
        $plan = new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant(1)))]);
        $a = new \Tests\Fake\PlanModel(new \Deriver\Model\ModelDescriptor('example.a', '1', 'a'), $plan);
        $z = new \Tests\Fake\PlanModel(new \Deriver\Model\ModelDescriptor('example.z', '2', 'z'), $plan);
        $source = '<?php function target(){return 1;}';
        $first = \Tests\Fake\Analysis::session($source, new \Deriver\Api\Project\Configuration(models:[$z,$a]));
        $second = \Tests\Fake\Analysis::session($source, new \Deriver\Api\Project\Configuration(models:[$a,$z]));
        self::assertSame($first->snapshot()->id, $second->snapshot()->id);
        self::assertSame($first->snapshot()->models, $second->snapshot()->models);
        self::assertSame(['model:example.a','model:example.z'], array_keys($first->snapshot()->models));
    }


    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotKeepsModelAndDomainIdentitiesInSeparateNamespaces(): void
    {
        $plan = new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant(1)))]);
        $first = new \Tests\Fake\PlanModel(new \Deriver\Model\ModelDescriptor('domain:example.policy', '1', 'run'), $plan);
        $second = new \Tests\Fake\PlanModel(new \Deriver\Model\ModelDescriptor('domain:example.policy', '2', 'run'), $plan);
        $source = '<?php function target(){return 1;}';
        $a = \Tests\Fake\Analysis::session($source, new \Deriver\Api\Project\Configuration(models:[$first], domains:[new \Tests\Fake\PolicyDomain()]));
        $b = \Tests\Fake\Analysis::session($source, new \Deriver\Api\Project\Configuration(models:[$second], domains:[new \Tests\Fake\PolicyDomain()]));
        self::assertNotSame($a->snapshot()->id, $b->snapshot()->id);
        self::assertCount(2, $a->snapshot()->models);
        self::assertSame('1', $a->snapshot()->models['domain:example.policy']);
        self::assertArrayHasKey('model:domain:example.policy', $a->snapshot()->models);
    }

}
