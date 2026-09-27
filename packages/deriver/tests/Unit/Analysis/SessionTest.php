<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use Deriver\Analysis\CallObservations;
use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\ResidualPaths;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
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
use Deriver\Exception\InvalidInputException;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Builtin\Library;
use Deriver\Model\Contract\DomainLaws;
use Deriver\Model\Domain\DomainFact;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Provider\ObservationProvider;
use Deriver\Model\Provider\Provider;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ModelPrecedence;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\SignatureIdentity;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
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
use Deriver\Reference\ExpressionRef;
use Deriver\Reference\Observation;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Assessment;
use Deriver\Result\Derivation;
use Deriver\Result\DerivationResult;
use Deriver\Result\Exceptional;
use Deriver\Result\Explanation;
use Deriver\Result\Frontier;
use Deriver\Result\ResultSet;
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
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\EffectInspection;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\ConstantSignatures;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\CallSiteIndex;
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
use Deriver\Value\SecretFingerprint;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Session::class)]
#[UsesClass(CallObservations::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(UnknownCall::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(ResidualPaths::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
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
#[UsesClass(Library::class)]
#[UsesClass(DomainLaws::class)]
#[UsesClass(DomainFact::class)]
#[UsesClass(\Deriver\Model\Domain\DomainOperations::class)]
#[UsesClass(ModelDescriptor::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ModelPrecedence::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(SignatureIdentity::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(Signature::class)]
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
#[UsesClass(ExpressionRef::class)]
#[UsesClass(Observation::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(Exceptional::class)]
#[UsesClass(Explanation::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(ResultSet::class)]
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
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallSiteIndex::class)]
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
#[UsesClass(SecretFingerprint::class)]
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
