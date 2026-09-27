<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Model;

use Deriver\Api\InvalidInputException;
use Deriver\Api\Project\TargetProfile;
use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\Model\ModelBoundary;
use Deriver\Model\CallDescription;
use Deriver\Model\CallModel;
use Deriver\Model\Domain\AbstractDomain;
use Deriver\Model\Domain\DomainFact;
use Deriver\Model\Intrinsic\IntrinsicDescriptor;
use Deriver\Model\Intrinsic\PureIntrinsic;
use Deriver\Model\Signature\Signature;
use Deriver\Value\Projection;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ModelBoundary::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\ModelPrecedence::class)]
#[UsesClass(\Deriver\Internal\Model\PlanActions::class)]
#[UsesClass(\Deriver\Internal\Model\PlanCompiler::class)]
#[UsesClass(\Deriver\Internal\Model\PlanFootprints::class)]
#[UsesClass(\Deriver\Internal\Model\PlanValidation::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\SignatureIdentity::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\Inputs::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(CallDescription::class)]
#[UsesClass(\Deriver\Model\Contract\DomainLaws::class)]
#[UsesClass(DomainFact::class)]
#[UsesClass(IntrinsicDescriptor::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchTarget::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(Projection::class)]
#[UsesClass(Term::class)]
#[Small]
final class ModelBoundaryTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDescribePreservesTheSemanticContract(): void
    {
        $model = new \Tests\Fake\PlanModel(
            new \Deriver\Model\ModelDescriptor('example.key', '1', 'key', new Signature([new \Deriver\Model\Signature\Parameter('id', 'int')])),
            new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::binary('.', \Deriver\Model\Plan\Expression::literal(Term::constant('user:')), \Deriver\Model\Plan\Expression::parameter('id')))]),
        );
        $session = \Tests\Fake\Analysis::session('<?php function target(){return key(3);}', new \Deriver\Api\Project\Configuration(models: [$model]));
        $result = $session->derive(new \Deriver\Api\Query\ReturnQuery('target'));
        self::assertSame('user:3', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testDescriptorCapturesTheDeclaredIdentity(): void
    {
        $model = new \Tests\Fake\PlanModel(new \Deriver\Model\ModelDescriptor('example', '1', 'f'), new \Deriver\Model\Plan\SemanticPlan([]));
        self::assertSame('example', (new ModelBoundary())->descriptor($model)->id);
    }
    public function testIntrinsicDescriptorRetainsExplicitDependencyIndices(): void
    {
        $descriptor = (new ModelBoundary())->intrinsicDescriptor(new \Tests\Fake\PolicyOperation('compose'));
        self::assertSame([0,1], $descriptor->dependencies);
        self::assertSame(2, $descriptor->arity);
    }
    public function testDomainRegistrationChecksTheLatticeBeforeAcceptingItsIdentity(): void
    {
        self::assertSame(['example.policy','1'], (new ModelBoundary())->domainRegistration(new \Tests\Fake\PolicyDomain()));
    }
    public function testEvaluateRejectsAnArityMismatchAsAContractFailure(): void
    {
        $result = (new ModelBoundary())->evaluate(new \Tests\Fake\PolicyOperation('compose'), [], new TargetProfile());
        self::assertSame('MODEL_CONTRACT_VIOLATION', $result->literal);
        self::assertSame('opaque', $result->kind);
    }
    public function testProviderRegistrationCapturesTheProviderVersion(): void
    {
        self::assertSame(['example.container','1'], (new ModelBoundary())->providerRegistration(new \Tests\Fake\MiniContainer()));
    }
    public function testDeclarationsReturnsCapturedSourceWithoutExecutingIt(): void
    {
        $input = (new ModelBoundary())->declarations(new \Tests\Fake\MiniContainer());
        self::assertSame('generated/service.php', $input->files[0]->path);
        self::assertStringContainsString('class GeneratedService', $input->files[0]->contents);
    }
    public function testEnvironmentRetainsExplicitCapturedValues(): void
    {
        $values = (new ModelBoundary())->environment(new \Tests\Fake\MiniContainer());
        self::assertSame('captured', $values['env:APP_NAME']->native());
    }
    public function testEntriesPreservesTheProviderLifecycleRoots(): void
    {
        self::assertSame('entry', (new ModelBoundary())->entries(new \Tests\Fake\MiniContainer())[0]->symbol);
    }
    public function testDomainsReturnsTheDeclaredLattice(): void
    {
        $domains = (new ModelBoundary())->domains(new \Tests\Fake\PolicyProvider());
        self::assertCount(1, $domains);
        self::assertSame('example.policy', $domains[0]->id());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testQueriesUsesTheCapturedSessionEntries(): void
    {
        $provider = new \Tests\Fake\MiniContainer();
        $session = \Tests\Fake\Analysis::session('<?php function entry(){return 1;}', new \Deriver\Api\Project\Configuration(providers:[$provider]));
        $queries = (new ModelBoundary())->queries($provider, $session);
        self::assertSame('entrypoint', $queries['entry-return']->scope()->mode);
        self::assertSame('entry', $queries['entry-return']->scope()->entries[0]->symbol);
    }
    public function testDispatchKeepsCompletenessAndTheBoundReceiver(): void
    {
        $request = new \Deriver\Model\Provider\DispatchRequest(Term::parameter('service', 'Contract'), 'label', false, new SourceRef('s', 'f.php', 0, 1), new TargetProfile());
        $decision = (new ModelBoundary())->dispatch(new \Tests\Fake\MiniContainer(), $request);
        self::assertNotNull($decision);
        self::assertTrue($decision->exhaustive);
        self::assertSame('GeneratedService::label', $decision->targets[0]->symbol);
    }
    public function testRefinementPreservesTheSelectedBranchPolarity(): void
    {
        $predicate = Term::parameter('flag', 'bool');
        $value = (new ModelBoundary())->refinement(new \Tests\Fake\PolicyProvider(), $predicate, false);
        self::assertNotNull($value);
        self::assertSame('!', $value->literal);
        self::assertSame($predicate, $value->operands[0]);
    }
    public function testDomainJoinContainsBothInputFacts(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $value = (new ModelBoundary())->domain($domain, 'join', $domain->bottom()->term(), $domain->top()->term());
        self::assertSame('top', $value->operands['representation']->kind);
    }
    public function testContainsRequiresTheDeclaredInclusionDirection(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $boundary = new ModelBoundary();
        self::assertTrue($boundary->contains($domain, $domain->top()->term(), $domain->bottom()->term()));
        self::assertFalse($boundary->contains($domain, $domain->bottom()->term(), $domain->top()->term()));
    }

    public function testDescriptorContainsPluginFailuresWithoutExposingTheirMessages(): void
    {
        $plugin = self::createStub(CallModel::class);
        $plugin->method('descriptor')->willThrowException(new RuntimeException('sensitive plugin detail'));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONTRACT_VIOLATION: descriptor RuntimeException');
        (new ModelBoundary())->descriptor($plugin);
    }

    public function testIntrinsicDescriptorContainsPluginFailuresWithoutExposingTheirMessages(): void
    {
        $plugin = self::createStub(PureIntrinsic::class);
        $plugin->method('descriptor')->willThrowException(new RuntimeException('sensitive plugin detail'));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONTRACT_VIOLATION: intrinsic descriptor RuntimeException');
        (new ModelBoundary())->intrinsicDescriptor($plugin);
    }

    public function testProviderRegistrationContainsPluginFailuresWithoutExposingTheirMessages(): void
    {
        $plugin = self::createStub(\Deriver\Model\Provider\Provider::class);
        $plugin->method('id')->willThrowException(new RuntimeException('sensitive plugin detail'));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONTRACT_VIOLATION: provider providerRegistration RuntimeException');
        (new ModelBoundary())->providerRegistration($plugin);
    }

    public function testDeclarationsContainsPluginFailuresWithoutExposingTheirMessages(): void
    {
        $plugin = self::createStub(\Deriver\Model\Provider\DeclarationProvider::class);
        $plugin->method('declarations')->willThrowException(new RuntimeException('sensitive plugin detail'));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONTRACT_VIOLATION: provider declarations RuntimeException');
        (new ModelBoundary())->declarations($plugin);
    }

    public function testEnvironmentContainsPluginFailuresWithoutExposingTheirMessages(): void
    {
        $plugin = self::createStub(\Deriver\Model\Provider\EnvironmentProvider::class);
        $plugin->method('environment')->willThrowException(new RuntimeException('sensitive plugin detail'));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONTRACT_VIOLATION: provider environment RuntimeException');
        (new ModelBoundary())->environment($plugin);
    }

    public function testEntriesContainsPluginFailuresWithoutExposingTheirMessages(): void
    {
        $plugin = self::createStub(\Deriver\Model\Provider\EntryPointProvider::class);
        $plugin->method('entries')->willThrowException(new RuntimeException('sensitive plugin detail'));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONTRACT_VIOLATION: provider entries RuntimeException');
        (new ModelBoundary())->entries($plugin);
    }

    public function testDomainsContainsPluginFailuresWithoutExposingTheirMessages(): void
    {
        $plugin = self::createStub(\Deriver\Model\Provider\DomainProvider::class);
        $plugin->method('domains')->willThrowException(new RuntimeException('sensitive plugin detail'));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONTRACT_VIOLATION: provider domains RuntimeException');
        (new ModelBoundary())->domains($plugin);
    }

    public function testQueriesContainsPluginFailuresWithoutExposingTheirMessages(): void
    {
        $plugin = self::createStub(\Deriver\Model\Provider\ObservationProvider::class);
        $plugin->method('queries')->willThrowException(new RuntimeException('sensitive plugin detail'));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONTRACT_VIOLATION: provider queries RuntimeException');
        (new ModelBoundary())->queries($plugin, self::createStub(\Deriver\Api\AnalysisSession::class));
    }

    public function testDomainRegistrationContainsPluginFailuresWithoutExposingTheirMessages(): void
    {
        $plugin = self::createStub(AbstractDomain::class);
        $plugin->method('bottom')->willThrowException(new RuntimeException('sensitive plugin detail'));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONTRACT_VIOLATION: domain registration RuntimeException');
        (new ModelBoundary())->domainRegistration($plugin);
    }

    public function testDescribeKeepsPluginFailureSeparateFromSourceFallback(): void
    {
        $model = self::createStub(CallModel::class);
        $model->method('describe')->willThrowException(new RuntimeException('private detail'));
        $decision = (new ModelBoundary())->describe($model, new CallDescription('example', new Signature(), new TargetProfile()));
        self::assertSame('unsupported', $decision->kind);
        self::assertSame('MODEL_CONTRACT_VIOLATION:RuntimeException', $decision->reason);
        self::assertFalse($decision->fallbackToSource);
        self::assertNull($decision->plan);
    }

    public function testEvaluateKeepsArgumentsWhenAnIntrinsicThrows(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('test', '1', 'test', 1, [0]));
        $intrinsic->method('evaluate')->willThrowException(new RuntimeException('private detail'));
        $input = Term::parameter('x');
        $result = (new ModelBoundary())->evaluate($intrinsic, [$input], new TargetProfile());
        self::assertSame('opaque', $result->kind);
        self::assertSame('MODEL_CONTRACT_VIOLATION', $result->literal);
        self::assertSame([$input], $result->operands);
        self::assertSame(['type' => 'mixed','failureClass' => RuntimeException::class], $result->attributes);
    }

    public function testEvaluateRetainsArityMismatchDependencies(): void
    {
        $input = Term::parameter('x');
        $result = (new ModelBoundary())->evaluate(new \Tests\Fake\PolicyOperation('compose'), [$input], new TargetProfile());
        self::assertSame([$input], $result->operands);
        self::assertSame('MODEL_CONTRACT_VIOLATION', $result->literal);
    }

    public function testEvaluateCannotDiscardConfidentialInputMetadata(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('test', '1', 'test', 1, [0]));
        $intrinsic->method('evaluate')->willReturn(Term::constant('derived private text'));
        $result = (new ModelBoundary())->evaluate($intrinsic, [Term::constant('private', true)], new TargetProfile());
        self::assertTrue($result->isSecret());
        self::assertSame('derived private text', $result->native());
    }

    public function testEvaluatePreservesAnUnchangedPublicIntrinsicResult(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('test', '1', 'test', 1, [0]));
        $value = Term::constant('public');
        $intrinsic->method('evaluate')->willReturn($value);
        self::assertSame($value, (new ModelBoundary())->evaluate($intrinsic, [Term::constant('input')], new TargetProfile()));
    }

    public function testDispatchKeepsAnUnresolvedDecisionOnPluginFailure(): void
    {
        $provider = self::createStub(\Deriver\Model\Provider\DispatchProvider::class);
        $provider->method('resolve')->willThrowException(new RuntimeException());
        $request = new \Deriver\Model\Provider\DispatchRequest(Term::parameter('object', 'object'), 'method', false, new SourceRef('test', 'fixture.php', 0, 1), new TargetProfile());
        self::assertNull((new ModelBoundary())->dispatch($provider, $request));
    }

    public function testRefinementMarksAPluginFailureAsAContractBoundary(): void
    {
        $provider = self::createStub(\Deriver\Model\Provider\RefinementModel::class);
        $provider->method('refine')->willThrowException(new RuntimeException());
        $result = (new ModelBoundary())->refinement($provider, Term::parameter('x'), false);
        self::assertNotNull($result);
        self::assertSame('opaque', $result->kind);
        self::assertSame('MODEL_CONTRACT_VIOLATION', $result->literal);
    }

    public function testDomainRegistrationRejectsViolationsOfTheDeclaredLaws(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $fact = new DomainFact('broken', Term::constant(1));
        $domain->method('id')->willReturn('broken');
        $domain->method('bottom')->willReturn($fact);
        $domain->method('top')->willReturn($fact);
        $domain->method('join')->willReturn($fact);
        $domain->method('widen')->willReturn($fact);
        $domain->method('lessOrEqual')->willReturn(false);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONTRACT_VIOLATION: domain registration');
        (new ModelBoundary())->domainRegistration($domain);
    }

    public function testDomainRetainsSecrecyWhenAProjectionReplacesItsRepresentation(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $input = new Term('domain', $domain->id(), ['representation' => Term::fromNative(['x' => 'private'])], secret:true);
        $projected = (new ModelBoundary())->domain($domain, 'project', $input, projection:new Projection(['x']));
        self::assertSame('private', $projected->operands['representation']->native());
        self::assertTrue($projected->isSecret());
    }

    public function testDomainRejectsFactsOwnedByAnotherDomain(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $domain->method('id')->willReturn('example');
        $domain->method('project')->willReturn(new DomainFact('different', Term::constant(1)));
        $input = (new DomainFact('example', Term::constant(2)))->term();
        $result = (new ModelBoundary())->domain($domain, 'project', $input);
        self::assertSame('MODEL_CONTRACT_VIOLATION', $result->literal);
        self::assertSame([$input], $result->operands);
    }

    public function testDomainKeepsBothInputsWhenWideningViolatesInclusion(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $domain->method('id')->willReturn('example');
        $domain->method('widen')->willReturn(new DomainFact('example', Term::constant(1)));
        $domain->method('lessOrEqual')->willReturn(false);
        $a = (new DomainFact('example', Term::constant(1)))->term();
        $b = (new DomainFact('example', Term::constant(2, true)))->term();
        $result = (new ModelBoundary())->domain($domain, 'widen', $a, $b);
        self::assertSame('MODEL_CONTRACT_VIOLATION', $result->literal);
        self::assertSame([$a,$b], $result->operands);
        self::assertTrue($result->isSecret());
    }

    public function testDomainRejectsUnknownOperationsAndThrownProviderFailures(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $input = $domain->top()->term();
        self::assertSame('MODEL_CONTRACT_VIOLATION', (new ModelBoundary())->domain($domain, 'invalid', $input)->literal);
        $broken = self::createStub(AbstractDomain::class);
        $broken->method('id')->willReturn($domain->id());
        $broken->method('join')->willThrowException(new RuntimeException());
        self::assertSame('MODEL_CONTRACT_VIOLATION', (new ModelBoundary())->domain($broken, 'join', $input)->literal);
    }

    public function testContainsCannotTreatAPluginExceptionAsConvergence(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $domain->method('id')->willReturn('example');
        $domain->method('lessOrEqual')->willThrowException(new RuntimeException());
        $input = (new DomainFact('example', Term::constant(1)))->term();
        self::assertFalse((new ModelBoundary())->contains($domain, $input, $input));
    }
}
