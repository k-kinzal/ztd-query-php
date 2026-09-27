<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use Deriver\Analysis\Session;
use Deriver\Exception\InvalidInputException;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Provider\ObservationProvider;
use Deriver\Model\Provider\Provider;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\Budget;
use Deriver\Query\CancellationToken;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\ResultRef;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Session::class)]
#[UsesClass(\Deriver\Analysis\CallObservations::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\ResidualPaths::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
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
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Contract\DomainLaws::class)]
#[UsesClass(\Deriver\Model\Domain\DomainFact::class)]
#[UsesClass(\Deriver\Model\Domain\DomainOperations::class)]
#[UsesClass(ModelDescriptor::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ModelPrecedence::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\SignatureIdentity::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(Signature::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(CancellationToken::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ExpressionRef::class)]
#[UsesClass(\Deriver\Reference\Observation::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Exceptional::class)]
#[UsesClass(\Deriver\Result\Explanation::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Result\ResultSet::class)]
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
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallSiteIndex::class)]
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
#[UsesClass(\Deriver\Value\SecretFingerprint::class)]
#[UsesClass(Term::class)]
#[Small]
final class SessionTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDerivePreservesTheSemanticContract(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){return 42;}');
        $first = $session->derive(new ReturnQuery('target'));
        $second = $session->derive(new ReturnQuery('target'));
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
        $results = $session->deriveMany([new ReturnQuery('two'), new ReturnQuery('one')]);
        self::assertSame(2, $results->results[0]->normalOutcomes[0]->values['return']->native());
        self::assertSame(1, $results->results[1]->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testExplainFindsTheDerivedResultAndRejectsForeignIds(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){return 1;}');
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame($result->evidence, $session->explain($result->reference)->nodes);
        $this->expectException(InvalidInputException::class);
        $session->explain(new ResultRef('foreign'));
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
        $session = \Tests\Fake\Analysis::session('<?php function entry(){return 1;}', new Configuration(providers: [new \Tests\Fake\MiniContainer()]));
        self::assertSame('entry', $session->entrypoints()[0]->symbol);
        self::assertSame([], \Tests\Fake\Analysis::session('<?php function entry(){}')->entrypoints());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testObservationsCreatesQueriesUsingTheCapturedEntries(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function entry(){return 1;}', new Configuration(providers: [new \Tests\Fake\MiniContainer()]));
        $query = $session->observations()['entry-return'];
        self::assertSame('entrypoint', $query->scope()->mode);
        self::assertSame('entry', $query->scope()->entries[0]->symbol);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testDeriveCancellationOverridesCachedSuccessWithoutReplacingItsExplanation(): void
    {
        $token = new CancellationToken();
        $config = new Configuration(resources:new ResourceLimits(cancellation:$token));
        $session = \Tests\Fake\Analysis::session('<?php function target(){return 7;}', $config);
        $query = new ReturnQuery('target');
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
        $token = new CancellationToken();
        $token->cancel();
        $config = new Configuration(resources:new ResourceLimits(cancellation:$token));
        $result = \Tests\Fake\Analysis::session('<?php function target(){}', $config)->derive(new ReturnQuery('target'));
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->kind);
        self::assertSame('CANCELLED', $result->normalOutcomes[0]->values['return']->literal);
        self::assertSame(0, $result->statistics->transfers);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotIncludesDependencyVersionsInCacheIdentity(): void
    {
        $a = \Tests\Fake\Analysis::session('<?php function target(){return 1;}', new Configuration(dependencyVersions: ['example/library' => '1']));
        $b = \Tests\Fake\Analysis::session('<?php function target(){return 1;}', new Configuration(dependencyVersions: ['example/library' => '2']));
        self::assertNotSame($a->snapshot()->id, $b->snapshot()->id);
        self::assertSame(['example/library' => '2'], $b->snapshot()->dependencyVersions);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSemanticConfiguration')]
    public function testSnapshotSeparatesEveryCapturedSemanticAssumption(Configuration $configuration): void
    {
        $input = new ProjectInput([new SourceFile('a.php', '<?php function target(){return 1;}')]);
        $baseline = new Session($input, new Configuration());
        $changed = new Session($input, $configuration);
        self::assertNotSame($baseline->snapshot()->id, $changed->snapshot()->id);
        self::assertSame($configuration->closedWorld, $changed->snapshot()->closedWorld);
        self::assertSame($configuration->environmentVersion, $changed->snapshot()->environmentVersion);
        self::assertSame($configuration->dependencyVersions, $changed->snapshot()->dependencyVersions);
    }
    /**
     * @return iterable<string,array{Configuration}>
     */
    public static function providerSemanticConfiguration(): iterable
    {
        yield 'closed world' => [new Configuration(closedWorld:true)];
        yield 'environment version' => [new Configuration(environmentVersion:'revision-2')];
        yield 'environment value' => [new Configuration(environment:['constant:EXAMPLE' => Term::constant(2)])];
        yield 'secret environment' => [new Configuration(environment:['constant:EXAMPLE' => Term::constant(2, true)])];
        yield 'dependency version' => [new Configuration(dependencyVersions:['vendor/library' => '2'])];
        yield 'standard models' => [new Configuration(standardModels:false)];
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotNormalizesSourceAndAssumptionOrder(): void
    {
        $a = new SourceFile('a.php', '<?php function a(){return 1;}');
        $b = new SourceFile('b.php', '<?php function b(){return 2;}');
        $first = new Session(new ProjectInput([$b,$a]), new Configuration(environment:['b' => Term::constant(2),'a' => Term::constant(1)], dependencyVersions:['b' => '2','a' => '1']));
        $second = new Session(new ProjectInput([$a,$b]), new Configuration(environment:['a' => Term::constant(1),'b' => Term::constant(2)], dependencyVersions:['a' => '1','b' => '2']));
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
        $executable = new Session(new ProjectInput([new SourceFile('a.php', $source)]), new Configuration());
        $declarations = new Session(new ProjectInput([new SourceFile('a.php', $source, true)]), new Configuration());
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
        $public = \Tests\Fake\Analysis::session($source, new Configuration(environment:['example' => Term::constant('same')]));
        $secret = \Tests\Fake\Analysis::session($source, new Configuration(environment:['example' => Term::constant('same', true)]));
        self::assertNotSame($public->snapshot()->id, $secret->snapshot()->id);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDeriveReusesOnlyTheSameNormalizedQuery(): void
    {
        $session = new Session(new ProjectInput([new SourceFile('a.php', '<?php function target(){return 1;}')]), new Configuration());
        $query = new ReturnQuery('target');
        $first = $session->derive($query);
        $again = $session->derive(new ReturnQuery('target'));
        $different = $session->derive(new ReturnQuery('target', budget:new Budget(transfers:5000)));
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
        $result = $session->derive(new ReturnQuery('target'));
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
        $queries = self::createStub(ObservationProvider::class);
        $queries->method('id')->willReturn('example.queries');
        $queries->method('version')->willReturn('1');
        $a = new ReturnQuery('a');
        $b = new ReturnQuery('b');
        $queries->method('queries')->willReturn(['z' => $b,'a' => $a]);
        $ordinary = self::createStub(Provider::class);
        $ordinary->method('id')->willReturn('example.ordinary');
        $ordinary->method('version')->willReturn('1');
        $session = \Tests\Fake\Analysis::session('<?php function a(){}function b(){}', new Configuration(providers:[$ordinary,$queries]));
        self::assertSame(['a' => $a,'z' => $b], $session->observations());
        self::assertSame([], $session->entrypoints());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testObservationsRejectsConflictingNamesAcrossProviders(): void
    {
        $first = self::createStub(ObservationProvider::class);
        $first->method('id')->willReturn('example.first');
        $first->method('version')->willReturn('1');
        $first->method('queries')->willReturn(['same' => new ReturnQuery('a')]);
        $second = self::createStub(ObservationProvider::class);
        $second->method('id')->willReturn('example.second');
        $second->method('version')->willReturn('1');
        $second->method('queries')->willReturn(['same' => new ReturnQuery('b')]);
        $session = \Tests\Fake\Analysis::session('<?php function a(){}function b(){}', new Configuration(providers:[$first,$second]));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONFLICT: repeated observation name same');
        $session->observations();
    }


    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerModelIdentityPairs')]
    public function testSnapshotSeparatesModelContractsWithoutDelimiterCollisions(ModelDescriptor $firstDescriptor, ModelDescriptor $secondDescriptor): void
    {
        $plan = new SemanticPlan([Action::returns(Expression::literal(Term::constant(1)))]);
        $first = new \Tests\Fake\PlanModel($firstDescriptor, $plan);
        $second = new \Tests\Fake\PlanModel($secondDescriptor, $plan);
        $source = '<?php function target(){return 1;}';
        $a = \Tests\Fake\Analysis::session($source, new Configuration(models:[$first]));
        $b = \Tests\Fake\Analysis::session($source, new Configuration(models:[$second]));
        self::assertNotSame($a->snapshot()->id, $b->snapshot()->id);
        self::assertNotSame($a->snapshot()->models, $b->snapshot()->models);
    }
    /**
     * @return iterable<string,array{ModelDescriptor,ModelDescriptor}>
     */
    public static function providerModelIdentityPairs(): iterable
    {
        $base = new ModelDescriptor('example.model', '1', 'run');
        yield 'version separator' => [new ModelDescriptor('example.model', '1:Box:', 'run'),new ModelDescriptor('example.model', '1', 'Box::run')];
        yield 'replacement list separator' => [new ModelDescriptor('example.model', '1', 'run', replaces:['one,two']),new ModelDescriptor('example.model', '1', 'run', replaces:['one','two'])];
        yield 'model id' => [$base,new ModelDescriptor('example.other', '1', 'run')];
        yield 'version' => [$base,new ModelDescriptor('example.model', '2', 'run')];
        yield 'symbol' => [$base,new ModelDescriptor('example.model', '1', 'other')];
        yield 'priority' => [$base,new ModelDescriptor('example.model', '1', 'run', priority:10)];
        yield 'replacement declaration' => [$base,new ModelDescriptor('example.model', '1', 'run', replaces:['legacy'])];
        yield 'source replacement' => [$base,new ModelDescriptor('example.model', '1', 'run', replaceSource:true)];
        yield 'signature' => [$base,new ModelDescriptor('example.model', '1', 'run', new Signature([new Parameter('value', 'int')]))];
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotNormalizesModelRegistrationOrder(): void
    {
        $plan = new SemanticPlan([Action::returns(Expression::literal(Term::constant(1)))]);
        $a = new \Tests\Fake\PlanModel(new ModelDescriptor('example.a', '1', 'a'), $plan);
        $z = new \Tests\Fake\PlanModel(new ModelDescriptor('example.z', '2', 'z'), $plan);
        $source = '<?php function target(){return 1;}';
        $first = \Tests\Fake\Analysis::session($source, new Configuration(models:[$z,$a]));
        $second = \Tests\Fake\Analysis::session($source, new Configuration(models:[$a,$z]));
        self::assertSame($first->snapshot()->id, $second->snapshot()->id);
        self::assertSame($first->snapshot()->models, $second->snapshot()->models);
        self::assertSame(['model:example.a','model:example.z'], array_keys($first->snapshot()->models));
    }


    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotKeepsModelAndDomainIdentitiesInSeparateNamespaces(): void
    {
        $plan = new SemanticPlan([Action::returns(Expression::literal(Term::constant(1)))]);
        $first = new \Tests\Fake\PlanModel(new ModelDescriptor('domain:example.policy', '1', 'run'), $plan);
        $second = new \Tests\Fake\PlanModel(new ModelDescriptor('domain:example.policy', '2', 'run'), $plan);
        $source = '<?php function target(){return 1;}';
        $a = \Tests\Fake\Analysis::session($source, new Configuration(models:[$first], domains:[new \Tests\Fake\PolicyDomain()]));
        $b = \Tests\Fake\Analysis::session($source, new Configuration(models:[$second], domains:[new \Tests\Fake\PolicyDomain()]));
        self::assertNotSame($a->snapshot()->id, $b->snapshot()->id);
        self::assertCount(2, $a->snapshot()->models);
        self::assertSame('1', $a->snapshot()->models['domain:example.policy']);
        self::assertArrayHasKey('model:domain:example.policy', $a->snapshot()->models);
    }

}
